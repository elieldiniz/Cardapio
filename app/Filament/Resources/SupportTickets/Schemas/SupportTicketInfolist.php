<?php

namespace App\Filament\Resources\SupportTickets\Schemas;

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
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
                        TextEntry::make('category')->label('Categoria')->formatStateUsing(fn (TicketCategory $state) => $state->label()),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (TicketStatus $state) => $state->label())
                            ->color(fn (TicketStatus $state) => $state->color()),
                    ]),
                Section::make('Conversa')
                    ->schema([
                        View::make('filament.support-thread'),
                    ]),
            ]);
    }
}
