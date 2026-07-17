<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificado extends Model
{
    protected $table = 'certificados';

    public $timestamps = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'contribuinte_id', 'nome_arquivo', 'caminho', 'senha_encrypted',
        'cnpj_certificado', 'titular', 'validade', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'validade' => 'date',
            'ativo' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function contribuinte(): BelongsTo
    {
        return $this->belongsTo(Contribuinte::class, 'contribuinte_id');
    }
}
