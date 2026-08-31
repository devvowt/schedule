@extends('layouts.app')
@section('title', 'Execuções')
@section('subtitle', $runs->total().' execução(ões) registradas')

@section('content')
    <div class="card">
        <div class="card-body" style="padding:14px 18px">
            <form method="GET" class="filters">
                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">Todos</option>
                        @foreach (\App\Enums\RunStatus::cases() as $case)
                            <option value="{{ $case->value }}" @selected(($filters['status'] ?? '') === $case->value)>{{ $case->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="min-width:260px">
                    <label for="task">Tarefa (UUID)</label>
                    <input type="text" id="task" name="task" value="{{ $filters['task'] ?? '' }}" class="mono">
                </div>
                <button class="btn" type="submit">Filtrar</button>
                <a class="btn" href="{{ route('panel.runs.index') }}">Limpar</a>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Quando</th><th>Tarefa</th><th>Modo</th><th>Origem</th><th>Status</th><th>Duração</th><th>Retorno</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($runs as $run)
                    <tr>
                        <td class="mono">{{ $run->created_at->format('d/m H:i:s') }}</td>
                        <td>
                            @if ($run->task)
                                <a href="{{ route('panel.tasks.show', $run->task) }}">{{ $run->task->name }}</a>
                            @else
                                <span class="muted">tarefa removida</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $run->execution_mode?->value === 'parallel' ? 'blue' : 'gray' }}">
                                {{ $run->execution_mode?->value === 'parallel' ? 'paralelo' : ($run->execution_group ?: 'fila') }}
                            </span>
                        </td>
                        <td class="muted">{{ $run->trigger->label() }}</td>
                        <td>@include('partials.status', ['status' => $run->status])</td>
                        <td class="mono muted">{{ $run->durationForHumans() }}</td>
                        <td class="mono muted">{{ $run->http_status ?? ($run->exit_code !== null ? 'exit '.$run->exit_code : '—') }}</td>
                        <td style="text-align:right"><a class="btn sm" href="{{ route('panel.runs.show', $run) }}">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">Nenhuma execução encontrada.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($runs->hasPages())
            <div class="pagination">{{ $runs->links() }}</div>
        @endif
    </div>
@endsection
