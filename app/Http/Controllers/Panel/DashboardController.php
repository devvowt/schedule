<?php

namespace App\Http\Controllers\Panel;

use App\Enums\ExecutionMode;
use App\Enums\RunStatus;
use App\Http\Controllers\Controller;
use App\Models\ScheduledTask;
use App\Models\TaskRun;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $since = Carbon::now()->subDay();

        return view('dashboard', [
            'totalTasks' => ScheduledTask::count(),
            'activeTasks' => ScheduledTask::active()->count(),
            'parallelTasks' => ScheduledTask::active()->where('execution_mode', ExecutionMode::Parallel)->count(),
            'sequentialTasks' => ScheduledTask::active()->where('execution_mode', ExecutionMode::Sequential)->count(),
            'runs24h' => TaskRun::where('created_at', '>=', $since)->count(),
            'failures24h' => TaskRun::where('created_at', '>=', $since)
                ->whereIn('status', [RunStatus::Failed->value, RunStatus::Timeout->value])->count(),
            'running' => TaskRun::where('status', RunStatus::Running->value)->count(),
            'queued' => TaskRun::where('status', RunStatus::Queued->value)->count(),
            'upcoming' => ScheduledTask::active()
                ->whereNotNull('next_run_at')
                ->orderBy('next_run_at')
                ->limit(8)
                ->get(),
            'recentRuns' => TaskRun::with('task')->latest('id')->limit(10)->get(),
            'failing' => ScheduledTask::active()
                ->where('consecutive_failures', '>', 0)
                ->orderByDesc('consecutive_failures')
                ->limit(5)
                ->get(),
        ]);
    }
}
