<?php

use App\Actions\Videos\StartOwnVideoUpload;
use App\Exceptions\OwnVideoRejectedException;
use App\Models\GenerationBalance;
use App\Models\GenerationLedger;
use App\Models\Video;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->mux = fakeVideoPipeline();
    [$this->dono, $this->dish] = ownerWithDish(photos: 0, monthly: 3, addon: 2);
    $this->actingAs($this->dono);
});

test('an uploaded video never debits the generation balance', function () {
    $result = Livewire::test('pages::panel.dish-video', ['dish' => $this->dish])
        ->call('startUpload', 9.2, 1080, 1920)
        ->call('uploadFinished');

    $balance = GenerationBalance::where('restaurant_id', $this->dish->restaurant_id)->first();

    expect(Video::count())->toBe(1)
        ->and($balance->monthly_balance)->toBe(3)
        ->and($balance->addon_balance)->toBe(2)
        ->and(GenerationLedger::count())->toBe(0);
});

test('an uploaded video row has no generation id', function () {
    $result = app(StartOwnVideoUpload::class)->handle($this->dish, 9.2, 1080, 1920);
    $video = $result['video']->fresh(['origin', 'status']);

    expect($video->generation_id)->toBeNull()
        ->and($video->origin->slug)->toBe('upload')
        ->and($video->status->slug)->toBe('processando')
        ->and($result['upload_url'])->toBe('https://storage.mux.test/upload-1')
        ->and($this->mux->uploads[0]['new_asset_settings']['passthrough'])->toBe("video:{$video->id}");
});

test('a video shorter than 5s or longer than 15s is rejected with a reason', function (float $seconds, string $reason) {
    expect(fn () => app(StartOwnVideoUpload::class)->handle($this->dish, $seconds, 1080, 1920))
        ->toThrow(OwnVideoRejectedException::class, $reason);

    $response = Livewire::test('pages::panel.dish-video', ['dish' => $this->dish])->call('startUpload', $seconds, 1080, 1920);

    expect($response->effects['returns'][0])->toMatchArray(['ok' => false])
        ->and($response->effects['returns'][0]['error'])->toContain($reason)
        ->and(Video::count())->toBe(0)
        ->and($this->mux->uploads)->toBeEmpty();
})->with([
    'too short' => [3.0, 'o mínimo é 5s'],
    'too long' => [22.6, 'o máximo é 15s'],
]);

test('a non vertical video is flagged before publishing', function () {
    $response = Livewire::test('pages::panel.dish-video', ['dish' => $this->dish])->call('startUpload', 8.0, 1920, 1080);

    expect($response->effects['returns'][0]['ok'])->toBeTrue()
        ->and($response->effects['returns'][0]['warnings'][0])->toContain('não está na vertical');

    $vertical = Livewire::test('pages::panel.dish-video', ['dish' => $this->dish])->call('startUpload', 8.0, 1080, 1920);

    expect($vertical->effects['returns'][0]['warnings'])->toBe([]);
});

it('refuses an upload whose real encoded duration is out of range when mux reports it', function () {
    $video = app(StartOwnVideoUpload::class)->handle($this->dish, 10.0, 1080, 1920)['video'];

    $this->postJson(route('webhooks.mux'), ['type' => 'video.asset.ready', 'data' => [
        'id' => 'asset-long', 'duration' => 31.0, 'passthrough' => "video:{$video->id}",
        'playback_ids' => [['id' => 'pb', 'policy' => 'public']],
    ]], ['Mux-Signature' => 'valid-signature'])->assertOk();

    expect($video->fresh()->status->slug)->toBe('rejeitado');

    Livewire::test('pages::panel.dish-video', ['dish' => $this->dish])->assertSee('Recusado · 31s');
});

it('shows an uploaded video awaiting approval in the same review flow and keeps one active video', function () {
    $previous = Video::factory()->approved()->create(['dish_id' => $this->dish->id]);
    $this->dish->update(['active_video_id' => $previous->id]);

    $video = app(StartOwnVideoUpload::class)->handle($this->dish, 10.0, 1080, 1920)['video'];

    $this->postJson(route('webhooks.mux'), ['type' => 'video.asset.ready', 'data' => [
        'id' => 'asset-own', 'duration' => 10.0, 'passthrough' => "video:{$video->id}",
        'playback_ids' => [['id' => 'pb-own', 'policy' => 'public']],
    ]], ['Mux-Signature' => 'valid-signature'])->assertOk();

    Livewire::test('pages::panel.dish-video', ['dish' => $this->dish])
        ->assertSee('Aguardando aprovação')
        ->call('approve', $video->id);

    expect($this->dish->fresh()->active_video_id)->toBe($video->id)
        ->and($previous->fresh()->status->slug)->toBe('rejeitado');
});
