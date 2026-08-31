@extends('layouts.base')

@section('body')
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px">
    <div style="width:100%;max-width:400px">
        <div style="display:flex;align-items:center;gap:9px;justify-content:center;margin-bottom:20px;font-weight:650">
            <span style="width:9px;height:9px;border-radius:50%;background:#34d399;display:inline-block"></span>
            Agendador de Tarefas
        </div>

        <div class="card">
            <div class="card-body" style="padding:24px">
                @if (session('status'))
                    <div class="alert success">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert error">{{ session('error') }}</div>
                @endif

                @yield('content')
            </div>
        </div>

        <p class="muted" style="text-align:center;margin-top:16px;font-size:12px">
            Acesso restrito · autenticação por código de uso único
        </p>
    </div>
</div>
@endsection
