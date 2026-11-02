<?php

use App\Models\User;
use Livewire\Livewire;

/**
 * RF-24 — the help center search.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
});

it('lists all the videos without a search', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.help')->assertSee('Abrir e fechar o caixa')->assertSee('Cupons e cartão fidelidade');
});

it('finds videos by a term in the title, description or tags, ignoring case and accents', function (string $term, string $expected) {
    Livewire::actingAs($this->dono)->test('pages::panel.help')->set('search', $term)->assertSee($expected);
})->with([
    'tag' => ['SANGRIA', 'Abrir e fechar o caixa'],
    'sem acento' => ['promocao', 'Cadastrar produtos, complementos e pizzas'],
    'descrição' => ['selos', 'Cupons e cartão fidelidade'],
]);

it('shows an empty state when nothing matches', function () {
    Livewire::actingAs($this->dono)->test('pages::panel.help')->set('search', 'xyzabc')->assertSee('Nada encontrado')->assertDontSee('Abrir e fechar o caixa');
});

it('shows the support WhatsApp only when it is configured', function () {
    config(['help_videos.support_whatsapp' => '(69) 99604-1447']);
    Livewire::actingAs($this->dono)->test('pages::panel.help')->assertSeeHtml('data-support-whatsapp')->assertSeeHtml('phone=69996041447');

    config(['help_videos.support_whatsapp' => null]);
    Livewire::actingAs($this->dono)->test('pages::panel.help')->assertDontSeeHtml('data-support-whatsapp');
});

it('is reachable from the menu', function () {
    $this->actingAs($this->dono)->get(route('panel.help'))->assertOk()->assertSee('Ajuda');
});
