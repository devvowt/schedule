<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Identifica a loja dona da tarefa. Não é cadastrado no painel:
            // chega pelo header X-Store-Uuid em cada chamada de API.
            $table->uuid('store_uuid')->nullable()->index();

            $table->string('name');
            $table->string('description', 500)->nullable();

            // http | command
            $table->string('type', 20)->index();

            // http: {url, method, headers, body, verify_ssl}
            // command: {command}
            $table->json('payload');

            $table->string('cron_expression', 120);
            $table->string('timezone', 64)->default('America/Sao_Paulo');

            // parallel | sequential
            $table->string('execution_mode', 20)->default('sequential')->index();

            // Tarefas sequenciais do mesmo grupo nunca se sobrepõem.
            $table->string('execution_group', 100)->default('default');

            // Ordem dentro do grupo sequencial (menor roda primeiro).
            $table->unsignedInteger('sequence_order')->default(0);

            // Defasagem em minutos entre uma tarefa e a anterior do grupo.
            // 0 = encadeamento puro (a próxima começa ao fim da anterior).
            $table->unsignedSmallInteger('stagger_minutes')->default(0);

            $table->unsignedInteger('timeout')->default(60);
            $table->unsignedTinyInteger('max_attempts')->default(1);
            $table->unsignedInteger('retry_delay_seconds')->default(60);

            $table->boolean('is_active')->default(true)->index();

            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->string('last_status', 20)->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);

            $table->string('notify_email')->nullable();

            // panel | api
            $table->string('created_via', 20)->default('panel');
            $table->foreignId('api_client_id')->nullable()->constrained('api_clients')->nullOnDelete();
            $table->string('created_by_email')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'execution_mode']);
            $table->index(['execution_group', 'sequence_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_tasks');
    }
};
