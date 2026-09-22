<?php

use App\Models\Dish;
use App\Models\User;
use App\Models\Video;
use App\Notifications\VideoReadyForReview;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedReferenceData();
    fakeVideoPipeline();
    Notification::fake();
});

function readyEvent(array $overrides = []): array
{
    return ['type' => 'video.asset.ready', 'data' => array_merge([
        'id' => 'asset-abc',
        'status' => 'ready',
        'duration' => 8.4,
        'playback_ids' => [['id' => 'playback-xyz', 'policy' => 'public']],
    ], $overrides)];
}

test('a valid ready webhook moves the video to aguardando aprovacao', function () {
    $dono = User::factory()->create();
    $video = Video::factory()->create(['mux_asset_id' => 'asset-abc', 'dish_id' => Dish::factory()->create(['restaurant_id' => $dono->restaurant_id])->id]);

    $this->postJson(route('webhooks.mux'), readyEvent(), ['Mux-Signature' => 'valid-signature'])->assertOk();

    $video->refresh();

    expect($video->status->slug)->toBe('aguardando_aprovacao')
        ->and($video->mux_playback_id)->toBe('playback-xyz')
        ->and($video->cover_path)->toBe('https://image.mux.com/playback-xyz/thumbnail.webp?time=0')
        ->and($video->duration_seconds)->toBe(8);

    Notification::assertSentTo($dono, VideoReadyForReview::class);
});

test('an invalid signature is rejected without changing any video', function () {
    $video = Video::factory()->create(['mux_asset_id' => 'asset-abc']);

    $this->postJson(route('webhooks.mux'), readyEvent(), ['Mux-Signature' => 't=1,v1=forged'])->assertForbidden();

    expect($video->fresh()->status->slug)->toBe('processando');
    Notification::assertNothingSent();
});

it('matches an upload by its passthrough before the asset id is known', function () {
    $video = Video::factory()->upload()->create(['mux_asset_id' => null]);

    $this->postJson(route('webhooks.mux'), readyEvent(['id' => 'asset-new', 'passthrough' => "video:{$video->id}"]), ['Mux-Signature' => 'valid-signature'])->assertOk();

    expect($video->fresh())
        ->mux_asset_id->toBe('asset-new')
        ->mux_playback_id->toBe('playback-xyz');
});
