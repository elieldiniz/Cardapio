<?php

namespace App\Filament\Resources\Restaurants\Schemas;

use App\Models\Restaurant;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RestaurantInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Restaurante')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')->label('Nome'),
                        TextEntry::make('slug')->label('Cardápio')->url(fn (Restaurant $record) => $record->feedUrl(), true)->formatStateUsing(fn (string $state) => '/r/'.$state),
                        TextEntry::make('status.name')->label('Status')->badge()->color(fn (Restaurant $record) => $record->isSuspended() ? 'danger' : 'success'),
                        TextEntry::make('owner')->label('Dono')->state(fn (Restaurant $record) => $record->stripeEmail()),
                        TextEntry::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i'),
                        TextEntry::make('stripe_id')->label('Cliente Stripe')->placeholder('—'),
                    ]),
                Section::make('Plano e uso')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('plan.name')->label('Plano')->badge(),
                        TextEntry::make('dishes')->label('Pratos')->state(fn (Restaurant $record) => $record->dishes()->count().' / '.($record->plan?->dish_limit ?? '∞')),
                        TextEntry::make('categories')->label('Categorias')->state(fn (Restaurant $record) => $record->categories()->count()),
                        TextEntry::make('generationBalance.monthly_balance')->label('Saldo mensal')->default(0),
                        TextEntry::make('generationBalance.addon_balance')->label('Saldo avulso')->default(0),
                        TextEntry::make('generationBalance.renews_at')->label('Renova em')->date('d/m/Y')->placeholder('—'),
                    ]),
            ]);
    }
}
