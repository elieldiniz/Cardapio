<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * The kitchen ticket ("comanda") of an order, laid out for a 58 or 80 mm thermal printer (RF-23).
 * The width and font are chosen on the device and kept in its localStorage — the server stores nothing.
 */
class TicketController extends Controller
{
    public function show(Order $order): View
    {
        Gate::authorize('view', $order);

        return $this->render($order->load(['items', 'status', 'paymentMethod', 'restaurant']), false);
    }

    /** A sample ticket to test the printer settings without a real order. */
    public function sample(): View
    {
        $restaurant = auth()->user()->restaurant;

        $order = new Order([
            'code' => 'TESTE', 'fulfillment' => 'entrega', 'customer_name' => 'Maria Silva', 'customer_phone' => '5569999990000',
            'address_street' => 'Rua das Flores', 'address_number' => '120', 'neighborhood' => 'Centro', 'address_reference' => 'Portão azul',
            'subtotal' => '58.00', 'delivery_fee' => '5.00', 'discount' => '0.00', 'total' => '63.00', 'notes' => 'Tocar a campainha',
        ]);
        $order->created_at = now();
        $order->setRelation('restaurant', $restaurant);
        $order->setRelation('items', collect([
            new OrderItem(['name' => 'X-Bacon', 'quantity' => '2', 'sale_type' => 'unit', 'unit_price' => '29.00', 'total' => '58.00', 'notes' => 'Sem cebola', 'selections' => ['complements' => [['group' => 'Adicionais', 'name' => 'Queijo extra', 'price' => '3.00', 'quantity' => 1]]]]),
        ]));

        return $this->render($order, true);
    }

    private function render(Order $order, bool $sample): View
    {
        return view('panel.ticket', ['order' => $order, 'restaurant' => $order->restaurant, 'sample' => $sample]);
    }
}
