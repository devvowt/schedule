<?php

namespace App\Services\Otp;

use App\Mail\OtpCodeMail;
use App\Models\OtpCode;
use App\Models\PanelUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Login do painel por código de uso único enviado por e-mail.
 *
 * Só e-mails listados em PANEL_ALLOWED_EMAILS podem pedir código; o código é
 * guardado apenas como hash, expira em minutos e tem limite de tentativas.
 */
class OtpService
{
    public function isAllowed(string $email): bool
    {
        return in_array(strtolower(trim($email)), array_map('strtolower', config('scheduler.panel.allowed_emails', [])), true);
    }

    /**
     * Ainda está no intervalo mínimo entre dois envios?
     */
    public function secondsUntilResend(string $email): int
    {
        $last = OtpCode::where('email', $this->normalize($email))->latest('id')->first();

        if ($last === null) {
            return 0;
        }

        $elapsed = $last->created_at->diffInSeconds(Carbon::now());
        $window = (int) config('scheduler.otp.resend_seconds');

        return (int) max(0, $window - $elapsed);
    }

    /**
     * Gera e envia um novo código. Retorna o código em claro apenas para uso
     * em testes e no ambiente local — a aplicação não o expõe.
     */
    public function send(string $email, ?string $ip = null, ?string $userAgent = null): string
    {
        $email = $this->normalize($email);

        // Códigos anteriores deixam de valer assim que um novo é emitido.
        OtpCode::where('email', $email)->whereNull('consumed_at')->update(['consumed_at' => Carbon::now()]);

        $code = $this->generateCode();

        OtpCode::create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'expires_at' => Carbon::now()->addMinutes((int) config('scheduler.otp.ttl_minutes')),
            'request_ip' => $ip,
            'user_agent' => $userAgent ? mb_strcut($userAgent, 0, 512) : null,
        ]);

        Mail::to($email)->send(new OtpCodeMail($code, (int) config('scheduler.otp.ttl_minutes')));

        Log::info('Código OTP enviado.', ['email' => $email, 'ip' => $ip]);

        return $code;
    }

    /**
     * Confere o código e, se válido, devolve (criando se preciso) o usuário do
     * painel correspondente.
     */
    public function verify(string $email, string $code): ?PanelUser
    {
        $email = $this->normalize($email);

        $otp = OtpCode::where('email', $email)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($otp === null || ! $otp->isUsable()) {
            return null;
        }

        $otp->increment('attempts');

        if (! Hash::check($code, $otp->code_hash)) {
            return null;
        }

        $otp->forceFill(['consumed_at' => Carbon::now()])->save();

        $user = PanelUser::firstOrCreate(
            ['email' => $email],
            ['name' => strtok($email, '@'), 'is_active' => true]
        );

        if (! $user->is_active) {
            return null;
        }

        $user->forceFill(['last_login_at' => Carbon::now()])->save();

        return $user;
    }

    protected function generateCode(): string
    {
        $length = (int) config('scheduler.otp.length');

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    protected function normalize(string $email): string
    {
        return strtolower(trim($email));
    }
}
