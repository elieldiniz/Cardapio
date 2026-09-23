<?php

namespace App\Filament\Resources\Restaurants\Actions;

use App\Actions\Admin\Impersonation;
use App\Actions\Admin\PurgeRestaurantAccount;
use App\Actions\Admin\RestoreRestaurant;
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
            ->visible(fn (Restaurant $record) => ! $record->trashed() && ! $record->isSuspended())
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
            ->visible(fn (Restaurant $record) => ! $record->trashed() && $record->isSuspended())
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
            ->visible(fn (Restaurant $record) => ! $record->trashed())
            ->requiresConfirmation()
            ->modalDescription('Você vai navegar no painel do restaurante como o dono. A ação fica registrada no log de auditoria.')
            ->action(function (Restaurant $record) {
                app(Impersonation::class)->start(auth()->user(), $record);

                return redirect()->route('panel.home');
            });
    }

    public static function restore(): Action
    {
        return Action::make('restore')
            ->label('Restaurar conta')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->visible(fn (Restaurant $record) => $record->trashed())
            ->requiresConfirmation()
            ->modalDescription('O dono volta a acessar o painel e o cardápio volta ao ar, no plano Grátis (a assinatura foi cancelada na exclusão).')
            ->action(function (Restaurant $record) {
                app(RestoreRestaurant::class)->handle(auth()->user(), $record);
                Notification::make()->title('Conta restaurada')->success()->send();
            });
    }

    public static function purge(): Action
    {
        return Action::make('purge')
            ->label('Apagar definitivamente')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (Restaurant $record) => $record->trashed())
            ->requiresConfirmation()
            ->modalHeading('Apagar definitivamente?')
            ->modalDescription('Apaga pratos, fotos, vídeos (também no Mux), métricas e os usuários do restaurante. Não dá para desfazer.')
            ->modalSubmitActionLabel('Apagar para sempre')
            ->action(function (Restaurant $record) {
                app(PurgeRestaurantAccount::class)->handle(auth()->user(), $record);
                Notification::make()->title('Exclusão definitiva iniciada')->body('Os dados são apagados em segundo plano.')->success()->send();
            });
    }
}
