<?php

use App\Models\Badge;
use App\Models\Category;
use App\Models\Dish;
use App\Models\DishPhoto;
use App\Models\DishVariant;
use App\Models\Restaurant;

it('a category belongs to a restaurant and has many dishes', function () {
    $restaurant = Restaurant::factory()->create();
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $dish = Dish::factory()->create(['category_id' => $category->id, 'restaurant_id' => $restaurant->id]);

    expect($category->restaurant->is($restaurant))->toBeTrue()
        ->and($category->dishes->pluck('id')->all())->toBe([$dish->id]);
});

it('the visible scope only returns visible categories', function () {
    $restaurant = Restaurant::factory()->create();
    $visible = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    Category::factory()->hidden()->create(['restaurant_id' => $restaurant->id]);

    expect(Category::visible()->pluck('id')->all())->toBe([$visible->id]);
});

it('a dish belongs to its restaurant, category and status', function () {
    $dish = Dish::factory()->create();

    expect($dish->restaurant)->toBeInstanceOf(Restaurant::class)
        ->and($dish->category)->toBeInstanceOf(Category::class)
        ->and($dish->status->slug)->toBe('ativo');
});

it('a dish has many variants and photos', function () {
    $dish = Dish::factory()->create();
    $variant = DishVariant::factory()->create(['dish_id' => $dish->id]);
    $photo = DishPhoto::factory()->create(['dish_id' => $dish->id]);

    expect($dish->variants->pluck('id')->all())->toBe([$variant->id])
        ->and($dish->photos->pluck('id')->all())->toBe([$photo->id])
        ->and($variant->dish->is($dish))->toBeTrue()
        ->and($photo->dish->is($dish))->toBeTrue();
});

it('a dish can have many badges through badge_dish and vice versa', function () {
    $dish = Dish::factory()->create();
    $badge = Badge::create(['name' => 'Novo', 'slug' => 'novo']);

    $dish->badges()->attach($badge);

    expect($dish->badges->pluck('id')->all())->toBe([$badge->id])
        ->and($badge->dishes->pluck('id')->all())->toBe([$dish->id]);
});
