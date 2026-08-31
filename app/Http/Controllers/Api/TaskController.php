<?php

namespace App\Http\Controllers\Api;

use App\Enums\RunTrigger;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Http\Resources\TaskRunResource;
use App\Models\ScheduledTask;
use App\Services\Dispatching\TaskDispatcher;
use App\Services\Tasks\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * CRUD de tarefas agendadas para clientes externos.
 *
 * Todo acesso é escopado pelo header X-Store-Uuid: um store nunca enxerga nem
 * altera tarefa de outro, mesmo usando as mesmas credenciais.
 */
class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly TaskDispatcher $dispatcher,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ScheduledTask::query()
            ->forStore($this->storeUuid($request))
            ->latest('id');

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('execution_mode')) {
            $query->where('execution_mode', $request->string('execution_mode'));
        }

        if ($request->filled('execution_group')) {
            $query->where('execution_group', $request->string('execution_group'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search').'%');
        }

        return TaskResource::collection(
            $query->paginate(min((int) $request->integer('per_page', 25), 100))->withQueryString()
        );
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $this->tasks->create(
            $request->validated(),
            storeUuid: $this->storeUuid($request),
            client: $request->attributes->get('api_client'),
        );

        return TaskResource::make($task)->response()->setStatusCode(201);
    }

    public function show(Request $request, ScheduledTask $task): TaskResource
    {
        $this->authorizeStore($request, $task);

        return TaskResource::make($task);
    }

    public function update(UpdateTaskRequest $request, ScheduledTask $task): TaskResource
    {
        $this->authorizeStore($request, $task);

        return TaskResource::make($this->tasks->update($task, $request->validated()));
    }

    public function destroy(Request $request, ScheduledTask $task): JsonResponse
    {
        $this->authorizeStore($request, $task);

        $task->delete();

        return response()->json(['message' => 'Tarefa removida.', 'uuid' => $task->uuid]);
    }

    /**
     * Dispara a tarefa agora, fora do agendamento.
     */
    public function run(Request $request, ScheduledTask $task): JsonResponse
    {
        $this->authorizeStore($request, $task);

        $run = $this->dispatcher->dispatchNow($task, RunTrigger::Api);

        return TaskRunResource::make($run)->response()->setStatusCode(202);
    }

    /**
     * Histórico de execuções da tarefa.
     */
    public function runs(Request $request, ScheduledTask $task): AnonymousResourceCollection
    {
        $this->authorizeStore($request, $task);

        return TaskRunResource::collection(
            $task->runs()->latest('id')->paginate(min((int) $request->integer('per_page', 25), 100))
        );
    }

    protected function storeUuid(Request $request): ?string
    {
        return $request->attributes->get('store_uuid');
    }

    /**
     * Tarefa de outro store responde 404 — não confirmamos sequer a existência.
     */
    protected function authorizeStore(Request $request, ScheduledTask $task): void
    {
        abort_unless($task->store_uuid === $this->storeUuid($request), 404, 'Tarefa não encontrada.');
    }
}
