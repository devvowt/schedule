<?php

namespace App\Services\Execution;

use App\Models\ScheduledTask;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Dispara a URL configurada na tarefa e considera sucesso os status listados
 * em scheduler.http.success_statuses (ou o range 2xx quando a tarefa não
 * restringe).
 */
class HttpTaskExecutor implements TaskExecutor
{
    public function execute(ScheduledTask $task): ExecutionResult
    {
        $payload = $task->payload;

        $url = (string) ($payload['url'] ?? '');
        $method = strtoupper((string) ($payload['method'] ?? 'GET'));
        $headers = (array) ($payload['headers'] ?? []);
        $body = $payload['body'] ?? null;
        $verifySsl = (bool) ($payload['verify_ssl'] ?? ! config('scheduler.http.allow_insecure_ssl'));

        if ($url === '') {
            return ExecutionResult::failed('A tarefa não possui URL configurada.');
        }

        $request = Http::withHeaders($headers)
            ->withUserAgent($headers['User-Agent'] ?? config('scheduler.http.user_agent'))
            ->timeout($task->timeout)
            ->connectTimeout(min(15, $task->timeout))
            ->withOptions(['verify' => $verifySsl])
            ->withoutRedirecting();

        $options = [];

        if ($body !== null && $body !== '') {
            $options = is_array($body)
                ? ['json' => $body]
                : ['body' => (string) $body];
        }

        $startedAt = microtime(true);

        try {
            $response = $request->send($method, $url, $options);
        } catch (ConnectionException $e) {
            $elapsed = microtime(true) - $startedAt;

            // O cliente HTTP não distingue timeout de recusa de conexão pelo
            // tipo da exceção; a duração aproximada resolve na prática.
            return $elapsed >= $task->timeout - 1
                ? ExecutionResult::timeout("Timeout de {$task->timeout}s ao chamar {$url}.")
                : ExecutionResult::failed('Falha de conexão: '.$e->getMessage());
        } catch (Throwable $e) {
            return ExecutionResult::failed('Erro inesperado: '.$e->getMessage());
        }

        $status = $response->status();
        $output = $response->body();

        $accepted = (array) ($payload['expected_status'] ?? []);

        $ok = $accepted !== []
            ? in_array($status, array_map('intval', $accepted), true)
            : ($status >= 200 && $status < 300);

        return $ok
            ? ExecutionResult::success($output, null, $status)
            : ExecutionResult::failed("HTTP {$status} retornado por {$url}.", $output, null, $status);
    }
}
