<?php

use App\Models\Plan;
use App\Models\User;

beforeEach(fn () => seedReferenceData());

it('serves the landing page at the root with a signup call to action', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Seu cardápio em vídeo, direto do QR Code da mesa.')
        ->assertSee(route('register'), false)
        ->assertSee('<meta property="og:title"', false)
        ->assertSee('Perguntas frequentes');
});

it('shows the plans from the database so admin edits appear on the landing', function () {
    Plan::where('name', 'Básico')->update(['price_cents' => 4500, 'dish_limit' => 40]);

    $this->get('/')
        ->assertSeeInOrder(['Grátis', 'Básico', 'R$ 45,00', 'Até 40 pratos', 'Pro'])
        ->assertSee('Pacotes avulsos a partir de');

    config()->set('landing.show_prices', false);

    $this->get('/')->assertDontSee('data-plans', false)->assertDontSee('R$ 45,00');
});

it('links every institutional page in the footer and all of them render', function () {
    $response = $this->get('/');

    foreach (['marketing.about', 'legal.terms', 'legal.privacy', 'legal.refund', 'marketing.contact', 'legal.cookies'] as $route) {
        $response->assertSee('href="'.route($route).'"', false);
        $this->get(route($route))->assertOk()->assertSee('data-footer', false);
    }

    $response->assertSee('Copyright © '.now()->year);
});

it('shows contact channels only when configured', function () {
    $this->get(route('marketing.contact'))->assertDontSee('data-contact-channels', false);

    config()->set('landing.contact', ['email' => 'oi@degustou.test', 'whatsapp' => '5569999999999', 'instagram' => 'degustou']);

    $this->get(route('marketing.contact'))
        ->assertSee('oi@degustou.test')
        ->assertSee('https://wa.me/5569999999999', false)
        ->assertSee('@degustou');
});

it('offers the panel instead of login to a signed in user', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertSee('Ir para o painel')
        ->assertSee(route('panel.home'), false);
});
