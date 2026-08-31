<?php

namespace App\Enums;

enum RunTrigger: string
{
    case Schedule = 'schedule';
    case Manual = 'manual';
    case Api = 'api';

    public function label(): string
    {
        return match ($this) {
            self::Schedule => 'Agendamento',
            self::Manual => 'Manual (painel)',
            self::Api => 'API',
        };
    }
}
