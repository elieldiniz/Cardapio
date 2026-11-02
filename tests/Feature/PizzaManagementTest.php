<?php

use App\Actions\Menu\SavePizza;
use App\Models\Category;
use App\Models\Dish;
use App\Models\PizzaFlavor;
use App\Models\PizzaFlavorPrice;
use App\Models\PizzaSize;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * RF-14 — saving a pizza: sizes, flavors and prices, never half saved.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dish = Dish::factory()->create(['category_id' => Category::factory()->create()->id]);
    $this->sizes = [
        ['name' => 'Broto', 'slices' => 4, 'max_flavors' => 1],
        ['name' => 'Média', 'slices' => 6, 'max_flavors' => 2],
        ['name' => 'Grande', 'slices' => 8, 'max_flavors' => 3],
    ];
    $this->flavors = [
        ['name' => 'Calabresa', 'section' => 'Tradicionais', 'ingredients' => 'Calabresa e cebola', 'prices' => ['30,00', '45,00', '55,00']],
        ['name' => 'Portuguesa', 'section' => 'Especiais', 'prices' => ['34,00', '', '62,00']],
    ];
});

it('creates sizes, flavors and prices together and reads them back', function () {
    app(SavePizza::class)->handle($this->dish, $this->sizes, $this->flavors);

    $dish = $this->dish->fresh();

    expect($dish->kind)->toBe('pizza')
        ->and($dish->pizzaSizes->pluck('name')->all())->toBe(['Broto', 'Média', 'Grande'])
        ->and($dish->pizzaSizes->pluck('max_flavors')->all())->toBe([1, 2, 3])
        ->and($dish->pizzaFlavors->pluck('name')->all())->toBe(['Calabresa', 'Portuguesa'])
        ->and($dish->pizzaFlavors[1]->section)->toBe('Especiais')
        ->and(PizzaFlavorPrice::count())->toBe(5);

    $portuguesa = $dish->pizzaFlavors[1];

    // The empty price means the flavor does not exist in that size.
    expect($portuguesa->prices()->count())->toBe(2)->and($portuguesa->prices()->whereHas('size', fn ($q) => $q->where('name', 'Média'))->exists())->toBeFalse();
});

it('uses the cheapest price as the product price ("a partir de")', function () {
    app(SavePizza::class)->handle($this->dish, $this->sizes, $this->flavors);

    expect($this->dish->fresh()->price)->toBe('30.00');
});

it('refuses a pizza without any flavor with a price, saving nothing', function () {
    foreach ([[], [['name' => 'Calabresa', 'prices' => ['', '', '']]]] as $flavors) {
        expect(fn () => app(SavePizza::class)->handle($this->dish, $this->sizes, $flavors))->toThrow(ValidationException::class);
    }

    expect(PizzaSize::count())->toBe(0)->and(PizzaFlavor::count())->toBe(0)->and($this->dish->fresh()->kind)->toBe('common');
});

it('refuses a flavor without a price in some tab and names it', function () {
    $flavors = [...$this->flavors, ['name' => 'Mussarela', 'prices' => ['', '', '']]];

    try {
        app(SavePizza::class)->handle($this->dish, $this->sizes, $flavors);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('flavors.2.prices');
        expect($exception->errors()['flavors.2.prices'][0])->toContain('Mussarela');
    }

    expect(PizzaFlavor::count())->toBe(0);
});

it('validates the sizes and the prices', function (array $sizes, array $flavors, string $field) {
    try {
        app(SavePizza::class)->handle($this->dish, $sizes, $flavors);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }
})->with([
    'sem tamanho' => [[], [['name' => 'A', 'prices' => ['10']]], 'sizes'],
    'tamanho sem nome' => [[['name' => '', 'max_flavors' => 1]], [['name' => 'A', 'prices' => ['10']]], 'sizes.0.name'],
    'sabores demais' => [[['name' => 'G', 'max_flavors' => 9]], [['name' => 'A', 'prices' => ['10']]], 'sizes.0.max_flavors'],
    'preço inválido' => [[['name' => 'G', 'max_flavors' => 1]], [['name' => 'A', 'prices' => ['abc']]], 'flavors.0.prices.0'],
    'sabor sem nome' => [[['name' => 'G', 'max_flavors' => 1]], [['name' => '', 'prices' => ['10']]], 'flavors.0.name'],
]);

it('keeps the ids of sizes and flavors on edit and deletes the ones left out with their prices', function () {
    app(SavePizza::class)->handle($this->dish, $this->sizes, $this->flavors);
    $dish = $this->dish->fresh();
    $grande = $dish->pizzaSizes[2];
    $calabresa = $dish->pizzaFlavors[0];
    $portuguesaId = $dish->pizzaFlavors[1]->id;

    app(SavePizza::class)->handle($dish, [
        ['id' => $grande->id, 'name' => 'Gigante', 'slices' => 10, 'max_flavors' => 4],
    ], [
        ['id' => $calabresa->id, 'name' => 'Calabresa', 'prices' => ['70,00']],
    ]);

    expect($grande->fresh())->name->toBe('Gigante')->max_flavors->toBe(4)
        ->and($dish->pizzaSizes()->count())->toBe(1)
        ->and(PizzaFlavor::query()->whereKey($portuguesaId)->exists())->toBeFalse()
        ->and($calabresa->prices()->sole()->price)->toBe('70.00')
        ->and(PizzaFlavorPrice::count())->toBe(1)
        ->and($dish->fresh()->price)->toBe('70.00');
});

