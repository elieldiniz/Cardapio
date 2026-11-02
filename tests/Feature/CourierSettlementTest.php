<?php

use App\Actions\Cash\OpenCashSession;
use App\Actions\Couriers\AssignCourier;
use App\Actions\Couriers\SettleCourierRun;
use App\Models\CashMovement;
use App\Models\Category;
use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\RestaurantOrderSetting;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * RF-22, RNF-04 — assigning a courier and settling deliveries once.
 */
beforeEach(function () {
    seedReferenceData();
    $this->dono = User::factory()->create();
    $this->store = $this->dono->restaurant;
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $this->store->id, 'whatsapp_number' => '5569996041447']);
    $this->dish = feedDish($this->store, ['name' => 'X-Bacon', 'price' => '50.00'], Category::factory()->create(['restaurant_id' => $this->store->id]));
    $this->courier = Courier::create(['restaurant_id' => $this->store->id, 'name' => 'João Silva', 'fee_per_delivery' => '6.00']);
    $this->delivery = function (string $status = 'em_rota') {
        $this->postJson(route('feed.orders.store', $this->store), [
            'items' => [['dish_id' => $this->dish->id, 'quantity' => 1]], 'fulfillment' => 'entrega',
            'address' => ['street' => 'Rua A', 'number' => '1', 'neighborhood' => 'Centro'],
            'customer' => ['name' => 'Ana Souza', 'phone' => '(69) 99999-0000'], 'payment_method' => 'pix',
        ])->assertCreated();
        $order = Order::latest('id')->first();
        $order->update(['status_id' => OrderStatus::idFor($status)]);

        return $order;
    };
});

it('assigns a courier to an open delivery and takes it back', function () {
    $order = ($this->delivery)();

    app(AssignCourier::class)->handle($order, $this->courier);
    expect($order->fresh()->courier_id)->toBe($this->courier->id);

    app(AssignCourier::class)->handle($order->fresh(), null);
    expect($order->fresh()->courier_id)->toBeNull();
});

it('refuses a courier of another store or a finalized order', function () {
    $order = ($this->delivery)();
    $other = Courier::create(['restaurant_id' => User::factory()->create()->restaurant_id, 'name' => 'Alheio']);

    expect(fn () => app(AssignCourier::class)->handle($order, $other))->toThrow(ValidationException::class);

    $order->update(['status_id' => OrderStatus::idFor('finalizado')]);
    expect(fn () => app(AssignCourier::class)->handle($order->fresh(), $this->courier))->toThrow(ValidationException::class);
});

it('writes one expense for the finalized deliveries even when settling twice', function () {
    app(OpenCashSession::class)->handle($this->store, '0');
    foreach (range(1, 3) as $_) {
        $order = ($this->delivery)();
        app(AssignCourier::class)->handle($order, $this->courier);
        $order->update(['status_id' => OrderStatus::idFor('finalizado')]);
    }
    $open = ($this->delivery)();
    app(AssignCourier::class)->handle($open, $this->courier);

    expect(app(SettleCourierRun::class)->pending($this->courier))->toBe(['count' => 3, 'amount' => '18.00']);

    $first = app(SettleCourierRun::class)->handle($this->courier, $this->dono);
    $second = app(SettleCourierRun::class)->handle($this->courier, $this->dono);

    expect($first)->amount->toBe('18.00')->category->toBe('Motoboys')->and($second)->toBeNull()
        ->and(CashMovement::where('category', 'Motoboys')->count())->toBe(1)
        ->and($open->fresh()->courier_settled_at)->toBeNull();
});

it('does not claim the deliveries when the caixa is closed', function () {
    $order = ($this->delivery)();
    app(AssignCourier::class)->handle($order, $this->courier);
    $order->update(['status_id' => OrderStatus::idFor('finalizado')]);

    expect(fn () => app(SettleCourierRun::class)->handle($this->courier, $this->dono))->toThrow(ValidationException::class);
    expect($order->fresh()->courier_settled_at)->toBeNull();
});

it('offers a WhatsApp notice to the courier with the order code and the courier link', function () {
    $this->courier->update(['phone' => '5569999991111']);
    $order = ($this->delivery)();
    app(AssignCourier::class)->handle($order, $this->courier);

    $html = Livewire::actingAs($this->dono)->test('pages::panel.orders')->html();

    expect($html)->toContain('data-notify-courier')
        ->toContain('phone=5569999991111')
        ->toContain(rawurlencode($order->code))
        ->toContain(rawurlencode(route('courier.show', $this->courier->token)));
});

it('does not offer the notice when the courier has no phone or the link was revoked', function () {
    $order = ($this->delivery)();
    app(AssignCourier::class)->handle($order, $this->courier);

    expect(Livewire::actingAs($this->dono)->test('pages::panel.orders')->html())->not->toContain('data-notify-courier');

    $this->courier->update(['phone' => '5569999991111', 'revoked_at' => now()]);

    expect(Livewire::actingAs(User::find($this->dono->id))->test('pages::panel.orders')->html())->not->toContain('data-notify-courier');
});
