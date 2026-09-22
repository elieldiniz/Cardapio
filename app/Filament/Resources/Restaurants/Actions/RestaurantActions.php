<?php

namespace App\Filament\Resources\Restaurants\Actions;

use App\Actions\Admin\Impersonation;
use App\Actions\Admin\SetRestaurantSuspension;
use App\Models\Restaurant;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Support actions on a restaurant (US-7.2), shared by the table and the view page.
 */
class RestaurantActions
{
    public static function suspend(): Action
    {
        return Action::make('suspend')
            ->label('Suspender')
            ->icon(Heroicon::OutlinedPauseCircle)
            ->color('danger')
            ->visible(fn (Restaurant $record) => ! $record->isSuspended())
            ->requiresConfirmation()
            ->modalDescription('O painel do dono e o cardápio público ficam indisponíveis até reativar.')
            ->action(function (Restaurant $record) {
                app(SetRestaurantSuspension::class)->handle(auth()->user(), $record, true);
                Notification::make()->title('Restaurante suspenso')->success()->send();
            });
    }

    public static function reactivate(): Action
    {
        return Action::make('reactivate')
            ->label('Reativar')
            ->icon(Heroicon::OutlinedPlayCircle)
            ->color('success')
            ->visible(fn (Restaurant $record) => $record->isSuspended())
            ->requiresConfirmation()
            ->action(function (Restaurant $record) {
                app(SetRestaurantSuspension::class)->handle(auth()->user(), $record, false);
                Notification::make()->title('Restaurante reativado')->success()->send();
            });
    }

    public static function impersonate(): Action
    {
        return Action::make('impersonate')
            ->label('Entrar como o dono')
            ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Você vai navegar no painel do restaurante como o dono. A ação fica registrada no log de auditoria.')
            ->action(function (Restaurant $record) {
                app(Impersonation::class)->start(auth()->user(), $record);

                return redirect()->route('panel.home');
            });
    }
}
