<?php

namespace App\Enums;

enum ExecutionMode: string
{
    /** Roda simultaneamente com as demais, nos task workers do Octane. */
    case Parallel = 'parallel';

    /** Entra na fila do grupo e espera a tarefa anterior terminar. */
    case Sequential = 'sequential';

    public function label(): string
    {
        return match ($this) {
            self::Parallel => 'Paralelo (Octane)',
            self::Sequential => 'Sequencial (fila)',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
