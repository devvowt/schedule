@php
    $editing = $task->exists;
    $payload = $task->payload ?? [];
    $headersRaw = collect($payload['headers'] ?? [])->map(fn ($v, $k) => "$k: $v")->implode("\n");
    $currentType = old('type', $task->type?->value ?? 'http');
    $currentMode = old('execution_mode', $task->execution_mode?->value ?? 'sequential');
@endphp

@extends('layouts.app')
@section('title', $editing ? 'Editar tarefa' : 'Nova tarefa')
@section('subtitle', $editing ? $task->uuid : 'Cadastro de tarefa agendada')

@section('content')
<form method="POST" action="{{ $editing ? route('panel.tasks.update', $task) : route('panel.tasks.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="grid cols-2">
        <div>
            <fieldset>
                <legend>Identificação</legend>

                <div class="field">
                    <label for="name">Nome</label>
                    <input type="text" id="name" name="name" required value="{{ old('name', $task->name) }}"
                           placeholder="Sincronizar pedidos">
                    @error('name')<div class="error">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="description">Descrição</label>
                    <input type="text" id="description" name="description" value="{{ old('description', $task->description) }}"
                           placeholder="Opcional">
                    @error('description')<div class="error">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="store_uuid">Store UUID</label>
                    <input type="text" id="store_uuid" name="store_uuid" value="{{ old('store_uuid', $task->store_uuid) }}"
                           placeholder="deixe vazio para tarefa interna do painel">
                    <div class="help">
                        Identifica a loja dona da tarefa. Cadastros feitos pela API preenchem isso
                        automaticamente com o header <span class="mono">X-Store-Uuid</span>.
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>O que executar</legend>

                <div class="field">
                    <label for="type">Tipo</label>
                    <select id="type" name="type" onchange="toggleType(this.value)">
                        <option value="http" @selected($currentType === 'http')>Requisição HTTP (chamar uma URL)</option>
                        <option value="command" @selected($currentType === 'command')>Comando de shell</option>
                    </select>
                    @error('type')<div class="error">{{ $message }}</div>@enderror
                </div>

                <div id="block-http" style="display:{{ $currentType === 'http' ? 'block' : 'none' }}">
                    <div class="field">
                        <label for="url">URL</label>
                        <input type="text" id="url" name="url" value="{{ old('url', $payload['url'] ?? '') }}"
                               placeholder="https://api.exemplo.com.br/jobs/sincronizar">
                        @error('url')<div class="error">{{ $message }}</div>@enderror
                    </div>

                    <div class="field">
                        <label for="method">Método</label>
                        <select id="method" name="method">
                            @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD'] as $m)
                                <option value="{{ $m }}" @selected(old('method', $payload['method'] ?? 'GET') === $m)>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="headers_raw">Headers</label>
                        <textarea id="headers_raw" name="headers_raw"
                                  placeholder="Authorization: Bearer xxx&#10;Content-Type: application/json">{{ old('headers_raw', $headersRaw) }}</textarea>
                        <div class="help">Um por linha, no formato <span class="mono">Chave: valor</span>.</div>
                    </div>

                    <div class="field">
                        <label for="body">Corpo</label>
                        <textarea id="body" name="body" placeholder='{"loja":123}'>{{ old('body', is_array($payload['body'] ?? null) ? json_encode($payload['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ($payload['body'] ?? '')) }}</textarea>
                    </div>

                    <div class="field">
                        <label for="expected_status">Status considerados sucesso</label>
                        <input type="text" id="expected_status" name="expected_status"
                               value="{{ old('expected_status', implode(', ', $payload['expected_status'] ?? [])) }}"
                               placeholder="vazio = qualquer 2xx">
                    </div>

                    <div class="field">
                        <label class="check">
                            <input type="hidden" name="verify_ssl" value="0">
                            <input type="checkbox" name="verify_ssl" value="1"
                                   @checked(old('verify_ssl', $payload['verify_ssl'] ?? true))>
                            Validar certificado SSL
                        </label>
                    </div>
                </div>

                <div id="block-command" style="display:{{ $currentType === 'command' ? 'block' : 'none' }}">
                    <div class="field">
                        <label for="command">Comando</label>
                        <input type="text" id="command" name="command" value="{{ old('command', $payload['command'] ?? '') }}"
                               placeholder="artisan fila:processar --loja=123">
                        @error('command')<div class="error">{{ $message }}</div>@enderror
                        <div class="help">
                            Executado dentro do container.
                            @if (config('scheduler.commands.allowlist_enabled'))
                                Binários liberados: <span class="mono">{{ implode(', ', config('scheduler.commands.allowlist')) }}</span>.
                            @else
                                <strong>Allowlist desativada</strong> — qualquer binário é aceito.
                            @endif
                            Prefixe com <span class="mono">artisan</span> para comandos do próprio serviço.
                        </div>
                    </div>
                </div>
            </fieldset>
        </div>

        <div>
            <fieldset>
                <legend>Quando executar</legend>

                <div class="field">
                    <label for="cron_expression">Expressão cron</label>
                    <input type="text" id="cron_expression" name="cron_expression" required
                           value="{{ old('cron_expression', $task->cron_expression) }}" class="mono" placeholder="0 * * * *">
                    @error('cron_expression')<div class="error">{{ $message }}</div>@enderror
                    <div class="help" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">
                        @foreach ([
                            '* * * * *' => 'a cada minuto',
                            '*/5 * * * *' => 'a cada 5 min',
                            '*/30 * * * *' => 'a cada 30 min',
                            '0 * * * *' => 'de hora em hora',
                            '0 3 * * *' => 'todo dia 03:00',
                            '0 8 * * 1' => 'segundas 08:00',
                        ] as $expr => $label)
                            <button type="button" class="btn sm" onclick="document.getElementById('cron_expression').value='{{ $expr }}'">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="field">
                    <label for="timezone">Fuso horário</label>
                    <input type="text" id="timezone" name="timezone" value="{{ old('timezone', $task->timezone ?? config('scheduler.defaults.timezone')) }}">
                    @error('timezone')<div class="error">{{ $message }}</div>@enderror
                </div>
            </fieldset>

            <fieldset>
                <legend>Como executar</legend>

                <div class="field">
                    <label for="execution_mode">Modo</label>
                    <select id="execution_mode" name="execution_mode" onchange="toggleMode(this.value)">
                        <option value="sequential" @selected($currentMode === 'sequential')>Sequencial — entra na fila do grupo</option>
                        <option value="parallel" @selected($currentMode === 'parallel')>Paralelo — roda no Octane junto com as demais</option>
                    </select>
                    @error('execution_mode')<div class="error">{{ $message }}</div>@enderror
                </div>

                <div id="block-sequential" style="display:{{ $currentMode === 'sequential' ? 'block' : 'none' }}">
                    <div class="alert info" style="font-size:12.5px">
                        Cinco tarefas no mesmo horário e no mesmo grupo não sobem juntas: elas rodam
                        uma após a outra, na ordem abaixo.
                    </div>

                    <div class="field">
                        <label for="execution_group">Grupo</label>
                        <input type="text" id="execution_group" name="execution_group" list="grupos"
                               value="{{ old('execution_group', $task->execution_group ?? config('scheduler.sequential.default_group')) }}">
                        <datalist id="grupos">
                            @foreach ($groups as $g)<option value="{{ $g }}">@endforeach
                        </datalist>
                        @error('execution_group')<div class="error">{{ $message }}</div>@enderror
                        <div class="help">Tarefas de grupos diferentes rodam em paralelo entre si.</div>
                    </div>

                    <div class="grid cols-2" style="gap:12px">
                        <div class="field">
                            <label for="sequence_order">Ordem no grupo</label>
                            <input type="number" id="sequence_order" name="sequence_order" min="0" max="65535"
                                   value="{{ old('sequence_order', $task->sequence_order ?? 0) }}">
                            <div class="help">Menor roda primeiro.</div>
                        </div>

                        <div class="field">
                            <label for="stagger_minutes">Defasagem (min)</label>
                            <input type="number" id="stagger_minutes" name="stagger_minutes" min="0" max="1440"
                                   value="{{ old('stagger_minutes', $task->stagger_minutes ?? 0) }}">
                            <div class="help"><strong>0</strong> = começa assim que a anterior terminar.</div>
                        </div>
                    </div>
                </div>

                <div id="block-parallel" style="display:{{ $currentMode === 'parallel' ? 'block' : 'none' }}">
                    <div class="alert info" style="font-size:12.5px">
                        A execução é enviada aos task workers do Octane e roda ao mesmo tempo que as
                        demais tarefas do horário. Se o Octane estiver fora do ar, o agendador cai
                        automaticamente para a fila <span class="mono">{{ config('scheduler.parallel.queue') }}</span>.
                    </div>
                </div>

                <div class="grid cols-3" style="gap:12px">
                    <div class="field">
                        <label for="timeout">Timeout (s)</label>
                        <input type="number" id="timeout" name="timeout" min="1" max="{{ config('scheduler.max_timeout') }}"
                               value="{{ old('timeout', $task->timeout ?? config('scheduler.defaults.timeout')) }}">
                        @error('timeout')<div class="error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="max_attempts">Tentativas</label>
                        <input type="number" id="max_attempts" name="max_attempts" min="1" max="5"
                               value="{{ old('max_attempts', $task->max_attempts ?? 1) }}">
                    </div>
                    <div class="field">
                        <label for="retry_delay_seconds">Intervalo (s)</label>
                        <input type="number" id="retry_delay_seconds" name="retry_delay_seconds" min="0" max="3600"
                               value="{{ old('retry_delay_seconds', $task->retry_delay_seconds ?? 60) }}">
                    </div>
                </div>

                <div class="field">
                    <label for="notify_email">E-mail para falhas</label>
                    <input type="email" id="notify_email" name="notify_email" value="{{ old('notify_email', $task->notify_email) }}"
                           placeholder="Opcional">
                </div>

                <div class="field">
                    <label class="check">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $task->is_active ?? true))>
                        Tarefa ativa
                    </label>
                </div>
            </fieldset>
        </div>
    </div>

    <div class="actions">
        <button class="btn primary" type="submit">{{ $editing ? 'Salvar alterações' : 'Criar tarefa' }}</button>
        <a class="btn" href="{{ $editing ? route('panel.tasks.show', $task) : route('panel.tasks.index') }}">Cancelar</a>
    </div>
</form>

<script>
    function toggleType(value) {
        document.getElementById('block-http').style.display = value === 'http' ? 'block' : 'none';
        document.getElementById('block-command').style.display = value === 'command' ? 'block' : 'none';
    }
    function toggleMode(value) {
        document.getElementById('block-sequential').style.display = value === 'sequential' ? 'block' : 'none';
        document.getElementById('block-parallel').style.display = value === 'parallel' ? 'block' : 'none';
    }
</script>
@endsection
