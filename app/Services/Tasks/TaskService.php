<?php

namespace App\Services\Tasks;

use App\Enums\ExecutionMode;
use App\Enums\TaskType;
use App\Models\ApiClient;
use App\Models\ScheduledTask;
use Illuminate\Support\Arr;

/**
 * Traduz o payload de cadastro (API ou painel) em uma tarefa agendada. Os dois
 * canais compartilham este ponto para que as regras não divirjam.
 */
class TaskService
{
    public function create(array $data, ?string $storeUuid = null, ?ApiClient $client = null, ?string $email = null): ScheduledTask
    {
        $task = ScheduledTask::create([
            ...$this->attributes($data),
            'store_uuid' => $storeUuid,
            'created_via' => $client !== null ? 'api' : 'panel',
            'api_client_id' => $client?->id,
            'created_by_email' => $email,
        ]);

        $task->refreshNextRun();

        return $task->refresh();
    }

    public function update(ScheduledTask $task, array $data): ScheduledTask
    {
        $attributes = $this->attributes($data, $task);

        $task->update($attributes);
        $task->refreshNextRun();

        return $task->refresh();
    }

    /**
     * Monta os atributos do modelo, incluindo o payload específico do tipo.
     */
    protected function attributes(array $data, ?ScheduledTask $existing = null): array
    {
        $type = isset($data['type'])
            ? TaskType::from($data['type'])
            : ($existing?->type ?? TaskType::Http);

        $attributes = array_filter([
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'type' => $type->value,
            'cron_expression' => $data['cron_expression'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'execution_mode' => $data['execution_mode'] ?? null,
            'execution_group' => $data['execution_group'] ?? null,
            'notify_email' => $data['notify_email'] ?? null,
        ], fn ($value) => $value !== null);

        // Campos numéricos/booleanos precisam aceitar 0 e false.
        foreach (['sequence_order', 'stagger_minutes', 'timeout', 'max_attempts', 'retry_delay_seconds'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $attributes[$field] = (int) $data[$field];
            }
        }

        if (array_key_exists('is_active', $data) && $data['is_active'] !== null) {
            $attributes['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        // Padrões de quem não informou nada.
        if ($existing === null) {
            $attributes += [
                'timezone' => config('scheduler.defaults.timezone'),
                'execution_mode' => ExecutionMode::Sequential->value,
                'execution_group' => config('scheduler.sequential.default_group'),
                'timeout' => config('scheduler.defaults.timeout'),
                'max_attempts' => config('scheduler.defaults.max_attempts'),
            ];
        }

        $payload = $this->payload($type, $data, $existing);

        if ($payload !== null) {
            $attributes['payload'] = $payload;
        }

        return $attributes;
    }

    protected function payload(TaskType $type, array $data, ?ScheduledTask $existing): ?array
    {
        $current = $existing?->payload ?? [];

        if ($type === TaskType::Command) {
            $command = $data['command'] ?? ($existing?->type === TaskType::Command ? ($current['command'] ?? null) : null);

            return $command === null ? null : ['command' => $command];
        }

        $payload = [
            'url' => $data['url'] ?? $current['url'] ?? null,
            'method' => strtoupper($data['method'] ?? $current['method'] ?? 'GET'),
            'headers' => $data['headers'] ?? $current['headers'] ?? [],
            'body' => Arr::get($data, 'body', $current['body'] ?? null),
            'expected_status' => $data['expected_status'] ?? $current['expected_status'] ?? [],
            'verify_ssl' => array_key_exists('verify_ssl', $data)
                ? filter_var($data['verify_ssl'], FILTER_VALIDATE_BOOLEAN)
                : ($current['verify_ssl'] ?? true),
        ];

        return $payload['url'] === null ? null : $payload;
    }
}
