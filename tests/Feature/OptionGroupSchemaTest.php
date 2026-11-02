<?php

use App\Models\Dish;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Restaurant;
use Illuminate\Database\QueryException;

/**
 * RF-03, RNF-01, RNF-02 — complement groups: templates and relations.
 */
it('ships the nine ready-made templates', function () {
    $templates = config('option_group_templates');

    expect($templates)->toHaveCount(9)
        ->and(array_keys($templates))->toBe(['tamanhos', 'adicionais', 'bordas', 'bebida-combo', 'molhos', 'ponto-carne', 'tipo-corte', 'acai', 'zero']);

    foreach ($templates as $template) {
        expect($template)->toHaveKeys(['label', 'description', 'mode', 'max_choices', 'is_required', 'allow_repeat', 'options']);
    }
});

it('links groups to a dish in the dono order and lists their options', function () {
    $restaurant = Restaurant::factory()->create();
    $dish = Dish::factory()->create(['restaurant_id' => $restaurant->id]);
    $sizes = OptionGroup::factory()->required()->create(['restaurant_id' => $restaurant->id, 'name' => 'Tamanho']);
    $extras = OptionGroup::factory()->create(['restaurant_id' => $restaurant->id, 'name' => 'Adicionais', 'allow_repeat' => true]);
    Option::factory()->create(['option_group_id' => $extras->id, 'name' => 'Bacon', 'price' => '4.00']);

    $dish->optionGroups()->attach([$extras->id => ['display_order' => 2], $sizes->id => ['display_order' => 1]]);

    expect($dish->fresh()->optionGroups->pluck('name')->all())->toBe(['Tamanho', 'Adicionais'])
        ->and($extras->options()->first()->price)->toBe('4.00')
        ->and($sizes->isSingleChoice())->toBeTrue()
        ->and($extras->dishes()->count())->toBe(1);
});

it('does not link the same group twice to a dish', function () {
    $dish = Dish::factory()->create();
    $group = OptionGroup::factory()->create(['restaurant_id' => $dish->restaurant_id]);

    $dish->optionGroups()->attach($group->id);

    expect(fn () => $dish->optionGroups()->attach($group->id))->toThrow(QueryException::class);
});

it('keeps dish variants working next to complements', function () {
    $dish = Dish::factory()->create();
    $dish->variants()->create(['name' => 'Grande', 'price' => '55.00']);

    expect($dish->variants()->count())->toBe(1);
});

it('deleting a group removes its options and the dish link', function () {
    $dish = Dish::factory()->create();
    $group = OptionGroup::factory()->create(['restaurant_id' => $dish->restaurant_id]);
    Option::factory()->count(2)->create(['option_group_id' => $group->id]);
    $dish->optionGroups()->attach($group->id);

    $group->delete();

    expect(Option::count())->toBe(0)->and($dish->optionGroups()->count())->toBe(0);
});
