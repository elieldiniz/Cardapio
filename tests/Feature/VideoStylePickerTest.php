<?php

use App\Actions\Ai\RequestVideoGeneration;
use App\Models\AiPreset;
use App\Models\VideoGeneration;
use App\Services\Ai\GeminiVeoVideoGenerationProvider;
use Livewire\Livewire;
use Tests\Fakes\FakeVideoGenerationProvider;

beforeEach(function () {
    seedReferenceData();
    fakeVideoPipeline();

    $this->default = AiPreset::sole();
    $this->default->update(['name' => 'Aproximação suave', 'prompt' => 'Slow push-in on the dish with gentle steam.', 'camera_movement' => 'slow push-in']);

    $this->second = AiPreset::factory()->create([
        'provider_id' => $this->default->provider_id,
        'name' => 'Close nos detalhes',
        'prompt' => 'Macro slow pan across the sauce and herbs.',
        'camera_movement' => 'macro pan',
    ]);
});

it('lists the active styles and prefills the text with the default one', function () {
    [$dono, $dish] = ownerWithDish(photos: 1, addon: 5);
    AiPreset::factory()->inactive()->create(['provider_id' => $this->default->provider_id, 'name' => 'Estilo desligado']);

    $this->actingAs($dono);

    Livewire::test('pages::panel.dish-video', ['dish' => $dish])
        ->assertSee('Aproximação suave')
        ->assertSee('Close nos detalhes')
        ->assertDontSee('Estilo desligado')
        ->assertSeeHtml('data-write-own')
        ->assertSee('mantenha o prato como ele realmente é')
        ->assertDontSee('giro completo')
        ->assertSet('presetId', $this->default->id)
        ->assertSet('prompt', 'Slow push-in on the dish with gentle steam.');
});

it('swaps the text when another style is picked and clears it for writing from scratch', function () {
    [$dono, $dish] = ownerWithDish(photos: 1, addon: 5);
    $this->actingAs($dono);

    Livewire::test('pages::panel.dish-video', ['dish' => $dish])
        ->call('choosePreset', $this->second->id)
        ->assertSet('presetId', $this->second->id)
        ->assertSet('prompt', 'Macro slow pan across the sauce and herbs.')
        ->call('writeOwn')
        ->assertSet('prompt', '');
});

it('uses the style exactly as registered when the text is left untouched', function () {
    [$dono, $dish] = ownerWithDish(photos: 1, addon: 5);
    $this->actingAs($dono);

    videoScreenWithPhoto($dish)
        ->call('choosePreset', $this->second->id)
        ->call('generate')
        ->assertHasNoErrors();

    $generation = VideoGeneration::sole();
    [$call] = FakeVideoGenerationProvider::$calls;

    expect($generation->preset_id)->toBe($this->second->id)
        ->and($generation->custom_prompt)->toBeNull()
        ->and($call['preset']['prompt'])->toBe('Macro slow pan across the sauce and herbs.')
        ->and($call['preset']['camera_movement'])->toBe('macro pan');
});

it('sends the dono own text instead of the style prompt', function () {
    [$dono, $dish] = ownerWithDish(photos: 1, addon: 5);
    $this->actingAs($dono);

    videoScreenWithPhoto($dish)
        ->set('prompt', "  A câmera chega devagar\n no prato, com vapor subindo.  ")
        ->call('generate')
        ->assertHasNoErrors();

    $generation = VideoGeneration::sole();
    [$call] = FakeVideoGenerationProvider::$calls;

    expect($generation->custom_prompt)->toBe('A câmera chega devagar no prato, com vapor subindo.')
        ->and($call['preset']['prompt'])->toBe('A câmera chega devagar no prato, com vapor subindo.')
        // The text describes the movement, so the style's camera note is dropped.
        ->and($call['preset']['camera_movement'])->toBe('');
});

it('asks for a description when the text is empty or out of range', function (string $text) {
    [$dono, $dish] = ownerWithDish(photos: 1, addon: 5);
    $this->actingAs($dono);

    videoScreenWithPhoto($dish)
        ->set('prompt', $text)
        ->call('generate')
        ->assertHasErrors('prompt');

    expect(VideoGeneration::count())->toBe(0);
})->with([
    'empty' => [''],
    'blank' => ['     '],
    'too short' => ['curto'],
    'too long' => [str_repeat('a', RequestVideoGeneration::PROMPT_MAX + 1)],
]);

it('rejects a style that is inactive or unknown', function () {
    [, $dish] = ownerWithDish(photos: 1, addon: 5);
    $off = AiPreset::factory()->inactive()->create(['provider_id' => $this->default->provider_id]);

    expect(fn () => app(RequestVideoGeneration::class)->handle($dish, $dish->photos->pluck('id')->all(), 2, $off->id))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(RequestVideoGeneration::class)->handle($dish, $dish->photos->pluck('id')->all(), 2, 999999))
        ->toThrow(InvalidArgumentException::class);
});

it('does not repeat the camera note in the provider prompt when the dono wrote their own', function () {
    $withNote = GeminiVeoVideoGenerationProvider::promptFor(['prompt' => 'X', 'camera_movement' => 'macro pan'], 1);
    $without = GeminiVeoVideoGenerationProvider::promptFor(['prompt' => 'Câmera chegando devagar', 'camera_movement' => ''], 1);

    expect($withNote)->toContain('Camera movement: macro pan.')
        ->and($without)->not->toContain('Camera movement')
        ->and($without)->toContain('Câmera chegando devagar')
        // The safety rules always follow the dono's text.
        ->and($without)->toContain('no people, no hands, no text, no logos')
        // Nothing may be invented: no steam, smoke or extra elements, even if the dono's text asks for them.
        ->and($without)->toContain('Do not add anything that is not in the photo');
});
