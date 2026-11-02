<?php

use App\Models\Order;
use Illuminate\Console\Scheduling\Schedule;

/**
 * RNF-08, RF-07a — address and notes disappear 30 days after the order ended.
 */
beforeEach(function () {
    seedReferenceData();
});

function endedOrder(string $status, int $daysAgo, array $attributes = []): Order
{
    $order = Order::factory()->delivery()->status($status)->create(array_merge([
        'address_complement' => 'Apto 301',
        'address_reference' => 'Portão azul',
        'notes' => 'Deixar com o porteiro',
        'customer_name' => 'Ana Teste',
        'customer_phone' => '5569999990000',
        'finished_at' => now()->subDays($daysAgo),
    ], $attributes));
    $order->items()->create(['name' => 'X', 'quantity' => 1, 'unit_price' => '10.00', 'total' => '10.00', 'notes' => 'Sem cebola']);

    return $order;
}

it('clears address and notes 31 days after the end but keeps them at 29', function () {
    $old = endedOrder('finalizado', 31);
    $recent = endedOrder('finalizado', 29);

    $this->artisan('orders:prune-personal-data')->assertSuccessful();

    $old->refresh();
    $recent->refresh();

    expect($old)->address_street->toBeNull()->address_number->toBeNull()->address_complement->toBeNull()
        ->neighborhood->toBeNull()->address_reference->toBeNull()->notes->toBeNull()
        ->and($old->personal_data_purged_at)->not->toBeNull()
        ->and($old->items()->first()->notes)->toBeNull()
        ->and($recent->address_street)->toBe('Rua das Flores')
        ->and($recent->notes)->toBe('Deixar com o porteiro')
        ->and($recent->personal_data_purged_at)->toBeNull();
});

it('keeps name, WhatsApp and amounts of a purged order', function () {
    $order = endedOrder('finalizado', 40);

    $this->artisan('orders:prune-personal-data');

    expect($order->fresh())->customer_name->toBe('Ana Teste')->customer_phone->toBe('5569999990000')->total->toBe('45.00');
});

it('purges refused and cancelled orders too', function () {
    $refused = endedOrder('recusado', 35, ['finished_at' => null, 'cancelled_at' => now()->subDays(35)]);
    $cancelled = endedOrder('cancelado', 35, ['finished_at' => null, 'cancelled_at' => now()->subDays(35)]);

    $this->artisan('orders:prune-personal-data');

    expect($refused->fresh()->address_street)->toBeNull()->and($cancelled->fresh()->notes)->toBeNull();
});

it('never touches an order still in progress, however old', function () {
    $open = Order::factory()->delivery()->status('em_preparo')->create(['notes' => 'Sem pimenta', 'created_at' => now()->subDays(90), 'updated_at' => now()->subDays(90)]);

    $this->artisan('orders:prune-personal-data');

    expect($open->fresh())->address_street->toBe('Rua das Flores')->notes->toBe('Sem pimenta')->personal_data_purged_at->toBeNull();
});

it('is harmless to run twice and reports the count', function () {
    endedOrder('finalizado', 31);

    $this->artisan('orders:prune-personal-data')->expectsOutput('Pedidos com dados pessoais apagados: 1.')->assertSuccessful();
    $this->artisan('orders:prune-personal-data')->expectsOutput('Pedidos com dados pessoais apagados: 0.')->assertSuccessful();
});

it('is scheduled daily on one server', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'orders:prune-personal-data'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('30 4 * * *')
        ->and($event->onOneServer)->toBeTrue();
});
