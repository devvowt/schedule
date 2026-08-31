<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Agendamento interno
|--------------------------------------------------------------------------
|
| O container "scheduler" roda `php artisan schedule:work`, que a cada minuto
| chama o comando abaixo. É ele quem lê a tabela scheduled_tasks, descobre o
| que vence naquele minuto e despacha para o Octane ou para a fila do grupo.
|
*/

Schedule::command('tasks:dispatch-due')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

Schedule::command('tasks:prune-runs')
    ->dailyAt('03:20');
