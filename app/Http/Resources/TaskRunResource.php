<?php

namespace App\Http\Resources;

use App\Models\TaskRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TaskRun */
class TaskRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'task_uuid' => $this->whenLoaded('task', fn () => $this->task->uuid),
            'status' => $this->status->value,
            'trigger' => $this->trigger->value,
            'execution_mode' => $this->execution_mode?->value,
            'execution_group' => $this->execution_group,
            'attempt' => $this->attempt,
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'duration_ms' => $this->duration_ms,
            'http_status' => $this->http_status,
            'exit_code' => $this->exit_code,
            'error' => $this->error,
            'output' => $this->when($request->boolean('with_output'), fn () => $this->output),
        ];
    }
}
