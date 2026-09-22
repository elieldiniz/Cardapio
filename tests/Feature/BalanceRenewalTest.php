<?php

use App\Models\GenerationBalance;
use App\Models\GenerationLedger;
use App\Models\Plan;
use App\Models\Restaurant;

test('invoice paid resets monthly balance to the plans allowance without accumulating prior leftover', function () {
    seedReferenceData();
    Plan::where('name', 'Pro')->update(['stripe_price_id' => 'price_pro']);
    $restaurant = Restaurant::factory()->create(['stripe_id' => 'cus_123', 'plan_id' => Plan::where('name', 'Pro')->value('id')]);
    GenerationBalance::create(['restaurant_id' => $restaurant->id, 'monthly_balance' => 37, 'addon_balance' => 4]);

    $periodEnd = now()->addMonth()->startOfSecond();

    stripeWebhook('invoice.paid', [
        'id' => 'in_1', 'object' => 'invoice', 'customer' => 'cus_123', 'billing_reason' => 'subscription_cycle',
        'parent' => ['subscription_details' => ['subscription' => 'sub_1']],
        'lines' => ['data' => [['pricing' => ['price_details' => ['price' => 'price_pro']], 'period' => ['end' => $periodEnd->timestamp]]]],
    ])->assertOk();

    $balance = GenerationBalance::where('restaurant_id', $restaurant->id)->first();

    expect($balance->monthly_balance)->toBe(100)
        ->and($balance->addon_balance)->toBe(4)
        ->and($balance->renews_at->timestamp)->toBe($periodEnd->timestamp)
        ->and(GenerationLedger::with('type')->sole()->type->slug)->toBe('renovacao');
});
