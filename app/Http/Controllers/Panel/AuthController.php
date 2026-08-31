<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\Otp\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login do painel: e-mail autorizado → código por e-mail → sessão.
 * Não há senha em lugar nenhum.
 */
class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('panel.dashboard');
        }

        return view('auth.login');
    }

    public function sendCode(Request $request, OtpService $otp): RedirectResponse
    {
        $data = $request->validate(
            ['email' => ['required', 'email', 'max:255']],
            [],
            ['email' => 'e-mail']
        );

        $email = strtolower(trim($data['email']));
        $throttleKey = 'otp-send:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Muitas tentativas. Aguarde '.RateLimiter::availableIn($throttleKey).' segundos.',
            ]);
        }

        RateLimiter::hit($throttleKey, 600);

        // Resposta idêntica para e-mail autorizado ou não: quem está de fora
        // não descobre quais endereços existem.
        if ($this->otp->isAllowed($email)) {
            $wait = $this->otp->secondsUntilResend($email);

            if ($wait > 0) {
                return back()
                    ->with('error', "Aguarde {$wait} segundos para pedir um novo código.")
                    ->withInput();
            }

            $this->otp->send($email, $request->ip(), $request->userAgent());
        }

        $request->session()->put('otp_email', $email);

        return redirect()
            ->route('panel.login.code')
            ->with('status', 'Se o e-mail estiver autorizado, o código chegará em instantes.');
    }

    public function showCode(Request $request): View|RedirectResponse
    {
        $email = $request->session()->get('otp_email');

        if (! $email) {
            return redirect()->route('panel.login');
        }

        return view('auth.verify', ['email' => $email]);
    }

    public function verifyCode(Request $request): RedirectResponse
    {
        $data = $request->validate(
            ['code' => ['required', 'string', 'max:12']],
            [],
            ['code' => 'código']
        );

        $email = $request->session()->get('otp_email');

        if (! $email) {
            return redirect()->route('panel.login');
        }

        $throttleKey = 'otp-verify:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            throw ValidationException::withMessages([
                'code' => 'Muitas tentativas. Aguarde '.RateLimiter::availableIn($throttleKey).' segundos.',
            ]);
        }

        RateLimiter::hit($throttleKey, 600);

        $user = $this->otp->verify($email, trim($data['code']));

        if ($user === null) {
            throw ValidationException::withMessages([
                'code' => 'Código inválido, expirado ou já utilizado.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user, remember: true);

        $request->session()->regenerate();
        $request->session()->forget('otp_email');

        return redirect()->intended(route('panel.dashboard'))
            ->with('status', 'Bem-vindo, '.$user->name.'.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('panel.login')->with('status', 'Sessão encerrada.');
    }
}
