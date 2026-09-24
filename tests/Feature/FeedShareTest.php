<?php

use App\Models\Category;
use App\Models\Dish;
use App\Models\Restaurant;

beforeEach(function () {
    seedReferenceData();
    $this->restaurant = Restaurant::factory()->create(['slug' => 'fumaca', 'name' => 'Fumaça']);
});

test('the feed has a share button pointing at the restaurant feed url', function () {
    feedDish($this->restaurant);

    $this->get('/r/fumaca')
        ->assertOk()
        ->assertSee('data-share-url="'.route('feed.show', $this->restaurant).'"', false)
        ->assertSee('aria-label="Compartilhar prato"', false)
        ->assertDontSee('data-open-dish-on-load', false);
});

test('the plain feed link previews the restaurant', function () {
    feedDish($this->restaurant);

    $this->get('/r/fumaca')
        ->assertSee('<meta property="og:title" content="Fumaça · Cardápio">', false)
        ->assertSee('<meta property="og:url" content="'.route('feed.show', $this->restaurant).'">', false);
});

test('a shared dish link opens the feed on that dish with its category rendered', function () {
    $burgers = Category::factory()->create(['restaurant_id' => $this->restaurant->id, 'display_order' => 0]);
    $drinks = Category::factory()->create(['restaurant_id' => $this->restaurant->id, 'display_order' => 1]);
    feedDish($this->restaurant, ['name' => 'Burger Clássico'], $burgers);
    $shared = feedDish($this->restaurant, ['name' => 'Limonada Suíça', 'short_description' => 'Gelada e cremosa.'], $drinks);

    $html = $this->get("/r/fumaca?prato={$shared->id}")->assertOk()->getContent();

    expect($html)
        ->toContain('data-open-dish-on-load="'.$shared->id.'"')
        ->toContain('data-category="'.$drinks->id.'"')
        ->toContain('data-dish-id="'.$shared->id.'"')
        ->toContain('<meta property="og:title" content="Limonada Suíça · Fumaça">')
        ->toContain('<meta property="og:description" content="Gelada e cremosa.">')
        ->toContain('<meta property="og:image" content="'.$shared->activeVideo->cover_path.'">')
        ->toContain('<meta property="og:url" content="'.route('feed.show', $this->restaurant).'?prato='.$shared->id.'">');
});

test('a shared link to a dish that is not in this feed falls back to the normal feed', function (Closure $prato) {
    feedDish($this->restaurant);

    $this->get('/r/fumaca?'.http_build_query(['prato' => $prato($this)]))
        ->assertOk()
        ->assertDontSee('data-open-dish-on-load', false)
        ->assertSee('<meta property="og:title" content="Fumaça · Cardápio">', false);
})->with([
    'another restaurant' => fn () => fn () => feedDish(Restaurant::factory()->create())->id,
    'without an approved video' => fn () => fn ($test) => Dish::factory()->create(['restaurant_id' => $test->restaurant->id])->id,
    'not an id' => fn () => fn () => 'abc',
    'an array' => fn () => fn () => ['1'],
    'zero' => fn () => fn () => '0',
]);
