<?php

namespace App\Filament\Resources\SupportTickets\Tables;

use App\Models\SupportTicket;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupportTicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['restaurant', 'user']))
            ->defaultSort('last_message_at', 'desc')
            ->columns([
                TextColumn::make('subject')
                    ->label('Assunto')
                    ->description(fn (SupportTicket $record) => $record->categoryLabel())
                    ->searchable()
                    ->limit(60),
                TextColumn::make('restaurant.name')->label('Restaurante')->searchable(),
                TextColumn::make('user.email')->label('Dono')->searchable()->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => SupportTicket::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        SupportTicket::STATUS_OPEN => 'danger',
                        SupportTicket::STATUS_ANSWERED => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('last_message_at')->label('Última mensagem')->since()->sortable(),
                TextColumn::make('created_at')->label('Aberto em')->dateTime('d/m/Y H:i')->sortable()->toggledHiddenByDefault(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(SupportTicket::STATUSES)->default(SupportTicket::STATUS_OPEN),
            ])
            ->recordActions([
                ViewAction::make()->label('Abrir'),
            ]);
    }
}
