<?php

namespace App\Services\Execution;

use App\Enums\TaskType;
use App\Models\ScheduledTask;
use InvalidArgumentException;

class ExecutorFactory
{
    public function make(ScheduledTask $task): TaskExecutor
    {
        return match ($task->type) {
            TaskType::Http => app(HttpTaskExecutor::class),
            TaskType::Command => app(CommandTaskExecutor::class),
            default => throw new InvalidArgumentException("Tipo de tarefa não suportado: {$task->type->value}."),
        };
    }
}
