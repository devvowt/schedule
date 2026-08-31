<?php

namespace Database\Factories;

use App\Enums\ExecutionMode;
use App\Enums\TaskType;
use App\Models\ScheduledTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledTask>
 */
class ScheduledTaskFactory extends Factory
{
    protected $model = ScheduledTask::class;

    public function definition(): array
    {
        return [
            'store_uuid' => $this->faker->uuid(),
            'name' => $this->faker->unique()->sentence(3),
            'description' => $this->faker->sentence(),
            'type' => TaskType::Http->value,
            'payload' => [
                'url' => 'https://example.test/'.$this->faker->slug(),
                'method' => 'GET',
                'headers' => [],
                'body' => null,
                'expected_status' => [],
                'verify_ssl' => true,
            ],
            'cron_expression' => '0 * * * *',
            'timezone' => 'America/Sao_Paulo',
            'execution_mode' => ExecutionMode::Sequential->value,
            'execution_group' => 'default',
            'sequence_order' => 0,
            'stagger_minutes' => 0,
            'timeout' => 30,
            'max_attempts' => 1,
            'retry_delay_seconds' => 60,
            'is_active' => true,
            'created_via' => 'panel',
        ];
    }

    public function parallel(): static
    {
        return $this->state(fn () => ['execution_mode' => ExecutionMode::Parallel->value]);
    }

    public function sequential(string $group = 'default', int $order = 0, int $stagger = 0): static
    {
        return $this->state(fn () => [
            'execution_mode' => ExecutionMode::Sequential->value,
            'execution_group' => $group,
            'sequence_order' => $order,
            'stagger_minutes' => $stagger,
        ]);
    }

    public function command(string $command = 'echo ola'): static
    {
        return $this->state(fn () => [
            'type' => TaskType::Command->value,
            'payload' => ['command' => $command],
        ]);
    }

    public function everyMinute(): static
    {
        return $this->state(fn () => ['cron_expression' => '* * * * *']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
