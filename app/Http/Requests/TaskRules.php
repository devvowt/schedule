<?php

namespace App\Http\Requests;

use App\Enums\ExecutionMode;
use App\Enums\TaskType;
use App\Rules\AllowedShellCommand;
use App\Rules\ValidCronExpression;
use Illuminate\Validation\Rule;

/**
 * Regras compartilhadas entre a API e o painel — o cadastro de tarefa é o
 * mesmo nos dois canais.
 */
class TaskRules
{
    /**
     * @param  bool  $partial  true em PATCH/edição parcial
     */
    public static function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],

            'type' => [$required, Rule::enum(TaskType::class)],

            // --- tipo http ---------------------------------------------------
            'url' => ['exclude_unless:type,http', $required, 'url:http,https', 'max:2000'],
            'method' => ['nullable', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])],
            'headers' => ['nullable', 'array'],
            'headers.*' => ['string', 'max:2000'],
            'body' => ['nullable'],
            'expected_status' => ['nullable', 'array'],
            'expected_status.*' => ['integer', 'between:100,599'],
            'verify_ssl' => ['nullable', 'boolean'],

            // --- tipo command ------------------------------------------------
            'command' => ['exclude_unless:type,command', $required, 'string', 'max:2000', new AllowedShellCommand],

            // --- agendamento --------------------------------------------------
            'cron_expression' => [$required, 'string', 'max:120', new ValidCronExpression],
            'timezone' => ['nullable', 'timezone'],

            // --- execução -----------------------------------------------------
            'execution_mode' => [$required, Rule::enum(ExecutionMode::class)],
            'execution_group' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._:-]+$/'],
            'sequence_order' => ['nullable', 'integer', 'between:0,65535'],
            'stagger_minutes' => ['nullable', 'integer', 'between:0,1440'],

            'timeout' => ['nullable', 'integer', 'between:1,'.config('scheduler.max_timeout')],
            'max_attempts' => ['nullable', 'integer', 'between:1,5'],
            'retry_delay_seconds' => ['nullable', 'integer', 'between:0,3600'],

            'is_active' => ['nullable', 'boolean'],
            'notify_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public static function messages(): array
    {
        return [
            'url.required' => 'Informe a URL que será chamada.',
            'url.url' => 'A URL precisa começar com http:// ou https://.',
            'command.required' => 'Informe o comando que será executado.',
            'execution_mode.required' => 'Escolha entre execução paralela (Octane) ou sequencial (fila).',
            'cron_expression.required' => 'Informe a expressão cron do agendamento.',
        ];
    }

    public static function attributes(): array
    {
        return [
            'name' => 'nome',
            'type' => 'tipo',
            'url' => 'URL',
            'method' => 'método',
            'command' => 'comando',
            'cron_expression' => 'expressão cron',
            'timezone' => 'fuso horário',
            'execution_mode' => 'modo de execução',
            'execution_group' => 'grupo',
            'sequence_order' => 'ordem',
            'stagger_minutes' => 'defasagem',
            'timeout' => 'timeout',
            'max_attempts' => 'tentativas',
        ];
    }
}
