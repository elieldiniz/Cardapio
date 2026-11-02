<?php

use App\Services\Orders\OrderPricer;

/**
 * RF-04, RNF-06, RNF-07 — the cart assets: lazy, light, and in step with the server rules.
 */
function jsSource(string $name): string
{
    return file_get_contents(resource_path("js/{$name}.js"));
}

it('keeps the client limits equal to the server limits', function () {
    $cart = jsSource('cart');

    expect($cart)->toContain('NOTES_LIMIT = '.OrderPricer::NOTES_LIMIT)
        ->and($cart)->toContain('MAX_UNITS = '.OrderPricer::MAX_UNITS)
        ->and($cart)->toContain('MAX_KG = '.(int) OrderPricer::MAX_KG);
});

it('loads the ordering chunks on demand so the feed does not wait for them', function () {
    $feed = jsSource('feed');

    expect($feed)->toContain("import('./cart.js')")
        ->and($feed)->not->toMatch('/^import .*cart/m')
        ->and($feed)->toContain('requestIdleCallback')
        ->and(jsSource('cart'))->toContain("import('./checkout.js')");
});

it('never loads the cart in the appearance preview or on stores that take no orders', function () {
    $feed = jsSource('feed');

    expect($feed)->toContain("root.hasAttribute('data-preview') || !document.querySelector('[data-order-config]')");
});

it('escapes every server text it writes into the page', function () {
    $cart = jsSource('cart');

    expect($cart)->toContain('esc(dish.name)')->and($cart)->toContain('esc(group.name)')->and($cart)->toContain('esc(option.name)');
    // No raw insertion of the dish name into HTML.
    expect($cart)->not->toContain('${dish.name}');
});

it('keeps the new JavaScript within the 30 KB gzip budget', function () {
    $bytes = collect(['cart', 'checkout', 'track'])->sum(fn ($name) => strlen(gzencode(jsSource($name), 9)));

    expect($bytes)->toBeLessThan(30 * 1024);
});

it('posts the checkout to the server route and never sends prices', function () {
    $checkout = jsSource('checkout');

    preg_match('/const payload = \{(.*?)\n        \};/s', $checkout, $matches);

    expect($checkout)->toContain('config.orders_url')
        ->and($matches[1] ?? '')->toContain('dish_id: Number(item.dishId)')
        ->and($matches[1] ?? '')->not->toContain('price')
        ->and($matches[1] ?? '')->not->toContain('total');
});

it('registers the tracking script as a Vite entry', function () {
    expect(file_get_contents(base_path('vite.config.js')))->toContain('resources/js/track.js');
});
