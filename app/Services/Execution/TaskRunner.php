<?php

namespace App\Services\Execution;

use App\Enums\ExecutionMode;
use App\Enums\RunStatus;
use App\Jobs\ExecuteTaskRun;
use App\Models\ScheduledTask;
use App\Models\TaskRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ponto único onde uma execução sai de "queued" e vira resultado gravado.
 * Tanto o worker de fila quanto os task workers do Octane passam por aqui.
 */
class TaskRunner
{
    public function __construct(private readonly ExecutorFactory $factory) {}

    public function run(TaskRun $run): TaskRun
    {
        $task = $run->task;

        if ($task === null) {
            $run->forceFill([
                'status' => RunStatus::Skipped,
                'error' => 'Tarefa removida antes da execução.',
                'finished_at' => Carbon::now(),
            ])->save();

            return $run;
        }

        if (! $task->is_active) {
            $run->forceFill([
                'status' => RunStatus::Skipped,
                'error' => 'Tarefa inativa no momento da execução.',
                'finished_at' => Carbon::now(),
            ])->save();

            return $run;
        }

        $startedAt = Carbon::now();

        $run->forceFill([
            'status' => RunStatus::Running,
            'started_at' => $startedAt,
        ])->save();

        $startedMicro = microtime(true);

        try {
            $result = $this->factory->make($task)->execute($task);
        } catch (Throwable $e) {
            Log::error('Falha não tratada ao executar tarefa agendada.', [
                'task' => $task->uuid,
                'run' => $run->uuid,
                'exception' => $e->getMessage(),
            ]);

            $result = ExecutionResult::failed(get_class($e).': '.$e->getMessage());
        }

        $finishedAt = Carbon::now();
        $limit = (int) config('scheduler.output_limit');

        $run->forceFill([
            'status' => $result->status,
            'output' => $result->output === null ? null : mb_strcut($result->output, 0, $limit),
            'error' => $result->error === null ? null : mb_strcut($result->error, 0, 1000),
            'exit_code' => $result->exitCode,
            'http_status' => $result->httpStatus,
            'finished_at' => $finishedAt,
            'duration_ms' => (int) round((microtime(true) - $startedMicro) * 1000),
        ])->save();

        $task->forceFill([
            'last_run_at' => $finishedAt,
            'last_status' => $result->status->value,
            'consecutive_failures' => $result->isSuccess() ? 0 : $task->consecutive_failures + 1,
            'next_run_at' => $task->nextRunAt($finishedAt),
        ])->saveQuietly();

        Log::withContext(['task' => $task->uuid, 'run' => $run->uuid])->log(
            $result->isSuccess() ? 'info' : 'warning',
            "Tarefa \"{$task->name}\" finalizada com status {$result->status->value}.",
            ['duration_ms' => $run->duration_ms, 'mode' => $run->execution_mode?->value]
        );

        $this->scheduleRetryIfNeeded($run, $task);

        return $run;
    }

    /**
     * Falhou e ainda restam tentativas? Uma nova execução é criada com atraso,
     * para que cada tentativa fique registrada separadamente no histórico.
     */
    protected function scheduleRetryIfNeeded(TaskRun $run, ScheduledTask $task): void
    {
        if (! $run->status->isFailure() || $run->attempt >= $task->max_attempts) {
            return;
        }

        $retry = TaskRun::create([
            'scheduled_task_id' => $task->id,
            'store_uuid' => $task->store_uuid,
            'status' => RunStatus::Queued,
            'trigger' => $run->trigger,
            'execution_mode' => $run->execution_mode,
            'execution_group' => $run->execution_group,
            'scheduled_for' => Carbon::now()->addSeconds($task->retry_delay_seconds),
            'queued_at' => Carbon::now(),
            'attempt' => $run->attempt + 1,
        ]);

        $queue = $run->execution_mode === ExecutionMode::Parallel
            ? config('scheduler.parallel.queue')
            : config('scheduler.sequential.queue');

        ExecuteTaskRun::dispatch($retry->id)
            ->onQueue($queue)
            ->delay(Carbon::now()->addSeconds($task->retry_delay_seconds));

        Log::info("Retentativa {$retry->attempt} agendada para a tarefa \"{$task->name}\".", [
            'task' => $task->uuid,
            'run' => $retry->uuid,
        ]);
    }
}
