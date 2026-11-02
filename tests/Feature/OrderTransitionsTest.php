<?php

use App\Actions\Orders\AcceptOrder;
use App\Actions\Orders\AdvanceOrder;
use App\Actions\Orders\RefuseOrder;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderTransitions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * RF-08, RNF-01, RNF-04 — order status transitions.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->order = Order::factory()->delivery()->create(['restaurant_id' => $this->dono->restaurant_id]);
});

it('walks the normal flow and writes one history row per move', function () {
    $advance = app(AdvanceOrder::class);

    $advance->handle($this->order, 'em_preparo', $this->dono);
    $advance->handle($this->order, 'em_rota', $this->dono);
    $order = $advance->handle($this->order, 'finalizado', $this->dono);

    expect($order->status->slug)->toBe('finalizado')
        ->and($order->accepted_at)->not->toBeNull()
        ->and($order->dispatched_at)->not->toBeNull()
        ->and($order->finished_at)->not->toBeNull()
        ->and($order->history()->count())->toBe(3)
        ->and($order->history()->with('status')->get()->pluck('status.slug')->all())->toBe(['em_preparo', 'em_rota', 'finalizado'])
        ->and($order->history()->first()->user_id)->toBe($this->dono->id);
});

it('rejects an invalid move and leaves the order untouched', function () {
    expect(fn () => app(AdvanceOrder::class)->handle($this->order, 'finalizado'))->toThrow(ValidationException::class);

    expect($this->order->fresh()->status->slug)->toBe('novo')->and($this->order->history()->count())->toBe(0);
});

it('never moves a finished, refused or cancelled order again', function (string $ended) {
    $order = Order::factory()->status($ended)->create(['restaurant_id' => $this->dono->restaurant_id]);

    foreach (['novo', 'em_preparo', 'em_rota'] as $to) {
        expect(fn () => app(AdvanceOrder::class)->handle($order, $to))->toThrow(ValidationException::class);
    }
})->with(['finalizado', 'recusado', 'cancelado']);

it('is a no-op when the order is already in the asked status', function () {
    $advance = app(AdvanceOrder::class);

    $advance->handle($this->order, 'em_preparo');
    $advance->handle($this->order, 'em_preparo');

    expect($this->order->history()->count())->toBe(1);
});

it('allows one step back before the order ends', function () {
    $advance = app(AdvanceOrder::class);

    $advance->handle($this->order, 'em_preparo');
    $advance->handle($this->order, 'em_rota');
    $advance->handle($this->order, 'em_preparo');

    expect($this->order->fresh()->status->slug)->toBe('em_preparo')
        ->and($this->order->fresh()->dispatched_at)->not->toBeNull()
        ->and($this->order->history()->count())->toBe(3);
});

it('keeps the first accepted_at when an order goes back and forth', function () {
    $advance = app(AdvanceOrder::class);

    $advance->handle($this->order, 'em_preparo');
    $first = $this->order->fresh()->accepted_at;

    $this->travel(5)->minutes();
    $advance->handle($this->order, 'novo');
    $advance->handle($this->order, 'em_preparo');

    expect($this->order->fresh()->accepted_at->equalTo($first))->toBeTrue();
});

it('accepts an order whose delivery fee is "a combinar" only with a fee, and updates the total', function () {
    $order = Order::factory()->delivery()->create([
        'restaurant_id' => $this->dono->restaurant_id, 'delivery_fee' => null, 'delivery_fee_pending' => true,
        'subtotal' => '40.00', 'total' => '40.00',
    ]);

    expect(fn () => app(AcceptOrder::class)->handle($order))->toThrow(ValidationException::class);
    expect($order->fresh()->status->slug)->toBe('novo');

    app(AcceptOrder::class)->handle($order, 'R$ 7,50', $this->dono);

    expect($order->fresh())->delivery_fee->toBe('7.50')->total->toBe('47.50')->delivery_fee_pending->toBeFalse()
        ->and($order->fresh()->status->slug)->toBe('em_preparo');
});

it('accepts a normal order without asking for a fee', function () {
    app(AcceptOrder::class)->handle($this->order);

    expect($this->order->fresh()->status->slug)->toBe('em_preparo')->and($this->order->fresh()->delivery_fee)->toBe('5.00');
});

it('refuses a new order with an optional reason', function () {
    app(RefuseOrder::class)->handle($this->order, 'Sem ingredientes', $this->dono);

    expect($this->order->fresh())->cancel_reason->toBe('Sem ingredientes')->cancelled_at->not->toBeNull()
        ->and($this->order->fresh()->status->slug)->toBe('recusado');

    expect(fn () => app(AdvanceOrder::class)->handle($this->order, 'em_preparo'))->toThrow(ValidationException::class);
});

it('exposes the transition map', function () {
    expect(OrderTransitions::can('novo', 'em_preparo'))->toBeTrue()
        ->and(OrderTransitions::can('novo', 'finalizado'))->toBeFalse()
        ->and(OrderTransitions::allowed('finalizado'))->toBe([])
        ->and(OrderTransitions::allowed('nope'))->toBe([]);
});

it('lets only the dono of the store handle the order', function () {
    $other = User::factory()->create();

    expect(Gate::forUser($this->dono)->allows('update', $this->order))->toBeTrue()
        ->and(Gate::forUser($other)->allows('update', $this->order))->toBeFalse()
        ->and(Gate::forUser($other)->allows('view', $this->order))->toBeFalse();
});
