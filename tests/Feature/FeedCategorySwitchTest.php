<?php

use App\Models\Category;
use App\Models\DishStatus;
use App\Models\Restaurant;
use App\Support\MuxUrls;

test('switching category returns only that categorys visible dishes starting at the first one', function () {
    seedReferenceData();
    $restaurant = Restaurant::factory()->create();
    $burgers = Category::factory()->create(['restaurant_id' => $restaurant->id, 'display_order' => 0]);
    $bebidas = Category::factory()->create(['restaurant_id' => $restaurant->id, 'display_order' => 1]);

    feedDish($restaurant, ['name' => 'Burger'], $burgers);
    $second = feedDish($restaurant, ['name' => 'Chopp', 'display_order' => 2], $bebidas);
    $first = feedDish($restaurant, ['name' => 'Limonada', 'display_order' => 1], $bebidas);
    feedDish($restaurant, ['name' => 'Oculto', 'status_id' => DishStatus::idFor('oculto')], $bebidas);

    $response = $this->getJson(route('feed.category', [$restaurant, $bebidas]))->assertOk();

    expect($response->json('dishes.*.name'))->toBe(['Limonada', 'Chopp'])
        ->and($response->json('dishes.0.id'))->toBe($first->id)
        ->and($response->json('dishes.0.video_url'))->toBe(MuxUrls::mp4($first->activeVideo->mux_playback_id));
});

it('does not serve another restaurants or a hidden category', function () {
    seedReferenceData();
    $restaurant = Restaurant::factory()->create();
    $foreign = Category::factory()->create();
    $hidden = Category::factory()->hidden()->create(['restaurant_id' => $restaurant->id]);

    $this->getJson(route('feed.category', [$restaurant, $foreign]))->assertNotFound();
    $this->getJson(route('feed.category', [$restaurant, $hidden]))->assertNotFound();
});
