<?php

use App\Actions\Ai\ReprocessGeneration;
use App\Actions\Ai\RequestVideoGeneration;
use App\Filament\Resources\VideoGenerations\Pages\ListVideoGenerations;
use App\Models\User;
use App\Support\FailureReason;
use Livewire\Livewire;
use Tests\Fakes\FakeVideoGenerationProvider;

/**
 * Support can read why a generation failed without digging in the server log.
 */
beforeEach(function () {
    seedReferenceData();
    fakeVideoPipeline();
});

it('keeps the reason on the failed generation and shows it in the admin', function () {
    [, $dish] = ownerWithDish(photos: 1, monthly: 0, addon: 5);
    FakeVideoGenerationProvider::$shouldFail = true;

    $generation = app(RequestVideoGeneration::class)->handle($dish, $dish->photos->pluck('id')->all(), 2)->fresh();

    expect($generation->status->slug)->toBe('erro')->and($generation->failure_reason)->not->toBeNull()->and($generation->failure_reason)->not->toBe('');

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ListVideoGenerations::class)->assertSee('Motivo do erro')->assertSee(mb_substr($generation->failure_reason, 0, 30));
});

it('clears the reason when the generation is sent back to the queue', function () {
    [, $dish] = ownerWithDish(photos: 1, monthly: 0, addon: 5);
    FakeVideoGenerationProvider::$shouldFail = true;
    $generation = app(RequestVideoGeneration::class)->handle($dish, $dish->photos->pluck('id')->all(), 2)->fresh();
    FakeVideoGenerationProvider::$shouldFail = false;

    app(ReprocessGeneration::class)->handle($generation);

    expect($generation->fresh()->failure_reason)->toBeNull();
});

it('never stores API keys or tokens from an exception message', function () {
    $message = 'Client error: GET https://generativelanguage.googleapis.com/v1/x?key=AIzaSyDUMMYKEY1234567890abcdefghij&alt=sse returned 403 with x-goog-api-key: AIzaSyOTHERKEY1234567890abcdefghijk and token=abc123def456';

    $reason = FailureReason::from(new RuntimeException($message));

    expect($reason)->not->toContain('AIzaSy')->not->toContain('abc123def456')->toContain('403')->toContain('[oculto]');
});

it('cuts a very long message and names the kind of error', function () {
    $reason = FailureReason::from(new InvalidArgumentException(str_repeat('x', 5000)));

    expect(mb_strlen($reason))->toBeLessThanOrEqual(1000)->and($reason)->toStartWith('InvalidArgumentException: ');
    expect(FailureReason::from(null))->toBeNull();
});

it('classifies failures into what the owner can understand, defaulting to a generic service problem', function (?string $reason, string $category) {
    expect(FailureReason::category($reason))->toBe($category);
})->with([
    'foto ilegível' => ['Could not read photo [https://x/y.jpg].', 'foto'],
    'sem foto' => ['Veo needs at least one photo.', 'foto'],
    'filtro de segurança' => ['Veo produced no video: ["Responsible AI filtered"]', 'seguranca'],
    'prazo' => ['The provider did not finish in time.', 'tempo'],
    'chave ausente' => ['GEMINI_API_KEY is not set.', 'servico'],
    'erro do google' => ['HTTP request returned status code 403', 'servico'],
    'sem motivo' => [null, 'servico'],
]);

it('shows the owner a plain message and a code, never the technical reason', function () {
    [$dono, $dish] = ownerWithDish(photos: 1, monthly: 0, addon: 5);
    FakeVideoGenerationProvider::$shouldFail = true;
    $generation = app(RequestVideoGeneration::class)->handle($dish, $dish->photos->pluck('id')->all(), 2)->fresh();
    $generation->update(['failure_reason' => 'RequestException: 403 Forbidden for key=AIzaSySECRET https://generativelanguage.googleapis.com']);

    $code = $generation->fresh()->errorCode();

    expect($code)->toMatch('/^GER-X-\d{5}$/');

    Livewire::actingAs($dono)->test('pages::panel.dish-video', ['dish' => $dish])
        ->assertSeeHtml('data-generation-error')
        ->assertSee('Tivemos um problema no nosso serviço de vídeo')
        ->assertSee('Nenhuma geração foi descontada')
        ->assertSee($code)
        ->assertDontSee('403')->assertDontSee('Forbidden')->assertDontSee('googleapis')->assertDontSee('RequestException');
});

it('tells the owner what to try for a photo the AI could not use', function () {
    [$dono, $dish] = ownerWithDish(photos: 1, monthly: 0, addon: 5);
    FakeVideoGenerationProvider::$shouldFail = true;
    $generation = app(RequestVideoGeneration::class)->handle($dish, $dish->photos->pluck('id')->all(), 2)->fresh();
    $generation->update(['failure_reason' => 'Could not read photo [https://x/y.jpg].']);

    expect($generation->fresh()->errorCode())->toStartWith('GER-F-');

    Livewire::actingAs($dono)->test('pages::panel.dish-video', ['dish' => $dish])->assertSee('Envie outra, com boa luz');
});
