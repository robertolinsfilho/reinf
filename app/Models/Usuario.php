<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable
{
    protected $table = 'usuarios';

    protected $fillable = [
        'nome',
        'email',
        'senha',
        'perfil',
        'ativo',
        'force_password_change',
        'trial_expira',
        'ultimo_acesso',
    ];

    protected $hidden = [
        'senha',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'force_password_change' => 'boolean',
            'trial_expira' => 'date',
            'ultimo_acesso' => 'datetime',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->senha;
    }

    public function getAuthPasswordName(): string
    {
        return 'senha';
    }

    public function isAdmin(): bool
    {
        return $this->perfil === 'admin';
    }

    public function contribuintes(): HasMany
    {
        return $this->hasMany(Contribuinte::class, 'usuario_id');
    }
}
