<?php

namespace App\Services\Dispatching;

use App\Jobs\ExecuteTaskRun;
use App\Models\TaskRun;
use App\Services\Execution\TaskRunner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Octane\Swoole\SwooleHttpTaskDispatcher;
use Throwable;

/**
 * Modo paralelo.
 *
 * As execuções são enviadas aos task workers do Octane (Swoole), que as rodam
 * simultaneamente — o agendador não espera nenhuma delas. O dispatcher HTTP do
 * Octane funciona a partir de qualquer processo (CLI, worker, web), bastando
 * apontar para o container do servidor.
 *
 * Se o Octane não responder, as execuções caem para a fila "parallel", que roda
 * com vários workers concorrentes. Nada é perdido.
 */
class ParallelDispatcher
{
    /**
     * @param  Collection<int, TaskRun>  $runs
     * @return string  driver efetivamente usado ("octane" ou "queue")
     */
    public function dispatch(Collection $runs): string
    {
        if ($runs->isEmpty()) {
            return config('scheduler.parallel.driver');
        }

        if (config('scheduler.parallel.driver') === 'octane') {
            try {
                $this->dispatchToOctane($runs);

                return 'octane';
            } catch (Throwable $e) {
                Log::warning('Octane indisponível para execução paralela; usando a fila como fallback.', [
                    'exception' => $e->getMessage(),
                    'runs' => $runs->count(),
                ]);
            }
        }

        $this->dispatchToQueue($runs);

        return 'queue';
    }

    /**
     * @param  Collection<int, TaskRun>  $runs
     */
    protected function dispatchToOctane(Collection $runs): void
    {
        $dispatcher = $this->octaneDispatcher();

        $runs->chunk((int) config('scheduler.parallel.chunk'))->each(function (Collection $chunk) use ($dispatcher) {
            $tasks = $chunk->mapWithKeys(function (TaskRun $run) {
                // A closure é serializada e roda dentro de um task worker do
                // Octane; por isso captura apenas o id, nunca o modelo.
                $runId = $run->id;

                return ['run-'.$runId => static function () use ($runId) {
                    $fresh = TaskRun::with('task')->find($runId);

                    if ($fresh === null || $fresh->status->isFinished()) {
                        return null;
                    }

                    return app(TaskRunner::class)->run($fresh)->status->value;
                }];
            })->all();

            $dispatcher->dispatch($tasks);
        });
    }

    protected function octaneDispatcher(): SwooleHttpTaskDispatcher
    {
        return new SwooleHttpTaskDispatcher(
            (string) config('scheduler.parallel.octane.host'),
            (string) config('scheduler.parallel.octane.port'),
            new ThrowingTaskDispatcher,
        );
    }

    /**
     * @param  Collection<int, TaskRun>  $runs
     */
    protected function dispatchToQueue(Collection $runs): void
    {
        $queue = (string) config('scheduler.parallel.queue');

        $runs->each(fn (TaskRun $run) => ExecuteTaskRun::dispatch($run->id)->onQueue($queue));
    }
}
