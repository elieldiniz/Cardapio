<?php

use App\Models\Dish;
use App\Models\DishView;
use App\Models\Restaurant;

beforeEach(function () {
    seedReferenceData();
    $this->restaurant = Restaurant::factory()->create();
    $this->dish = feedDish($this->restaurant);
});

test('watch time for the same session and day accumulates into one row instead of many', function () {
    foreach ([4.2, 3.0, 5.8] as $seconds) {
        $this->postJson(route('feed.views', $this->restaurant), [
            'session_token' => 'session-a',
            'views' => [['dish_id' => $this->dish->id, 'seconds' => $seconds]],
        ])->assertNoContent();
    }

    $view = DishView::sole();

    expect($view->seconds_watched)->toBe(13)
        ->and($view->restaurant_id)->toBe($this->restaurant->id)
        ->and($view->viewed_on->toDateString())->toBe(today()->toDateString());

    // A new session is a new view.
    $this->postJson(route('feed.views', $this->restaurant), [
        'session_token' => 'session-b',
        'views' => [['dish_id' => $this->dish->id, 'seconds' => 0]],
    ]);

    expect(DishView::count())->toBe(2);
});

it('accepts a text plain beacon body and ignores dishes of other restaurants', function () {
    $foreign = Dish::factory()->create();

    $this->call('POST', route('feed.views', $this->restaurant), [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode([
        'session_token' => 'beacon',
        'views' => [
            ['dish_id' => $this->dish->id, 'seconds' => 2],
            ['dish_id' => $foreign->id, 'seconds' => 50],
        ],
    ]))->assertNoContent();

    expect(DishView::pluck('dish_id')->all())->toBe([$this->dish->id]);
});
