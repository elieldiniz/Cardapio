<?php

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plano')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label('Nome')->required()->maxLength(60)->unique(ignoreRecord: true),
                        TextInput::make('price_cents')->label('Preço mensal (centavos)')->helperText('Ex.: 6900 = R$ 69,00')->numeric()->integer()->minValue(0)->required(),
                        TextInput::make('stripe_price_id')->label('Stripe price ID')->helperText('Preço recorrente mensal em BRL criado na Stripe (price_...).')->maxLength(255)->unique(ignoreRecord: true)->nullable(),
                        Toggle::make('is_active')->label('Ativo (à venda)')->default(true)->inline(false),
                    ]),
                Section::make('Limites e recursos')
                    ->columns(2)
                    ->schema([
                        TextInput::make('monthly_generations')->label('Gerações por mês')->helperText('Renova a cada fatura paga; não acumula.')->numeric()->integer()->minValue(0)->required(),
                        TextInput::make('initial_generations')->label('Gerações iniciais (uma vez)')->helperText('Concedidas no cadastro, não renovam.')->numeric()->integer()->minValue(0)->required(),
                        TextInput::make('dish_limit')->label('Limite de pratos')->helperText('Vazio = ilimitado.')->numeric()->integer()->minValue(1)->nullable(),
                        Select::make('metrics_level_id')->label('Nível de métricas')->relationship('metricsLevel', 'name')->required(),
                        Toggle::make('removes_branding')->label('Remove a marca "feito com"')->inline(false),
                    ]),
            ]);
    }
}
