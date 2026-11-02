<?php

use App\Models\User;
use Livewire\Livewire;

/**
 * RF-01, RF-02, RF-02e, UI-01 — the Configurações screen.
 */
beforeEach(function () {
    seedReferenceData();
});

it('requires a logged in owner', function () {
    $this->get(route('panel.settings'))->assertRedirect(route('login'));
});

it('renders the settings screen for the owner', function () {
    $dono = User::factory()->create();

    $this->actingAs($dono)->get(route('panel.settings'))
        ->assertOk()
        ->assertSee('Configurações')
        ->assertSee('Contato e Pix');
});

it('saves the contact and reopens with the same values', function () {
    $dono = User::factory()->create();

    Livewire::actingAs($dono)->test('pages::panel.settings')
        ->set('whatsapp_number', '(69) 99604-1447')
        ->set('address', 'Rua das Flores, 120')
        ->set('pix_key', 'teste@docin.com.br')
        ->set('pix_holder', 'Docin Teste')
        ->set('pix_city', 'Porto Velho')
        ->call('saveContact')
        ->assertHasNoErrors();

    Livewire::actingAs($dono->fresh())->test('pages::panel.settings')
        ->assertSet('whatsapp_number', '5569996041447')
        ->assertSet('address', 'Rua das Flores, 120')
        ->assertSet('pix_key', 'teste@docin.com.br')
        ->assertSet('pix_holder', 'Docin Teste')
        ->assertSet('pix_city', 'Porto Velho');
});

it('rejects an invalid whatsapp number', function () {
    $dono = User::factory()->create();

    Livewire::actingAs($dono)->test('pages::panel.settings')
        ->set('whatsapp_number', '123')
        ->call('saveContact')
        ->assertHasErrors('whatsapp_number');
});

it('saves the operation, hours and timezone', function () {
    $dono = User::factory()->create();

    Livewire::actingAs($dono)->test('pages::panel.settings')
        ->set('hours_enabled', true)
        ->set('timezone', 'America/Manaus')
        ->call('addShift', 1)
        ->set('hours.1.0.opens', '09:00')
        ->set('hours.1.0.closes', '17:00')
        ->call('repeatMondayToFriday')
        ->call('saveOperation')
        ->assertHasNoErrors();

    $restaurant = $dono->restaurant->fresh();

    expect($restaurant->orderSettings())->hours_enabled->toBeTrue()->timezone->toBe('America/Manaus')
        ->and($restaurant->businessHours()->count())->toBe(5);

    Livewire::actingAs($dono->fresh())->test('pages::panel.settings')
        ->assertSet('hours.3.0.opens', '09:00')
        ->assertSet('hours.0', []);
});

it('shows an error for an invalid time and keeps the stored hours', function () {
    $dono = User::factory()->create();

    Livewire::actingAs($dono)->test('pages::panel.settings')
        ->set('hours_enabled', true)
        ->call('addShift', 2)
        ->set('hours.2.0.opens', '18:00')
        ->set('hours.2.0.closes', '18:00')
        ->call('saveOperation')
        ->assertHasErrors('hours');

    expect($dono->restaurant->businessHours()->count())->toBe(0);
});

it('offers the number test link without creating anything', function () {
    $dono = User::factory()->create();

    Livewire::actingAs($dono)->test('pages::panel.settings')
        ->set('whatsapp_number', '69996041447')
        ->assertSeeHtml('data-test-number')
        ->assertSeeHtml('https://wa.me/5569996041447');
});

it('shows how customers see the store right now', function () {
    $dono = User::factory()->create();

    Livewire::actingAs($dono)->test('pages::panel.settings')->assertSee('Aberto agora');
});
