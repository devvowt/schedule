<?php

namespace Database\Factories;

use App\Enums\ExecutionMode;
use App\Enums\RunStatus;
use App\Enums\RunTrigger;
use App\Models\ScheduledTask;
use App\Models\TaskRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskRun>
 */
class TaskRunFactory extends Factory
{
    protected $model = TaskRun::class;

    public function definition(): array
    {
        return [
            'scheduled_task_id' => ScheduledTask::factory(),
            'store_uuid' => $this->faker->uuid(),
            'status' => RunStatus::Queued->value,
            'trigger' => RunTrigger::Schedule->value,
            'execution_mode' => ExecutionMode::Sequential->value,
            'execution_group' => 'default',
            'scheduled_for' => now(),
            'queued_at' => now(),
            'attempt' => 1,
        ];
    }
}
