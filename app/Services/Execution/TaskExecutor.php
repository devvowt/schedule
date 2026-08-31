<?php

namespace App\Services\Execution;

use App\Models\ScheduledTask;

interface TaskExecutor
{
    public function execute(ScheduledTask $task): ExecutionResult;
}
