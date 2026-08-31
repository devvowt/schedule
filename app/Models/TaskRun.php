<?php

namespace App\Models;

use App\Enums\ExecutionMode;
use App\Enums\RunStatus;
use App\Enums\RunTrigger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskRun extends Model
{
    /** @use HasFactory<\Database\Factories\TaskRunFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'scheduled_task_id',
        'store_uuid',
        'status',
        'trigger',
        'execution_mode',
        'execution_group',
        'dedupe_key',
        'scheduled_for',
        'queued_at',
        'started_at',
        'finished_at',
        'duration_ms',
        'attempt',
        'exit_code',
        'http_status',
        'output',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'trigger' => RunTrigger::class,
            'execution_mode' => ExecutionMode::class,
            'scheduled_for' => 'datetime',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'int',
            'attempt' => 'int',
            'exit_code' => 'int',
            'http_status' => 'int',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ScheduledTask::class, 'scheduled_task_id');
    }

    public function scopeForStore(Builder $query, ?string $storeUuid): Builder
    {
        return $storeUuid === null
            ? $query->whereNull('store_uuid')
            : $query->where('store_uuid', $storeUuid);
    }

    public function durationForHumans(): string
    {
        if ($this->duration_ms === null) {
            return '—';
        }

        return $this->duration_ms < 1000
            ? $this->duration_ms.' ms'
            : number_format($this->duration_ms / 1000, 2, ',', '.').' s';
    }
}
