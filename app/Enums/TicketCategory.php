<?php

namespace App\Enums;

enum TicketCategory: string
{
    case Question = 'duvida';
    case Problem = 'problema';
    case Billing = 'cobranca';
    case Video = 'video';
    case Suggestion = 'sugestao';
    case Other = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::Question => 'Dúvida',
            self::Problem => 'Problema técnico',
            self::Billing => 'Cobrança / assinatura',
            self::Video => 'Vídeos e IA',
            self::Suggestion => 'Sugestão',
            self::Other => 'Outro',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
