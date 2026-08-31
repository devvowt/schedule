<?php

namespace Tests\Feature\Execution;

use App\Enums\RunStatus;
use App\Models\ScheduledTask;
use App\Models\TaskRun;
use App\Services\Dispatching\TaskDispatcher;
use App\Services\Execution\TaskRunner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TaskExecutionTest extends TestCase
{
    protected function runFor(ScheduledTask $task): TaskRun
    {
        $run = app(TaskDispatcher::class)->dispatchNow($task);

        return app(TaskRunner::class)->run($run->fresh()->load('task'));
    }

    public function test_tarefa_http_bem_sucedida(): void
    {
        Queue::fake();
        Http::fake(['https://api.test/*' => Http::response('tudo certo', 200)]);

        $task = ScheduledTask::factory()->create([
            'payload' => ['url' => 'https://api.test/sync', 'method' => 'POST'],
        ]);

        $run = $this->runFor($task);

        $this->assertSame(RunStatus::Success, $run->status);
        $this->assertSame(200, $run->http_status);
        $this->assertSame('tudo certo', $run->output);
        $this->assertNotNull($run->duration_ms);

        $this->assertSame('success', $task->fresh()->last_status);
        $this->assertSame(0, $task->fresh()->consecutive_failures);

        Http::assertSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_tarefa_http_com_erro_do_servidor_falha(): void
    {
        Queue::fake();
        Http::fake(['https://api.test/*' => Http::response('boom', 500)]);

        $task = ScheduledTask::factory()->create([
            'payload' => ['url' => 'https://api.test/sync', 'method' => 'GET'],
        ]);

        $run = $this->runFor($task);

        $this->assertSame(RunStatus::Failed, $run->status);
        $this->assertSame(500, $run->http_status);
        $this->assertSame(1, $task->fresh()->consecutive_failures);
    }

    public function test_status_esperado_customizado(): void
    {
        Queue::fake();
        Http::fake(['https://api.test/*' => Http::response('', 302)]);

        $task = ScheduledTask::factory()->create([
            'payload' => ['url' => 'https://api.test/x', 'method' => 'GET', 'expected_status' => [302]],
        ]);

        $this->assertSame(RunStatus::Success, $this->runFor($task)->status);
    }

    public function test_falha_de_conexao_e_registrada(): void
    {
        Queue::fake();
        Http::fake(fn () => throw new ConnectionException('host inacessível'));

        $task = ScheduledTask::factory()->create([
            'payload' => ['url' => 'https://api.test/x', 'method' => 'GET'],
        ]);

        $run = $this->runFor($task);

        $this->assertSame(RunStatus::Failed, $run->status);
        $this->assertStringContainsString('Falha de conexão', $run->error);
    }

    public function test_tarefa_de_comando(): void
    {
        Queue::fake();

        $task = ScheduledTask::factory()->command('echo agendador-ok')->create();

        $run = $this->runFor($task);

        $this->assertSame(RunStatus::Success, $run->status);
        $this->assertSame(0, $run->exit_code);
        $this->assertStringContainsString('agendador-ok', $run->output);
    }

    public function test_comando_com_erro_registra_codigo_de_saida(): void
    {
        Queue::fake();

        $task = ScheduledTask::factory()->command('false')->create();

        $run = $this->runFor($task);

        $this->assertSame(RunStatus::Failed, $run->status);
        $this->assertSame(1, $run->exit_code);
    }

    public function test_comando_fora_da_allowlist_e_bloqueado_na_execucao(): void
    {
        Queue::fake();

        // Cadastro antigo, feito antes de a allowlist mudar.
        $task = ScheduledTask::factory()->command('rm -rf /tmp/x')->create();

        $run = $this->runFor($task);

        $this->assertSame(RunStatus::Failed, $run->status);
        $this->assertStringContainsString('allowlist', $run->error);
    }

    public function test_tarefa_pausada_nao_executa(): void
    {
        Queue::fake();

        $task = ScheduledTask::factory()->create();
        $run = app(TaskDispatcher::class)->dispatchNow($task);

        $task->forceFill(['is_active' => false])->save();

        $executed = app(TaskRunner::class)->run($run->fresh()->load('task'));

        $this->assertSame(RunStatus::Skipped, $executed->status);
    }

    public function test_falha_agenda_nova_tentativa_quando_configurado(): void
    {
        Queue::fake();
        Http::fake(['https://api.test/*' => Http::response('', 500)]);

        $task = ScheduledTask::factory()->create([
            'payload' => ['url' => 'https://api.test/x', 'method' => 'GET'],
            'max_attempts' => 3,
            'retry_delay_seconds' => 30,
        ]);

        $this->runFor($task);

        $this->assertDatabaseHas('task_runs', [
            'scheduled_task_id' => $task->id,
            'attempt' => 2,
            'status' => RunStatus::Queued->value,
        ]);
    }

    public function test_nao_agenda_retentativa_alem_do_limite(): void
    {
        Queue::fake();
        Http::fake(['https://api.test/*' => Http::response('', 500)]);

        $task = ScheduledTask::factory()->create([
            'payload' => ['url' => 'https://api.test/x', 'method' => 'GET'],
            'max_attempts' => 1,
        ]);

        $this->runFor($task);

        $this->assertSame(1, TaskRun::where('scheduled_task_id', $task->id)->count());
    }

    public function test_proxima_execucao_e_recalculada_apos_rodar(): void
    {
        Queue::fake();
        Http::fake(['https://api.test/*' => Http::response('ok')]);

        $task = ScheduledTask::factory()->create([
            'payload' => ['url' => 'https://api.test/x', 'method' => 'GET'],
            'cron_expression' => '0 * * * *',
        ]);

        $this->runFor($task);

        $this->assertNotNull($task->fresh()->next_run_at);
        $this->assertTrue($task->fresh()->next_run_at->isFuture());
    }
}
