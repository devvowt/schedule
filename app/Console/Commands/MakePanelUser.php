<?php

namespace App\Console\Commands;

use App\Models\PanelUser;
use Illuminate\Console\Command;

class MakePanelUser extends Command
{
    protected $signature = 'panel:user {email} {--name= : Nome exibido no painel}';

    protected $description = 'Cria ou reativa um usuário do painel (login por OTP)';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));

        if (! in_array($email, config('scheduler.panel.allowed_emails', []), true)) {
            $this->warn("O e-mail {$email} não está em PANEL_ALLOWED_EMAILS — o login por OTP será recusado.");
        }

        $user = PanelUser::updateOrCreate(
            ['email' => $email],
            ['name' => $this->option('name') ?: strtok($email, '@'), 'is_active' => true]
        );

        $this->info("Usuário {$user->email} pronto para login por OTP.");

        return self::SUCCESS;
    }
}
