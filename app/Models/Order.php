<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An order placed through the cardápio or the counter (RF-06). The code is unique per
 * store; the tracking token is the unguessable key of the customer's public page (RF-07).
 */
#[Fillable([
    'restaurant_id', 'code', 'tracking_token', 'status_id', 'fulfillment', 'channel', 'origin', 'campaign',
    'customer_id', 'customer_name', 'customer_phone',
    'address_street', 'address_number', 'address_complement', 'neighborhood', 'address_reference', 'notes',
    'subtotal', 'delivery_fee', 'delivery_fee_pending', 'distance_km', 'discount', 'total', 'promo_code_id', 'promo_released_at', 'loyalty_card_id', 'loyalty_reverted_at',
    'payment_method_id', 'change_for', 'placed_off_hours', 'courier_id', 'delivered_at', 'courier_settled_at', 'cash_session_id',
    'whatsapp_opened_at', 'customer_confirmed_at', 'accepted_at', 'dispatched_at', 'finished_at',
    'cancelled_at', 'cancel_reason', 'personal_data_purged_at', 'original_total', 'adjusted_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const TRACKING_DAYS = 7;

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'change_for' => 'decimal:2',
            'delivery_fee_pending' => 'boolean',
            'placed_off_hours' => 'boolean',
            'whatsapp_opened_at' => 'datetime',
            'customer_confirmed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'finished_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'personal_data_purged_at' => 'datetime',
            'original_total' => 'decimal:2',
            'adjusted_at' => 'datetime',
            'promo_released_at' => 'datetime',
            'loyalty_reverted_at' => 'datetime',
            'delivered_at' => 'datetime',
            'courier_settled_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'status_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('display_order')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('changed_at')->orderBy('id');
    }

    public function statusSlug(): ?string
    {
        return $this->status?->slug;
    }

    /**
     * Finished, refused or cancelled: the customer's link keeps working for 7 days after this (RF-07a).
     */
    public function isEnded(): bool
    {
        return in_array($this->statusSlug(), ['finalizado', 'recusado', 'cancelado'], true);
    }

    public function endedAt(): ?CarbonInterface
    {
        if (! $this->isEnded()) {
            return null;
        }

        return $this->finished_at
            ?? $this->cancelled_at
            ?? $this->history()->where('status_id', $this->status_id)->max('changed_at')
            ?? $this->updated_at;
    }

    public function trackingExpired(): bool
    {
        $endedAt = $this->endedAt();

        return $endedAt !== null && Carbon::parse($endedAt)->addDays(self::TRACKING_DAYS)->isPast();
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function isDelivery(): bool
    {
        return $this->fulfillment === 'entrega';
    }

    /**
     * "Rua das Flores, 120 · Centro" — the single line shown to the dono.
     */
    public function addressLine(): ?string
    {
        if ($this->address_street === null && $this->neighborhood === null) {
            return null;
        }

        return collect([
            trim(($this->address_street ?? '').($this->address_number ? ', '.$this->address_number : '')),
            $this->address_complement,
        ])->filter()->implode(' — ').($this->neighborhood ? ' · '.$this->neighborhood : '');
    }
}
