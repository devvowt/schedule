<?php

namespace App\Console\Commands;

use App\Services\Dispatching\TaskDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DispatchDueTasks extends Command
{
    protected $signature = 'tasks:dispatch-due
                            {--at= : Minuto a avaliar (Y-m-d H:i), útil para testes}
                            {--dry-run : Apenas lista o que seria despachado}';

    protected $description = 'Despacha as tarefas agendadas que vencem no minuto corrente';

    public function handle(TaskDispatcher $dispatcher): int
    {
        $moment = $this->option('at')
            ? Carbon::parse($this->option('at'))
            : Carbon::now();

        if ($this->option('dry-run')) {
            $due = $dispatcher->dueTasks($moment->copy()->startOfMinute());

            $this->table(
                ['UUID', 'Nome', 'Modo', 'Grupo', 'Ordem', 'Defasagem'],
                $due->map(fn ($t) => [
                    $t->uuid,
                    $t->name,
                    $t->execution_mode->value,
                    $t->groupKey(),
                    $t->sequence_order,
                    $t->stagger_minutes.' min',
                ])->all()
            );

            $this->info("{$due->count()} tarefa(s) venceriam em {$moment->format('d/m/Y H:i')}.");

            return self::SUCCESS;
        }

        $report = $dispatcher->dispatchDue($moment);

        if ($report->due === 0) {
            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d tarefa(s) despachada(s) — %d em paralelo (%s), %d em %d corrente(s) sequencial(is)%s.',
            $report->total(),
            $report->parallel,
            $report->parallelDriver ?? '—',
            $report->sequential,
            $report->chains,
            $report->skipped > 0 ? ", {$report->skipped} ignorada(s) por duplicidade" : ''
        ));

        return self::SUCCESS;
    }
}
