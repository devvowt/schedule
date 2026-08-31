<?php

namespace Database\Factories;

use App\Models\ApiClient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiClient>
 */
class ApiClientFactory extends Factory
{
    protected $model = ApiClient::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'description' => null,
            'client_id' => 'cid_'.Str::random(28),
            'client_secret_hash' => Hash::make('sk_teste'),
            'secret_hint' => 'teste',
            'allowed_store_uuids' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
