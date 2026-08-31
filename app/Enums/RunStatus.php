<?php

namespace App\Enums;

enum RunStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
    case Timeout = 'timeout';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Queued => 'Na fila',
            self::Running => 'Executando',
            self::Success => 'Sucesso',
            self::Failed => 'Falhou',
            self::Timeout => 'Timeout',
            self::Skipped => 'Ignorada',
        };
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Success, self::Failed, self::Timeout, self::Skipped], true);
    }

    public function isFailure(): bool
    {
        return in_array($this, [self::Failed, self::Timeout], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Success => 'green',
            self::Failed, self::Timeout => 'red',
            self::Running => 'blue',
            self::Queued, self::Pending => 'gray',
            self::Skipped => 'amber',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
