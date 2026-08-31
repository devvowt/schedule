@extends('layouts.app')
@section('title', $task->name)
@section('subtitle', $task->description ?: 'Tarefa '.$task->uuid)

@section('actions')
    <form method="POST" action="{{ route('panel.tasks.run', $task) }}">
        @csrf
        <button class="btn" type="submit">Executar agora</button>
    </form>
    <form method="POST" action="{{ route('panel.tasks.toggle', $task) }}">
        @csrf
        <button class="btn" type="submit">{{ $task->is_active ? 'Pausar' : 'Ativar' }}</button>
    </form>
    <a class="btn primary" href="{{ route('panel.tasks.edit', $task) }}">Editar</a>
@endsection

@section('content')
    <div class="grid cols-2">
        <div class="card" style="margin-top:0">
            <div class="card-head"><h2>Configuração</h2></div>
            <div class="card-body">
                <dl class="kv">
                    <dt>Situação</dt>
                    <dd>
                        <span class="badge {{ $task->is_active ? 'green' : 'amber' }}">{{ $task->is_active ? 'ativa' : 'pausada' }}</span>
                    </dd>

                    <dt>Tipo</dt>
                    <dd>{{ $task->type->label() }}</dd>

                    <dt>Alvo</dt>
                    <dd class="mono">{{ $task->target() }}</dd>

                    @if ($task->type === \App\Enums\TaskType::Http && !empty($task->payload['headers']))
                        <dt>Headers</dt>
                        <dd class="mono">
                            @foreach ($task->payload['headers'] as $key => $value)
                                {{ $key }}: {{ Str::limit($value, 60) }}<br>
                            @endforeach
                        </dd>
                    @endif

                    <dt>Cron</dt>
                    <dd><span class="mono">{{ $task->cron_expression }}</span> <span class="muted">({{ $task->timezone }})</span></dd>

                    <dt>Próxima execução</dt>
                    <dd class="mono">{{ $task->next_run_at?->format('d/m/Y H:i') ?? '—' }}</dd>

                    <dt>Store</dt>
                    <dd class="mono">{{ $task->store_uuid ?? '— (cadastro pelo painel)' }}</dd>

                    <dt>Origem</dt>
                    <dd>{{ $task->created_via === 'api' ? 'API · '.($task->apiClient?->name ?? 'cliente removido') : 'Painel · '.($task->created_by_email ?? '—') }}</dd>
                </dl>
            </div>
        </div>

        <div class="card" style="margin-top:0">
            <div class="card-head"><h2>Execução</h2></div>
            <div class="card-body">
                <dl class="kv">
                    <dt>Modo</dt>
                    <dd>
                        @if ($task->isParallel())
                            <span class="badge blue">paralelo</span>
                            <div class="muted" style="margin-top:4px">
                                Despachada aos task workers do Octane; roda ao mesmo tempo que as demais.
                            </div>
                        @else
                            <span class="badge gray">sequencial</span>
                            <div class="muted" style="margin-top:4px">
                                Entra na fila do grupo <strong>{{ $task->groupKey() }}</strong>, na posição {{ $task->sequence_order }}.
                                @if ($task->stagger_minutes > 0)
                                    Começa {{ $task->stagger_minutes }} minuto(s) depois da tarefa anterior do grupo.
                                @else
                                    Começa assim que a tarefa anterior do grupo terminar.
                                @endif
                            </div>
                        @endif
                    </dd>

                    <dt>Timeout</dt>
                    <dd>{{ $task->timeout }} s</dd>

                    <dt>Tentativas</dt>
                    <dd>{{ $task->max_attempts }} (intervalo de {{ $task->retry_delay_seconds }} s)</dd>

                    <dt>Falhas seguidas</dt>
                    <dd>
                        @if ($task->consecutive_failures > 0)
                            <span class="badge red">{{ $task->consecutive_failures }}</span>
                        @else
                            <span class="badge green">0</span>
                        @endif
                    </dd>

                    <dt>Última execução</dt>
                    <dd>
                        {{ $task->last_run_at?->format('d/m/Y H:i') ?? '—' }}
                        @if ($task->last_status)
                            @include('partials.status', ['status' => \App\Enums\RunStatus::from($task->last_status)])
                        @endif
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2>Últimas execuções</h2>
            <a class="btn sm" href="{{ route('panel.runs.index', ['task' => $task->uuid]) }}">Ver todas</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Quando</th><th>Origem</th><th>Status</th><th>Duração</th><th>Retorno</th><th></th></tr></thead>
                <tbody>
                @forelse ($runs as $run)
                    <tr>
                        <td class="mono">{{ $run->created_at->format('d/m H:i:s') }}</td>
                        <td class="muted">{{ $run->trigger->label() }}{{ $run->attempt > 1 ? ' · tent. '.$run->attempt : '' }}</td>
                        <td>@include('partials.status', ['status' => $run->status])</td>
                        <td class="mono muted">{{ $run->durationForHumans() }}</td>
                        <td class="mono muted">{{ $run->http_status ?? ($run->exit_code !== null ? 'exit '.$run->exit_code : '—') }}</td>
                        <td style="text-align:right"><a class="btn sm" href="{{ route('panel.runs.show', $run) }}">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Sem execuções registradas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
            <div class="muted" style="font-size:12.5px">
                Remover a tarefa mantém o histórico de execuções, mas o agendamento para imediatamente.
            </div>
            <form method="POST" action="{{ route('panel.tasks.destroy', $task) }}"
                  onsubmit="return confirm('Remover a tarefa {{ $task->name }}?')">
                @csrf @method('DELETE')
                <button class="btn danger" type="submit">Remover tarefa</button>
            </form>
        </div>
    </div>
@endsection
