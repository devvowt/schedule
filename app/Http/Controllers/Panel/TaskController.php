<?php

namespace App\Http\Controllers\Panel;

use App\Enums\ExecutionMode;
use App\Enums\RunTrigger;
use App\Enums\TaskType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\ScheduledTask;
use App\Services\Dispatching\TaskDispatcher;
use App\Services\Tasks\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly TaskDispatcher $dispatcher,
    ) {}

    public function index(Request $request): View
    {
        $query = ScheduledTask::query()->latest('id');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('execution_group', 'like', "%{$search}%")
                ->orWhere('store_uuid', $search));
        }

        if ($request->filled('mode')) {
            $query->where('execution_mode', $request->string('mode'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        return view('tasks.index', [
            'tasks' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'mode', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('tasks.form', [
            'task' => new ScheduledTask([
                'type' => TaskType::Http->value,
                'execution_mode' => ExecutionMode::Sequential->value,
                'execution_group' => config('scheduler.sequential.default_group'),
                'timezone' => config('scheduler.defaults.timezone'),
                'timeout' => config('scheduler.defaults.timeout'),
                'max_attempts' => config('scheduler.defaults.max_attempts'),
                'cron_expression' => '0 * * * *',
                'is_active' => true,
            ]),
            'groups' => $this->groups(),
        ]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $task = $this->tasks->create(
            $request->validated(),
            storeUuid: $request->filled('store_uuid') ? $request->string('store_uuid')->toString() : null,
            email: $request->user()->email,
        );

        return redirect()
            ->route('panel.tasks.show', $task)
            ->with('status', "Tarefa \"{$task->name}\" criada.");
    }

    public function show(ScheduledTask $task): View
    {
        return view('tasks.show', [
            'task' => $task,
            'runs' => $task->runs()->latest('id')->limit(20)->get(),
        ]);
    }

    public function edit(ScheduledTask $task): View
    {
        return view('tasks.form', ['task' => $task, 'groups' => $this->groups()]);
    }

    public function update(UpdateTaskRequest $request, ScheduledTask $task): RedirectResponse
    {
        $data = $request->validated();

        // Checkboxes ausentes no POST significam "desmarcado".
        $data['is_active'] = $request->boolean('is_active');

        $this->tasks->update($task, $data);

        if ($request->has('store_uuid')) {
            $task->forceFill([
                'store_uuid' => $request->filled('store_uuid') ? $request->string('store_uuid')->toString() : null,
            ])->save();
        }

        return redirect()
            ->route('panel.tasks.show', $task)
            ->with('status', 'Tarefa atualizada.');
    }

    public function destroy(ScheduledTask $task): RedirectResponse
    {
        $task->delete();

        return redirect()->route('panel.tasks.index')->with('status', "Tarefa \"{$task->name}\" removida.");
    }

    public function toggle(ScheduledTask $task): RedirectResponse
    {
        $task->forceFill(['is_active' => ! $task->is_active])->save();
        $task->refreshNextRun();

        return back()->with('status', $task->is_active ? 'Tarefa ativada.' : 'Tarefa pausada.');
    }

    public function run(ScheduledTask $task): RedirectResponse
    {
        $run = $this->dispatcher->dispatchNow($task, RunTrigger::Manual);

        return redirect()
            ->route('panel.runs.show', $run)
            ->with('status', 'Execução despachada.');
    }

    /**
     * Grupos já usados, para sugerir no formulário.
     *
     * @return array<int, string>
     */
    protected function groups(): array
    {
        return ScheduledTask::query()
            ->distinct()
            ->orderBy('execution_group')
            ->pluck('execution_group')
            ->filter()
            ->values()
            ->all();
    }
}
