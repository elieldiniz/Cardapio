<?php

use App\Models\Dish;
use App\Models\User;
use App\Models\Video;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->mux = fakeVideoPipeline();
    $this->dono = User::factory()->create();
    $this->dish = Dish::factory()->create(['restaurant_id' => $this->dono->restaurant_id]);
    $this->actingAs($this->dono);
});

function activeVideoFor(Dish $dish): Video
{
    $video = Video::factory()->approved()->create(['dish_id' => $dish->id]);
    $dish->update(['active_video_id' => $video->id]);

    return $video;
}

it('deletes a replaced video and its Mux asset', function () {
    activeVideoFor($this->dish);
    $replaced = Video::factory()->rejected()->create(['dish_id' => $this->dish->id]);

    Livewire::test('pages::panel.dish-video', ['dish' => $this->dish])
        ->assertSeeHtml('data-delete-video')
        ->call('deleteVideo', $replaced->id);

    expect(Video::find($replaced->id))->toBeNull()
        ->and($this->mux->deleted)->toBe([$replaced->mux_asset_id]);
});

it('refuses to delete the video on the cardápio or one still processing', function () {
    $active = activeVideoFor($this->dish);
    $processing = Video::factory()->create(['dish_id' => $this->dish->id]);

    Livewire::test('pages::panel.dish-video', ['dish' => $this->dish])
        ->call('deleteVideo', $active->id)
        ->assertDispatched('toast', type: 'error')
        ->call('deleteVideo', $processing->id)
        ->assertDispatched('toast', type: 'error');

    expect(Video::find($active->id))->not->toBeNull()
        ->and(Video::find($processing->id))->not->toBeNull()
        ->and($this->mux->deleted)->toBe([]);
});

it('brings a replaced video back to the cardápio without a new generation', function () {
    $current = activeVideoFor($this->dish);
    $replaced = Video::factory()->rejected()->create(['dish_id' => $this->dish->id]);

    Livewire::test('pages::panel.dish-video', ['dish' => $this->dish])
        ->assertSee('Usar de novo')
        ->call('approve', $replaced->id);

    expect($this->dish->fresh()->active_video_id)->toBe($replaced->id)
        ->and($replaced->fresh()->status->slug)->toBe('aprovado')
        ->and($current->fresh()->status->slug)->toBe('rejeitado');
});

it('does not offer to reuse an upload that was refused', function () {
    $refused = Video::factory()->upload()->rejected()->create(['dish_id' => $this->dish->id, 'mux_playback_id' => null, 'duration_seconds' => 30]);

    Livewire::test('pages::panel.dish-video', ['dish' => $this->dish])
        ->assertDontSee('Usar de novo')
        ->call('approve', $refused->id)
        ->assertDispatched('toast', type: 'error');

    expect($this->dish->fresh()->active_video_id)->toBeNull();
});
