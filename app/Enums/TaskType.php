<?php

namespace App\Enums;

enum TaskType: string
{
    case Http = 'http';
    case Command = 'command';

    public function label(): string
    {
        return match ($this) {
            self::Http => 'Requisição HTTP',
            self::Command => 'Comando de shell',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
