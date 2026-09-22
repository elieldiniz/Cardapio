<?php

namespace App\Listeners;

use App\Actions\Billing\ApplyPlan;
use App\Actions\Billing\CreditAddonPurchase;
use App\Actions\Billing\RenewMonthlyBalance;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\VideoAddonPackage;
use App\Notifications\PaymentFailed;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Reacts to Stripe webhooks (signature verified by Cashier when
 * STRIPE_WEBHOOK_SECRET is set). Cashier mirrors subscriptions and their
 * items locally; this listener applies the business rules on top.
 */
class StripeWebhookListener
{
    public function __construct(
        private readonly ApplyPlan $applyPlan,
        private readonly RenewMonthlyBalance $renewMonthlyBalance,
        private readonly CreditAddonPurchase $creditAddonPurchase,
    ) {}

    /**
     * Subscription events, after Cashier has mirrored them (US-5.2, US-5.5).
     */
    public function handleWebhookHandled(WebhookHandled $event): void
    {
        $type = $event->payload['type'] ?? null;

        if (! in_array($type, ['customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted'], true)) {
            return;
        }

        $object = $event->payload['data']['object'];

        if ($restaurant = $this->restaurant($object['customer'] ?? null)) {
            $endReason = $type === 'customer.subscription.deleted'
                ? ($object['cancellation_details']['reason'] ?? null)
                : null;

            $this->applyPlan->handle($restaurant, $endReason);
        }
    }

    /**
     * Events Cashier does not handle itself.
     */
    public function handleWebhookReceived(WebhookReceived $event): void
    {
        $object = $event->payload['data']['object'] ?? [];

        match ($event->payload['type'] ?? null) {
            'invoice.paid' => $this->invoicePaid($object),
            'invoice.payment_failed' => $this->invoicePaymentFailed($object),
            'checkout.session.completed' => $this->checkoutCompleted($object),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function invoicePaid(array $invoice): void
    {
        $restaurant = $this->restaurant($invoice['customer'] ?? null);

        if ($restaurant === null || $this->subscriptionId($invoice) === null) {
            return;
        }

        if (! in_array($invoice['billing_reason'] ?? null, ['subscription_create', 'subscription_cycle', 'subscription_update'], true)) {
            return;
        }

        // A paid invoice proves the plan, even if it arrives before customer.subscription.created.
        $plan = Plan::query()->whereIn('stripe_price_id', $this->priceIds($invoice))->first();

        if ($plan !== null && $restaurant->plan_id !== $plan->id) {
            $restaurant->update(['plan_id' => $plan->id]);
        }

        $periodEnd = $invoice['lines']['data'][0]['period']['end'] ?? $invoice['period_end'] ?? null;

        $this->renewMonthlyBalance->handle(
            $restaurant->fresh(),
            (string) $invoice['id'],
            $periodEnd ? Carbon::createFromTimestamp($periodEnd) : null,
        );
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function invoicePaymentFailed(array $invoice): void
    {
        $restaurant = $this->restaurant($invoice['customer'] ?? null);

        if ($restaurant === null || $this->subscriptionId($invoice) === null) {
            return;
        }

        $owners = $restaurant->users()->whereHas('role', fn ($role) => $role->where('slug', 'dono'))->get();

        Notification::send($owners, new PaymentFailed);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function checkoutCompleted(array $session): void
    {
        if (($session['mode'] ?? null) !== 'payment' || ($session['payment_status'] ?? null) !== 'paid') {
            return;
        }

        $restaurant = $this->restaurant($session['customer'] ?? null);
        $package = VideoAddonPackage::find($session['metadata']['addon_package_id'] ?? null);

        if ($restaurant !== null && $package !== null) {
            $this->creditAddonPurchase->handle($restaurant, $package, (string) $session['id']);
        }
    }

    private function restaurant(?string $stripeCustomerId): ?Restaurant
    {
        return $stripeCustomerId ? Cashier::findBillable($stripeCustomerId) : null;
    }

    /**
     * Supports both the current (parent.subscription_details) and legacy invoice shapes.
     *
     * @param  array<string, mixed>  $invoice
     */
    private function subscriptionId(array $invoice): ?string
    {
        return $invoice['parent']['subscription_details']['subscription'] ?? $invoice['subscription'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $invoice
     * @return array<int, string>
     */
    private function priceIds(array $invoice): array
    {
        return collect($invoice['lines']['data'] ?? [])
            ->map(fn (array $line) => $line['pricing']['price_details']['price'] ?? $line['price']['id'] ?? null)
            ->filter()
            ->values()
            ->all();
    }
}
