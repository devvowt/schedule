<?php

namespace App\Jobs;

use App\Enums\ExecutionMode;
use App\Enums\RunStatus;
use App\Models\TaskRun;
use App\Services\Execution\TaskRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Executa uma linha de task_runs. É o job usado pela fila sequencial e pelo
 * fallback do modo paralelo (quando o Octane não está disponível).
 */
class ExecuteTaskRun implements ShouldQueue
{
    use Queueable;

    /** Retentativas ficam a cargo do agendador, não do worker. */
    public int $tries = 1;

    public function __construct(public int $taskRunId) {}

    /**
     * Tarefas sequenciais do mesmo grupo não podem se sobrepor, mesmo que o
     * worker da fila seja escalado para mais de um processo.
     */
    public function middleware(): array
    {
        $run = TaskRun::find($this->taskRunId);

        if ($run === null || $run->execution_mode !== ExecutionMode::Sequential) {
            return [];
        }

        return [
            (new WithoutOverlapping('task-group:'.$run->execution_group))
                ->releaseAfter(10)
                ->expireAfter((int) config('scheduler.sequential.lock_seconds')),
        ];
    }

    public function handle(TaskRunner $runner): void
    {
        $run = TaskRun::with('task')->find($this->taskRunId);

        if ($run === null) {
            return;
        }

        if ($run->status->isFinished()) {
            return; // já executada — despacho duplicado
        }

        $runner->run($run);
    }

    public function failed(?Throwable $e): void
    {
        TaskRun::where('id', $this->taskRunId)
            ->whereIn('status', [RunStatus::Pending->value, RunStatus::Queued->value, RunStatus::Running->value])
            ->update([
                'status' => RunStatus::Failed->value,
                'error' => mb_strcut('Job falhou: '.($e?->getMessage() ?? 'motivo desconhecido'), 0, 1000),
                'finished_at' => Carbon::now(),
            ]);
    }
}
