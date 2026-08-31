<?php

namespace App\Http\Resources;

use App\Models\ScheduledTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ScheduledTask */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'store_uuid' => $this->store_uuid,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'payload' => $this->payload,
            'schedule' => [
                'cron_expression' => $this->cron_expression,
                'timezone' => $this->timezone,
                'next_run_at' => $this->next_run_at?->toIso8601String(),
                'last_run_at' => $this->last_run_at?->toIso8601String(),
                'last_status' => $this->last_status,
            ],
            'execution' => [
                'mode' => $this->execution_mode->value,
                'group' => $this->execution_group,
                'sequence_order' => $this->sequence_order,
                'stagger_minutes' => $this->stagger_minutes,
                'timeout' => $this->timeout,
                'max_attempts' => $this->max_attempts,
                'retry_delay_seconds' => $this->retry_delay_seconds,
            ],
            'is_active' => $this->is_active,
            'consecutive_failures' => $this->consecutive_failures,
            'notify_email' => $this->notify_email,
            'created_via' => $this->created_via,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'runs' => TaskRunResource::collection($this->whenLoaded('runs')),
        ];
    }
}