it('clears the pizza data when the product stops being a pizza', function () {
    app(SavePizza::class)->handle($this->dish, $this->sizes, $this->flavors);

    app(SavePizza::class)->clear($this->dish->fresh());

    expect($this->dish->fresh()->kind)->toBe('common')->and(PizzaSize::count())->toBe(0)->and(PizzaFlavor::count())->toBe(0)->and(PizzaFlavorPrice::count())->toBe(0);
});

it('rolls everything back when a late price is invalid', function () {
    $flavors = [
        ['name' => 'Calabresa', 'prices' => ['30,00', '45,00', '55,00']],
        ['name' => 'Portuguesa', 'prices' => ['34,00', 'xx', '62,00']],
    ];

    expect(fn () => app(SavePizza::class)->handle($this->dish, $this->sizes, $flavors))->toThrow(ValidationException::class);

    expect(PizzaSize::count())->toBe(0)->and(PizzaFlavorPrice::count())->toBe(0);
});

describe('product form', function () {
    beforeEach(function () {
        $this->dono = User::factory()->create();
        $this->category = Category::factory()->create(['restaurant_id' => $this->dono->restaurant_id]);
        $this->form = fn (?Dish $dish = null) => Livewire::actingAs($this->dono)->test('pages::panel.dish-form', ['dish' => $dish]);
    });

    it('creates a pizza with sizes, flavors and prices in one save and reopens with them', function () {
        $page = ($this->form)()->set('name', 'Pizza')->set('category_id', $this->category->id)->set('kind', 'pizza');

        expect($page->get('pizzaSizes'))->toHaveCount(4)->and($page->get('pizzaFlavors'))->toHaveCount(1);

        $page->set('pizzaFlavors.0.name', 'Calabresa')->set('pizzaFlavors.0.prices.0', '30,00')->set('pizzaFlavors.0.prices.2', '55,00')->call('save')->assertHasNoErrors();

        $dish = Dish::query()->where('name', 'Pizza')->firstOrFail();

        expect($dish->kind)->toBe('pizza')->and($dish->pizzaSizes)->toHaveCount(4)->and($dish->pizzaFlavors->sole()->prices)->toHaveCount(2)->and($dish->price)->toBe('30.00');

        ($this->form)($dish)->assertSet('kind', 'pizza')->assertSet('pizzaFlavors.0.name', 'Calabresa')->assertSet('pizzaFlavors.0.prices.0', '30,00')->assertSet('pizzaFlavors.0.prices.1', '')->assertSet('pizzaFlavors.0.prices.2', '55,00');
    });

    it('does not save a pizza without a flavor with a price and keeps the product out of the database', function () {
        ($this->form)()->set('name', 'Pizza')->set('category_id', $this->category->id)->set('kind', 'pizza')->set('pizzaFlavors.0.name', 'Calabresa')->call('save')->assertHasErrors('pizzaFlavors.0.prices');

        expect(Dish::query()->where('name', 'Pizza')->exists())->toBeFalse();
    });

    it('does not ask for a product price when it is a pizza', function () {
        ($this->form)()->set('name', 'Pizza')->set('category_id', $this->category->id)->set('kind', 'pizza')->set('pizzaFlavors.0.name', 'Calabresa')->set('pizzaFlavors.0.prices.0', '30,00')->set('price', '')->call('save')->assertHasNoErrors('price');
    });

    it('adds and removes sizes keeping the flavor prices aligned', function () {
        $page = ($this->form)()->set('kind', 'pizza')->set('pizzaFlavors.0.prices.1', '45,00')->set('pizzaFlavors.0.prices.2', '55,00');

        $page->call('removePizzaSize', 0);

        expect($page->get('pizzaSizes'))->toHaveCount(3)->and($page->get('pizzaFlavors.0.prices'))->toBe(['45,00', '55,00', '']);

        $page->call('addPizzaSize');

        expect($page->get('pizzaSizes'))->toHaveCount(4)->and($page->get('pizzaFlavors.0.prices'))->toHaveCount(4);
    });

    it('turns a pizza back into a common product and drops its flavors', function () {
        $page = ($this->form)()->set('name', 'Pizza')->set('category_id', $this->category->id)->set('kind', 'pizza')->set('pizzaFlavors.0.name', 'Calabresa')->set('pizzaFlavors.0.prices.0', '30,00')->call('save');
        $dish = Dish::query()->where('name', 'Pizza')->firstOrFail();

        ($this->form)($dish)->set('kind', 'common')->set('price', '25,00')->call('save')->assertHasNoErrors();

        expect($dish->fresh())->kind->toBe('common')->price->toBe('25.00')->and(PizzaFlavor::count())->toBe(0);
    });

    it('saves a product sold by kg with a promotion and the featured flag', function () {
        ($this->form)()
            ->set('name', 'Torta por kg')->set('category_id', $this->category->id)->set('price', '59,90')
            ->set('sale_type', 'kg')->set('promo_price', '49,90')->set('promo_ends_on', '2026-12-31')->set('is_featured', true)
            ->call('save')->assertHasNoErrors();

        $dish = Dish::query()->where('name', 'Torta por kg')->firstOrFail();

        expect($dish)->sale_type->toBe('kg')->promo_price->toBe('49.90')->is_featured->toBeTrue()->and($dish->promo_ends_at->toDateString())->toBe('2027-01-01');

        ($this->form)($dish)->assertSet('sale_type', 'kg')->assertSet('promo_price', '49,90')->assertSet('promo_ends_on', '2026-12-31')->assertSet('is_featured', true);
    });

    it('rejects a promotional price that is not lower than the price', function () {
        ($this->form)()->set('name', 'X')->set('category_id', $this->category->id)->set('price', '20,00')->set('promo_price', '25,00')->call('save')->assertHasErrors('promo_price');

        expect(Dish::query()->where('name', 'X')->exists())->toBeFalse();
    });
});
