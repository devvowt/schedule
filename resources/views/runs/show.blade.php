@extends('layouts.app')
@section('title', 'Execução')
@section('subtitle', $run->uuid)

@section('actions')
    @if ($run->task)
        <a class="btn" href="{{ route('panel.tasks.show', $run->task) }}">Ver tarefa</a>
    @endif
@endsection

@section('content')
    <div class="card" style="margin-top:0">
        <div class="card-head">
            <h2>{{ $run->task?->name ?? 'Tarefa removida' }}</h2>
            @include('partials.status', ['status' => $run->status])
        </div>
        <div class="card-body">
            <dl class="kv">
                <dt>Origem</dt><dd>{{ $run->trigger->label() }}</dd>
                <dt>Tentativa</dt><dd>{{ $run->attempt }}</dd>
                <dt>Modo</dt>
                <dd>
                    @if ($run->execution_mode?->value === 'parallel')
                        <span class="badge blue">paralelo · octane</span>
                    @else
                        <span class="badge gray">sequencial · grupo {{ $run->execution_group ?: '—' }}</span>
                    @endif
                </dd>
                <dt>Agendada para</dt><dd class="mono">{{ $run->scheduled_for?->format('d/m/Y H:i:s') ?? '—' }}</dd>
                <dt>Início</dt><dd class="mono">{{ $run->started_at?->format('d/m/Y H:i:s') ?? '—' }}</dd>
                <dt>Fim</dt><dd class="mono">{{ $run->finished_at?->format('d/m/Y H:i:s') ?? '—' }}</dd>
                <dt>Duração</dt><dd class="mono">{{ $run->durationForHumans() }}</dd>
                @if ($run->http_status)
                    <dt>Status HTTP</dt><dd class="mono">{{ $run->http_status }}</dd>
                @endif
                @if ($run->exit_code !== null)
                    <dt>Código de saída</dt><dd class="mono">{{ $run->exit_code }}</dd>
                @endif
                <dt>Store</dt><dd class="mono">{{ $run->store_uuid ?? '—' }}</dd>
            </dl>
        </div>
    </div>

    @if ($run->error)
        <div class="card">
            <div class="card-head"><h2>Erro</h2></div>
            <div class="card-body"><div class="alert error" style="margin:0">{{ $run->error }}</div></div>
        </div>
    @endif

    <div class="card">
        <div class="card-head"><h2>Saída</h2></div>
        <div class="card-body">
            @if (filled($run->output))
                <pre class="output">{{ $run->output }}</pre>
            @else
                <div class="muted">Sem saída registrada.</div>
            @endif
        </div>
    </div>
@endsection
