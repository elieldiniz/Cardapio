<?php

use App\Models\Category;
use App\Models\Dish;
use App\Models\DishStatus;
use App\Models\Restaurant;
use App\Models\Video;
use App\Support\MuxUrls;

beforeEach(function () {
    seedReferenceData();
    $this->restaurant = Restaurant::factory()->create(['slug' => 'fumaca']);
});

test('the feed route requires no authentication', function () {
    feedDish($this->restaurant, ['name' => 'Burger Clássico']);

    $this->assertGuest();
    $this->get('/r/fumaca')->assertOk()->assertSee('Burger Clássico');
});

test('the initial response embeds the first dishs cover and video url', function () {
    $burgers = Category::factory()->create(['restaurant_id' => $this->restaurant->id, 'display_order' => 0]);
    $first = feedDish($this->restaurant, ['display_order' => 0], $burgers);
    feedDish($this->restaurant, ['display_order' => 1], $burgers);

    $playback = $first->activeVideo->mux_playback_id;
    $html = $this->get(route('feed.show', $this->restaurant))->getContent();

    expect($html)
        ->toContain('src="'.MuxUrls::mp4($playback).'"')
        ->toContain('poster="'.$first->activeVideo->cover_path.'"')
        ->toContain('<link rel="preload" as="image" href="'.$first->activeVideo->cover_path.'"');

    // Only the first video gets an eager src; the rest wait for the observer.
    expect(substr_count($html, ' autoplay'))->toBe(1);
});

test('a dish without an approved active video never appears in the feed', function () {
    $category = Category::factory()->create(['restaurant_id' => $this->restaurant->id]);
    feedDish($this->restaurant, ['name' => 'Com vídeo'], $category);

    $withoutVideo = Dish::factory()->create(['restaurant_id' => $this->restaurant->id, 'category_id' => $category->id, 'name' => 'Sem vídeo']);
    $pending = Dish::factory()->create(['restaurant_id' => $this->restaurant->id, 'category_id' => $category->id, 'name' => 'Vídeo pendente']);
    $pendingVideo = Video::factory()->awaitingApproval()->create(['dish_id' => $pending->id]);
    $pending->update(['active_video_id' => $pendingVideo->id]);

    $this->get(route('feed.show', $this->restaurant))
        ->assertSee('Com vídeo')
        ->assertDontSee('Sem vídeo')
        ->assertDontSee('Vídeo pendente');
});

test('a dish in a hidden category never appears in the feed', function () {
    $hidden = Category::factory()->hidden()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Secreta']);
    feedDish($this->restaurant, ['name' => 'Prato escondido'], $hidden);
    feedDish($this->restaurant, ['name' => 'Prato visível']);

    $this->get(route('feed.show', $this->restaurant))
        ->assertSee('Prato visível')
        ->assertDontSee('Prato escondido')
        ->assertDontSee('Secreta');
});

it('orders categories and dishes and keeps sold out dishes flagged', function () {
    $second = Category::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Bebidas', 'display_order' => 1]);
    $first = Category::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Burgers', 'display_order' => 0]);
    feedDish($this->restaurant, ['name' => 'B-dois', 'display_order' => 2], $first);
    feedDish($this->restaurant, ['name' => 'B-um', 'display_order' => 1, 'status_id' => DishStatus::idFor('esgotado')], $first);
    feedDish($this->restaurant, ['name' => 'Limonada'], $second);

    $this->get(route('feed.show', $this->restaurant))
        ->assertSeeInOrder(['Burgers', 'Bebidas'])
        ->assertSeeInOrder(['Esgotado', 'B-um', 'B-dois'])
        ->assertDontSee('Limonada');
});

it('shows the made-with branding only on plans that keep it', function () {
    feedDish($this->restaurant);

    $this->get(route('feed.show', $this->restaurant))->assertSee('feito com');

    $this->restaurant->plan->update(['removes_branding' => true]);

    $this->get(route('feed.show', $this->restaurant))->assertDontSee('feito com');
});

it('reflects a price change on the next request without caching', function () {
    $dish = feedDish($this->restaurant, ['price' => '32.90']);

    $this->get(route('feed.show', $this->restaurant))->assertSee('R$ 32,90')->assertHeader('Cache-Control', 'no-cache, private');

    $dish->update(['price' => '35.00']);

    $this->get(route('feed.show', $this->restaurant))->assertSee('R$ 35,00');
});
