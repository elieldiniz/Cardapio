<?php

namespace App\Enums;

enum TicketStatus: string
{
    /** Waiting on the support team. */
    case Open = 'aberto';

    /** Waiting on the dono. */
    case Answered = 'respondido';

    case Closed = 'fechado';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aguardando suporte',
            self::Answered => 'Respondido',
            self::Closed => 'Fechado',
        };
    }

    /** Filament badge color. */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::Answered => 'success',
            self::Closed => 'gray',
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
