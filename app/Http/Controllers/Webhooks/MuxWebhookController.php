<?php

namespace App\Http\Controllers\Webhooks;

use App\Contracts\MuxClient;
use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\VideoStatus;
use App\Notifications\VideoReadyForReview;
use App\Support\MuxUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;

/**
 * Mux → app webhook (US-3.3). Only signed requests are processed; the
 * asset-ready event moves the matching video to `aguardando_aprovacao`.
 */
class MuxWebhookController extends Controller
{
    public function __invoke(Request $request, MuxClient $mux): Response
    {
        if (! $mux->verifyWebhookSignature($request->getContent(), (string) $request->header('Mux-Signature'))) {
            return response('Invalid signature.', 403);
        }

        $event = $request->json()->all();

        match ($event['type'] ?? null) {
            'video.asset.ready' => $this->assetReady($event['data'] ?? []),
            default => null,
        };

        return response('OK');
    }

    /**
     * @param  array<string, mixed>  $asset
     */
    private function assetReady(array $asset): void
    {
        $video = $this->findVideo($asset);

        if ($video === null || $video->status?->slug !== 'processando') {
            return;
        }

        $playbackId = collect($asset['playback_ids'] ?? [])->firstWhere('policy', 'public')['id']
            ?? ($asset['playback_ids'][0]['id'] ?? null);

        $video->update([
            'status_id' => VideoStatus::idFor('aguardando_aprovacao'),
            'mux_asset_id' => $asset['id'] ?? $video->mux_asset_id,
            'mux_playback_id' => $playbackId,
            'cover_path' => $playbackId ? MuxUrls::thumbnail($playbackId) : null,
            'duration_seconds' => isset($asset['duration']) ? (int) round((float) $asset['duration']) : null,
        ]);

        $this->notifyOwners($video);
    }

    /**
     * Videos are matched by the passthrough set at creation ("video:{id}"),
     * falling back to the Mux asset id.
     *
     * @param  array<string, mixed>  $asset
     */
    private function findVideo(array $asset): ?Video
    {
        $passthrough = (string) ($asset['passthrough'] ?? '');

        if (preg_match('/^video:(\d+)$/', $passthrough, $matches)) {
            return Video::with('status')->find((int) $matches[1]);
        }

        if (! empty($asset['id'])) {
            return Video::with('status')->where('mux_asset_id', $asset['id'])->first();
        }

        return null;
    }

    private function notifyOwners(Video $video): void
    {
        $owners = $video->dish->restaurant->users()
            ->whereHas('role', fn ($role) => $role->where('slug', 'dono'))
            ->get();

        Notification::send($owners, new VideoReadyForReview($video));
    }
}
