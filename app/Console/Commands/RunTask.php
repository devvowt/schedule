<?php

namespace App\Console\Commands;

use App\Enums\RunTrigger;
use App\Models\ScheduledTask;
use App\Services\Dispatching\TaskDispatcher;
use App\Services\Execution\TaskRunner;
use Illuminate\Console\Command;

class RunTask extends Command
{
    protected $signature = 'tasks:run
                            {task : UUID da tarefa}
                            {--sync : Executa no processo atual em vez de despachar}';

    protected $description = 'Dispara manualmente uma tarefa agendada';

    public function handle(TaskDispatcher $dispatcher, TaskRunner $runner): int
    {
        $task = ScheduledTask::where('uuid', $this->argument('task'))->first();

        if ($task === null) {
            $this->error('Tarefa não encontrada.');

            return self::FAILURE;
        }

        $run = $dispatcher->dispatchNow($task, RunTrigger::Manual);

        if ($this->option('sync')) {
            $run = $runner->run($run);

            $this->line("Status: <options=bold>{$run->status->value}</> em {$run->durationForHumans()}");

            if ($run->error) {
                $this->error($run->error);
            }

            if ($run->output) {
                $this->line(mb_strcut($run->output, 0, 2000));
            }

            return $run->status->isFailure() ? self::FAILURE : self::SUCCESS;
        }

        $this->info("Execução {$run->uuid} despachada ({$task->execution_mode->value}).");

        return self::SUCCESS;
    }
}
