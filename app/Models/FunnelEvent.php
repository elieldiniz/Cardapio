<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A step of the ordering funnel taken by one visitor session (RF-21).
 */
#[Fillable(['restaurant_id', 'session_token', 'event', 'dish_id', 'occurred_on', 'hour'])]
class FunnelEvent extends Model
{
    public const EVENTS = ['visit', 'add', 'checkout'];

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['occurred_on' => 'date'];
    }
}
