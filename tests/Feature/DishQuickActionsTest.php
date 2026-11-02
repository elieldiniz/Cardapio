<?php

use App\Actions\Menu\DeleteDish;
use App\Actions\Menu\DuplicateDish;
use App\Actions\Menu\SetDishFlag;
use App\Actions\Menu\UpdateDishPrice;
use App\Models\Badge;
use App\Models\Category;
use App\Models\Dish;
use App\Models\DishPhoto;
use App\Models\DishStatus;
use App\Models\DishView;
use App\Models\OptionGroup;
use App\Models\Order;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoStatus;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * RF-06f, RNF-01, RF-25 — the quick actions of the product list.
 */
beforeEach(function () {
    seedReferenceData();
    fakeVideoPipeline();
    $this->dono = User::factory()->create();
    $this->restaurant = $this->dono->restaurant;
    $this->category = Category::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $this->dish = Dish::factory()->create(['restaurant_id' => $this->restaurant->id, 'category_id' => $this->category->id, 'name' => 'X-Bacon', 'price' => '10.00', 'status_id' => DishStatus::idFor('ativo')]);
});

it('reads a pasted price in every documented format', function (string $input, string $expected) {
    app(UpdateDishPrice::class)->handle($this->dish, $input);

    expect($this->dish->fresh()->price)->toBe($expected);
})->with([
    'com R$ e vírgula' => ['R$ 32,90', '32.90'],
    'só vírgula' => ['32,90', '32.90'],
    'centavos colados' => ['3290', '32.90'],
    'inteiro curto' => ['32', '32.00'],
    'milhar' => ['1.234,50', '1234.50'],
    'ponto decimal' => ['32.90', '32.90'],
]);

it('rejects an invalid or zero price', function (string $input) {
    expect(fn () => app(UpdateDishPrice::class)->handle($this->dish, $input))->toThrow(ValidationException::class);

    expect($this->dish->fresh()->price)->toBe('10.00');
})->with(['abc', '', '0', '0,00', '-5']);

it('keeps Money::parse as it was for plain integers', function () {
    expect(Money::parse('3290'))->toBe('3290.00')->and(Money::parsePrice('3290'))->toBe('32.90')->and(Money::parsePrice('999'))->toBe('999.00');
});

it('does not change the snapshot of a past order when the price changes', function () {
    $order = Order::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $item = $order->items()->create(['dish_id' => $this->dish->id, 'name' => 'X-Bacon', 'quantity' => 1, 'unit_price' => '10.00', 'total' => '10.00']);

    app(UpdateDishPrice::class)->handle($this->dish, '99,00');

    expect($item->fresh()->unit_price)->toBe('10.00');
});

it('switches available and visible on the product status', function () {
    $action = app(SetDishFlag::class);

    expect($action->handle($this->restaurant, $this->dish, 'available', false)->status->slug)->toBe('esgotado')
        ->and($action->handle($this->restaurant, $this->dish, 'available', true)->status->slug)->toBe('ativo')
        ->and($action->handle($this->restaurant, $this->dish, 'visible', false)->status->slug)->toBe('oculto')
        ->and($action->handle($this->restaurant, $this->dish, 'available', true)->status->slug)->toBe('oculto')
        ->and($action->handle($this->restaurant, $this->dish, 'visible', true)->status->slug)->toBe('ativo');
});

it('refuses to show a hidden product above the plan limit', function () {
    $this->restaurant->plan->update(['dish_limit' => 1]);
    $hidden = Dish::factory()->create(['restaurant_id' => $this->restaurant->id, 'category_id' => $this->category->id, 'status_id' => DishStatus::idFor('oculto')]);

    expect(fn () => app(SetDishFlag::class)->handle($this->restaurant->fresh(), $hidden, 'visible', true))->toThrow(ValidationException::class);

    expect($hidden->fresh()->status->slug)->toBe('oculto');
});

it('duplicates a product with variations, badges and complement groups but no video', function () {
    $this->dish->variants()->create(['name' => 'Grande', 'price' => '20.00']);
    $this->dish->badges()->sync([Badge::first()->id]);
    $group = OptionGroup::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $this->dish->optionGroups()->attach($group->id, ['display_order' => 1]);
    $video = Video::factory()->approved()->create(['dish_id' => $this->dish->id]);
    $this->dish->update(['active_video_id' => $video->id]);

    $copy = app(DuplicateDish::class)->handle($this->restaurant, $this->dish->fresh());

    expect($copy->id)->not->toBe($this->dish->id)
        ->and($copy->name)->toBe('X-Bacon (cópia)')
        ->and($copy->price)->toBe('10.00')
        ->and($copy->variants()->count())->toBe(1)
        ->and($copy->badges()->count())->toBe(1)
        ->and($copy->optionGroups()->count())->toBe(1)
        ->and($copy->active_video_id)->toBeNull()
        ->and($copy->videos()->count())->toBe(0);
});

it('blocks duplicating at the plan limit', function () {
    $this->restaurant->plan->update(['dish_limit' => 1]);

    expect(fn () => app(DuplicateDish::class)->handle($this->restaurant->fresh(), $this->dish))->toThrow(ValidationException::class);

    expect($this->restaurant->dishes()->count())->toBe(1);
});

it('deletes a product with everything attached and keeps past orders readable', function () {
    $this->dish->variants()->create(['name' => 'Grande', 'price' => '20.00']);
    $this->dish->badges()->sync([Badge::first()->id]);
    DishPhoto::factory()->create(['dish_id' => $this->dish->id]);
    $video = Video::factory()->approved()->create(['dish_id' => $this->dish->id]);
    $this->dish->update(['active_video_id' => $video->id]);
    DishView::query()->create(['dish_id' => $this->dish->id, 'restaurant_id' => $this->restaurant->id, 'session_token' => 'abc', 'viewed_on' => now()->toDateString(), 'watch_seconds' => 3]);
    $order = Order::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $item = $order->items()->create(['dish_id' => $this->dish->id, 'name' => 'X-Bacon', 'quantity' => 1, 'unit_price' => '10.00', 'total' => '10.00']);

    app(DeleteDish::class)->handle($this->restaurant, $this->dish->fresh());

    expect(Dish::query()->whereKey($this->dish->id)->exists())->toBeFalse()
        ->and(Video::query()->where('dish_id', $this->dish->id)->exists())->toBeFalse()
        ->and(DishPhoto::query()->where('dish_id', $this->dish->id)->exists())->toBeFalse()
        ->and($item->fresh())->name->toBe('X-Bacon')->dish_id->toBeNull();
});

it('does not delete a product with a video still processing', function () {
    Video::factory()->create(['dish_id' => $this->dish->id, 'status_id' => VideoStatus::idFor('processando')]);

    expect(fn () => app(DeleteDish::class)->handle($this->restaurant, $this->dish->fresh()))->toThrow(ValidationException::class);

    expect(Dish::query()->whereKey($this->dish->id)->exists())->toBeTrue();
});

it('never acts on a product of another store', function () {
    $foreign = Dish::factory()->create();

    foreach ([
        fn () => app(DuplicateDish::class)->handle($this->restaurant, $foreign),
        fn () => app(DeleteDish::class)->handle($this->restaurant, $foreign),
        fn () => app(SetDishFlag::class)->handle($this->restaurant, $foreign, 'visible', false),
    ] as $attempt) {
        expect($attempt)->toThrow(NotFoundHttpException::class);
    }

    expect(Dish::query()->whereKey($foreign->id)->exists())->toBeTrue();
});
