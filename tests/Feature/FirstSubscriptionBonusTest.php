<?php

use App\Models\GenerationBalance;
use App\Models\GenerationLedger;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\VideoAddonPackage;
use Database\Seeders\VideoAddonPackageSeeder;

beforeEach(function () {
    seedReferenceData();
    Plan::where('name', 'Básico')->update(['stripe_price_id' => 'price_basico']);
    $this->restaurant = Restaurant::factory()->create(['stripe_id' => 'cus_bonus', 'plan_id' => Plan::where('name', 'Grátis')->value('id')]);
    GenerationBalance::create(['restaurant_id' => $this->restaurant->id, 'monthly_balance' => 0, 'addon_balance' => 3]);
});

function paidInvoice(string $id, string $reason = 'subscription_create', int $paid = 3990): array
{
    return [
        'id' => $id, 'object' => 'invoice', 'customer' => 'cus_bonus', 'billing_reason' => $reason, 'amount_paid' => $paid,
        'parent' => ['subscription_details' => ['subscription' => 'sub_1']],
        'lines' => ['data' => [['pricing' => ['price_details' => ['price' => 'price_basico']], 'period' => ['end' => now()->addMonth()->timestamp]]]],
    ];
}

function bonusEntries(Restaurant $restaurant)
{
    return GenerationLedger::with('type')->where('restaurant_id', $restaurant->id)->get()->filter(fn ($entry) => $entry->type->slug === 'bonus');
}

it('seeds the new model: no monthly generations and a one-time bonus on the first subscription', function () {
    $basic = Plan::where('name', 'Básico')->first();

    expect($basic->monthly_generations)->toBe(0)->and($basic->first_subscription_bonus)->toBe(6);
});

it('gives the plan bonus on the first paid subscription invoice, on top of what the restaurant already has', function () {
    stripeWebhook('invoice.paid', paidInvoice('in_1'))->assertOk();

    $balance = GenerationBalance::where('restaurant_id', $this->restaurant->id)->first();

    expect($balance->addon_balance)->toBe(3 + 6)
        ->and($balance->monthly_balance)->toBe(0)
        ->and(bonusEntries($this->restaurant))->toHaveCount(1)
        ->and(bonusEntries($this->restaurant)->first()->quantity)->toBe(6);
});

it('never gives the bonus twice: replays, later cycles and a cancel then resubscribe', function () {
    stripeWebhook('invoice.paid', paidInvoice('in_1'));
    // Same invoice delivered again.
    stripeWebhook('invoice.paid', paidInvoice('in_1'));
    // A monthly renewal.
    stripeWebhook('invoice.paid', paidInvoice('in_2', 'subscription_cycle'));
    // The restaurant cancels and subscribes again later: a new "subscription_create" invoice.
    stripeWebhook('invoice.paid', paidInvoice('in_3'));

    expect(GenerationBalance::where('restaurant_id', $this->restaurant->id)->value('addon_balance'))->toBe(3 + 6)
        ->and(bonusEntries($this->restaurant))->toHaveCount(1);
});

it('does not give the bonus on a renewal, an upgrade or an invoice with nothing paid', function (string $reason, int $paid) {
    stripeWebhook('invoice.paid', paidInvoice('in_x', $reason, $paid));

    expect(GenerationBalance::where('restaurant_id', $this->restaurant->id)->value('addon_balance'))->toBe(3)
        ->and(bonusEntries($this->restaurant))->toHaveCount(0);
})->with([
    'monthly cycle' => ['subscription_cycle', 3990],
    'plan update' => ['subscription_update', 3990],
    'fully discounted first invoice' => ['subscription_create', 0],
]);

it('gives nothing when the plan has no bonus', function () {
    Plan::where('name', 'Básico')->update(['first_subscription_bonus' => 0]);

    stripeWebhook('invoice.paid', paidInvoice('in_1'));

    expect(GenerationBalance::where('restaurant_id', $this->restaurant->id)->value('addon_balance'))->toBe(3)
        ->and(bonusEntries($this->restaurant))->toHaveCount(0);
});

it('keeps the bonus after the monthly renewal resets the monthly balance to the plan allowance', function () {
    stripeWebhook('invoice.paid', paidInvoice('in_1'));

    // The new plans carry no monthly allowance, so nothing monthly appears and the bonus stays untouched.
    expect(GenerationBalance::where('restaurant_id', $this->restaurant->id)->first())
        ->monthly_balance->toBe(0)
        ->addon_balance->toBe(9);
});

it('sells add-on packs at no less than R$ 3,90 per generation', function () {
    VideoAddonPackage::query()->delete();
    $this->seed(VideoAddonPackageSeeder::class);

    foreach (VideoAddonPackage::all() as $package) {
        expect($package->price_cents / $package->generations_count)->toBeGreaterThanOrEqual(356);
    }

    expect(VideoAddonPackage::pluck('generations_count')->sort()->values()->all())->toBe([10, 25]);
});

it('shows the new offer on the landing: trial, first-subscription bonus and no monthly videos on paid plans', function () {
    config()->set('landing.show_prices', true);

    $this->get('/')
        ->assertOk()
        ->assertSee('3 vídeos para experimentar')
        ->assertSee('+6 vídeos de brinde ao assinar')
        ->assertSee('+12 vídeos de brinde ao assinar')
        ->assertSee('R$ 39,90')
        ->assertSee('R$ 79,90')
        ->assertDontSee('vídeos do prato por mês');
});

it('lists everything on every plan card, so each can be read on its own', function () {
    config()->set('landing.show_prices', true);

    $response = $this->get('/')->assertOk()->assertDontSee('Tudo do Grátis, mais:')->assertDontSeeHtml('data-all-plans');

    // Shared benefits show up on all three cards (Grátis, Básico, Pro).
    foreach ([
        'Envie seus próprios vídeos, sem limite',
        'QR Code que nunca muda, com display de mesa para imprimir',
        'Logo, capa, cor e fonte do seu restaurante',
        'Preço e “esgotado” atualizados na hora',
        'Compre mais vídeos quando quiser, em pacotes sem validade',
        'Suporte pelo painel',
    ] as $benefit) {
        expect(substr_count($response->getContent(), $benefit))->toBe(3, "'{$benefit}' should be on the 3 plan cards");
    }

    $response->assertSee('por quanto tempo cada prato é assistido')->assertSee('Cardápio sem a marca');
});
