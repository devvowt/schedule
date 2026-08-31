@extends('layouts.app')
@section('title', 'Visão geral')
@section('subtitle', 'Estado do agendador nas últimas 24 horas')

@section('actions')
    <a class="btn primary" href="{{ route('panel.tasks.create') }}">Nova tarefa</a>
@endsection

@section('content')
    <div class="grid cols-4">
        <div class="stat">
            <div class="label">Tarefas ativas</div>
            <div class="value">{{ $activeTasks }}</div>
            <div class="hint">{{ $totalTasks }} cadastradas no total</div>
        </div>
        <div class="stat">
            <div class="label">Execuções 24h</div>
            <div class="value">{{ $runs24h }}</div>
            <div class="hint">{{ $running }} rodando · {{ $queued }} na fila</div>
        </div>
        <div class="stat">
            <div class="label">Falhas 24h</div>
            <div class="value" style="color:{{ $failures24h > 0 ? 'var(--red)' : 'inherit' }}">{{ $failures24h }}</div>
            <div class="hint">{{ $runs24h > 0 ? number_format($failures24h / $runs24h * 100, 1, ',', '.').'% do período' : 'sem execuções' }}</div>
        </div>
        <div class="stat">
            <div class="label">Modo de execução</div>
            <div class="value">{{ $parallelTasks }} / {{ $sequentialTasks }}</div>
            <div class="hint">paralelas (Octane) / sequenciais (fila)</div>
        </div>
    </div>

    @if ($failing->isNotEmpty())
        <div class="card" style="margin-top:18px">
            <div class="card-head"><h2>Tarefas falhando em sequência</h2></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Tarefa</th><th>Falhas seguidas</th><th>Último status</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($failing as $task)
                        <tr>
                            <td><a href="{{ route('panel.tasks.show', $task) }}">{{ $task->name }}</a></td>
                            <td><span class="badge red">{{ $task->consecutive_failures }}×</span></td>
                            <td class="muted">{{ $task->last_run_at?->format('d/m H:i') ?? '—' }}</td>
                            <td style="text-align:right"><a class="btn sm" href="{{ route('panel.tasks.show', $task) }}">Detalhes</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="grid cols-2" style="margin-top:18px">
        <div class="card" style="margin-top:0">
            <div class="card-head">
                <h2>Próximas execuções</h2>
                <a class="btn sm" href="{{ route('panel.tasks.index') }}">Ver tarefas</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Quando</th><th>Tarefa</th><th>Modo</th></tr></thead>
                    <tbody>
                    @forelse ($upcoming as $task)
                        <tr>
                            <td class="mono">{{ $task->next_run_at?->format('d/m H:i') }}</td>
                            <td><a href="{{ route('panel.tasks.show', $task) }}">{{ $task->name }}</a></td>
                            <td>
                                <span class="badge {{ $task->isParallel() ? 'blue' : 'gray' }}">
                                    {{ $task->isParallel() ? 'paralelo' : $task->groupKey() }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">Nenhuma tarefa ativa agendada.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card" style="margin-top:0">
            <div class="card-head">
                <h2>Execuções recentes</h2>
                <a class="btn sm" href="{{ route('panel.runs.index') }}">Ver todas</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Quando</th><th>Tarefa</th><th>Status</th><th>Duração</th></tr></thead>
                    <tbody>
                    @forelse ($recentRuns as $run)
                        <tr>
                            <td class="mono">{{ $run->created_at->format('d/m H:i') }}</td>
                            <td><a href="{{ route('panel.runs.show', $run) }}">{{ $run->task?->name ?? '—' }}</a></td>
                            <td>@include('partials.status', ['status' => $run->status])</td>
                            <td class="mono muted">{{ $run->durationForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">Nada executado ainda.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
