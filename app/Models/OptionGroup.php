<?php

namespace App\Models;

use Database\Factories\OptionGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable group of complements the customer chooses from (RF-03): "one" = a single
 * option (size, edge), "many" = several up to `max_choices` (add-ons, sauces).
 */
#[Fillable(['restaurant_id', 'name', 'mode', 'max_choices', 'is_required', 'allow_repeat', 'display_order', 'is_active'])]
class OptionGroup extends Model
{
    /** @use HasFactory<OptionGroupFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'allow_repeat' => 'boolean',
            'is_active' => 'boolean',
            'max_choices' => 'integer',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(Option::class)->orderBy('display_order')->orderBy('id');
    }

    public function dishes(): BelongsToMany
    {
        return $this->belongsToMany(Dish::class, 'dish_option_group')->withPivot('display_order');
    }

    public function isSingleChoice(): bool
    {
        return $this->mode === 'one';
    }
}
