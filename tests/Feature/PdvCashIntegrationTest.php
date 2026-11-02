<?php

use App\Actions\Cash\OpenCashSession;
use App\Actions\Orders\CreatePdvSale;
use App\Actions\Orders\FinalizeOrder;
use App\Models\Category;
use App\Models\Dish;
use App\Models\DishStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Cash\CashSummary;
use App\Services\Cash\ReportBuilder;
use Livewire\Livewire;

/**
 * RF-13, RF-25 — counter sales and delivery share one caixa and one report.
 */
it('counts counter sales and delivery together in the caixa and in the report', function () {
    seedReferenceData();
    $dono = User::factory()->create();
    $store = $dono->restaurant;
    $category = Category::factory()->create(['restaurant_id' => $store->id]);
    $dish = Dish::factory()->create(['restaurant_id' => $store->id, 'category_id' => $category->id, 'status_id' => DishStatus::idFor('ativo'), 'price' => '40.00']);
    $session = app(OpenCashSession::class)->handle($store, '100,00', $dono);

    // A delivery paid in cash and a counter sale paid with Pix.
    $delivery = Order::factory()->delivery()->status('em_rota')->create(['restaurant_id' => $store->id, 'subtotal' => '40.00', 'delivery_fee' => '5.00', 'total' => '45.00', 'origin' => 'instagram']);
    app(FinalizeOrder::class)->handle($delivery, [['method' => 'dinheiro', 'amount' => '45,00']], $dono);

    Livewire::actingAs($dono)->test('pages::panel.pdv')
        ->call('pick', $dish->id)->call('pick', $dish->id)
        ->call('charge')->set('payMode', 'pix')->call('finishSale')->assertHasNoErrors();

    $summary = app(CashSummary::class)->for($session);

    expect($summary['sales']['dinheiro'])->toBe('45.00')
        ->and($summary['sales']['pix'])->toBe('80.00')
        ->and($summary['sales_count'])->toBe(2)
        ->and($summary['expected_cash'])->toBe('145.00');

    $report = app(ReportBuilder::class)->build($store, 'hoje');
    $origins = collect($report['by_origin'])->keyBy('origin');

    expect($report['sales_total'])->toBe('125.00')
        ->and($report['sales_count'])->toBe(2)
        ->and($report['received'])->toBe('125.00')
        ->and($report['by_method'])->toBe(['pix' => '80.00', 'dinheiro' => '45.00', 'cartao' => '0.00'])
        ->and($origins['balcao']['total'])->toBe('80.00')
        ->and($origins['instagram']['total'])->toBe('45.00');
});

it('shows the counter sale in the order manager', function () {
    seedReferenceData();
    $dono = User::factory()->create();
    $category = Category::factory()->create(['restaurant_id' => $dono->restaurant_id]);
    $dish = Dish::factory()->create(['restaurant_id' => $dono->restaurant_id, 'category_id' => $category->id, 'status_id' => DishStatus::idFor('ativo'), 'name' => 'Pastel', 'price' => '9.00']);
    app(OpenCashSession::class)->handle($dono->restaurant, '0');

    $order = app(CreatePdvSale::class)->handle($dono->restaurant, [['dish_id' => $dish->id, 'quantity' => 1]], [['method' => 'pix', 'amount' => '9,00']], ['send_to_kitchen' => true], $dono);

    Livewire::actingAs($dono)->test('pages::panel.orders')->assertSee($order->code)->assertSee('Balcão')->assertSee('Pastel');
});
