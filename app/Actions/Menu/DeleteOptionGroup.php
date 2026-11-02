<?php

namespace App\Actions\Menu;

use App\Models\OptionGroup;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a complement group with its options and its links to products (RF-03).
 * Past orders keep their own snapshot of what was chosen.
 */
class DeleteOptionGroup
{
    public function handle(OptionGroup $group): void
    {
        DB::transaction(fn () => $group->delete());
    }
}
