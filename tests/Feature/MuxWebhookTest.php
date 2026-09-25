<?php

use App\Models\Dish;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoGeneration;
use App\Notifications\VideoReadyForReview;
use Illuminate\Support\Collection;
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

/**
 * @return array{0: User, 1: Collection<int, Video>}
 */
function generationWithVariations(int $count, array $donoAttributes = []): array
{
    $dono = User::factory()->create($donoAttributes);
    $dish = Dish::factory()->create(['restaurant_id' => $dono->restaurant_id]);
    $generation = VideoGeneration::factory()->create(['dish_id' => $dish->id]);

    $videos = collect(range(1, $count))->map(fn (int $i) => Video::factory()->create([
        'dish_id' => $dish->id,
        'generation_id' => $generation->id,
        'mux_asset_id' => "asset-{$i}",
    ]));

    return [$dono, $videos];
}

it('notifies once per generation, only after the last variation is ready', function () {
    [$dono] = generationWithVariations(2);

    $this->postJson(route('webhooks.mux'), readyEvent(['id' => 'asset-1']), ['Mux-Signature' => 'valid-signature'])->assertOk();
    Notification::assertNothingSent();

    $this->postJson(route('webhooks.mux'), readyEvent(['id' => 'asset-2']), ['Mux-Signature' => 'valid-signature'])->assertOk();

    Notification::assertSentToTimes($dono, VideoReadyForReview::class, 1);
    Notification::assertSentTo($dono, VideoReadyForReview::class, fn (VideoReadyForReview $notification, array $channels) => $notification->variations === 2
        && $channels === ['database']);
});

it('also e-mails the dono who opted in', function () {
    [$dono] = generationWithVariations(1, ['notify_video_ready' => true]);

    $this->postJson(route('webhooks.mux'), readyEvent(['id' => 'asset-1']), ['Mux-Signature' => 'valid-signature'])->assertOk();

    Notification::assertSentTo($dono, VideoReadyForReview::class, fn ($notification, array $channels) => $channels === ['database', 'mail']);
});

it('notifies with the ready variations when the last one errors', function () {
    [$dono] = generationWithVariations(2);

    $this->postJson(route('webhooks.mux'), readyEvent(['id' => 'asset-1']), ['Mux-Signature' => 'valid-signature'])->assertOk();
    $this->postJson(route('webhooks.mux'), ['type' => 'video.asset.errored', 'data' => ['id' => 'asset-2']], ['Mux-Signature' => 'valid-signature'])->assertOk();

    Notification::assertSentTo($dono, VideoReadyForReview::class, fn (VideoReadyForReview $notification) => $notification->variations === 1);
});

it('does not notify about the dono\'s own upload', function () {
    $dono = User::factory()->create();
    Video::factory()->upload()->create(['mux_asset_id' => 'asset-abc', 'dish_id' => Dish::factory()->create(['restaurant_id' => $dono->restaurant_id])->id]);

    $this->postJson(route('webhooks.mux'), readyEvent(), ['Mux-Signature' => 'valid-signature'])->assertOk();

    Notification::assertNothingSent();
});
