<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competencia extends Model
{
    protected $table = 'competencias';

    protected $fillable = [
        'contribuinte_id', 'periodo', 'status', 'num_recibo', 'data_envio', 'observacao',
    ];

    protected function casts(): array
    {
        return [
            'data_envio' => 'datetime',
        ];
    }

    public function contribuinte(): BelongsTo
    {
        return $this->belongsTo(Contribuinte::class, 'contribuinte_id');
    }

    public function arquivosGerados(): HasMany
    {
        return $this->hasMany(ArquivoGerado::class, 'competencia_id');
    }

    public function transmissoes(): HasMany
    {
        return $this->hasMany(Transmissao::class, 'competencia_id');
    }

    public function r2010(): HasMany
    {
        return $this->hasMany(R2010::class, 'competencia_id');
    }

    public function r2020(): HasMany
    {
        return $this->hasMany(R2020::class, 'competencia_id');
    }

    public function r2055(): HasMany
    {
        return $this->hasMany(R2055::class, 'competencia_id');
    }

    public function r2060(): HasMany
    {
        return $this->hasMany(R2060::class, 'competencia_id');
    }

    public function r4010(): HasMany
    {
        return $this->hasMany(R4010::class, 'competencia_id');
    }

    public function r4020(): HasMany
    {
        return $this->hasMany(R4020::class, 'competencia_id');
    }
}
