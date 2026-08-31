<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Execução paralela
    |--------------------------------------------------------------------------
    |
    | Tarefas marcadas como "parallel" são despachadas para os task workers do
    | Laravel Octane (Swoole), que as executam simultaneamente. O driver "queue"
    | existe como alternativa (e como fallback automático quando o servidor
    | Octane não responde): as tarefas vão para a fila "parallel", processada
    | por vários workers concorrentes.
    |
    | Suportado: "octane", "queue"
    |
    */

    'parallel' => [
        'driver' => env('SCHEDULER_PARALLEL_DRIVER', 'octane'),

        'octane' => [
            'host' => env('SCHEDULER_OCTANE_HOST', 'octane'),
            'port' => env('SCHEDULER_OCTANE_PORT', '8000'),
        ],

        // Nº máximo de tarefas enviadas ao Octane em uma mesma chamada.
        'chunk' => (int) env('SCHEDULER_PARALLEL_CHUNK', 10),

        // Fila usada pelo driver "queue" e pelo fallback.
        'queue' => env('SCHEDULER_PARALLEL_QUEUE', 'parallel'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Execução sequencial
    |--------------------------------------------------------------------------
    |
    | Tarefas "sequential" do mesmo grupo nunca rodam ao mesmo tempo. Quando o
    | grupo não define defasagem, as tarefas entram encadeadas (Bus::chain) na
    | fila sequencial — a próxima só começa quando a anterior termina. Quando há
    | defasagem, cada tarefa é despachada com atraso de N minutos sobre a
    | anterior.
    |
    */

    'sequential' => [
        'queue' => env('SCHEDULER_SEQUENTIAL_QUEUE', 'sequential'),

        // Grupo usado quando a tarefa não informa nenhum.
        'default_group' => env('SCHEDULER_DEFAULT_GROUP', 'default'),

        // Trava de sobreposição por grupo (segundos).
        'lock_seconds' => (int) env('SCHEDULER_LOCK_SECONDS', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Padrões de execução
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'timeout' => (int) env('SCHEDULER_DEFAULT_TIMEOUT', 60),
        'max_attempts' => (int) env('SCHEDULER_DEFAULT_MAX_ATTEMPTS', 1),
        'timezone' => env('SCHEDULER_DEFAULT_TIMEZONE', 'America/Sao_Paulo'),
    ],

    // Teto absoluto de duração de uma execução, em segundos.
    'max_timeout' => (int) env('SCHEDULER_MAX_TIMEOUT', 900),

    // Tamanho máximo (bytes) da saída guardada em task_runs.output.
    'output_limit' => (int) env('SCHEDULER_OUTPUT_LIMIT', 65535),

    // Dias de retenção do histórico de execuções.
    'run_retention_days' => (int) env('SCHEDULER_RUN_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Tarefas do tipo "command"
    |--------------------------------------------------------------------------
    |
    | Comandos de shell são executados dentro do container. Com a allowlist
    | ativa, apenas binários listados podem ser invocados — recomendado sempre
    | que a API estiver aberta a terceiros.
    |
    */

    'commands' => [
        'allowlist_enabled' => env('SCHEDULER_COMMAND_ALLOWLIST_ENABLED', true),

        'allowlist' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SCHEDULER_COMMAND_ALLOWLIST', 'php,curl,echo,ls,artisan'))
        ))),

        // Diretório de trabalho dos comandos.
        'working_directory' => env('SCHEDULER_COMMAND_CWD', base_path()),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tarefas do tipo "http"
    |--------------------------------------------------------------------------
    */

    'http' => [
        'user_agent' => env('SCHEDULER_HTTP_USER_AGENT', 'VowtScheduler/1.0'),
        'allow_insecure_ssl' => (bool) env('SCHEDULER_HTTP_ALLOW_INSECURE', false),
        'success_statuses' => [200, 201, 202, 204],
    ],

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    */

    'api' => [
        // Requisições por minuto, contadas por cliente (ou por IP sem credencial).
        'rate_limit' => (int) env('SCHEDULER_API_RATE_LIMIT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Painel / OTP
    |--------------------------------------------------------------------------
    */

    'panel' => [
        // Somente estes e-mails podem solicitar código OTP.
        'allowed_emails' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('PANEL_ALLOWED_EMAILS', 'suporte@vowt.com.br'))
        ))),
    ],

    'otp' => [
        'length' => (int) env('OTP_LENGTH', 6),
        'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 10),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        // Intervalo mínimo entre dois envios para o mesmo e-mail (segundos).
        'resend_seconds' => (int) env('OTP_RESEND_SECONDS', 60),
        'session_lifetime_minutes' => (int) env('PANEL_SESSION_LIFETIME', 480),
    ],
];
