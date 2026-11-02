<?php

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Restaurant;
use App\Services\Orders\OrderCodeGenerator;
use App\Services\Orders\WhatsAppMessageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * RF-06, RF-06c, RF-07a — the ready-to-send WhatsApp message and the order code.
 */
beforeEach(function () {
    seedReferenceData();
});

function deliveryOrder(array $attributes = []): Order
{
    $order = Order::factory()->delivery()->create(array_merge([
        'code' => 'D-X6ECG',
        'customer_name' => 'Ana Teste',
        'subtotal' => '129.90',
        'delivery_fee' => '0.00',
        'total' => '129.90',
        'payment_method_id' => PaymentMethod::idFor('dinheiro'),
        'change_for' => '150.00',
    ], $attributes));

    $order->items()->create([
        'name' => 'X-Bacon', 'quantity' => 1, 'unit_price' => '43.90', 'total' => '43.90', 'notes' => 'Sem cebola',
        'selections' => [
            'icon' => '🍔',
            'variant' => ['id' => 1, 'name' => 'Médio', 'price' => '33.90'],
            'complements' => [
                ['group' => 'Adicionais', 'name' => 'Bacon', 'price' => '4.00', 'quantity' => 2],
                ['group' => 'Adicionais', 'name' => 'Ovo', 'price' => '2.00', 'quantity' => 1],
            ],
        ],
    ]);

    return $order->fresh(['items', 'paymentMethod']);
}

it('writes the whole order into one message with a tracking link', function () {
    $message = (new WhatsAppMessageBuilder)->build(deliveryOrder(), 'https://degusta.test/p/abc123');

    expect($message)
        ->toContain('🧾 Pedido D-X6ECG')
        ->toContain('🍔 X-Bacon — 1 unidade (R$ 43,90)')
        ->toContain('   ↳ Opção: Médio')
        ->toContain('   ↳ Adicionais: 2× Bacon (+R$ 8,00), Ovo (+R$ 2,00)')
        ->toContain('   ↳ Obs.: Sem cebola')
        ->toContain('Subtotal: R$ 129,90')
        ->toContain('🛵 Entrega: grátis 🎉')
        ->toContain('💰 Total estimado: R$ 129,90')
        ->toContain('📦 Forma de recebimento: Entrega')
        ->toContain('💵 Pagamento: Dinheiro — troco para R$ 150,00')
        ->toContain('Quem recebe: Ana Teste')
        ->toContain('Rua das Flores, 120')
        ->toContain('Bairro: Centro')
        ->toContain('📍 Acompanhe seu pedido: https://degusta.test/p/abc123');
});

it('says "a combinar" when the delivery fee is pending and asks for the Pix proof', function () {
    $order = deliveryOrder(['delivery_fee' => null, 'delivery_fee_pending' => true, 'payment_method_id' => PaymentMethod::idFor('pix'), 'change_for' => null]);

    $message = (new WhatsAppMessageBuilder)->build($order, 'https://x/p/t');

    expect($message)->toContain('🛵 Entrega: a combinar')->toContain('⚡ Pagamento: Pix')->toContain('📎 Pagamento via Pix: mandar comprovante!');
});

it('marks a pickup order, an off hours order and the order notes', function () {
    $order = Order::factory()->create(['code' => 'D-AAAAA', 'customer_name' => 'Bia', 'notes' => 'Retiro às 19h', 'placed_off_hours' => true, 'payment_method_id' => PaymentMethod::idFor('credito')]);

    $message = (new WhatsAppMessageBuilder)->build($order, 'https://x/p/t');

    expect($message)->toContain('📦 Forma de recebimento: Retirada na loja')->toContain('👤 Nome: Bia')->toContain('📝 Observação: Retiro às 19h')->toContain('⏰ Pedido feito fora do horário')->toContain('Crédito')->not->toContain('Endereço de entrega');
});

it('builds the wa.me link with the encoded message', function () {
    $link = (new WhatsAppMessageBuilder)->link('5569996041447', "Olá!\nPedido");

    expect($link)->toBe('https://api.whatsapp.com/send?phone=5569996041447&text=Ol%C3%A1%21%0APedido');
});

it('generates unique, readable order codes per store and long tokens', function () {
    $store = Restaurant::factory()->create(['name' => 'Ótima Pizzaria']);
    $generator = new OrderCodeGenerator;

    $codes = collect(range(1, 30))->map(function () use ($store, $generator) {
        $code = $generator->code($store);
        Order::factory()->create(['restaurant_id' => $store->id, 'code' => $code]);

        return $code;
    });

    expect($codes->unique())->toHaveCount(30)
        ->and($codes->every(fn ($code) => preg_match('/^O-[A-HJ-NP-Z2-9]{5}$/', $code) === 1))->toBeTrue()
        ->and(strlen($generator->token()))->toBe(32);
});
