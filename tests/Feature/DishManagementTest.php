<?php

use App\Models\Badge;
use App\Models\Category;
use App\Models\Dish;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
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

it('saves optional descriptions badges variants and photos', function () {
    Storage::fake('public');
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
        ->set('newPhotos', [fakeImage('frente.png'), fakeImage('lado.png')])
        ->call('save')
        ->assertHasNoErrors();

    $dish = Dish::with(['badges', 'variants', 'photos'])->firstOrFail();

    expect($dish->badges->pluck('slug')->all())->toBe(['novo'])
        ->and($dish->variants->first()->only('name', 'price'))->toBe(['name' => 'G', 'price' => '14.90'])
        ->and($dish->photos)->toHaveCount(2)
        ->and($dish->photos->sortBy('display_order')->pluck('display_order')->all())->toBe([1, 2]);

    Storage::disk('public')->assertExists($dish->photos->first()->file_path);
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
