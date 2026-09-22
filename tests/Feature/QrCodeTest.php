<?php

use App\Models\Category;
use App\Models\User;
use App\Support\QrCode;
use Livewire\Livewire;

test('the generated qr code still resolves to the feed after editing categories dishes and appearance', function () {
    seedReferenceData();
    $dono = User::factory()->create();
    $restaurant = $dono->restaurant;
    $this->actingAs($dono);

    $encoded = $restaurant->feedUrl();
    $svgBefore = QrCode::svg($encoded);

    expect($encoded)->toBe(url('/r/'.$restaurant->slug));

    Livewire::test('pages::panel.qr-code')->assertSeeHtml('data-qr')->assertSee($encoded);

    // Edit categories, dishes and appearance.
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $dish = feedDish($restaurant, ['name' => 'Novo prato'], $category);
    Livewire::test('pages::panel.categories')->set("names.{$category->id}", 'Renomeada')->call('rename', $category->id);
    Livewire::test('pages::panel.dishes')->call('updateStatus', $dish->id, 'esgotado');
    Livewire::test('pages::panel.appearance')->set('accent_color', '#2E9E6B')->set('font', 'Lora')->call('save')->assertHasNoErrors();

    $restaurant->refresh();

    expect($restaurant->feedUrl())->toBe($encoded)
        ->and(QrCode::svg($restaurant->feedUrl()))->toBe($svgBefore);

    $this->get($encoded)->assertOk()->assertSee('Novo prato');
});

it('offers a printable table display with the qr code', function () {
    seedReferenceData();
    $dono = User::factory()->create();

    $this->actingAs($dono)
        ->get(route('panel.qr-code.print', ['mesa' => '12']))
        ->assertOk()
        ->assertSee('Mesa 12')
        ->assertSee('Aponte a câmera')
        ->assertSee('<svg', false);
});
