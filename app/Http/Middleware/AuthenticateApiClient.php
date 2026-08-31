<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticação da API.
 *
 * Credenciais (cadastradas no painel):
 *   X-Client-Id / X-Client-Secret — ou Authorization: Basic base64(id:secret)
 *
 * Escopo da loja (NÃO é cadastrado no painel):
 *   X-Store-Uuid — identifica de quem é cada tarefa. Toda leitura e escrita da
 *   API fica restrita ao store informado.
 */
class AuthenticateApiClient
{
    public function handle(Request $request, Closure $next): Response
    {
        [$clientId, $clientSecret] = $this->credentials($request);

        if (blank($clientId) || blank($clientSecret)) {
            return $this->unauthorized('Credenciais ausentes. Envie X-Client-Id e X-Client-Secret.');
        }

        $client = $this->resolveClient($clientId, $clientSecret);

        if ($client === null) {
            return $this->unauthorized('Credenciais inválidas.');
        }

        if (! $client->is_active) {
            return $this->forbidden('Cliente de API desativado.');
        }

        $storeUuid = trim((string) $request->header('X-Store-Uuid'));

        if ($storeUuid === '') {
            return $this->unprocessable('Header X-Store-Uuid é obrigatório.');
        }

        if (! Str::isUuid($storeUuid)) {
            return $this->unprocessable('Header X-Store-Uuid deve ser um UUID válido.');
        }

        if (! $client->canAccessStore($storeUuid)) {
            return $this->forbidden('Este cliente não tem acesso ao store informado.');
        }

        // Grava no máximo uma vez por minuto para não escrever a cada request.
        Cache::remember("api-client:{$client->id}:touched", 60, function () use ($client) {
            $client->forceFill(['last_used_at' => now()])->saveQuietly();

            return true;
        });

        $request->attributes->set('api_client', $client);
        $request->attributes->set('store_uuid', $storeUuid);

        return $next($request);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function credentials(Request $request): array
    {
        $clientId = $request->header('X-Client-Id');
        $clientSecret = $request->header('X-Client-Secret');

        if ($clientId === null && str_starts_with((string) $request->header('Authorization'), 'Basic ')) {
            $decoded = base64_decode(substr($request->header('Authorization'), 6), true);

            if (is_string($decoded) && str_contains($decoded, ':')) {
                [$clientId, $clientSecret] = explode(':', $decoded, 2);
            }
        }

        return [$clientId, $clientSecret];
    }

    /**
     * O hash do segredo é bcrypt; validar a cada request custa caro. O
     * resultado positivo fica em cache por poucos minutos, chaveado pelo
     * próprio segredo apresentado.
     */
    protected function resolveClient(string $clientId, string $clientSecret): ?ApiClient
    {
        $client = ApiClient::where('client_id', $clientId)->first();

        if ($client === null) {
            return null;
        }

        $cacheKey = 'api-client-auth:'.$client->id.':'.hash('sha256', $clientSecret);

        $valid = Cache::remember($cacheKey, now()->addMinutes(5), fn () => $client->secretMatches($clientSecret));

        if (! $valid) {
            Cache::forget($cacheKey);

            return null;
        }

        return $client;
    }

    protected function unauthorized(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 401);
    }

    protected function forbidden(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 403);
    }

    protected function unprocessable(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 422);
    }
}
