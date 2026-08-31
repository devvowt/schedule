<?php

namespace Tests\Feature\Api;

use App\Models\ApiClient;
use App\Models\ScheduledTask;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskCrudApiTest extends TestCase
{
    protected array $headers;

    protected string $storeUuid;

    protected function setUp(): void
    {
        parent::setUp();

        [$client, $secret] = ApiClient::issue(['name' => 'Loja teste', 'is_active' => true]);

        $this->storeUuid = (string) Str::uuid();

        $this->headers = [
            'X-Client-Id' => $client->client_id,
            'X-Client-Secret' => $secret,
            'X-Store-Uuid' => $this->storeUuid,
        ];
    }

    public function test_cadastra_tarefa_http(): void
    {
        $response = $this->postJson('/api/v1/tasks', [
            'name' => 'Sincronizar pedidos',
            'type' => 'http',
            'url' => 'https://api.loja.test/sync',
            'method' => 'POST',
            'headers' => ['Authorization' => 'Bearer abc'],
            'cron_expression' => '0 * * * *',
            'execution_mode' => 'sequential',
            'execution_group' => 'loja-123',
            'stagger_minutes' => 5,
            'sequence_order' => 2,
        ], $this->headers);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Sincronizar pedidos')
            ->assertJsonPath('data.store_uuid', $this->storeUuid)
            ->assertJsonPath('data.execution.mode', 'sequential')
            ->assertJsonPath('data.execution.group', 'loja-123')
            ->assertJsonPath('data.execution.stagger_minutes', 5)
            ->assertJsonPath('data.payload.method', 'POST')
            ->assertJsonPath('data.created_via', 'api');

        $this->assertNotNull($response->json('data.schedule.next_run_at'));

        $this->assertDatabaseHas('scheduled_tasks', [
            'name' => 'Sincronizar pedidos',
            'store_uuid' => $this->storeUuid,
            'execution_group' => 'loja-123',
        ]);
    }

    public function test_cadastra_tarefa_paralela(): void
    {
        $this->postJson('/api/v1/tasks', [
            'name' => 'Aquecer cache',
            'type' => 'http',
            'url' => 'https://api.loja.test/cache',
            'cron_expression' => '*/10 * * * *',
            'execution_mode' => 'parallel',
        ], $this->headers)
            ->assertCreated()
            ->assertJsonPath('data.execution.mode', 'parallel');
    }

    public function test_recusa_cron_invalido(): void
    {
        $this->postJson('/api/v1/tasks', [
            'name' => 'Quebrada',
            'type' => 'http',
            'url' => 'https://api.loja.test/x',
            'cron_expression' => 'toda hora',
            'execution_mode' => 'parallel',
        ], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('cron_expression');
    }

    public function test_recusa_comando_fora_da_allowlist(): void
    {
        $this->postJson('/api/v1/tasks', [
            'name' => 'Comando proibido',
            'type' => 'command',
            'command' => 'rm -rf /',
            'cron_expression' => '0 * * * *',
            'execution_mode' => 'sequential',
        ], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('command');
    }

    public function test_aceita_comando_da_allowlist(): void
    {
        $this->postJson('/api/v1/tasks', [
            'name' => 'Comando ok',
            'type' => 'command',
            'command' => 'echo rodando',
            'cron_expression' => '0 * * * *',
            'execution_mode' => 'sequential',
        ], $this->headers)
            ->assertCreated()
            ->assertJsonPath('data.payload.command', 'echo rodando');
    }

    public function test_lista_apenas_tarefas_do_proprio_store(): void
    {
        ScheduledTask::factory()->count(2)->create(['store_uuid' => $this->storeUuid]);
        ScheduledTask::factory()->count(3)->create(['store_uuid' => (string) Str::uuid()]);

        $this->getJson('/api/v1/tasks', $this->headers)
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_nao_enxerga_tarefa_de_outro_store(): void
    {
        $task = ScheduledTask::factory()->create(['store_uuid' => (string) Str::uuid()]);

        $this->getJson("/api/v1/tasks/{$task->uuid}", $this->headers)->assertStatus(404);
        $this->deleteJson("/api/v1/tasks/{$task->uuid}", [], $this->headers)->assertStatus(404);
    }

    public function test_atualiza_tarefa(): void
    {
        $task = ScheduledTask::factory()->create(['store_uuid' => $this->storeUuid]);

        $this->patchJson("/api/v1/tasks/{$task->uuid}", [
            'name' => 'Nome novo',
            'stagger_minutes' => 7,
            'is_active' => false,
        ], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Nome novo')
            ->assertJsonPath('data.execution.stagger_minutes', 7)
            ->assertJsonPath('data.is_active', false);
    }

    public function test_atualizacao_parcial_preserva_a_url(): void
    {
        $task = ScheduledTask::factory()->create([
            'store_uuid' => $this->storeUuid,
            'payload' => ['url' => 'https://api.loja.test/original', 'method' => 'GET'],
        ]);

        $this->patchJson("/api/v1/tasks/{$task->uuid}", ['name' => 'Só o nome'], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.payload.url', 'https://api.loja.test/original');
    }

    public function test_remove_tarefa(): void
    {
        $task = ScheduledTask::factory()->create(['store_uuid' => $this->storeUuid]);

        $this->deleteJson("/api/v1/tasks/{$task->uuid}", [], $this->headers)->assertOk();

        $this->assertSoftDeleted('scheduled_tasks', ['id' => $task->id]);
    }

    public function test_dispara_execucao_manual_pela_api(): void
    {
        $task = ScheduledTask::factory()->create(['store_uuid' => $this->storeUuid]);

        $this->postJson("/api/v1/tasks/{$task->uuid}/run", [], $this->headers)
            ->assertStatus(202)
            ->assertJsonPath('data.trigger', 'api');

        $this->assertDatabaseHas('task_runs', [
            'scheduled_task_id' => $task->id,
            'trigger' => 'api',
        ]);
    }

    public function test_lista_execucoes_da_tarefa(): void
    {
        $task = ScheduledTask::factory()->create(['store_uuid' => $this->storeUuid]);
        $task->runs()->createMany([
            ['status' => 'success', 'trigger' => 'schedule', 'scheduled_for' => now(), 'store_uuid' => $this->storeUuid],
            ['status' => 'failed', 'trigger' => 'schedule', 'scheduled_for' => now(), 'store_uuid' => $this->storeUuid],
        ]);

        $this->getJson("/api/v1/tasks/{$task->uuid}/runs", $this->headers)
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
