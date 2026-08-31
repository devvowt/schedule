<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\TaskRun;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RunController extends Controller
{
    public function index(Request $request): View
    {
        $query = TaskRun::query()->with('task')->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('task')) {
            $query->whereHas('task', fn ($q) => $q->where('uuid', $request->string('task')));
        }

        return view('runs.index', [
            'runs' => $query->paginate(30)->withQueryString(),
            'filters' => $request->only(['status', 'task']),
        ]);
    }

    public function show(TaskRun $run): View
    {
        return view('runs.show', ['run' => $run->load('task')]);
    }
}
