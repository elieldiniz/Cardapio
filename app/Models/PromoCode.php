<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A discount code of one restaurant (RF-17): a percentage or a fixed amount, with an optional
 * minimum order, use limit and validity. Not to be confused with `Coupon`, the platform's billing coupon.
 */
#[Fillable(['restaurant_id', 'code', 'type', 'value', 'min_order', 'max_uses', 'uses', 'starts_on', 'ends_on', 'description', 'is_active'])]
class PromoCode extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_order' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
            'max_uses' => 'integer',
            'uses' => 'integer',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function isPercent(): bool
    {
        return $this->type === 'percentual';
    }

    /**
     * Why the code cannot be used on a purchase of this value today, or null when it can (RF-17).
     * `$today` is the store's calendar day (RNF-09).
     */
    public function problem(int $subtotalCents, string $today): ?string
    {
        if (! $this->is_active) {
            return 'Este cupom não está mais ativo.';
        }

        if ($this->starts_on !== null && $this->starts_on->toDateString() > $today) {
            return 'Este cupom ainda não começou.';
        }

        if ($this->ends_on !== null && $this->ends_on->toDateString() < $today) {
            return 'Este cupom expirou.';
        }

        if ($this->max_uses !== null && $this->uses >= $this->max_uses) {
            return 'Este cupom atingiu o limite de usos.';
        }

        $minimum = (int) round(((float) $this->min_order) * 100);

        if ($minimum > 0 && $subtotalCents < $minimum) {
            return 'Este cupom vale para pedidos a partir de R$ '.number_format($minimum / 100, 2, ',', '.').'.';
        }

        return null;
    }

    /**
     * The discount in cents for a purchase, never above the purchase itself.
     */
    public function discountCents(int $subtotalCents): int
    {
        $discount = $this->isPercent()
            ? intdiv($subtotalCents * (int) round(((float) $this->value) * 100) + 5000, 10000)
            : (int) round(((float) $this->value) * 100);

        return max(0, min($discount, $subtotalCents));
    }
}
