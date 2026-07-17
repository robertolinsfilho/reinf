<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transmissao extends Model
{
    protected $table = 'transmissoes';

    public $timestamps = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'competencia_id', 'usuario_id', 'tipo_operacao', 'evento', 'protocolo',
        'numero_recibo', 'xml_enviado', 'xml_retorno', 'codigo_retorno',
        'descricao_retorno', 'sucesso', 'tempo_resposta_ms', 'ambiente',
    ];

    protected function casts(): array
    {
        return [
            'sucesso' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(Competencia::class, 'competencia_id');
    }
}
