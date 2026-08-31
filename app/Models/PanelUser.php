<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class PanelUser extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\PanelUserFactory> */
    use HasFactory, HasUuids, Notifiable;

    protected $table = 'panel_users';

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
            'last_login_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
