<?php

use App\Http\Controllers\Panel\ApiClientController;
use App\Http\Controllers\Panel\AuthController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\RunController;
use App\Http\Controllers\Panel\TaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Painel
|--------------------------------------------------------------------------
|
| Autenticação exclusivamente por código de uso único (OTP) enviado ao e-mail
| autorizado em PANEL_ALLOWED_EMAILS.
|
*/

Route::redirect('/', '/painel');

Route::prefix('painel')->name('panel.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'sendCode'])->name('login.send');
        Route::get('login/codigo', [AuthController::class, 'showCode'])->name('login.code');
        Route::post('login/codigo', [AuthController::class, 'verifyCode'])->name('login.verify');
    });

    Route::post('logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

    Route::middleware('auth')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('tarefas', [TaskController::class, 'index'])->name('tasks.index');
        Route::get('tarefas/nova', [TaskController::class, 'create'])->name('tasks.create');
        Route::post('tarefas', [TaskController::class, 'store'])->name('tasks.store');
        Route::get('tarefas/{task}', [TaskController::class, 'show'])->name('tasks.show');
        Route::get('tarefas/{task}/editar', [TaskController::class, 'edit'])->name('tasks.edit');
        Route::put('tarefas/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::delete('tarefas/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
        Route::post('tarefas/{task}/alternar', [TaskController::class, 'toggle'])->name('tasks.toggle');
        Route::post('tarefas/{task}/executar', [TaskController::class, 'run'])->name('tasks.run');

        Route::get('execucoes', [RunController::class, 'index'])->name('runs.index');
        Route::get('execucoes/{run}', [RunController::class, 'show'])->name('runs.show');

        Route::get('clientes', [ApiClientController::class, 'index'])->name('clients.index');
        Route::post('clientes', [ApiClientController::class, 'store'])->name('clients.store');
        Route::post('clientes/{client}/rotacionar', [ApiClientController::class, 'rotate'])->name('clients.rotate');
        Route::post('clientes/{client}/alternar', [ApiClientController::class, 'toggle'])->name('clients.toggle');
        Route::delete('clientes/{client}', [ApiClientController::class, 'destroy'])->name('clients.destroy');
    });
});
