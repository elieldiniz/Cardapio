<?php

use App\Models\CashMovement;
use App\Models\CashMovementType;
use App\Models\CashSession;
use App\Models\PaymentMethod;
use App\Models\Receivable;
use App\Models\Restaurant;
use Illuminate\Database\QueryException;

/**
 * RF-09, RF-10, RF-11, RNF-02, RNF-03, RNF-04 — cash schema constraints.
 */
beforeEach(function () {
    seedReferenceData();
    $this->store = Restaurant::factory()->create();
});

function openSession(Restaurant $store, array $attributes = []): CashSession
{
    return CashSession::query()->create(array_merge(['restaurant_id' => $store->id, 'opening_float' => '100.00', 'opened_at' => now()], $attributes));
}

it('allows only one open cash session per store', function () {
    openSession($this->store);

    expect(fn () => openSession($this->store))->toThrow(QueryException::class);

    expect($this->store->cashSessions()->count())->toBe(1);
});

it('lets a store open a new session once the previous one closed', function () {
    $first = openSession($this->store);
    $first->update(['closed_at' => now()]);

    expect(openSession($this->store)->exists)->toBeTrue()->and($this->store->openCashSession()->id)->not->toBe($first->id);
});

it('keeps other stores independent', function () {
    openSession($this->store);

    expect(openSession(Restaurant::factory()->create())->exists)->toBeTrue();
});

it('rejects a duplicate movement reference', function () {
    $session = openSession($this->store);
    $row = fn () => CashMovement::query()->create([
        'restaurant_id' => $this->store->id, 'cash_session_id' => $session->id, 'type_id' => CashMovementType::idFor('venda'),
        'payment_method_id' => PaymentMethod::idFor('dinheiro'), 'amount' => '10.00', 'reference' => 'order_sale:1:0', 'occurred_at' => now(),
    ]);

    $row();

    expect($row)->toThrow(QueryException::class);
});

it('stores money with two decimals', function () {
    $session = openSession($this->store, ['opening_float' => '50']);

    expect($session->fresh()->opening_float)->toBe('50.00');
});

it('rejects a duplicate receivable reference and knows when it is open', function () {
    $make = fn () => Receivable::query()->create(['restaurant_id' => $this->store->id, 'customer_name' => 'Ana', 'amount' => '30.00', 'reference' => 'order_fiado:1']);

    $receivable = $make();

    expect($receivable->isOpen())->toBeTrue();
    expect($make)->toThrow(QueryException::class);

    $receivable->update(['paid_at' => now()]);

    expect($receivable->fresh()->isOpen())->toBeFalse();
});

it('has the cash movement types as lookups', function () {
    expect(CashMovementType::idFor('estorno'))->toBeInt()->and(CashMovementType::idFor('reforco'))->toBeInt();
});
