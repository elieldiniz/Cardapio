<?php

use Illuminate\Support\Facades\Route;

/**
 * RF-25, CT-01, CT-06 — new routes never collide with each other or with the existing ones.
 * Re-run at the close of every phase.
 */
it('has no two routes with the same name', function () {
    $names = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter()
        ->values();

    $duplicates = $names->duplicates()->unique()->values()->all();

    expect($duplicates)->toBe([]);
});

it('has no two routes with the same method and uri', function () {
    $signatures = collect(Route::getRoutes()->getRoutes())
        ->flatMap(fn ($route) => collect($route->methods())
            ->reject(fn (string $method) => $method === 'HEAD')
            ->map(fn (string $method) => $method.' '.$route->uri()))
        ->values();

    expect($signatures->duplicates()->unique()->values()->all())->toBe([]);
});

it('keeps every route that existed before the ordering features', function () {
    $existing = [
        'home', 'marketing.about', 'marketing.contact', 'legal.terms', 'legal.privacy', 'legal.refund', 'legal.cookies',
        'register', 'login', 'password.request', 'password.reset', 'logout', 'verification.notice', 'verification.verify',
        'impersonation.stop', 'feed.show', 'feed.category', 'feed.views', 'webhooks.mux',
        'panel.home', 'panel.categories', 'panel.dishes', 'panel.dishes.create', 'panel.dishes.edit', 'panel.dishes.video',
        'panel.subscription', 'panel.appearance', 'panel.appearance.preview', 'panel.qr-code', 'panel.qr-code.print',
        'panel.qr-code.svg', 'panel.views', 'panel.support', 'panel.support.show', 'panel.account',
    ];

    foreach ($existing as $name) {
        expect(Route::has($name))->toBeTrue("Route [{$name}] disappeared.");
    }
});

it('keeps the dish URLs of the panel unchanged', function () {
    expect(route('panel.dishes', absolute: false))->toBe('/painel/pratos')
        ->and(route('panel.dishes.create', absolute: false))->toBe('/painel/pratos/novo')
        ->and(route('feed.show', 'fumaca', false))->toBe('/r/fumaca');
});

it('registers the new panel screens under the existing prefix and name space', function () {
    expect(route('panel.settings', absolute: false))->toBe('/painel/configuracoes');
});

it('registers every new screen and public endpoint at the documented path (CT-01 to CT-06)', function () {
    $paths = [
        'panel.option-groups' => '/painel/complementos', 'panel.orders' => '/painel/pedidos', 'panel.pdv' => '/painel/pdv',
        'panel.cash' => '/painel/caixa', 'panel.receivables' => '/painel/fiado', 'panel.report' => '/painel/relatorio',
        'panel.customers' => '/painel/clientes', 'panel.promo-codes' => '/painel/cupons', 'panel.loyalty' => '/painel/fidelidade',
        'panel.couriers' => '/painel/motoboys', 'panel.help' => '/painel/ajuda', 'panel.ticket.sample' => '/painel/comanda-teste',
    ];

    foreach ($paths as $name => $path) {
        expect(route($name, absolute: false))->toBe($path);
    }

    expect(route('feed.orders.store', 'loja', false))->toBe('/r/loja/pedidos')
        ->and(route('feed.events', 'loja', false))->toBe('/r/loja/eventos')
        ->and(route('feed.orders.coupon', 'loja', false))->toBe('/r/loja/cupom')
        ->and(route('order.track', 'abc', false))->toBe('/p/abc')
        ->and(route('courier.show', 'abc', false))->toBe('/entregador/abc');
});
