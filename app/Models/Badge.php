<?php

namespace App\Models;

use App\Models\Concerns\IsLookupTable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug'])]
class Badge extends Model
{
    use IsLookupTable;

    public function dishes(): BelongsToMany
    {
        return $this->belongsToMany(Dish::class, 'badge_dish');
    }
}
