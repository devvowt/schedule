<?php

use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskRunController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API pública do agendador
|--------------------------------------------------------------------------
|
| Autenticação por credenciais de cliente (cadastradas no painel):
|
|   X-Client-Id:     cid_...
|   X-Client-Secret: sk_...
|   X-Store-Uuid:    UUID da loja dona das tarefas
|
| O store não é cadastrado no painel — ele chega em cada chamada e delimita
| tudo o que o cliente enxerga e altera.
|
*/

Route::middleware(['api.client', 'throttle:api'])->prefix('v1')->group(function () {
    Route::get('tasks', [TaskController::class, 'index'])->name('api.tasks.index');
    Route::post('tasks', [TaskController::class, 'store'])->name('api.tasks.store');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->name('api.tasks.show');
    Route::match(['put', 'patch'], 'tasks/{task}', [TaskController::class, 'update'])->name('api.tasks.update');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('api.tasks.destroy');

    Route::post('tasks/{task}/run', [TaskController::class, 'run'])->name('api.tasks.run');
    Route::get('tasks/{task}/runs', [TaskController::class, 'runs'])->name('api.tasks.runs');

    Route::get('runs', [TaskRunController::class, 'index'])->name('api.runs.index');
    Route::get('runs/{run}', [TaskRunController::class, 'show'])->name('api.runs.show');
});

Route::get('health', fn () => response()->json([
    'service' => 'schedule-service',
    'status' => 'ok',
    'time' => now()->toIso8601String(),
]))->name('api.health');
