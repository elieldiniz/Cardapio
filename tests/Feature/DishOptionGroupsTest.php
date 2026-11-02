<?php

use App\Actions\Menu\SaveDish;
use App\Models\Category;
use App\Models\Dish;
use App\Models\OptionGroup;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * RF-03, RNF-01 — complements saved together with the product, including on create.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->restaurant = $this->dono->restaurant;
    $this->category = Category::factory()->create(['restaurant_id' => $this->restaurant->id]);
});

it('creates a product with two groups and reopens with both, in order', function () {
    $sizes = OptionGroup::factory()->required()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Tamanho']);
    $extras = OptionGroup::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Adicionais']);

    Livewire::actingAs($this->dono)->test('pages::panel.dish-form')
        ->set('name', 'X-Bacon')
        ->set('category_id', $this->category->id)
        ->set('price', '28,90')
        ->set('option_group_ids', [$extras->id, $sizes->id])
        ->call('save')
        ->assertHasNoErrors();

    $dish = Dish::query()->where('name', 'X-Bacon')->firstOrFail();

    expect($dish->optionGroups->pluck('name')->all())->toBe(['Adicionais', 'Tamanho']);

    Livewire::actingAs($this->dono)->test('pages::panel.dish-form', ['dish' => $dish])
        ->assertSet('option_group_ids', [$extras->id, $sizes->id]);
});

it('keeps the groups when the Action is called without the key', function () {
    $group = OptionGroup::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $dish = Dish::factory()->create(['restaurant_id' => $this->restaurant->id, 'category_id' => $this->category->id]);
    $dish->optionGroups()->attach($group->id, ['display_order' => 1]);

    app(SaveDish::class)->handle($this->restaurant, [
        'category_id' => $this->category->id, 'name' => 'Novo nome', 'price' => '10.00',
        'short_description' => null, 'description' => null, 'badge_ids' => [], 'variants' => [],
    ], $dish);

    expect($dish->fresh()->optionGroups()->count())->toBe(1);
});

it('rejects a group of another restaurant', function () {
    $foreign = OptionGroup::factory()->create();

    expect(fn () => app(SaveDish::class)->handle($this->restaurant, [
        'category_id' => $this->category->id, 'name' => 'Intruso', 'price' => '10.00',
        'short_description' => null, 'description' => null, 'badge_ids' => [], 'variants' => [],
        'option_group_ids' => [$foreign->id],
    ]))->toThrow(ValidationException::class);

    expect(Dish::query()->where('name', 'Intruso')->exists())->toBeFalse();
});

it('rejects a foreign group sent through the form', function () {
    $foreign = OptionGroup::factory()->create();

    Livewire::actingAs($this->dono)->test('pages::panel.dish-form')
        ->set('name', 'Intruso')
        ->set('category_id', $this->category->id)
        ->set('price', '10,00')
        ->set('option_group_ids', [$foreign->id])
        ->call('save')
        ->assertHasErrors('option_group_ids.0');
});

it('detaches groups when the dono unticks them', function () {
    $group = OptionGroup::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $dish = Dish::factory()->create(['restaurant_id' => $this->restaurant->id, 'category_id' => $this->category->id]);
    $dish->optionGroups()->attach($group->id, ['display_order' => 1]);

    Livewire::actingAs($this->dono)->test('pages::panel.dish-form', ['dish' => $dish])
        ->set('option_group_ids', [])
        ->call('save')
        ->assertHasNoErrors();

    expect($dish->fresh()->optionGroups()->count())->toBe(0);
});
