<?php

namespace App\Models;

use Database\Factories\RestaurantOrderSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Order-taking settings of one restaurant (RF-01, RF-02, RF-02e, RF-06b).
 */
#[Fillable(['restaurant_id', 'whatsapp_number', 'address', 'pix_key', 'pix_holder', 'pix_city', 'accepts_orders', 'hours_enabled', 'accepts_when_closed', 'timezone', 'delivery_mode', 'free_delivery_over', 'min_order_delivery', 'accepts_other_neighborhood', 'pizza_pricing_rule', 'lat', 'lng'])]
class RestaurantOrderSetting extends Model
{
    /** @use HasFactory<RestaurantOrderSettingFactory> */
    use HasFactory;

    public const DEFAULT_TIMEZONE = 'America/Porto_Velho';

    protected function casts(): array
    {
        return [
            'accepts_orders' => 'boolean',
            'hours_enabled' => 'boolean',
            'accepts_when_closed' => 'boolean',
            'accepts_other_neighborhood' => 'boolean',
            'free_delivery_over' => 'decimal:2',
            'min_order_delivery' => 'decimal:2',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
