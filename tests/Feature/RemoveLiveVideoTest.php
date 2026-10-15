<?php

use App\Models\Dish;
use App\Models\GenerationStatus;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoGeneration;
use App\Models\VideoStatus;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->mux = fakeVideoPipeline();
    $this->dono = User::factory()->create();
    $this->dish = Dish::factory()->create(['restaurant_id' => $this->dono->restaurant_id]);
    $this->actingAs($this->dono);
});

function liveVideoFor(Dish $dish, array $attributes = []): Video
{
    $video = Video::factory()->approved()->create(['dish_id' => $dish->id] + $attributes);
    $dish->update(['active_video_id' => $video->id]);

    return $video;
}

it('shows the live video only once, at the top, and not again in the uploads list', function () {
    $live = liveVideoFor($this->dish);
    $other = Video::factory()->rejected()->create(['dish_id' => $this->dish->id]);

    $html = Livewire::test('pages::panel.dish-video', ['dish' => $this->dish])->html();

    expect(substr_count($html, 'data-video="'.$live->id.'"'))->toBe(0)
        ->and(substr_count($html, 'data-video="'.$other->id.'"'))->toBe(1)
        ->and($html)->toContain('data-remove-live');
});

it('lets the dono take the live video off the cardápio and deletes it with its Mux asset', function () {
    $live = liveVideoFor($this->dish);

    Livewire::test('pages::panel.dish-video', ['dish' => $this->dish])
        ->set('confirmingRemoval', true)
        ->call('removeLive')
        ->assertSet('confirmingRemoval', false)
        ->assertDontSeeHtml('data-remove-live')
        ->assertSee('Sem vídeo aprovado');

    expect(Video::find($live->id))->toBeNull()
        ->and($this->dish->fresh()->active_video_id)->toBeNull()
        ->and($this->mux->deleted)->toBe([$live->mux_asset_id]);
});

it('asks for confirmation first and never removes the live video on a bare call', function () {
    $live = liveVideoFor($this->dish);

    $component = Livewire::test('pages::panel.dish-video', ['dish' => $this->dish])
        ->assertSeeHtml('data-confirm-remove')
        ->assertSee('Não dá para desfazer')
        ->call('removeLive')
        ->assertSet('confirmingRemoval', false);

    expect(Video::find($live->id))->not->toBeNull()
        ->and($this->dish->fresh()->active_video_id)->toBe($live->id)
        ->and($this->mux->deleted)->toBe([]);

    // Cancelling the window leaves everything as it was.
    $component->set('confirmingRemoval', true)->set('confirmingRemoval', false)->call('removeLive');

    expect(Video::find($live->id))->not->toBeNull();
});

it('does nothing when the dish has no live video', function () {
    Livewire::test('pages::panel.dish-video', ['dish' => $this->dish])
        ->set('confirmingRemoval', true)
        ->call('removeLive')
        ->assertHasNoErrors();

    expect($this->mux->deleted)->toBe([]);
});

it('never lets a dono remove the live video of another restaurant', function () {
    $foreign = Dish::factory()->create();
    liveVideoFor($foreign);

    Livewire::test('pages::panel.dish-video', ['dish' => $foreign])->assertNotFound();
});

it('does not call a round "Pronto" while its video is still being processed', function () {
    $generation = VideoGeneration::factory()->create(['dish_id' => $this->dish->id, 'status_id' => GenerationStatus::idFor('pronto')]);
    Video::factory()->create(['dish_id' => $this->dish->id, 'generation_id' => $generation->id]);

    Livewire::test('pages::panel.dish-video', ['dish' => $this->dish])
        ->assertSee('Finalizando')
        ->assertDontSeeHtml('>Pronto</span>');

    Video::query()->update(['status_id' => VideoStatus::idFor('aguardando_aprovacao')]);

    Livewire::test('pages::panel.dish-video', ['dish' => $this->dish])
        ->assertDontSee('Finalizando')
        ->assertSee('Pronto');
});
