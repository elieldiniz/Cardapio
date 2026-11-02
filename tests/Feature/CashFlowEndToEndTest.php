<?php

use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\OpenCashSession;
use App\Models\CashMovement;
use App\Models\Category;
use App\Models\Order;
use App\Models\RestaurantOrderSetting;
use App\Models\User;
use App\Services\Cash\CashSummary;
use App\Services\Cash\ReportBuilder;
use Livewire\Livewire;

/**
 * RF-25, RF-09, RF-10, RF-12 — from the customer's order to the closed caixa and the report.
 */
it('takes an order from the customer to the cash register and the report, and back with a refund', function () {
    seedReferenceData();
    $dono = User::factory()->create();
    $store = $dono->restaurant;
    RestaurantOrderSetting::factory()->create(['restaurant_id' => $store->id, 'whatsapp_number' => '5569996041447']);
    $dish = feedDish($store, ['name' => 'X-Bacon', 'price' => '40.00'], Category::factory()->create(['restaurant_id' => $store->id]));

    // 1. The caixa is opened with R$ 100 of change.
    $session = app(OpenCashSession::class)->handle($store, '100,00', $dono);

    // 2. The customer orders through the public checkout.
    $created = $this->postJson(route('feed.orders.store', $store), [
        'items' => [['dish_id' => $dish->id, 'quantity' => 2]],
        'fulfillment' => 'retirada',
        'customer' => ['name' => 'Ana Souza', 'phone' => '(69) 99999-0000'],
        'payment_method' => 'dinheiro',
        'origin' => 'instagram',
    ])->assertCreated();

    $order = Order::query()->where('code', $created->json('code'))->firstOrFail();

    // 3. The dono accepts, sends and finalizes it paid in cash.
    $page = Livewire::actingAs($dono)->test('pages::panel.orders');
    $page->call('accept', $order->id)->call('advance', $order->id, 'em_rota');
    $page->call('openFinalize', $order->id)->set('payMode', 'dinheiro')->set('received', '100,00')->call('confirmPayment')->assertHasNoErrors();

    expect($order->fresh()->status->slug)->toBe('finalizado');

    // 4. It fell into the caixa on its own.
    $summary = app(CashSummary::class)->for($session);

    expect(CashMovement::count())->toBe(1)
        ->and($summary['sales']['dinheiro'])->toBe('80.00')
        ->and($summary['sales_count'])->toBe(1)
        ->and($summary['expected_cash'])->toBe('180.00');

    // 5. An expense is launched and the report matches the movements.
    Livewire::actingAs($dono)->test('pages::panel.cash')->call('startMovement', 'despesa')->set('movementAmount', '30,00')->set('movementCategory', 'Gás')->call('saveMovement');

    $report = app(ReportBuilder::class)->build($store, 'hoje');

    expect($report)->toMatchArray(['sales_total' => '80.00', 'received' => '80.00', 'expenses' => '30.00', 'result' => '50.00'])
        ->and(collect($report['by_origin'])->firstWhere('origin', 'instagram')['total'])->toBe('80.00');

    // 6. Cancelling refunds the sale in the open caixa; the report drops it.
    $page->call('openCancel', $order->id)->set('cancelReason', 'Cliente desistiu')->call('confirmCancel')->assertHasNoErrors();

    $after = app(ReportBuilder::class)->build($store, 'hoje');

    expect($after['sales_total'])->toBe('0.00')->and($after['received'])->toBe('0.00')->and($after['result'])->toBe('-30.00');

    // 7. Closing: 100 change − 30 gas expense (cash) = 70 expected; the dono counts it right.
    $closed = app(CloseCashSession::class)->handle($session->fresh(), '70,00', null, $dono);

    expect($closed->expected_cash)->toBe('70.00')->and($closed->difference)->toBe('0.00');
});

it('locks the cash screens in the menu when the plan switches them off after the trial', function () {
    seedReferenceData();
    $dono = User::factory()->create();
    $dono->restaurant->plan->update(['features' => ['cash' => false]]);
    endTrial($dono->restaurant);

    $this->actingAs($dono->fresh())->get(route('panel.home'))->assertOk()->assertSee('data-nav-lock', false)->assertSee(route('panel.locked', 'cash'));
});
