<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Actions\Support\ReplyToSupportTicket;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Models\SupportTicket;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->label('Responder')
                ->icon('heroicon-o-paper-airplane')
                ->schema([
                    Textarea::make('body')->label('Mensagem')->required()->rows(6)->maxLength(5000),
                ])
                ->action(function (array $data, SupportTicket $record) {
                    app(ReplyToSupportTicket::class)->handle($record, auth()->user(), $data['body']);

                    Notification::make()->title('Resposta enviada')->success()->send();
                }),
            Action::make('close')
                ->label('Fechar chamado')
                ->color('gray')
                ->visible(fn (SupportTicket $record) => ! $record->isClosed())
                ->requiresConfirmation()
                ->action(fn (SupportTicket $record) => app(ReplyToSupportTicket::class)->close($record)),
            Action::make('reopen')
                ->label('Reabrir')
                ->color('gray')
                ->visible(fn (SupportTicket $record) => $record->isClosed())
                ->action(fn (SupportTicket $record) => app(ReplyToSupportTicket::class)->reopen($record)),
        ];
    }
}
