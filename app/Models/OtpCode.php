<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $fillable = [
        'email',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
        'request_ip',
        'user_agent',
    ];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'attempts' => 'int',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isUsable(): bool
    {
        return ! $this->isExpired()
            && ! $this->isConsumed()
            && $this->attempts < config('scheduler.otp.max_attempts');
    }
}
