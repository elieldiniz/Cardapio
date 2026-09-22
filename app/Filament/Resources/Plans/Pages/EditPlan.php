<?php

namespace App\Filament\Resources\Plans\Pages;

use App\Filament\Resources\Plans\PlanResource;
use App\Models\AdminLog;
use Filament\Resources\Pages\EditRecord;

/**
 * Editing a plan changes future grants only — balances already granted
 * are stored per restaurant and are not touched (US-7.3).
 */
class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function afterSave(): void
    {
        AdminLog::record(auth()->user(), 'plano_atualizado', "plan:{$this->record->id}", [
            'changes' => $this->record->getChanges(),
        ]);
    }
}
