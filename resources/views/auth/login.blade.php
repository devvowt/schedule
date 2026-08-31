@extends('layouts.guest')
@section('title', 'Entrar')

@section('content')
    <h2 style="font-size:16px;margin-bottom:4px">Entrar no painel</h2>
    <p class="muted" style="margin:0 0 20px;font-size:13px">
        Enviaremos um código de uso único para o e-mail autorizado.
    </p>

    <form method="POST" action="{{ route('panel.login.send') }}">
        @csrf
        <div class="field">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" required autofocus
                   value="{{ old('email', config('scheduler.panel.allowed_emails.0')) }}"
                   placeholder="voce@empresa.com.br">
            @error('email')<div class="error">{{ $message }}</div>@enderror
        </div>

        <button class="btn primary" type="submit" style="width:100%;justify-content:center">
            Enviar código
        </button>
    </form>
@endsection
