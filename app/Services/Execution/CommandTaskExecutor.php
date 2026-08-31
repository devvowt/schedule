<?php

namespace App\Services\Execution;

use App\Models\ScheduledTask;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Executa um comando de shell dentro do container. A allowlist limita quais
 * binários podem ser invocados — importante porque a API é aberta a clientes
 * externos.
 */
class CommandTaskExecutor implements TaskExecutor
{
    public function execute(ScheduledTask $task): ExecutionResult
    {
        $command = trim((string) ($task->payload['command'] ?? ''));

        if ($command === '') {
            return ExecutionResult::failed('A tarefa não possui comando configurado.');
        }

        if (! static::isAllowed($command)) {
            return ExecutionResult::failed(
                'Comando bloqueado pela allowlist: "'.static::binaryOf($command).'".'
            );
        }

        try {
            $result = Process::path((string) config('scheduler.commands.working_directory'))
                ->timeout($task->timeout)
                ->env(['SCHEDULED_TASK_UUID' => $task->uuid])
                ->run($this->normalize($command));
        } catch (ProcessTimedOutException $e) {
            return ExecutionResult::timeout("Timeout de {$task->timeout}s ao executar o comando.");
        } catch (Throwable $e) {
            return ExecutionResult::failed('Erro ao iniciar o processo: '.$e->getMessage());
        }

        $output = trim($result->output()."\n".$result->errorOutput());

        return $result->successful()
            ? ExecutionResult::success($output, $result->exitCode())
            : ExecutionResult::failed(
                'Comando terminou com código '.$result->exitCode().'.',
                $output,
                $result->exitCode()
            );
    }

    /**
     * "artisan foo:bar" vira "php artisan foo:bar" para facilitar o cadastro.
     */
    protected function normalize(string $command): string
    {
        return str_starts_with($command, 'artisan ')
            ? 'php '.$command
            : $command;
    }

    public static function isAllowed(string $command): bool
    {
        if (! config('scheduler.commands.allowlist_enabled')) {
            return true;
        }

        return in_array(static::binaryOf($command), config('scheduler.commands.allowlist', []), true);
    }

    public static function binaryOf(string $command): string
    {
        $first = preg_split('/\s+/', trim($command))[0] ?? '';

        return basename($first);
    }
}
