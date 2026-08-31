@extends('layouts.base')

@section('body')
<div class="shell">
    <aside class="sidebar">
        <div class="brand"><span class="dot"></span> Agendador</div>

        <nav class="nav">
            <a href="{{ route('panel.dashboard') }}" class="{{ request()->routeIs('panel.dashboard') ? 'active' : '' }}">Visão geral</a>
            <a href="{{ route('panel.tasks.index') }}" class="{{ request()->routeIs('panel.tasks.*') ? 'active' : '' }}">Tarefas</a>
            <a href="{{ route('panel.runs.index') }}" class="{{ request()->routeIs('panel.runs.*') ? 'active' : '' }}">Execuções</a>
            <a href="{{ route('panel.clients.index') }}" class="{{ request()->routeIs('panel.clients.*') ? 'active' : '' }}">Clientes da API</a>
        </nav>

        <div class="foot">
            {{ auth()->user()?->email }}
            <form method="POST" action="{{ route('panel.logout') }}">
                @csrf
                <button class="btn sm" type="submit" style="width:100%">Sair</button>
            </form>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <div>
                <h1 style="font-size:17px">@yield('title', 'Painel')</h1>
                @hasSection('subtitle')
                    <div class="muted" style="font-size:12.5px;margin-top:2px">@yield('subtitle')</div>
                @endif
            </div>
            <div class="actions">@yield('actions')</div>
        </header>

        <main class="content">
            @if (session('status'))
                <div class="alert success">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="alert error">{{ session('error') }}</div>
            @endif
            @if ($errors->any() && ! $errors->has('__form'))
                <div class="alert error">
                    Corrija os campos destacados abaixo.
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
@endsection
