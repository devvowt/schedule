<?php

namespace Database\Seeders;

use App\Models\PanelUser;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Todo e-mail autorizado ganha um usuário de painel; o login em si é
        // por código de uso único, então não há senha a definir.
        foreach (config('scheduler.panel.allowed_emails', []) as $email) {
            PanelUser::updateOrCreate(
                ['email' => strtolower($email)],
                ['name' => strtok($email, '@'), 'is_active' => true]
            );
        }
    }
}
