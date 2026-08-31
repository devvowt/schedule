@extends('layouts.app')
@section('title', 'Tarefas')
@section('subtitle', $tasks->total().' tarefa(s) cadastrada(s)')

@section('actions')
    <a class="btn primary" href="{{ route('panel.tasks.create') }}">Nova tarefa</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-body" style="padding:14px 18px">
            <form method="GET" class="filters">
                <div class="field">
                    <label for="search">Buscar</label>
                    <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="nome, grupo ou store">
                </div>
                <div class="field">
                    <label for="mode">Modo</label>
                    <select id="mode" name="mode">
                        <option value="">Todos</option>
                        <option value="parallel" @selected(($filters['mode'] ?? '') === 'parallel')>Paralelo</option>
                        <option value="sequential" @selected(($filters['mode'] ?? '') === 'sequential')>Sequencial</option>
                    </select>
                </div>
                <div class="field">
                    <label for="status">Situação</label>
                    <select id="status" name="status">
                        <option value="">Todas</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Ativas</option>
                        <option value="paused" @selected(($filters['status'] ?? '') === 'paused')>Pausadas</option>
                    </select>
                </div>
                <button class="btn" type="submit">Filtrar</button>
                <a class="btn" href="{{ route('panel.tasks.index') }}">Limpar</a>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Tarefa</th>
                    <th>Alvo</th>
                    <th>Cron</th>
                    <th>Execução</th>
                    <th>Próxima</th>
                    <th>Último</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($tasks as $task)
                    <tr>
                        <td>
                            <a href="{{ route('panel.tasks.show', $task) }}"><strong>{{ $task->name }}</strong></a>
                            @unless ($task->is_active)
                                <span class="badge amber" style="margin-left:6px">pausada</span>
                            @endunless
                            @if ($task->store_uuid)
                                <div class="muted mono" style="font-size:11px">store {{ Str::limit($task->store_uuid, 13) }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge gray">{{ $task->type->value }}</span>
                            <span class="mono truncate muted" title="{{ $task->target() }}">{{ $task->target() }}</span>
                        </td>
                        <td class="mono">{{ $task->cron_expression }}</td>
                        <td>
                            @if ($task->isParallel())
                                <span class="badge blue">paralelo · octane</span>
                            @else
                                <span class="badge gray">fila · {{ $task->groupKey() }}</span>
                                <div class="muted" style="font-size:11px">
                                    ordem {{ $task->sequence_order }}
                                    @if ($task->stagger_minutes > 0) · +{{ $task->stagger_minutes }} min @endif
                                </div>
                            @endif
                        </td>
                        <td class="mono muted">{{ $task->next_run_at?->format('d/m H:i') ?? '—' }}</td>
                        <td>
                            @if ($task->last_status)
                                @include('partials.status', ['status' => \App\Enums\RunStatus::from($task->last_status)])
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td style="text-align:right;white-space:nowrap">
                            <form method="POST" action="{{ route('panel.tasks.run', $task) }}" style="display:inline">
                                @csrf
                                <button class="btn sm" type="submit">Executar</button>
                            </form>
                            <a class="btn sm" href="{{ route('panel.tasks.edit', $task) }}">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">Nenhuma tarefa encontrada.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($tasks->hasPages())
            <div class="pagination">{{ $tasks->links() }}</div>
        @endif
    </div>
@endsection
