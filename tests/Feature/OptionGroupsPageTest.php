<?php

use App\Models\Dish;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\User;
use Livewire\Livewire;

/**
 * RF-03, UI-01 — the Complementos screen.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->restaurant = $this->dono->restaurant;
});

it('requires a logged in owner and renders for the owner', function () {
    $this->get(route('panel.option-groups'))->assertRedirect(route('login'));

    $this->actingAs($this->dono)->get(route('panel.option-groups'))->assertOk()->assertSee('Complementos');
});

it('creates a group from the Adicionais template and reopens it', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.option-groups')
        ->call('newGroup')
        ->assertSet('screen', 'templates')
        ->call('chooseTemplate', 'adicionais')
        ->assertSet('screen', 'form')
        ->assertSet('name', 'Adicionais')
        ->assertSet('mode', 'many')
        ->assertSet('allow_repeat', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('screen', 'list');

    $group = $this->restaurant->optionGroups()->firstOrFail();

    expect($group->options->pluck('name')->all())->toBe(['Bacon', 'Cheddar', 'Ovo', 'Hambúrguer extra'])
        ->and($group->options->first()->price)->toBe('4.00')
        ->and($group->max_choices)->toBe(5);

    Livewire::actingAs($this->dono)->test('pages::panel.option-groups')
        ->call('edit', $group->id)
        ->assertSet('name', 'Adicionais')
        ->assertSet('options.0.price', '4,00');
});

it('edits a group keeping the option ids and removing the dropped ones', function () {
    $group = OptionGroup::factory()->create(['restaurant_id' => $this->restaurant->id, 'name' => 'Molhos']);
    $keep = Option::factory()->create(['option_group_id' => $group->id, 'name' => 'Barbecue', 'display_order' => 1]);
    $drop = Option::factory()->create(['option_group_id' => $group->id, 'name' => 'Ketchup', 'display_order' => 2]);

    Livewire::actingAs($this->dono)->test('pages::panel.option-groups')
        ->call('edit', $group->id)
        ->set('options.0.name', 'Barbecue defumado')
        ->call('removeOption', 1)
        ->call('addOption')
        ->set('options.1.name', 'Alho')
        ->set('options.1.price', '2,50')
        ->call('save')
        ->assertHasNoErrors();

    expect($keep->fresh()->name)->toBe('Barbecue defumado')
        ->and(Option::query()->whereKey($drop->id)->exists())->toBeFalse()
        ->and($group->fresh()->options()->count())->toBe(2)
        ->and($group->options()->where('name', 'Alho')->value('price'))->toBe('2.50');
});

it('forces a single choice group to one choice and no repeat', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.option-groups')
        ->call('chooseTemplate', 'adicionais')
        ->set('mode', 'one')
        ->assertSet('max_choices', 1)
        ->assertSet('allow_repeat', false);
});

it('validates the name, the options and the prices', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.option-groups')
        ->call('chooseTemplate', 'zero')
        ->call('save')
        ->assertHasErrors(['name', 'options.0.name']);

    Livewire::actingAs($this->dono)->test('pages::panel.option-groups')
        ->call('chooseTemplate', 'zero')
        ->set('name', 'Teste')
        ->set('options.0.name', 'Opção')
        ->set('options.0.price', 'abc')
        ->call('save')
        ->assertHasErrors('options.0.price');

    expect($this->restaurant->optionGroups()->count())->toBe(0);
});

it('links the group to products of the store while saving', function () {
    $dish = Dish::factory()->create(['restaurant_id' => $this->restaurant->id]);

    Livewire::actingAs($this->dono)->test('pages::panel.option-groups')
        ->call('chooseTemplate', 'molhos')
        ->set('dish_ids', [$dish->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($dish->fresh()->optionGroups()->count())->toBe(1);
});

it('rejects a product of another store', function () {
    $foreign = Dish::factory()->create();

    Livewire::actingAs($this->dono)->test('pages::panel.option-groups')
        ->call('chooseTemplate', 'molhos')
        ->set('dish_ids', [$foreign->id])
        ->call('save')
        ->assertHasErrors('dish_ids');
});

it('deletes a group', function () {
    $group = OptionGroup::factory()->create(['restaurant_id' => $this->restaurant->id]);

    Livewire::actingAs($this->dono)->test('pages::panel.option-groups')->call('remove', $group->id);

    expect(OptionGroup::query()->whereKey($group->id)->exists())->toBeFalse();
});

it('does not let a dono open or delete another store group', function () {
    $other = OptionGroup::factory()->create();

    Livewire::actingAs($this->dono)->test('pages::panel.option-groups')->call('edit', $other->id)->assertNotFound();
    Livewire::actingAs($this->dono)->test('pages::panel.option-groups')->call('remove', $other->id)->assertNotFound();

    expect(OptionGroup::query()->whereKey($other->id)->exists())->toBeTrue();
});

it('shows the group in the navigation menu', function () {
    $this->actingAs($this->dono)->get(route('panel.home'))->assertSee('Complementos');
});
