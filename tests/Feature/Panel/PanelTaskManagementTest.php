<?php

namespace Tests\Feature\Panel;

use App\Models\ApiClient;
use App\Models\PanelUser;
use App\Models\ScheduledTask;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PanelTaskManagementTest extends TestCase
{
    protected PanelUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = PanelUser::create([
            'name' => 'Suporte',
            'email' => 'suporte@vowt.com.br',
            'is_active' => true,
        ]);
    }

    public function test_dashboard_carrega(): void
    {
        ScheduledTask::factory()->count(3)->create();

        $this->actingAs($this->user)->get('/painel')
            ->assertOk()
            ->assertSee('Tarefas ativas');
    }

    public function test_lista_tarefas(): void
    {
        ScheduledTask::factory()->create(['name' => 'Sincronizar estoque']);

        $this->actingAs($this->user)->get('/painel/tarefas')
            ->assertOk()
            ->assertSee('Sincronizar estoque');
    }

    public function test_telas_de_tarefa_renderizam(): void
    {
        $task = ScheduledTask::factory()->command('echo oi')->create(['name' => 'Com comando']);
        $run = $task->runs()->create([
            'status' => 'failed',
            'trigger' => 'manual',
            'execution_mode' => 'sequential',
            'execution_group' => 'default',
            'scheduled_for' => now(),
            'started_at' => now(),
            'finished_at' => now(),
            'duration_ms' => 1200,
            'exit_code' => 1,
            'output' => 'saída de exemplo',
            'error' => 'Comando terminou com código 1.',
        ]);

        $this->actingAs($this->user)->get("/painel/tarefas/{$task->uuid}")->assertOk()->assertSee('Com comando');
        $this->actingAs($this->user)->get('/painel/tarefas/nova')->assertOk()->assertSee('Expressão cron', false);
        $this->actingAs($this->user)->get("/painel/tarefas/{$task->uuid}/editar")->assertOk()->assertSee('echo oi', false);
        $this->actingAs($this->user)->get("/painel/execucoes/{$run->uuid}")->assertOk()->assertSee('saída de exemplo', false);
        $this->actingAs($this->user)->get('/painel/clientes')->assertOk()->assertSee('Novo cliente');
    }

    public function test_tela_de_tarefa_http_com_headers_renderiza(): void
    {
        $task = ScheduledTask::factory()->create([
            'name' => 'Com headers',
            'payload' => [
                'url' => 'https://api.test/x',
                'method' => 'POST',
                'headers' => ['Authorization' => 'Bearer abc'],
            ],
        ]);

        $this->actingAs($this->user)->get("/painel/tarefas/{$task->uuid}")
            ->assertOk()
            ->assertSee('Authorization', false);
    }

    public function test_cadastra_tarefa_pelo_painel(): void
    {
        $response = $this->actingAs($this->user)->post('/painel/tarefas', [
            'name' => 'Fechar caixa',
            'type' => 'http',
            'url' => 'https://api.loja.test/fechar',
            'method' => 'POST',
            'headers_raw' => "Authorization: Bearer abc\nContent-Type: application/json",
            'cron_expression' => '0 23 * * *',
            'timezone' => 'America/Sao_Paulo',
            'execution_mode' => 'sequential',
            'execution_group' => 'financeiro',
            'sequence_order' => 1,
            'stagger_minutes' => 3,
            'timeout' => 45,
            'max_attempts' => 2,
            'retry_delay_seconds' => 60,
            'is_active' => '1',
        ]);

        $response->assertRedirect();

        $task = ScheduledTask::where('name', 'Fechar caixa')->firstOrFail();

        $this->assertSame('financeiro', $task->execution_group);
        $this->assertSame(3, $task->stagger_minutes);
        $this->assertSame('Bearer abc', $task->payload['headers']['Authorization']);
        $this->assertSame('panel', $task->created_via);
        $this->assertSame('suporte@vowt.com.br', $task->created_by_email);
    }

    public function test_edita_tarefa(): void
    {
        $task = ScheduledTask::factory()->create(['name' => 'Antigo']);

        $this->actingAs($this->user)->put("/painel/tarefas/{$task->uuid}", [
            'name' => 'Novo nome',
            'type' => 'http',
            'url' => $task->payload['url'],
            'cron_expression' => '*/15 * * * *',
            'execution_mode' => 'parallel',
            'is_active' => '1',
        ])->assertRedirect();

        $task->refresh();

        $this->assertSame('Novo nome', $task->name);
        $this->assertSame('parallel', $task->execution_mode->value);
        $this->assertSame('*/15 * * * *', $task->cron_expression);
    }

    public function test_pausa_e_reativa_tarefa(): void
    {
        $task = ScheduledTask::factory()->create();

        $this->actingAs($this->user)->post("/painel/tarefas/{$task->uuid}/alternar");
        $this->assertFalse($task->fresh()->is_active);

        $this->actingAs($this->user)->post("/painel/tarefas/{$task->uuid}/alternar");
        $this->assertTrue($task->fresh()->is_active);
    }

    public function test_executa_tarefa_manualmente(): void
    {
        Queue::fake();

        $task = ScheduledTask::factory()->create();

        $this->actingAs($this->user)->post("/painel/tarefas/{$task->uuid}/executar")->assertRedirect();

        $this->assertDatabaseHas('task_runs', [
            'scheduled_task_id' => $task->id,
            'trigger' => 'manual',
        ]);
    }

    public function test_remove_tarefa(): void
    {
        $task = ScheduledTask::factory()->create();

        $this->actingAs($this->user)->delete("/painel/tarefas/{$task->uuid}")->assertRedirect('/painel/tarefas');

        $this->assertSoftDeleted('scheduled_tasks', ['id' => $task->id]);
    }

    public function test_valida_cron_no_cadastro(): void
    {
        $this->actingAs($this->user)->post('/painel/tarefas', [
            'name' => 'Inválida',
            'type' => 'http',
            'url' => 'https://api.loja.test/x',
            'cron_expression' => 'sempre',
            'execution_mode' => 'parallel',
        ])->assertSessionHasErrors('cron_expression');
    }

    public function test_cadastra_cliente_de_api_e_mostra_o_segredo_uma_vez(): void
    {
        $response = $this->actingAs($this->user)->post('/painel/clientes', [
            'name' => 'Integração OpenCart',
        ]);

        $response->assertRedirect()->assertSessionHas('new_secret');

        $secret = session('new_secret');

        $this->assertStringStartsWith('sk_', $secret['client_secret']);
        $this->assertStringStartsWith('cid_', $secret['client_id']);

        // O banco guarda apenas o hash.
        $this->assertDatabaseMissing('api_clients', ['client_secret_hash' => $secret['client_secret']]);
        $this->assertTrue(ApiClient::first()->secretMatches($secret['client_secret']));
    }

    public function test_rotaciona_segredo_do_cliente(): void
    {
        [$client, $antigo] = ApiClient::issue(['name' => 'Cliente']);

        $this->actingAs($this->user)->post("/painel/clientes/{$client->uuid}/rotacionar")
            ->assertSessionHas('new_secret');

        $this->assertFalse($client->fresh()->secretMatches($antigo));
    }

    public function test_lista_execucoes(): void
    {
        $task = ScheduledTask::factory()->create(['name' => 'Com histórico']);
        $task->runs()->create(['status' => 'success', 'trigger' => 'schedule', 'scheduled_for' => now()]);

        $this->actingAs($this->user)->get('/painel/execucoes')
            ->assertOk()
            ->assertSee('Com histórico');
    }
}
