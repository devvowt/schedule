<?php

namespace Tests\Feature\Panel;

use App\Mail\OtpCodeMail;
use App\Models\OtpCode;
use App\Models\PanelUser;
use App\Services\Otp\OtpService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpLoginTest extends TestCase
{
    protected string $email = 'suporte@vowt.com.br';

    public function test_pagina_de_login_e_publica(): void
    {
        $this->get('/painel/login')->assertOk()->assertSee('Entrar no painel');
    }

    public function test_painel_exige_autenticacao(): void
    {
        $this->get('/painel')->assertRedirect('/painel/login');
        $this->get('/painel/tarefas')->assertRedirect('/painel/login');
    }

    public function test_envia_codigo_para_email_autorizado(): void
    {
        Mail::fake();

        $this->post('/painel/login', ['email' => $this->email])
            ->assertRedirect('/painel/login/codigo');

        Mail::assertSent(OtpCodeMail::class, fn ($mail) => $mail->hasTo($this->email));

        $this->assertDatabaseCount('otp_codes', 1);
    }

    public function test_nao_envia_codigo_para_email_nao_autorizado(): void
    {
        Mail::fake();

        // A resposta é a mesma, para não revelar quais e-mails existem.
        $this->post('/painel/login', ['email' => 'invasor@exemplo.com'])
            ->assertRedirect('/painel/login/codigo');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('otp_codes', 0);
    }

    public function test_login_com_codigo_correto(): void
    {
        Mail::fake();

        $code = app(OtpService::class)->send($this->email);

        $this->withSession(['otp_email' => $this->email])
            ->post('/painel/login/codigo', ['code' => $code])
            ->assertRedirect('/painel');

        $this->assertAuthenticated();
        $this->assertNotNull(PanelUser::where('email', $this->email)->first()?->last_login_at);
    }

    public function test_login_com_codigo_errado_falha(): void
    {
        Mail::fake();

        app(OtpService::class)->send($this->email);

        $this->withSession(['otp_email' => $this->email])
            ->post('/painel/login/codigo', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_codigo_expirado_nao_autentica(): void
    {
        Mail::fake();

        $code = app(OtpService::class)->send($this->email);

        Carbon::setTestNow(Carbon::now()->addMinutes(config('scheduler.otp.ttl_minutes') + 1));

        $this->withSession(['otp_email' => $this->email])
            ->post('/painel/login/codigo', ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertGuest();

        Carbon::setTestNow();
    }

    public function test_codigo_so_pode_ser_usado_uma_vez(): void
    {
        Mail::fake();

        $code = app(OtpService::class)->send($this->email);

        $this->withSession(['otp_email' => $this->email])
            ->post('/painel/login/codigo', ['code' => $code]);

        $this->post('/painel/logout');

        $this->withSession(['otp_email' => $this->email])
            ->post('/painel/login/codigo', ['code' => $code])
            ->assertSessionHasErrors('code');
    }

    public function test_novo_codigo_invalida_o_anterior(): void
    {
        Mail::fake();

        $service = app(OtpService::class);

        $antigo = $service->send($this->email);

        Carbon::setTestNow(Carbon::now()->addMinutes(2));
        $service->send($this->email);

        $this->withSession(['otp_email' => $this->email])
            ->post('/painel/login/codigo', ['code' => $antigo])
            ->assertSessionHasErrors('code');

        Carbon::setTestNow();
    }

    public function test_bloqueia_reenvio_dentro_da_janela(): void
    {
        Mail::fake();

        $this->post('/painel/login', ['email' => $this->email]);
        $this->post('/painel/login', ['email' => $this->email])->assertSessionHas('error');

        Mail::assertSentCount(1);
    }

    public function test_codigo_e_guardado_apenas_como_hash(): void
    {
        Mail::fake();

        $code = app(OtpService::class)->send($this->email);

        $this->assertDatabaseMissing('otp_codes', ['code_hash' => $code]);
        $this->assertNotSame($code, OtpCode::first()->code_hash);
    }

    public function test_logout_encerra_a_sessao(): void
    {
        $user = PanelUser::create(['name' => 'Suporte', 'email' => $this->email, 'is_active' => true]);

        $this->actingAs($user)->post('/painel/logout')->assertRedirect('/painel/login');

        $this->assertGuest();
    }
}
