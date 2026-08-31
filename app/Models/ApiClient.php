<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiClient extends Model
{
    /** @use HasFactory<\Database\Factories\ApiClientFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'client_id',
        'client_secret_hash',
        'secret_hint',
        'allowed_store_uuids',
        'is_active',
        'created_by_email',
    ];

    protected $hidden = ['client_secret_hash'];

    protected function casts(): array
    {
        return [
            'allowed_store_uuids' => 'array',
            'is_active' => 'bool',
            'last_used_at' => 'datetime',
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

    public function tasks(): HasMany
    {
        return $this->hasMany(ScheduledTask::class);
    }

    /**
     * Gera um novo par de credenciais. O segredo em claro só existe aqui —
     * o banco guarda apenas o hash.
     *
     * @return array{0: self, 1: string} [modelo, segredo em claro]
     */
    public static function issue(array $attributes): array
    {
        $secret = 'sk_'.Str::random(48);

        $client = static::create([
            ...$attributes,
            'client_id' => 'cid_'.Str::random(28),
            'client_secret_hash' => Hash::make($secret),
            'secret_hint' => substr($secret, -6),
        ]);

        return [$client, $secret];
    }

    /**
     * Substitui o segredo, devolvendo o novo valor em claro.
     */
    public function rotateSecret(): string
    {
        $secret = 'sk_'.Str::random(48);

        $this->forceFill([
            'client_secret_hash' => Hash::make($secret),
            'secret_hint' => substr($secret, -6),
        ])->save();

        return $secret;
    }

    public function secretMatches(string $secret): bool
    {
        return Hash::check($secret, $this->client_secret_hash);
    }

    public function canAccessStore(string $storeUuid): bool
    {
        $allowed = $this->allowed_store_uuids;

        return empty($allowed) || in_array($storeUuid, $allowed, true);
    }
}
