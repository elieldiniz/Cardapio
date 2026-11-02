<?php

use App\Models\Plan;
use App\Models\User;

beforeEach(fn () => seedReferenceData());

it('serves the landing page at the root with a signup call to action', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Faça o cliente')
        ->assertSee('desejar o prato')
        ->assertSee('e pedir na hora.')
        ->assertSee(route('register'), false)
        ->assertSee('<meta property="og:title"', false)
        ->assertSee('id="perguntas"', false)
        ->assertSee('Ficou com alguma');
});

it('shows the plans from the database so admin edits appear on the landing', function () {
    Plan::where('name', 'Básico')->update(['price_cents' => 4500, 'dish_limit' => 40]);

    $this->get('/')
        ->assertSeeInOrder(['Grátis', 'Básico', 'R$ 45', 'Até 40 pratos', 'Pro'])
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

it('compares an order by chat (11 messages) with the single Degusta message', function () {
    $this->get('/')->assertOk()->assertSee('data-comparison', false)->assertSee('11 mensagens')->assertSee('1 mensagem')
        ->assertSee('data-comparison-chat', false)->assertSee('data-comparison-one', false);
});

it('has a commission calculator where commission is sales times the percentage', function () {
    config()->set('landing.commission_default_sales', 10000);

    $response = $this->get('/')->assertOk()->assertSee('data-commission', false)->assertSee('data-commission-sales', false);

    foreach ([12 => 'R$ 1.200,00', 20 => 'R$ 2.000,00', 27 => 'R$ 2.700,00'] as $rate => $monthly) {
        $response->assertSee('data-commission-card="'.$rate.'"', false)->assertSee($monthly);
    }
});

it('announces the 14-day trial and lists what each plan includes for orders', function () {
    $this->get('/')->assertOk()->assertSee('data-trial-offer', false)->assertSee('14 dias de tudo liberado')
        ->assertSee('Receba pedidos pelo cardápio')->assertSee('Cartão fidelidade')->assertSee('Motoboys com link próprio');
});

it('tells the whole story: video, ordering, delivery and cash, with the trial in the hero, a strip and the final call', function () {
    $response = $this->get('/')->assertOk()
        ->assertSee('e pedir na hora.')
        ->assertSee('data-trial-strip', false)
        ->assertSee('Do QR Code ao pedido no seu WhatsApp')
        ->assertSee('id="recursos"', false)
        ->assertSee('Gestor de pedidos')->assertSee('Entrega do seu jeito')->assertSee('Caixa, fiado e relatório')->assertSee('PDV de balcão')
        ->assertSee('Foto vira vídeo');

    expect(substr_count($response->getContent(), 'Testar 14 dias grátis'))->toBeGreaterThanOrEqual(3);
});

it('lists on the landing what each plan includes after the trial, following the plans in the database', function () {
    $response = $this->get('/')->assertOk()->assertSee('data-plan-matrix', false)->assertSee('O que cada plano inclui');

    // Grátis keeps orders only; Pro has everything.
    expect($response->getContent())->toMatch('/Cartão fidelidade.*?Motoboys com link e acerto/s');

    Plan::where('name', 'Grátis')->update(['features' => array_merge(config('plan_features.by_plan.Grátis'), ['cash' => true])]);

    $row = fn () => preg_match('/Caixa, fiado e relatório<\/td>(.*?)<\/tr>/s', $this->get('/')->getContent(), $m) ? substr_count($m[1], 'Incluído') : null;

    expect($row())->toBe(3);
});

it('no longer says that ordering is not possible', function () {
    $this->get('/')->assertOk()->assertDontSee('Não. O cardápio é para ver os pratos em vídeo')->assertSee('Como funciona o pedido?')->assertSee('Vocês cobram comissão por pedido?')->assertSee('Como funcionam os 14 dias grátis?');
});

it('uses a title and description that mention ordering', function () {
    $this->get('/')->assertSee('Cardápio em vídeo com pedidos pelo QR Code', false)->assertSee('Teste 14 dias grátis', false);
});
