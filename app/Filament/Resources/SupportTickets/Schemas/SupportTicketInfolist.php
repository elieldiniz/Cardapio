<?php

namespace App\Filament\Resources\SupportTickets\Schemas;

use App\Models\SupportTicket;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class SupportTicketInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Chamado')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('restaurant.name')->label('Restaurante'),
                        TextEntry::make('user.email')->label('Dono')->copyable(),
                        TextEntry::make('category')->label('Categoria')->formatStateUsing(fn (string $state) => SupportTicket::CATEGORIES[$state] ?? $state),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (string $state) => SupportTicket::STATUSES[$state] ?? $state)
                            ->color(fn (string $state) => match ($state) {
                                SupportTicket::STATUS_OPEN => 'danger',
                                SupportTicket::STATUS_ANSWERED => 'success',
                                default => 'gray',
                            }),
                    ]),
                Section::make('Conversa')
                    ->schema([
                        View::make('filament.support-thread'),
                    ]),
            ]);
    }
}
