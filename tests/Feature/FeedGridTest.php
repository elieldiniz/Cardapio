<?php

use App\Models\Category;
use App\Models\DishPhoto;
use App\Models\DishStatus;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->restaurant = Restaurant::factory()->create([
        'slug' => 'fumaca',
        'description' => 'Carnes defumadas 12h na casa.',
        'cover_path' => 'covers/1/capa.jpg',
    ]);
});

it('opens on a grid of every dish across categories, with the restaurant header', function () {
    $burgers = Category::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Burgers', 'display_order' => 0]);
    $bebidas = Category::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Bebidas', 'display_order' => 1]);
    $burger = feedDish($this->restaurant, ['name' => 'Burger Clássico'], $burgers);
    $limonada = feedDish($this->restaurant, ['name' => 'Limonada'], $bebidas);

    $response = $this->get('/r/fumaca')->assertOk();

    $response
        ->assertSee('data-view="grid"', false)
        ->assertSee('Carnes defumadas 12h na casa.')
        ->assertSee(Storage::disk('public')->url('covers/1/capa.jpg'), false)
        ->assertSeeInOrder(['data-grid-filter="all"', 'Todos', 'Burgers', 'Bebidas'], false)
        ->assertSee('data-open-dish="'.$burger->id.'" data-category="'.$burgers->id.'"', false)
        ->assertSee('data-open-dish="'.$limonada->id.'" data-category="'.$bebidas->id.'"', false)
        ->assertSee('data-back-to-grid', false);
});

it('shows the dish photo in the grid, falling back to the video cover, and flags sold out dishes', function () {
    $withPhoto = feedDish($this->restaurant, ['name' => 'Com foto']);
    DishPhoto::factory()->create(['dish_id' => $withPhoto->id, 'file_path' => 'dish-photos/1/frente.jpg']);
    $soldOut = feedDish($this->restaurant, ['name' => 'Esgotado aqui', 'status_id' => DishStatus::idFor('esgotado')]);

    $html = $this->get('/r/fumaca')->getContent();

    expect($html)
        ->toContain('src="'.Storage::disk('public')->url('dish-photos/1/frente.jpg').'"')
        ->toContain('src="'.$soldOut->activeVideo->cover_path.'"')
        ->toMatch('/data-open-dish="'.$soldOut->id.'".*?grid-item__sold-out/s');
});

it('lets the dono edit the cover photo and description shown on top of the grid', function () {
    Storage::fake('public');
    $dono = User::factory()->create();

    Livewire::actingAs($dono)
        ->test('pages::panel.appearance')
        ->set('cover', fakeImage('capa.png'))
        ->set('description', 'Fumeiro contemporâneo.')
        ->call('save')
        ->assertHasNoErrors();

    $restaurant = $dono->restaurant->fresh();
    Storage::disk('public')->assertExists($restaurant->cover_path);

    expect($restaurant->description)->toBe('Fumeiro contemporâneo.');

    $this->get(route('feed.show', $restaurant))
        ->assertSee('Fumeiro contemporâneo.')
        ->assertSee(Storage::disk('public')->url($restaurant->cover_path), false);
});

it('limits the restaurant description to 200 characters', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::panel.appearance')
        ->set('description', str_repeat('a', 201))
        ->call('save')
        ->assertHasErrors('description');
});
