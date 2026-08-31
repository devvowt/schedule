@extends('layouts.app')
@section('title', 'Clientes da API')
@section('subtitle', 'Credenciais usadas para cadastrar e remover tarefas via API')

@section('content')
    @if (session('new_secret'))
        @php($cred = session('new_secret'))
        <div class="card" style="margin-top:0;border-color:#a7f3d0">
            <div class="card-head" style="background:var(--green-bg);border-color:#a7f3d0">
                <h2 style="color:var(--green)">Credenciais de "{{ $cred['name'] }}"</h2>
                <span class="badge green">copie agora</span>
            </div>
            <div class="card-body">
                <p class="muted" style="margin:0 0 14px;font-size:13px">
                    O segredo não é armazenado em texto — esta é a única vez que ele aparece.
                </p>
                <dl class="kv">
                    <dt>X-Client-Id</dt><dd class="mono">{{ $cred['client_id'] }}</dd>
                    <dt>X-Client-Secret</dt><dd class="mono">{{ $cred['client_secret'] }}</dd>
                </dl>
            </div>
        </div>
    @endif

    <div class="grid cols-2">
        <div class="card" style="margin-top:0">
            <div class="card-head"><h2>Novo cliente</h2></div>
            <div class="card-body">
                <form method="POST" action="{{ route('panel.clients.store') }}">
                    @csrf
                    <div class="field">
                        <label for="name">Nome</label>
                        <input type="text" id="name" name="name" required value="{{ old('name') }}" placeholder="Integração e-commerce">
                        @error('name')<div class="error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="description">Descrição</label>
                        <input type="text" id="description" name="description" value="{{ old('description') }}" placeholder="Opcional">
                    </div>
                    <div class="field">
                        <label for="allowed_store_uuids">Stores permitidos</label>
                        <textarea id="allowed_store_uuids" name="allowed_store_uuids" style="min-height:70px"
                                  placeholder="Um UUID por linha — vazio libera qualquer store">{{ old('allowed_store_uuids') }}</textarea>
                        <div class="help">
                            Restringe quais valores de <span class="mono">X-Store-Uuid</span> este cliente pode usar.
                        </div>
                    </div>
                    <button class="btn primary" type="submit">Gerar credenciais</button>
                </form>
            </div>
        </div>

        <div class="card" style="margin-top:0">
            <div class="card-head"><h2>Como autenticar</h2></div>
            <div class="card-body">
                <p class="muted" style="margin:0 0 12px;font-size:13px">
                    Toda chamada precisa das três informações abaixo. O store não é cadastrado aqui:
                    ele viaja em cada requisição e delimita o que o cliente enxerga.
                </p>
                <pre class="output">curl -X POST {{ config('app.url') }}/api/v1/tasks \
  -H "X-Client-Id: cid_..." \
  -H "X-Client-Secret: sk_..." \
  -H "X-Store-Uuid: 6f1c...-...." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Sincronizar pedidos",
    "type": "http",
    "url": "https://api.loja.com.br/sync",
    "method": "POST",
    "cron_expression": "0 * * * *",
    "execution_mode": "sequential",
    "execution_group": "loja-123",
    "stagger_minutes": 5
  }'</pre>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Clientes cadastrados</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Nome</th><th>Client ID</th><th>Segredo</th><th>Stores</th><th>Tarefas</th><th>Último uso</th><th></th></tr></thead>
                <tbody>
                @forelse ($clients as $client)
                    <tr>
                        <td>
                            <strong>{{ $client->name }}</strong>
                            @unless ($client->is_active)<span class="badge amber" style="margin-left:6px">inativo</span>@endunless
                            @if ($client->description)<div class="muted" style="font-size:12px">{{ $client->description }}</div>@endif
                        </td>
                        <td class="mono">{{ $client->client_id }}</td>
                        <td class="mono muted">…{{ $client->secret_hint }}</td>
                        <td class="muted">{{ $client->allowed_store_uuids ? count($client->allowed_store_uuids).' restrito(s)' : 'qualquer' }}</td>
                        <td>{{ $client->tasks_count }}</td>
                        <td class="mono muted">{{ $client->last_used_at?->format('d/m H:i') ?? '—' }}</td>
                        <td style="text-align:right;white-space:nowrap">
                            <form method="POST" action="{{ route('panel.clients.rotate', $client) }}" style="display:inline"
                                  onsubmit="return confirm('Gerar um novo segredo? O atual deixa de funcionar.')">
                                @csrf
                                <button class="btn sm" type="submit">Rotacionar</button>
                            </form>
                            <form method="POST" action="{{ route('panel.clients.toggle', $client) }}" style="display:inline">
                                @csrf
                                <button class="btn sm" type="submit">{{ $client->is_active ? 'Desativar' : 'Ativar' }}</button>
                            </form>
                            <form method="POST" action="{{ route('panel.clients.destroy', $client) }}" style="display:inline"
                                  onsubmit="return confirm('Remover o cliente {{ $client->name }}?')">
                                @csrf @method('DELETE')
                                <button class="btn sm danger" type="submit">Remover</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">Nenhum cliente cadastrado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
