<?php

use App\Actions\Ai\RequestVideoGeneration;
use App\Filament\Resources\AiPresets\Pages\EditAiPreset;
use App\Filament\Resources\AiProviders\Pages\CreateAiProvider;
use App\Models\AiPreset;
use App\Models\AiProvider;
use App\Models\User;
use App\Models\VideoGeneration;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->actingAs(User::factory()->superAdmin()->create());
});

test('changing a presets provider does not rewrite past generations provider snapshot', function () {
    $runway = AiProvider::factory()->create(['name' => 'Runway', 'slug' => 'null']);
    $other = AiProvider::factory()->create(['name' => 'Outro']);
    $preset = AiPreset::factory()->create(['provider_id' => $runway->id]);
    $past = VideoGeneration::factory()->ready()->create(['preset_id' => $preset->id, 'provider_id' => $runway->id]);

    Livewire::test(EditAiPreset::class, ['record' => $preset->getRouteKey()])
        ->fillForm([
            'provider_id' => $other->id,
            'prompt' => 'Novo prompt com vapor sutil',
            'camera_movement' => 'giro 40°',
            'duration_seconds' => 8,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($preset->fresh())
        ->provider_id->toBe($other->id)
        ->prompt->toBe('Novo prompt com vapor sutil')
        ->duration_seconds->toBe(8)
        ->and($past->fresh()->provider_id)->toBe($runway->id);
});

it('does not offer a deactivated provider on a preset but keeps it on history', function () {
    $active = AiProvider::factory()->create(['name' => 'Ativo']);
    $inactive = AiProvider::factory()->inactive()->create(['name' => 'Desligado']);
    $preset = AiPreset::factory()->create(['provider_id' => $active->id]);
    $past = VideoGeneration::factory()->create(['provider_id' => $inactive->id]);

    Livewire::test(EditAiPreset::class, ['record' => $preset->getRouteKey()])
        ->fillForm(['provider_id' => $inactive->id])
        ->call('save')
        ->assertHasFormErrors(['provider_id']);

    expect($preset->fresh()->provider_id)->toBe($active->id)
        ->and($past->fresh()->provider->name)->toBe('Desligado')
        ->and(RequestVideoGeneration::activePreset()?->id)->toBe($preset->id);
});

it('registers a provider only for an integration bound in config', function () {
    Livewire::test(CreateAiProvider::class)
        ->fillForm(['name' => 'Fornecedor X', 'slug' => 'nao-existe', 'is_active' => true])
        ->call('create')
        ->assertHasFormErrors(['slug']);

    Livewire::test(CreateAiProvider::class)
        ->fillForm(['name' => 'Nulo (dev)', 'slug' => 'null', 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(AiProvider::where('slug', 'null')->exists())->toBeTrue();
});
