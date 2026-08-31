@extends('layouts.guest')
@section('title', 'Código de acesso')

@section('content')
    <h2 style="font-size:16px;margin-bottom:4px">Digite o código</h2>
    <p class="muted" style="margin:0 0 20px;font-size:13px">
        Enviamos um código de {{ config('scheduler.otp.length') }} dígitos para
        <strong>{{ $email }}</strong>. Ele vale por {{ config('scheduler.otp.ttl_minutes') }} minutos.
    </p>

    <form method="POST" action="{{ route('panel.login.verify') }}">
        @csrf
        <div class="field">
            <label for="code">Código</label>
            <input type="text" id="code" name="code" required autofocus inputmode="numeric"
                   autocomplete="one-time-code" maxlength="{{ config('scheduler.otp.length') }}"
                   style="font-family:var(--mono);font-size:22px;letter-spacing:10px;text-align:center;padding:12px">
            @error('code')<div class="error">{{ $message }}</div>@enderror
        </div>

        <button class="btn primary" type="submit" style="width:100%;justify-content:center">Entrar</button>
    </form>

    <form method="POST" action="{{ route('panel.login.send') }}" style="margin-top:14px">
        @csrf
        <input type="hidden" name="email" value="{{ $email }}">
        <button class="btn sm" type="submit" style="width:100%;justify-content:center">Reenviar código</button>
    </form>

    <p style="text-align:center;margin:16px 0 0;font-size:12.5px">
        <a href="{{ route('panel.login') }}">Usar outro e-mail</a>
    </p>
@endsection
