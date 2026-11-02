<?php

use App\Models\Category;
use App\Models\Dish;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->restaurant = $this->dono->restaurant;
    $this->actingAs($this->dono);
});

it('creates renames and hides a category', function () {
    $component = Livewire::test('pages::panel.categories')
        ->set('newCategoryName', 'Burgers')
        ->call('addCategory')
        ->assertHasNoErrors();

    $category = $this->restaurant->categories()->firstOrFail();

    $component->set("names.{$category->id}", 'Hambúrgueres')
        ->call('rename', $category->id)
        ->call('toggleVisibility', $category->id);

    expect($category->fresh())
        ->name->toBe('Hambúrgueres')
        ->is_visible->toBeFalse();
});

test('reordering categories persists the new display order', function () {
    [$burgers, $fumados, $bebidas] = collect(['Burgers', 'Fumados', 'Bebidas'])
        ->map(fn (string $name, int $order) => Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => $name,
            'display_order' => $order,
        ]))->all();

    // Drag "Bebidas" to the top.
    Livewire::test('pages::panel.categories')->call('sortCategory', $bebidas->id, 0);

    expect($this->restaurant->categories()->orderBy('display_order')->pluck('name')->all())
        ->toBe(['Bebidas', 'Burgers', 'Fumados']);

    // Drag "Bebidas" back to the end.
    Livewire::test('pages::panel.categories')->call('sortCategory', $bebidas->id, 2);

    expect($this->restaurant->categories()->orderBy('display_order')->pluck('name')->all())
        ->toBe(['Burgers', 'Fumados', 'Bebidas']);
});

test('a hidden category is excluded from the visible scope', function () {
    $visible = Category::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $hidden = Category::factory()->create(['restaurant_id' => $this->restaurant->id]);

    $visibleDish = Dish::factory()->create(['restaurant_id' => $this->restaurant->id, 'category_id' => $visible->id]);
    Dish::factory()->create(['restaurant_id' => $this->restaurant->id, 'category_id' => $hidden->id]);

    Livewire::test('pages::panel.categories')->call('toggleVisibility', $hidden->id);

    expect($this->restaurant->categories()->visible()->pluck('id')->all())->toBe([$visible->id])
        ->and($this->restaurant->dishes()->visible()->pluck('id')->all())->toBe([$visibleDish->id]);
});

it('never lets a dono manage another restaurants categories', function () {
    $foreign = Category::factory()->create();

    Livewire::test('pages::panel.categories')
        ->call('toggleVisibility', $foreign->id)
        ->assertNotFound();

    expect($foreign->fresh()->is_visible)->toBeTrue();
});

it('saves an icon and an own time window, and reopens with them', function () {
    $category = Category::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Almoço']);

    Livewire::test('pages::panel.categories')
        ->call('editDetails', $category->id)
        ->set('icon', '🍛')
        ->set('shows_from', '11:00')
        ->set('shows_until', '15:00')
        ->call('saveDetails')
        ->assertHasNoErrors();

    expect($category->fresh())->icon->toBe('🍛')->and(substr($category->fresh()->shows_from, 0, 5))->toBe('11:00');

    Livewire::test('pages::panel.categories')
        ->call('editDetails', $category->id)
        ->assertSet('icon', '🍛')
        ->assertSet('shows_from', '11:00')
        ->assertSet('shows_until', '15:00')
        ->assertSee('11:00–15:00');
});

it('requires both ends of the window or neither', function () {
    $category = Category::factory()->create(['restaurant_id' => $this->restaurant->id]);

    Livewire::test('pages::panel.categories')
        ->call('editDetails', $category->id)
        ->set('shows_from', '11:00')
        ->call('saveDetails')
        ->assertHasErrors('shows_from');

    expect($category->fresh()->shows_from)->toBeNull();
});

it('does not edit the details of another store category', function () {
    $other = Category::factory()->create();

    Livewire::test('pages::panel.categories')
        ->call('editDetails', $other->id)
        ->assertNotFound();
});
