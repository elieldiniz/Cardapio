<?php

use App\Models\Category;
use App\Models\Dish;
use App\Models\User;
use App\Models\Video;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->restaurant = $this->dono->restaurant;
    $category = Category::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $this->dish = Dish::factory()->create(['restaurant_id' => $this->restaurant->id, 'category_id' => $category->id]);
    $this->actingAs($this->dono);
});

test('marking a dish out of stock keeps it listed but flagged unavailable', function () {
    Livewire::test('pages::panel.dishes')->call('updateStatus', $this->dish->id, 'esgotado');

    $visible = $this->restaurant->dishes()->visible()->get();

    expect($visible->pluck('id')->all())->toBe([$this->dish->id])
        ->and($visible->first()->isSoldOut())->toBeTrue();
});

test('hiding a dish removes it from the visible scope', function () {
    Livewire::test('pages::panel.dishes')->call('updateStatus', $this->dish->id, 'oculto');

    expect($this->restaurant->dishes()->visible()->exists())->toBeFalse()
        ->and(Dish::find($this->dish->id))->not->toBeNull();
});

test('updating price does not require revoking video approval', function () {
    $video = Video::factory()->approved()->create(['dish_id' => $this->dish->id]);
    $this->dish->update(['active_video_id' => $video->id]);

    Livewire::test('pages::panel.dishes')
        ->set("prices.{$this->dish->id}", '45,50')
        ->call('updatePrice', $this->dish->id)
        ->assertHasNoErrors();

    $this->dish->refresh();

    expect($this->dish->price)->toBe('45.50')
        ->and($this->dish->active_video_id)->toBe($video->id)
        ->and($video->fresh()->status->slug)->toBe('aprovado');
});

it('rejects an invalid quick-edit price', function () {
    Livewire::test('pages::panel.dishes')
        ->set("prices.{$this->dish->id}", 'abc')
        ->call('updatePrice', $this->dish->id)
        ->assertHasErrors("prices.{$this->dish->id}");
});
