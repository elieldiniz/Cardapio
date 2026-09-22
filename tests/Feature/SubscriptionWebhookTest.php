<?php

use App\Models\Category;
use App\Models\Dish;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\User;
use Tests\Fakes\FakeStripe;

beforeEach(function () {
    seedReferenceData();
    Plan::where('name', 'Pro')->update(['stripe_price_id' => 'price_pro']);
    Plan::where('name', 'Básico')->update(['stripe_price_id' => 'price_basico']);
    $this->restaurant = Restaurant::factory()->create(['stripe_id' => 'cus_123']);
});

afterEach(fn () => FakeStripe::uninstall());

test('a confirmed upgrade updates the restaurants plan immediately', function () {
    expect($this->restaurant->plan->name)->toBe('Grátis');

    stripeWebhook('customer.subscription.created', stripeSubscription('cus_123', 'price_pro'))->assertOk();

    $subscription = $this->restaurant->subscriptions()->with('items')->sole();

    expect($this->restaurant->fresh()->plan->name)->toBe('Pro')
        ->and($subscription->stripe_status)->toBe('active')
        ->and($subscription->items->first()->stripe_price)->toBe('price_pro');
});

test('limits from the previous plan no longer apply after upgrade', function () {
    $category = Category::factory()->create(['restaurant_id' => $this->restaurant->id]);
    Dish::factory()->count(15)->create(['restaurant_id' => $this->restaurant->id, 'category_id' => $category->id]);

    expect($this->restaurant->canAddDish())->toBeFalse();

    stripeWebhook('customer.subscription.created', stripeSubscription('cus_123', 'price_pro'));

    expect($this->restaurant->fresh()->canAddDish())->toBeTrue();
});

it('keeps the paid plan while stripe is still retrying a failed payment', function () {
    stripeWebhook('customer.subscription.created', stripeSubscription('cus_123', 'price_basico'));
    stripeWebhook('customer.subscription.updated', stripeSubscription('cus_123', 'price_basico', 'past_due'));

    expect($this->restaurant->fresh()->plan->name)->toBe('Básico');
});

it('opens a card-only subscription checkout from the plans screen', function () {
    $stripe = FakeStripe::install();
    $dono = User::factory()->create(['restaurant_id' => $this->restaurant->id]);

    Livewire\Livewire::actingAs($dono)
        ->test('pages::panel.subscription')
        ->assertSee('Assinar Pro')
        ->call('subscribe', Plan::where('name', 'Pro')->value('id'))
        ->assertRedirect('https://checkout.stripe.test/cs_fake_1');

    $params = $stripe->requestsTo('/v1/checkout/sessions')[0]['params'];

    expect($params['mode'])->toBe('subscription')
        ->and($params['payment_method_types'])->toBe(['card'])
        ->and($params['line_items'][0]['price'])->toBe('price_pro');
});
