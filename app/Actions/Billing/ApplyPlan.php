<?php

namespace App\Actions\Billing;

use App\Actions\RegisterRestaurantOwner;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\Subscription;

/**
 * Keeps restaurants.plan_id in step with the restaurant's Stripe subscription
 * (US-5.2, US-5.5). While Stripe is still retrying a failed payment
 * (past_due) the paid plan stays; once the subscription ends unpaid or is
 * canceled, the restaurant falls back to Grátis.
 */
class ApplyPlan
{
    /**
     * Subscription statuses that keep the paid plan. past_due = Stripe is still retrying.
     */
    public const PAID_STATUSES = ['active', 'trialing', 'past_due'];

    public function __construct(private readonly DowngradeToFreePlan $downgrade) {}

    /**
     * @param  string|null  $endReason  Stripe's cancellation_details.reason when the subscription ended.
     */
    public function handle(Restaurant $restaurant, ?string $endReason = null): void
    {
        $subscription = $restaurant->subscriptions()
            ->whereIn('stripe_status', self::PAID_STATUSES)
            ->latest('id')
            ->first();

        $plan = $subscription ? $this->planFor($subscription) : null;

        if ($plan !== null) {
            if ($restaurant->plan_id !== $plan->id) {
                $restaurant->update(['plan_id' => $plan->id]);
            }

            return;
        }

        if ($restaurant->plan?->name !== RegisterRestaurantOwner::FREE_PLAN_NAME) {
            $this->downgrade->handle($restaurant, $this->endedForNonPayment($restaurant, $endReason));
        }
    }

    private function planFor(Subscription $subscription): ?Plan
    {
        $priceIds = $subscription->items()->pluck('stripe_price')->push($subscription->stripe_price)->filter();

        return Plan::query()->whereIn('stripe_price_id', $priceIds)->first();
    }

    private function endedForNonPayment(Restaurant $restaurant, ?string $endReason): bool
    {
        if ($endReason !== null) {
            return $endReason === 'payment_failed';
        }

        return $restaurant->subscriptions()->where('stripe_status', 'unpaid')->exists();
    }
}
