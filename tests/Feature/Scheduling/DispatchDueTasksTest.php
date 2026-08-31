<?php

namespace Tests\Feature\Scheduling;

use App\Jobs\ExecuteTaskRun;
use App\Models\ScheduledTask;
use App\Services\Dispatching\TaskDispatcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * O comportamento central do serviço: o que vence agora vai para o caminho
 * certo — Octane quando paralelo, fila do grupo quando sequencial.
 */
class DispatchDueTasksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-03-10 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_ignora_tarefas_que_nao_vencem_agora(): void
    {
        Queue::fake();

        ScheduledTask::factory()->create(['cron_expression' => '0 3 * * *']);

        $report = app(TaskDispatcher::class)->dispatchDue();

        $this->assertSame(0, $report->due);
        Queue::assertNothingPushed();
    }

    public function test_ignora_tarefas_pausadas(): void
    {
        Queue::fake();

        ScheduledTask::factory()->inactive()->create(['cron_expression' => '0 * * * *']);

        $this->assertSame(0, app(TaskDispatcher::class)->dispatchDue()->due);
    }

    public function test_cinco_tarefas_do_mesmo_grupo_viram_uma_unica_corrente(): void
    {
        Queue::fake();

        foreach (range(1, 5) as $i) {
            ScheduledTask::factory()->sequential('loja-123', order: $i)->create([
                'name' => "Tarefa {$i}",
                'cron_expression' => '0 * * * *',
            ]);
        }

        $report = app(TaskDispatcher::class)->dispatchDue();

        $this->assertSame(5, $report->sequential);
        $this->assertSame(1, $report->chains);

        // Só o primeiro job vai para a fila; os outros quatro viajam
        // encadeados nele e sobem um a um, conforme cada um termina.
        Queue::assertPushed(ExecuteTaskRun::class, 1);

        Queue::assertPushed(ExecuteTaskRun::class, function (ExecuteTaskRun $job) {
            return $job->queue === 'sequential' && count($job->chained) === 4;
        });
    }

    public function test_a_corrente_respeita_a_ordem_configurada(): void
    {
        Queue::fake();

        $ultima = ScheduledTask::factory()->sequential('loja-123', order: 9)->create(['name' => 'Última', 'cron_expression' => '0 * * * *']);
        $primeira = ScheduledTask::factory()->sequential('loja-123', order: 1)->create(['name' => 'Primeira', 'cron_expression' => '0 * * * *']);

        app(TaskDispatcher::class)->dispatchDue();

        Queue::assertPushed(ExecuteTaskRun::class, function (ExecuteTaskRun $job) use ($primeira) {
            return $primeira->runs()->first()->id === $job->taskRunId;
        });

        $this->assertDatabaseHas('task_runs', ['scheduled_task_id' => $ultima->id]);
    }

    public function test_grupos_diferentes_geram_correntes_independentes(): void
    {
        Queue::fake();

        ScheduledTask::factory()->sequential('loja-a')->create(['cron_expression' => '0 * * * *']);
        ScheduledTask::factory()->sequential('loja-b')->create(['cron_expression' => '0 * * * *']);

        $report = app(TaskDispatcher::class)->dispatchDue();

        $this->assertSame(2, $report->chains);
        Queue::assertPushed(ExecuteTaskRun::class, 2);
    }

    public function test_defasagem_atrasa_cada_tarefa_do_grupo(): void
    {
        Queue::fake();

        ScheduledTask::factory()->sequential('loja-123', order: 1, stagger: 0)->create(['cron_expression' => '0 * * * *']);
        ScheduledTask::factory()->sequential('loja-123', order: 2, stagger: 5)->create(['cron_expression' => '0 * * * *']);
        ScheduledTask::factory()->sequential('loja-123', order: 3, stagger: 5)->create(['cron_expression' => '0 * * * *']);

        $report = app(TaskDispatcher::class)->dispatchDue();

        // Cada tarefa com defasagem abre uma nova corrente atrasada.
        $this->assertSame(3, $report->chains);

        $delays = collect(Queue::pushed(ExecuteTaskRun::class))
            ->map(fn (ExecuteTaskRun $job) => $job->delay instanceof \DateTimeInterface
                ? (int) round(Carbon::now()->diffInMinutes($job->delay))
                : 0)
            ->sort()
            ->values()
            ->all();

        $this->assertSame([0, 5, 10], $delays);
    }

    public function test_tarefas_paralelas_nao_entram_na_fila_sequencial(): void
    {
        Queue::fake();

        ScheduledTask::factory()->parallel()->count(3)->create(['cron_expression' => '0 * * * *']);

        $report = app(TaskDispatcher::class)->dispatchDue();

        $this->assertSame(3, $report->parallel);
        $this->assertSame(0, $report->sequential);

        Queue::assertPushedOn('parallel', ExecuteTaskRun::class);
        Queue::assertNotPushed(ExecuteTaskRun::class, fn (ExecuteTaskRun $job) => $job->queue === 'sequential');
    }

    public function test_paralelo_cai_para_a_fila_quando_o_octane_nao_responde(): void
    {
        Queue::fake();
        config()->set('scheduler.parallel.driver', 'octane');

        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        ScheduledTask::factory()->parallel()->count(2)->create(['cron_expression' => '0 * * * *']);

        $report = app(TaskDispatcher::class)->dispatchDue();

        $this->assertSame('queue', $report->parallelDriver);
        Queue::assertPushed(ExecuteTaskRun::class, 2);
    }

    public function test_paralelo_usa_o_octane_quando_disponivel(): void
    {
        Queue::fake();
        config()->set('scheduler.parallel.driver', 'octane');

        Http::fake(['*/octane/dispatch-tasks' => Http::response('', 200)]);

        ScheduledTask::factory()->parallel()->count(4)->create(['cron_expression' => '0 * * * *']);

        $report = app(TaskDispatcher::class)->dispatchDue();

        $this->assertSame('octane', $report->parallelDriver);
        Queue::assertNothingPushed();
        Http::assertSentCount(1);
    }

    public function test_nao_despacha_a_mesma_tarefa_duas_vezes_no_mesmo_minuto(): void
    {
        Queue::fake();

        ScheduledTask::factory()->create(['cron_expression' => '0 * * * *']);

        app(TaskDispatcher::class)->dispatchDue();
        $segundo = app(TaskDispatcher::class)->dispatchDue();

        $this->assertSame(1, $segundo->skipped);
        $this->assertSame(1, \App\Models\TaskRun::count());
    }

    public function test_comando_artisan_dispatch_due_roda(): void
    {
        Queue::fake();

        ScheduledTask::factory()->create(['cron_expression' => '0 * * * *']);

        $this->artisan('tasks:dispatch-due')->assertSuccessful();

        $this->assertDatabaseCount('task_runs', 1);
    }
}
