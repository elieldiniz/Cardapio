<?php

use App\Actions\Ai\DebitGenerationBalance;
use App\Actions\Ai\RequestVideoGeneration;
use App\Contracts\MuxClient;
use App\Filament\Resources\VideoGenerations\Pages\ListVideoGenerations;
use App\Filament\Resources\VideoGenerations\VideoGenerationResource;
use App\Jobs\GenerateDishVideo;
use App\Models\GenerationBalance;
use App\Models\GenerationLedger;
use App\Models\GenerationStatus;
use App\Models\User;
use App\Models\VideoGeneration;
use App\Services\Ai\VideoGenerationProviderResolver;
use Livewire\Livewire;
use Tests\Fakes\FakeVideoGenerationProvider;

beforeEach(function () {
    seedReferenceData();
    fakeVideoPipeline();
    $this->actingAs(User::factory()->superAdmin()->create());
});

test('reprocessing an errored generation does not double debit the balance', function () {
    [, $dish] = ownerWithDish(photos: 1, monthly: 0, addon: 5);

    FakeVideoGenerationProvider::$shouldFail = true;
    $generation = app(RequestVideoGeneration::class)->handle($dish, $dish->photos->pluck('id')->all(), 2);
    expect($generation->fresh()->status->slug)->toBe('erro');

    FakeVideoGenerationProvider::$shouldFail = false;

    Livewire::test(ListVideoGenerations::class)
        ->assertCanSeeTableRecords([$generation])
        ->callTableAction('reprocess', $generation);

    // A duplicate delivery of the job must not charge again.
    (new GenerateDishVideo($generation->id))->handle(app(VideoGenerationProviderResolver::class), app(MuxClient::class), app(DebitGenerationBalance::class));

    expect($generation->fresh()->status->slug)->toBe('pronto')
        ->and($generation->videos()->count())->toBe(2)
        ->and(GenerationLedger::count())->toBe(2)
        ->and(GenerationBalance::where('restaurant_id', $dish->restaurant_id)->value('addon_balance'))->toBe(3);
});

it('only offers reprocessing for errored generations and shows the monthly ai cost', function () {
    $ready = VideoGeneration::factory()->ready()->create(['cost_usd' => 0.5]);
    $errored = VideoGeneration::factory()->errored()->create();
    VideoGeneration::factory()->ready()->create(['cost_usd' => 9, 'created_at' => now()->subMonths(2)]);

    Livewire::test(ListVideoGenerations::class)
        ->assertTableActionVisible('reprocess', $errored)
        ->assertTableActionHidden('reprocess', $ready)
        ->filterTable('status', GenerationStatus::idFor('erro'))
        ->assertCanSeeTableRecords([$errored])
        ->assertCanNotSeeTableRecords([$ready]);

    $this->get(VideoGenerationResource::getUrl())
        ->assertOk()
        ->assertSee('Custo de IA no mês')
        ->assertSee('US$ 0,50');
});
