<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Brazilian municipality (IBGE), used to pick the neighborhoods a store delivers to.
 */
#[Fillable(['ibge_code', 'state', 'name', 'search_name', 'neighborhoods_fetched_at'])]
class City extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['neighborhoods_fetched_at' => 'datetime'];
    }

    public function neighborhoods(): HasMany
    {
        return $this->hasMany(Neighborhood::class);
    }

    public function label(): string
    {
        return "{$this->name}/{$this->state}";
    }
}
