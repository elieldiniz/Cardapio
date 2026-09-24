<?php

use App\Actions\Ai\DebitGenerationBalance;
use App\Actions\Ai\RequestVideoGeneration;
use App\Contracts\MuxClient;
use App\Jobs\GenerateDishVideo;
use App\Jobs\PollDishVideoGeneration;
use App\Models\AiPreset;
use App\Models\AiProvider;
use App\Models\GenerationBalance;
use App\Models\GenerationLedger;
use App\Models\VideoGeneration;
use App\Services\Ai\GeminiVeoVideoGenerationProvider;
use App\Services\Ai\VideoGenerationProviderResolver;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeMuxClient;

const VEO = 'https://generativelanguage.googleapis.com/v1beta';

beforeEach(function () {
    unset($GLOBALS['veo']);
    seedReferenceData();
    Storage::fake('public');

    config()->set('services.gemini.key', 'test-key');
    config()->set('services.gemini.video_model', 'veo-3.1-lite-generate-preview');
    config()->set('services.gemini.video_resolution', '720p');

    $this->mux = new FakeMuxClient;
    app()->instance(MuxClient::class, $this->mux);
});

/**
 * Veo + Mux upload fake, registered once; each response reads the current
 * state, so a test can flip an operation from running to done.
 */
function fakeVeo(bool $done = true, array $filtered = [], int $muxStatus = 200, ?int $rejectFrom = null, int $pollStatus = 200): void
{
    $already = isset($GLOBALS['veo']);
    $GLOBALS['veo'] = compact('done', 'filtered', 'muxStatus', 'rejectFrom', 'pollStatus') + ['counter' => $GLOBALS['veo']['counter'] ?? 0];

    if ($already) {
        return;
    }

    Http::fake([
        VEO.'/models/*:predictLongRunning' => function () {
            $counter = ++$GLOBALS['veo']['counter'];

            if ($GLOBALS['veo']['rejectFrom'] !== null && $counter >= $GLOBALS['veo']['rejectFrom']) {
                return Http::response(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED']], 429);
            }

            return Http::response(['name' => "models/veo-3.1-lite-generate-preview/operations/op-{$counter}"]);
        },
        VEO.'/models/veo-3.1-lite-generate-preview/operations/*' => function (Request $request) {
            $id = basename(parse_url($request->url(), PHP_URL_PATH));

            if ($GLOBALS['veo']['pollStatus'] !== 200) {
                return Http::response(['error' => ['code' => $GLOBALS['veo']['pollStatus']]], $GLOBALS['veo']['pollStatus']);
            }

            if (! $GLOBALS['veo']['done']) {
                return Http::response(['name' => $id, 'done' => false]);
            }

            if (in_array($id, $GLOBALS['veo']['filtered'], true)) {
                return Http::response(['name' => $id, 'done' => true, 'response' => ['generateVideoResponse' => ['raiMediaFilteredReasons' => ['food safety']]]]);
            }

            return Http::response(['name' => $id, 'done' => true, 'response' => ['generateVideoResponse' => [
                'generatedSamples' => [['video' => ['uri' => VEO."/files/{$id}:download?alt=media"]]],
            ]]]);
        },
        VEO.'/files/*' => Http::response('fake-mp4-bytes'),
        'storage.mux.test/*' => fn () => Http::response('', $GLOBALS['veo']['muxStatus']),
    ]);
}

function veoPreset(array $overrides = []): array
{
    return array_merge([
        'prompt' => 'Appetizing close-up of the dish.',
        'camera_movement' => 'slow orbit',
        'duration_seconds' => 5,
        'variations' => 2,
        'turn' => VideoGeneration::TURN_LIMITED,
    ], $overrides);
}

function photoUrl(): string
{
    Storage::disk('public')->put('dish-photos/1/prato.png', fakeImage()->getContent());

    return Storage::disk('public')->url('dish-photos/1/prato.png');
}

test('start submits one vertical Veo operation per variation with the first photo as the opening frame', function () {
    fakeVeo();

    $operations = app(GeminiVeoVideoGenerationProvider::class)->start([photoUrl()], veoPreset(['variations' => 3]));

    expect($operations)->toHaveCount(3)
        ->and(json_decode($operations[0], true))->toMatchArray(['name' => 'models/veo-3.1-lite-generate-preview/operations/op-1', 'seconds' => 6]);

    Http::assertSentCount(3);
    Http::assertSent(function (Request $request) {
        $instance = $request['instances'][0];

        return $request->hasHeader('x-goog-api-key', 'test-key')
            && str_ends_with($request->url(), 'models/veo-3.1-lite-generate-preview:predictLongRunning')
            && $request['parameters'] === ['aspectRatio' => '9:16', 'durationSeconds' => 6, 'resolution' => '720p']
            && $instance['image']['mimeType'] === 'image/png'
            && base64_decode($instance['image']['bytesBase64Encoded']) === fakeImage()->getContent()
            && str_contains($instance['prompt'], 'never reveal the back of the dish');
    });
});

test('when Veo accepts only some variations, the started ones are kept instead of wasted', function () {
    fakeVeo(rejectFrom: 2);

    $operations = app(GeminiVeoVideoGenerationProvider::class)->start([photoUrl()], veoPreset(['variations' => 3]));

    expect($operations)->toHaveCount(1);
});

test('start fails when Veo accepts no variation at all', function () {
    fakeVeo(rejectFrom: 1);

    app(GeminiVeoVideoGenerationProvider::class)->start([photoUrl()], veoPreset());
})->throws(RequestException::class);

test('a transient error while checking just means check again, a client error fails', function () {
    $operations = [json_encode(['name' => 'models/veo-3.1-lite-generate-preview/operations/op-1', 'model' => 'veo-3.1-lite-generate-preview', 'resolution' => '720p', 'seconds' => 6])];

    fakeVeo(pollStatus: 503);
    expect(app(GeminiVeoVideoGenerationProvider::class)->poll($operations))->toBeNull();

    fakeVeo(pollStatus: 429);
    expect(app(GeminiVeoVideoGenerationProvider::class)->poll($operations))->toBeNull();

    fakeVeo(pollStatus: 404);
    expect(fn () => app(GeminiVeoVideoGenerationProvider::class)->poll($operations))->toThrow(RequestException::class);
});

it('maps the preset duration to a length Veo accepts', function (int $preset, int $veo) {
    expect(GeminiVeoVideoGenerationProvider::durationFor($preset))->toBe($veo);
})->with([[4, 4], [5, 6], [6, 6], [7, 8], [10, 8]]);

test('poll waits while any operation is still running', function () {
    fakeVeo(done: false);

    $operations = [json_encode(['name' => 'models/veo-3.1-lite-generate-preview/operations/op-1', 'model' => 'veo-3.1-lite-generate-preview', 'resolution' => '720p', 'seconds' => 6])];

    expect(app(GeminiVeoVideoGenerationProvider::class)->poll($operations))->toBeNull();
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/files/'));
});

test('poll downloads finished clips, prices them and skips safety-filtered ones', function () {
    fakeVeo(filtered: ['op-2']);

    $handle = fn (int $n) => json_encode(['name' => "models/veo-3.1-lite-generate-preview/operations/op-{$n}", 'model' => 'veo-3.1-lite-generate-preview', 'resolution' => '720p', 'seconds' => 6]);
    $clips = app(GeminiVeoVideoGenerationProvider::class)->poll([$handle(1), $handle(2)]);

    expect($clips)->toHaveCount(1)
        ->and($clips[0]['cost_usd'])->toBe(0.30)
        ->and(file_get_contents($clips[0]['video_file']))->toBe('fake-mp4-bytes');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/files/op-1') && $request->hasHeader('x-goog-api-key', 'test-key'));
});

test('poll fails when no operation produced a video', function () {
    fakeVeo(filtered: ['op-1']);

    $handle = json_encode(['name' => 'models/veo-3.1-lite-generate-preview/operations/op-1', 'model' => 'veo-3.1-lite-generate-preview', 'resolution' => '720p', 'seconds' => 6]);

    app(GeminiVeoVideoGenerationProvider::class)->poll([$handle]);
})->throws(RuntimeException::class, 'Veo produced no video');

test('an async generation is submitted, polled until done, uploaded to Mux and only then debited', function () {
    Queue::fake();
    fakeVeo(done: false);

    $provider = AiProvider::factory()->create(['slug' => 'gemini', 'name' => 'Gemini Veo']);
    AiPreset::factory()->create(['provider_id' => $provider->id, 'duration_seconds' => 5]);
    [, $dish] = ownerWithDish(photos: 1, addon: 5);
    Storage::disk('public')->put($dish->photos->first()->file_path, fakeImage()->getContent());

    $generation = app(RequestVideoGeneration::class)->handle($dish, $dish->photos->pluck('id')->all(), 2);
    $run = fn (object $job) => $job->handle(app(VideoGenerationProviderResolver::class), app(MuxClient::class), app(DebitGenerationBalance::class));

    // 1. Submitted: two operations stored, the first check scheduled, nothing charged yet.
    $run(new GenerateDishVideo($generation->id));
    $generation->refresh();

    expect($generation->status->slug)->toBe('gerando')
        ->and($generation->provider_operations)->toHaveCount(2)
        ->and(GenerationLedger::count())->toBe(0);
    Queue::assertPushed(PollDishVideoGeneration::class, 1);

    // A redelivered start never submits (and pays for) the operations again.
    $run(new GenerateDishVideo($generation->id));
    Http::assertSentCount(2);

    // 2. Still running: another check is scheduled.
    $run(new PollDishVideoGeneration($generation->id));
    Queue::assertPushed(PollDishVideoGeneration::class, 2);
    expect($generation->fresh()->status->slug)->toBe('gerando');

    // 3. Done: clips go to Mux by direct upload, matched back through the passthrough.
    fakeVeo(done: true);
    $run(new PollDishVideoGeneration($generation->id));
    $generation = $generation->fresh(['status', 'videos.status', 'videos.origin']);

    expect($generation->status->slug)->toBe('pronto')
        ->and((float) $generation->cost_usd)->toBe(0.6)
        ->and($generation->videos)->toHaveCount(2)
        ->and($generation->videos->pluck('status.slug')->unique()->all())->toBe(['processando'])
        ->and($generation->videos->pluck('origin.slug')->unique()->all())->toBe(['ia'])
        ->and(collect($this->mux->uploads)->pluck('new_asset_settings.passthrough')->all())
        ->toBe($generation->videos->map(fn ($video) => "video:{$video->id}")->all())
        ->and(GenerationBalance::where('restaurant_id', $dish->restaurant_id)->value('addon_balance'))->toBe(3);

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && str_starts_with($request->url(), 'https://storage.mux.test/') && $request->body() === 'fake-mp4-bytes');
});

test('a Mux upload failure marks the async generation as erro without debiting', function () {
    Queue::fake();
    fakeVeo(muxStatus: 500);

    $provider = AiProvider::factory()->create(['slug' => 'gemini', 'name' => 'Gemini Veo']);
    AiPreset::factory()->create(['provider_id' => $provider->id]);
    [, $dish] = ownerWithDish(photos: 1, addon: 5);
    Storage::disk('public')->put($dish->photos->first()->file_path, fakeImage()->getContent());

    $generation = app(RequestVideoGeneration::class)->handle($dish, $dish->photos->pluck('id')->all(), 2);
    $run = fn (object $job) => $job->handle(app(VideoGenerationProviderResolver::class), app(MuxClient::class), app(DebitGenerationBalance::class));

    $run(new GenerateDishVideo($generation->id));
    $run(new PollDishVideoGeneration($generation->id));
    $generation = $generation->fresh(['status', 'videos']);

    expect($generation->status->slug)->toBe('erro')
        ->and($generation->videos)->toBeEmpty()
        ->and(GenerationLedger::count())->toBe(0);
});
