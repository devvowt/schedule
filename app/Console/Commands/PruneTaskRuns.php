<?php

namespace App\Console\Commands;

use App\Models\TaskRun;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PruneTaskRuns extends Command
{
    protected $signature = 'tasks:prune-runs {--days= : Dias de retenção}';

    protected $description = 'Remove execuções antigas do histórico';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('scheduler.run_retention_days'));

        $deleted = TaskRun::where('created_at', '<', Carbon::now()->subDays($days))->delete();

        $this->info("{$deleted} execução(ões) removida(s) (retenção de {$days} dias).");

        return self::SUCCESS;
    }
}
