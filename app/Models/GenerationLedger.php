<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['restaurant_id', 'type_id', 'quantity', 'reference'])]
class GenerationLedger extends Model
{
    protected $table = 'generation_ledger';

    const UPDATED_AT = null;

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(GenerationLedgerType::class, 'type_id');
    }
}
