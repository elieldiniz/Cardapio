<?php

use App\Models\Category;
use App\Models\Dish;
use App\Models\DishStatus;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\User;
use App\Notifications\PaymentFailed;
use App\Notifications\PlanDowngraded;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    Plan::where('name', 'Básico')->update(['stripe_price_id' => 'price_basico']);
    $this->restaurant = Restaurant::factory()->create(['stripe_id' => 'cus_123']);
    $this->dono = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
    stripeWebhook('customer.subscription.created', stripeSubscription('cus_123', 'price_basico'));
});

function endSubscriptionUnpaid(): void
{
    stripeWebhook('invoice.payment_failed', [
        'id' => 'in_x', 'object' => 'invoice', 'customer' => 'cus_123', 'subscription' => 'sub_1',
    ]);
    stripeWebhook('customer.subscription.deleted', stripeSubscription('cus_123', 'price_basico', 'canceled', 'sub_1', [
        'cancellation_details' => ['reason' => 'payment_failed'],
    ]));
}

test('an unrecovered failed payment reverts the restaurant to the free plan without suspending it', function () {
    Notification::fake();
    $statusBefore = $this->restaurant->fresh()->status_id;

    endSubscriptionUnpaid();

    $restaurant = $this->restaurant->fresh();

    expect($restaurant->plan->name)->toBe('Grátis')
        ->and($restaurant->status_id)->toBe($statusBefore)
        ->and($restaurant->status->slug)->toBe('ativo');

    Notification::assertSentTo($this->dono, PaymentFailed::class);
    Notification::assertSentTo($this->dono, PlanDowngraded::class, fn (PlanDowngraded $notification) => $notification->forNonPayment);
});

test('dishes beyond the free plans limit are auto hidden on downgrade', function () {
    $category = Category::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $dishes = Dish::factory()->count(18)->sequence(fn ($sequence) => ['display_order' => $sequence->index])
        ->create(['restaurant_id' => $this->restaurant->id, 'category_id' => $category->id]);

    endSubscriptionUnpaid();

    $hidden = Dish::where('restaurant_id', $this->restaurant->id)->where('status_id', DishStatus::idFor('oculto'))->orderBy('display_order')->pluck('id');

    expect($hidden->all())->toBe($dishes->slice(15)->pluck('id')->values()->all())
        ->and($this->restaurant->dishes()->visible()->count())->toBe(15);
});

it('shows the downgrade notice in the panel until dismissed', function () {
    endSubscriptionUnpaid();

    $this->actingAs($this->dono)
        ->get(route('panel.home'))
        ->assertSee('Pagamento não aprovado — plano alterado para Grátis')
        ->assertSee('Seu cardápio continua no ar');

    $notice = $this->dono->unreadNotifications()->where('type', PlanDowngraded::class)->first();

    Livewire::actingAs($this->dono)->test('panel-notices')->call('dismiss', $notice->id);

    $this->get(route('panel.home'))->assertDontSee('Pagamento não aprovado');
});

it('blocks un-hiding a dish beyond the free limit until regularized', function () {
    $category = Category::factory()->create(['restaurant_id' => $this->restaurant->id]);
    Dish::factory()->count(16)->create(['restaurant_id' => $this->restaurant->id, 'category_id' => $category->id]);
    endSubscriptionUnpaid();

    $hidden = Dish::where('restaurant_id', $this->restaurant->id)->where('status_id', DishStatus::idFor('oculto'))->firstOrFail();

    Livewire::actingAs($this->dono)->test('pages::panel.dishes')->call('updateStatus', $hidden->id, 'ativo');

    expect($hidden->fresh()->status_id)->toBe(DishStatus::idFor('oculto'));
});
