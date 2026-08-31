<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('client_id', 64)->unique();
            $table->string('client_secret_hash');
            // Últimos caracteres do segredo, apenas para identificação no painel.
            $table->string('secret_hint', 12)->nullable();
            // Quando preenchido, restringe quais stores o cliente pode operar.
            $table->json('allowed_store_uuids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->string('created_by_email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
