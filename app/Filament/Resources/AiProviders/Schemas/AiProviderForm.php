<?php

namespace App\Filament\Resources\AiProviders\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AiProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        $bound = array_keys(config('ai.providers'));

        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label('Nome')->required()->maxLength(80),
                Select::make('slug')
                    ->label('Integração')
                    ->helperText('Integrações disponíveis em config/ai.php. Adicionar um fornecedor novo é só implementar a integração e cadastrá-la aqui.')
                    ->options(array_combine($bound, $bound))
                    ->required()
                    ->unique(ignoreRecord: true),
                Toggle::make('is_active')->label('Ativo')->helperText('Um provedor inativo não pode mais ser escolhido em presets, mas continua no histórico.')->default(true)->inline(false),
            ]);
    }
}
