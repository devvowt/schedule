<?php

namespace Tests\Feature\Api;

use App\Models\ApiClient;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    protected function credentials(array $overrides = []): array
    {
        [$client, $secret] = ApiClient::issue([
            'name' => 'Integração de teste',
            'is_active' => true,
            ...$overrides,
        ]);

        return [$client, [
            'X-Client-Id' => $client->client_id,
            'X-Client-Secret' => $secret,
            'X-Store-Uuid' => (string) Str::uuid(),
        ]];
    }

    public function test_recusa_requisicao_sem_credenciais(): void
    {
        $this->getJson('/api/v1/tasks')->assertStatus(401);
    }

    public function test_recusa_segredo_invalido(): void
    {
        [$client] = $this->credentials();

        $this->getJson('/api/v1/tasks', [
            'X-Client-Id' => $client->client_id,
            'X-Client-Secret' => 'sk_errado',
            'X-Store-Uuid' => (string) Str::uuid(),
        ])->assertStatus(401);
    }

    public function test_exige_store_uuid(): void
    {
        [, $headers] = $this->credentials();
        unset($headers['X-Store-Uuid']);

        $this->getJson('/api/v1/tasks', $headers)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Header X-Store-Uuid é obrigatório.');
    }

    public function test_exige_store_uuid_em_formato_valido(): void
    {
        [, $headers] = $this->credentials();
        $headers['X-Store-Uuid'] = 'loja-123';

        $this->getJson('/api/v1/tasks', $headers)->assertStatus(422);
    }

    public function test_recusa_cliente_desativado(): void
    {
        [$client, $headers] = $this->credentials();
        $client->forceFill(['is_active' => false])->save();

        $this->getJson('/api/v1/tasks', $headers)->assertStatus(403);
    }

    public function test_recusa_store_fora_da_lista_permitida(): void
    {
        [, $headers] = $this->credentials(['allowed_store_uuids' => [(string) Str::uuid()]]);

        $this->getJson('/api/v1/tasks', $headers)->assertStatus(403);
    }

    public function test_aceita_autenticacao_basic(): void
    {
        [$client, $headers] = $this->credentials();

        // O segredo em claro não fica no modelo; reemitimos para o teste.
        $secret = $client->rotateSecret();

        $this->getJson('/api/v1/tasks', [
            'Authorization' => 'Basic '.base64_encode($client->client_id.':'.$secret),
            'X-Store-Uuid' => $headers['X-Store-Uuid'],
        ])->assertOk();
    }

    public function test_health_check_e_publico(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJsonPath('status', 'ok');
    }
}
