<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('scheduled_task_id')->constrained('scheduled_tasks')->cascadeOnDelete();
            $table->uuid('store_uuid')->nullable()->index();

            // pending | queued | running | success | failed | timeout | skipped
            $table->string('status', 20)->index();

            // schedule | manual | api
            $table->string('trigger', 20)->default('schedule');

            // Snapshot do modo no momento do despacho.
            $table->string('execution_mode', 20)->default('sequential');
            $table->string('execution_group', 100)->nullable();

            // Evita despacho duplicado do mesmo minuto agendado.
            $table->string('dedupe_key', 80)->nullable()->unique();

            $table->timestamp('scheduled_for')->index();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->unsignedTinyInteger('attempt')->default(1);
            $table->integer('exit_code')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();

            $table->longText('output')->nullable();
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['scheduled_task_id', 'created_at']);
            $table->index(['status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_runs');
    }
};
