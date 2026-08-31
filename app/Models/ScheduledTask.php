<?php

namespace App\Models;

use App\Enums\ExecutionMode;
use App\Enums\TaskType;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class ScheduledTask extends Model
{
    /** @use HasFactory<\Database\Factories\ScheduledTaskFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'uuid',
        'store_uuid',
        'name',
        'description',
        'type',
        'payload',
        'cron_expression',
        'timezone',
        'execution_mode',
        'execution_group',
        'sequence_order',
        'stagger_minutes',
        'timeout',
        'max_attempts',
        'retry_delay_seconds',
        'is_active',
        'notify_email',
        'created_via',
        'api_client_id',
        'created_by_email',
    ];

    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
            'execution_mode' => ExecutionMode::class,
            'payload' => 'array',
            'is_active' => 'bool',
            'sequence_order' => 'int',
            'stagger_minutes' => 'int',
            'timeout' => 'int',
            'max_attempts' => 'int',
            'retry_delay_seconds' => 'int',
            'consecutive_failures' => 'int',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    /**
     * O UUID público é gerado em "uuid"; a chave primária continua sendo o id.
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function runs(): HasMany
    {
        return $this->hasMany(TaskRun::class);
    }

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForStore(Builder $query, ?string $storeUuid): Builder
    {
        return $storeUuid === null
            ? $query->whereNull('store_uuid')
            : $query->where('store_uuid', $storeUuid);
    }

    public function isParallel(): bool
    {
        return $this->execution_mode === ExecutionMode::Parallel;
    }

    public function isSequential(): bool
    {
        return $this->execution_mode === ExecutionMode::Sequential;
    }

    public function groupKey(): string
    {
        return $this->execution_group ?: config('scheduler.sequential.default_group');
    }

    public function cron(): CronExpression
    {
        return new CronExpression($this->cron_expression);
    }

    /**
     * A tarefa está prevista para o minuto informado?
     */
    public function isDueAt(Carbon $moment): bool
    {
        // O objeto DateTime carrega o fuso; passar string faria a leitura
        // depender do timezone padrão do processo, que varia entre o container
        // web, o worker e a suíte de testes.
        return $this->cron()->isDue(
            $moment->copy()->setTimezone($this->timezone)->toDateTime(),
            $this->timezone
        );
    }

    public function nextRunAt(?Carbon $from = null): Carbon
    {
        $from ??= Carbon::now();

        return Carbon::instance(
            $this->cron()->getNextRunDate($from->copy()->setTimezone($this->timezone)->toDateTime(), 0, false, $this->timezone)
        )->setTimezone(config('app.timezone') ?: date_default_timezone_get());
    }

    public function refreshNextRun(): void
    {
        $this->forceFill(['next_run_at' => $this->nextRunAt()])->saveQuietly();
    }

    /** Descrição curta do alvo, para listagens. */
    public function target(): string
    {
        return $this->type === TaskType::Http
            ? strtoupper($this->payload['method'] ?? 'GET').' '.($this->payload['url'] ?? '')
            : (string) ($this->payload['command'] ?? '');
    }
}
