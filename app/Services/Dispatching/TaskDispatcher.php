<?php

namespace App\Services\Dispatching;

use App\Enums\ExecutionMode;
use App\Enums\RunStatus;
use App\Enums\RunTrigger;
use App\Jobs\ExecuteTaskRun;
use App\Models\ScheduledTask;
use App\Models\TaskRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Coração do agendador: descobre o que vence no minuto corrente, cria as linhas
 * de execução e entrega cada uma ao caminho certo — Octane (paralelo) ou fila
 * do grupo (sequencial).
 */
class TaskDispatcher
{
    public function __construct(
        private readonly ParallelDispatcher $parallel,
        private readonly SequentialDispatcher $sequential,
    ) {}

    public function dispatchDue(?Carbon $moment = null): DispatchReport
    {
        $moment = ($moment ?? Carbon::now())->copy()->startOfMinute();

        $due = $this->dueTasks($moment);

        $report = new DispatchReport(due: $due->count());

        if ($due->isEmpty()) {
            return $report;
        }

        [$parallelTasks, $sequentialTasks] = $due->partition(
            fn (ScheduledTask $task) => $task->execution_mode === ExecutionMode::Parallel
        );

        // --- Paralelo: tudo de uma vez, nos task workers do Octane ----------
        $parallelRuns = $this->createRuns($parallelTasks, $moment, $report);

        if ($parallelRuns->isNotEmpty()) {
            $report->parallel = $parallelRuns->count();
            $report->parallelDriver = $this->parallel->dispatch($parallelRuns);
        }

        // --- Sequencial: uma corrente por grupo ------------------------------
        $sequentialRuns = $this->createRuns($sequentialTasks, $moment, $report);

        $sequentialRuns
            ->groupBy(fn (TaskRun $run) => $run->execution_group)
            ->each(function (Collection $runs, string $group) use ($report) {
                $ordered = $runs->sortBy([
                    fn (TaskRun $a, TaskRun $b) => ($a->task?->sequence_order ?? 0) <=> ($b->task?->sequence_order ?? 0),
                    fn (TaskRun $a, TaskRun $b) => $a->id <=> $b->id,
                ])->values();

                $report->sequential += $ordered->count();
                $report->chains += $this->sequential->dispatchGroup($group, $ordered);
                $report->groups[] = $group;
            });

        return $report;
    }

    /**
     * Tarefas ativas cuja expressão cron vence no minuto informado.
     *
     * @return Collection<int, ScheduledTask>
     */
    public function dueTasks(Carbon $moment): Collection
    {
        return ScheduledTask::query()
            ->active()
            ->orderBy('execution_group')
            ->orderBy('sequence_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (ScheduledTask $task) => $task->isDueAt($moment))
            ->values();
    }

    /**
     * Execução avulsa, disparada pelo painel ou pela API.
     */
    public function dispatchNow(ScheduledTask $task, RunTrigger $trigger = RunTrigger::Manual): TaskRun
    {
        $run = TaskRun::create([
            'scheduled_task_id' => $task->id,
            'store_uuid' => $task->store_uuid,
            'status' => RunStatus::Queued,
            'trigger' => $trigger,
            'execution_mode' => $task->execution_mode,
            'execution_group' => $task->groupKey(),
            'scheduled_for' => Carbon::now(),
            'queued_at' => Carbon::now(),
            'attempt' => 1,
        ]);

        $run->setRelation('task', $task);

        if ($task->isParallel()) {
            $this->parallel->dispatch(collect([$run]));
        } else {
            ExecuteTaskRun::dispatch($run->id)->onQueue(config('scheduler.sequential.queue'));
        }

        return $run;
    }

    /**
     * Cria as linhas de task_runs, ignorando o que já foi despachado no mesmo
     * minuto (o agendador pode rodar duas vezes num restart, por exemplo).
     *
     * @param  Collection<int, ScheduledTask>  $tasks
     * @return Collection<int, TaskRun>
     */
    protected function createRuns(Collection $tasks, Carbon $moment, DispatchReport $report): Collection
    {
        return $tasks->map(function (ScheduledTask $task) use ($moment, $report) {
            $run = TaskRun::firstOrCreate(
                ['dedupe_key' => 'sched:'.$task->id.':'.$moment->format('YmdHi')],
                [
                    'scheduled_task_id' => $task->id,
                    'store_uuid' => $task->store_uuid,
                    'status' => RunStatus::Queued,
                    'trigger' => RunTrigger::Schedule,
                    'execution_mode' => $task->execution_mode,
                    'execution_group' => $task->groupKey(),
                    'scheduled_for' => $moment,
                    'queued_at' => Carbon::now(),
                    'attempt' => 1,
                ]
            );

            if (! $run->wasRecentlyCreated) {
                $report->skipped++;

                return null;
            }

            $run->setRelation('task', $task);
            $task->refreshNextRun();

            return $run;
        })->filter()->values();
    }
}
