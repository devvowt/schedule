<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskRunResource;
use App\Models\TaskRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskRunController extends Controller
{
    /**
     * Execuções recentes do store, de todas as tarefas.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = TaskRun::query()
            ->with('task')
            ->forStore($request->attributes->get('store_uuid'))
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return TaskRunResource::collection(
            $query->paginate(min((int) $request->integer('per_page', 25), 100))->withQueryString()
        );
    }

    public function show(Request $request, TaskRun $run): TaskRunResource
    {
        abort_unless($run->store_uuid === $request->attributes->get('store_uuid'), 404, 'Execução não encontrada.');

        return TaskRunResource::make($run->load('task'));
    }
}
