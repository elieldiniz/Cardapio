<?php

use App\Actions\Ai\DebitGenerationBalance;
use App\Models\Dish;
use App\Models\GenerationBalance;
use App\Models\GenerationLedger;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\VideoAddonPackage;
use App\Models\VideoGeneration;
use Tests\Fakes\FakeStripe;

beforeEach(function () {
    seedReferenceData();
    $this->restaurant = Restaurant::factory()->create(['stripe_id' => 'cus_123']);
    GenerationBalance::create(['restaurant_id' => $this->restaurant->id, 'monthly_balance' => 0, 'addon_balance' => 0]);
    $this->package = VideoAddonPackage::create(['name' => 'Pacote 10', 'generations_count' => 10, 'price_cents' => 2900, 'stripe_price_id' => 'price_pack10']);
});

afterEach(fn () => FakeStripe::uninstall());

function addonCheckoutCompleted(string $session, int $packageId): array
{
    return [
        'id' => $session,
        'object' => 'checkout.session',
        'customer' => 'cus_123',
        'mode' => 'payment',
        'payment_status' => 'paid',
        'metadata' => ['addon_package_id' => (string) $packageId],
    ];
}

test('a confirmed addon purchase credits addon balance and logs a compra entry', function () {
    stripeWebhook('checkout.session.completed', addonCheckoutCompleted('cs_1', $this->package->id))->assertOk();
    // Stripe may deliver the same event twice.
    stripeWebhook('checkout.session.completed', addonCheckoutCompleted('cs_1', $this->package->id))->assertOk();

    $entry = GenerationLedger::with('type')->sole();

    expect(GenerationBalance::where('restaurant_id', $this->restaurant->id)->value('addon_balance'))->toBe(10)
        ->and($entry->type->slug)->toBe('compra')
        ->and($entry->quantity)->toBe(10)
        ->and($entry->reference)->toBe('stripe_checkout:cs_1');
});

test('addon balance never expires and is spent only after monthly balance is exhausted', function () {
    Plan::where('name', 'Básico')->update(['stripe_price_id' => 'price_basico']);

    stripeWebhook('checkout.session.completed', addonCheckoutCompleted('cs_1', $this->package->id));

    // A monthly renewal resets the monthly balance but leaves addon untouched.
    stripeWebhook('invoice.paid', [
        'id' => 'in_1', 'object' => 'invoice', 'customer' => 'cus_123', 'billing_reason' => 'subscription_create',
        'parent' => ['subscription_details' => ['subscription' => 'sub_1']],
        'lines' => ['data' => [['pricing' => ['price_details' => ['price' => 'price_basico']], 'period' => ['end' => now()->addMonth()->timestamp]]]],
    ]);

    $balance = GenerationBalance::where('restaurant_id', $this->restaurant->id)->first();
    expect($balance->monthly_balance)->toBe(30)->and($balance->addon_balance)->toBe(10);

    // Spending 32: 30 from monthly, only then 2 from addon.
    $generation = VideoGeneration::factory()->ready()->create(['dish_id' => Dish::factory()->create(['restaurant_id' => $this->restaurant->id])->id]);
    app(DebitGenerationBalance::class)->handle($generation, 32);

    $balance->refresh();
    expect($balance->monthly_balance)->toBe(0)->and($balance->addon_balance)->toBe(8);

    // Next month resets monthly again; the remaining addon survives.
    stripeWebhook('invoice.paid', [
        'id' => 'in_2', 'object' => 'invoice', 'customer' => 'cus_123', 'billing_reason' => 'subscription_cycle',
        'subscription' => 'sub_1',
        'lines' => ['data' => [['price' => ['id' => 'price_basico'], 'period' => ['end' => now()->addMonths(2)->timestamp]]]],
    ]);

    $balance->refresh();
    expect($balance->monthly_balance)->toBe(30)->and($balance->addon_balance)->toBe(8);
});

it('opens a one-time payment checkout for an addon package', function () {
    $stripe = FakeStripe::install();
    $dono = User::factory()->create(['restaurant_id' => $this->restaurant->id]);

    Livewire\Livewire::actingAs($dono)
        ->test('pages::panel.subscription')
        ->call('buyPackage', $this->package->id)
        ->assertRedirect('https://checkout.stripe.test/cs_fake_1');

    $params = $stripe->requestsTo('/v1/checkout/sessions')[0]['params'];

    expect($params['mode'])->toBe('payment')
        ->and($params['payment_method_types'])->toBe(['card'])
        ->and((string) $params['metadata']['addon_package_id'])->toBe((string) $this->package->id)
        ->and($params['line_items'][0]['price'])->toBe('price_pack10');
});
