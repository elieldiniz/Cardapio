<?php

use App\Models\Badge;
use App\Models\Category;
use App\Models\Dish;
use App\Models\DishStatus;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->restaurant = $this->dono->restaurant;
    $this->category = Category::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $this->actingAs($this->dono);
});

test('creating a dish requires name price and category', function () {
    Livewire::test('pages::panel.dish-form')
        ->set('category_id', null)
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'price' => 'required', 'category_id' => 'required']);

    expect(Dish::count())->toBe(0);
});

test('a newly created dish has no active video until one is approved', function () {
    Livewire::test('pages::panel.dish-form')
        ->set('name', 'Burger Clássico')
        ->set('category_id', $this->category->id)
        ->set('price', '32,90')
        ->call('save')
        ->assertHasNoErrors();

    $dish = Dish::firstOrFail();

    expect($dish->active_video_id)->toBeNull()
        ->and($dish->price)->toBe('32.90')
        ->and($dish->status->slug)->toBe('ativo');
});

it('saves optional descriptions badges and variants', function () {
    $novo = Badge::where('slug', 'novo')->firstOrFail();

    Livewire::test('pages::panel.dish-form')
        ->set('name', 'Limonada Suíça')
        ->set('category_id', $this->category->id)
        ->set('price', 'R$ 10,90')
        ->set('short_description', 'Limonada batida com leite condensado.')
        ->set('description', 'Batida na hora, com hortelã fresca.')
        ->set('badge_ids', [$novo->id])
        ->call('addVariant')
        ->set('variants.0.name', 'G')
        ->set('variants.0.price', '14,90')
        ->call('save')
        ->assertHasNoErrors();

    $dish = Dish::with(['badges', 'variants'])->firstOrFail();

    expect($dish->badges->pluck('slug')->all())->toBe(['novo'])
        ->and($dish->variants->first()->only('name', 'price'))->toBe(['name' => 'G', 'price' => '14.90']);
});

it('edits an existing dish without touching its active video', function () {
    $dish = Dish::factory()->create(['restaurant_id' => $this->restaurant->id, 'category_id' => $this->category->id]);

    Livewire::test('pages::panel.dish-form', ['dish' => $dish])
        ->assertSet('name', $dish->name)
        ->set('name', 'Nome novo')
        ->call('save')
        ->assertHasNoErrors();

    expect($dish->fresh()->name)->toBe('Nome novo');
});

it('blocks new dishes once the plan dish limit is reached', function () {
    $this->restaurant->plan->update(['dish_limit' => 1]);
    Dish::factory()->create(['restaurant_id' => $this->restaurant->id, 'category_id' => $this->category->id]);

    $this->get(route('panel.dishes.create'))->assertForbidden();
});

it('returns 404 when editing another restaurants dish', function () {
    $foreign = Dish::factory()->create();

    $this->get(route('panel.dishes.edit', $foreign))->assertNotFound();
});

describe('product list (RF-06f)', function () {
    beforeEach(function () {
        $this->lanches = Category::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Lanches']);
        $this->bebidas = Category::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Bebidas']);

        $make = fn (string $name, Category $category, string $status) => Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id, 'category_id' => $category->id, 'name' => $name,
            'status_id' => DishStatus::idFor($status),
        ]);

        $this->ativo = $make('X-Bacon', $this->lanches, 'ativo');
        $this->esgotado = $make('X-Salada', $this->lanches, 'esgotado');
        $this->oculto = $make('Coca-Cola', $this->bebidas, 'oculto');
    });

    it('filters by the status tabs and shows the counts', function () {
        $page = Livewire::test('pages::panel.dishes');

        $page->assertSee('X-Bacon')->assertSee('X-Salada')->assertSee('Coca-Cola');

        $page->set('tab', 'ativos')->assertSee('X-Bacon')->assertDontSee('X-Salada')->assertDontSee('Coca-Cola');
        $page->set('tab', 'indisponiveis')->assertSee('X-Salada')->assertDontSee('X-Bacon');
        $page->set('tab', 'ocultos')->assertSee('Coca-Cola')->assertDontSee('X-Bacon');

        expect($page->instance()->counts())->toBe(['todos' => 3, 'ativos' => 1, 'indisponiveis' => 1, 'ocultos' => 1]);
    });

    it('searches by name and filters by category', function () {
        Livewire::test('pages::panel.dishes')
            ->set('search', 'salada')->assertSee('X-Salada')->assertDontSee('X-Bacon')
            ->set('search', '')
            ->set('category', (string) $this->bebidas->id)->assertSee('Coca-Cola')->assertDontSee('X-Bacon')
            ->set('category', (string) $this->lanches->id)->assertSee('X-Bacon')->assertDontSee('Coca-Cola');
    });

    it('says when nothing matches', function () {
        Livewire::test('pages::panel.dishes')->set('search', 'nada parecido')->assertSee('Nenhum produto encontrado');
    });

    it('edits the price in the row with every pasted format and keeps the old one on error', function () {
        $page = Livewire::test('pages::panel.dishes')
            ->set("prices.{$this->ativo->id}", '3290')->call('updatePrice', $this->ativo->id)->assertHasNoErrors();

        expect($this->ativo->fresh()->price)->toBe('32.90');

        $page->set("prices.{$this->ativo->id}", 'abc')->call('updatePrice', $this->ativo->id)->assertHasErrors("prices.{$this->ativo->id}");

        expect($this->ativo->fresh()->price)->toBe('32.90');
    });

    it('toggles available and visible from the row', function () {
        Livewire::test('pages::panel.dishes')
            ->call('toggleFlag', $this->ativo->id, 'available');
        expect($this->ativo->fresh()->status->slug)->toBe('esgotado');

        Livewire::test('pages::panel.dishes')->call('toggleFlag', $this->ativo->id, 'visible');
        expect($this->ativo->fresh()->status->slug)->toBe('oculto');

        Livewire::test('pages::panel.dishes')->call('toggleFlag', $this->ativo->id, 'visible');
        expect($this->ativo->fresh()->status->slug)->toBe('ativo');
    });

    it('duplicates and deletes a product', function () {
        Livewire::test('pages::panel.dishes')->call('duplicate', $this->ativo->id);

        expect($this->restaurant->dishes()->where('name', 'X-Bacon (cópia)')->exists())->toBeTrue();

        Livewire::test('pages::panel.dishes')->call('delete', $this->ativo->id);

        expect(Dish::query()->whereKey($this->ativo->id)->exists())->toBeFalse();
    });

    it('does not delete a product of another store from the list', function () {
        $foreign = Dish::factory()->create();

        Livewire::test('pages::panel.dishes')->call('delete', $foreign->id)->assertNotFound();

        expect(Dish::query()->whereKey($foreign->id)->exists())->toBeTrue();
    });
});
