<?php

use App\Actions\Ai\DebitGenerationBalance;
use App\Actions\Ai\RequestVideoGeneration;
use App\Contracts\MuxClient;
use App\Jobs\GenerateDishVideo;
use App\Jobs\PollDishVideoGeneration;
use App\Models\AiPreset;
use App\Models\AiProvider;
use App\Models\GenerationLedger;
use App\Models\VideoGeneration;
use App\Services\Ai\GeminiOmniVideoGenerationProvider;
use App\Services\Ai\VideoGenerationProviderResolver;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeMuxClient;

/**
 * Gemini Omni Flash replaced the Veo 3.1 preview (shut down by Google on 22 Oct 2026): photo in, vertical clip out, one call.
 */
const OMNI = 'https://generativelanguage.googleapis.com/v1beta/interactions';

beforeEach(function () {
    seedReferenceData();
    Storage::fake('public');

    config()->set('services.gemini.key', 'test-key');
    config()->set('services.gemini.omni_model', 'gemini-omni-1.1-flash');
    config()->set('services.gemini.video_resolution', '720p');

    $this->mux = new FakeMuxClient;
    app()->instance(MuxClient::class, $this->mux);
});

/** An `interactions` answer carrying a tiny "video" (the real one is a base64 MP4). */
function omniAnswer(string $bytes = 'fake-mp4-bytes', int $videoTokens = 11584, int $inputTokens = 1128): array
{
    return [
        'id' => 'v1_abc', 'status' => 'completed', 'model' => 'gemini-omni-1.1-flash',
        'usage' => ['total_input_tokens' => $inputTokens, 'output_tokens_by_modality' => [['modality' => 'video', 'tokens' => $videoTokens]]],
        'steps' => [
            ['type' => 'thought', 'signature' => 'xyz'],
            ['type' => 'model_output', 'content' => [['type' => 'video', 'mime_type' => 'video/mp4', 'data' => base64_encode($bytes)]]],
        ],
    ];
}

function omniPreset(array $overrides = []): array
{
    return array_merge(['prompt' => 'Appetizing close-up of the dish.', 'camera_movement' => 'slow orbit', 'duration_seconds' => 6, 'variations' => 1, 'turn' => VideoGeneration::TURN_LIMITED], $overrides);
}

function omniPhoto(): string
{
    Storage::disk('public')->put('dish-photos/1/prato.png', fakeImage()->getContent());

    return Storage::disk('public')->url('dish-photos/1/prato.png');
}

test('start asks Omni for a vertical clip from the photo and parks the video on the public disk', function () {
    Http::fake([OMNI => Http::response(omniAnswer('mp4-one'))]);

    $operations = app(GeminiOmniVideoGenerationProvider::class)->start([omniPhoto()], omniPreset());

    expect($operations)->toHaveCount(1);

    $handle = json_decode($operations[0], true);

    expect($handle)->toMatchArray(['model' => 'gemini-omni-1.1-flash', 'resolution' => '720p'])
        ->and(Storage::disk('public')->get($handle['path']))->toBe('mp4-one')
        ->and($handle['cost_usd'])->toBe(round((11584 * 17.5 + 1128 * 1.5) / 1_000_000, 4));

    Http::assertSent(function (Request $request) {
        $image = $request['input'][0];

        return $request->url() === OMNI
            && $request->hasHeader('x-goog-api-key', 'test-key')
            && $request['model'] === 'gemini-omni-1.1-flash'
            && $request['response_format'] === ['type' => 'video', 'aspect_ratio' => '9:16', 'resolution' => '720p']
            && $image['type'] === 'image' && $image['mime_type'] === 'image/png'
            && base64_decode($image['data']) === fakeImage()->getContent()
            && str_contains($request['input'][1]['text'], 'never reveal the back of the dish')
            && str_contains($request['input'][1]['text'], 'about 6 seconds');
    });
});

test('poll hands over each parked clip as a local file and clears the disk', function () {
    Http::fake([OMNI => Http::response(omniAnswer('mp4-one'))]);
    $operations = app(GeminiOmniVideoGenerationProvider::class)->start([omniPhoto()], omniPreset());
    $path = json_decode($operations[0], true)['path'];

    $clips = app(GeminiOmniVideoGenerationProvider::class)->poll($operations);

    expect($clips)->toHaveCount(1)->and(file_get_contents($clips[0]['video_file']))->toBe('mp4-one')->and($clips[0]['cost_usd'])->toBeGreaterThan(0);
    expect(Storage::disk('public')->exists($path))->toBeFalse();

    @unlink($clips[0]['video_file']);
});

test('when a later variation fails, the clips already generated are kept', function () {
    Http::fake([OMNI => Http::sequence()->push(omniAnswer('a'))->push(['error' => ['code' => 429]], 429)]);

    $operations = app(GeminiOmniVideoGenerationProvider::class)->start([omniPhoto()], omniPreset(['variations' => 3]));

    expect($operations)->toHaveCount(1);
});

test('start fails when Google refuses the request', function () {
    Http::fake([OMNI => Http::response(['error' => ['code' => 404, 'message' => 'model gone']], 404)]);

    expect(fn () => app(GeminiOmniVideoGenerationProvider::class)->start([omniPhoto()], omniPreset()))->toThrow(RequestException::class);
});

test('start says why when Google answers but returns no video', function () {
    Http::fake([OMNI => Http::response(['status' => 'failed', 'error' => ['message' => 'blocked by safety'], 'steps' => []])]);

    expect(fn () => app(GeminiOmniVideoGenerationProvider::class)->start([omniPhoto()], omniPreset()))->toThrow(RuntimeException::class, 'Gemini produced no video: blocked by safety');
});

test('start needs the API key', function () {
    config()->set('services.gemini.key', '');

    expect(fn () => app(GeminiOmniVideoGenerationProvider::class)->start([omniPhoto()], omniPreset()))->toThrow(RuntimeException::class, 'GEMINI_API_KEY is not set.');
});

test('the gemini provider of the admin runs Omni, so existing presets keep working', function () {
    config()->set('services.gemini.key', 'test-key');

    expect(app(VideoGenerationProviderResolver::class)->resolve('gemini'))->toBeInstanceOf(GeminiOmniVideoGenerationProvider::class);
});

test('a whole generation: submitted, parked, uploaded to Mux and only then debited', function () {
    Queue::fake();
    Http::fake([OMNI => Http::response(omniAnswer('mp4-final')), 'storage.mux.test/*' => Http::response('')]);

    $provider = AiProvider::factory()->create(['slug' => 'gemini', 'name' => 'Gemini']);
    AiPreset::factory()->create(['provider_id' => $provider->id, 'duration_seconds' => 6]);
    [, $dish] = ownerWithDish(photos: 1, addon: 5);
    Storage::disk('public')->put($dish->photos->first()->file_path, fakeImage()->getContent());

    $generation = app(RequestVideoGeneration::class)->handle($dish, $dish->photos->pluck('id')->all(), 1);
    $run = fn (object $job) => $job->handle(app(VideoGenerationProviderResolver::class), app(MuxClient::class), app(DebitGenerationBalance::class));

    $run(new GenerateDishVideo($generation->id));
    expect($generation->fresh()->status->slug)->toBe('gerando')->and(GenerationLedger::count())->toBe(0);

    $run(new PollDishVideoGeneration($generation->id));
    $generation = $generation->fresh(['status', 'videos']);

    expect($generation->status->slug)->toBe('pronto')->and($generation->videos)->toHaveCount(1)->and(GenerationLedger::count())->toBe(1)->and((float) $generation->cost_usd)->toBeGreaterThan(0);
});
