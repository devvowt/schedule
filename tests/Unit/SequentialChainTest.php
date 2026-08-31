<?php

namespace Tests\Unit;

use App\Models\ScheduledTask;
use PHPUnit\Framework\TestCase;

/**
 * A leitura de cron é o que decide o que roda em cada minuto — vale testar sem
 * banco, direto no modelo.
 */
class SequentialChainTest extends TestCase
{
    protected function task(string $cron, string $tz = 'America/Sao_Paulo'): ScheduledTask
    {
        return new ScheduledTask(['cron_expression' => $cron, 'timezone' => $tz]);
    }

    public function test_cron_de_hora_em_hora(): void
    {
        $task = $this->task('0 * * * *');

        $this->assertTrue($task->isDueAt(\Illuminate\Support\Carbon::parse('2026-03-10 09:00:00', 'America/Sao_Paulo')));
        $this->assertFalse($task->isDueAt(\Illuminate\Support\Carbon::parse('2026-03-10 09:01:00', 'America/Sao_Paulo')));
    }

    public function test_cron_a_cada_cinco_minutos(): void
    {
        $task = $this->task('*/5 * * * *');

        $this->assertTrue($task->isDueAt(\Illuminate\Support\Carbon::parse('2026-03-10 09:05:00', 'America/Sao_Paulo')));
        $this->assertFalse($task->isDueAt(\Illuminate\Support\Carbon::parse('2026-03-10 09:07:00', 'America/Sao_Paulo')));
    }

    public function test_fuso_horario_e_respeitado(): void
    {
        // 09:00 em São Paulo é 12:00 em UTC.
        $task = $this->task('0 9 * * *');

        $this->assertTrue($task->isDueAt(\Illuminate\Support\Carbon::parse('2026-03-10 12:00:00', 'UTC')));
        $this->assertFalse($task->isDueAt(\Illuminate\Support\Carbon::parse('2026-03-10 09:00:00', 'UTC')));
    }
}
