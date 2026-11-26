<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A neighborhood of a city, from IBGE or (for cities IBGE does not cover) OpenStreetMap.
 */
#[Fillable(['city_id', 'name', 'source'])]
class Neighborhood extends Model
{
    public $timestamps = false;

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
