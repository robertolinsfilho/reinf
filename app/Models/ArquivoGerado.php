<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArquivoGerado extends Model
{
    protected $table = 'arquivos_gerados';

    public $timestamps = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'competencia_id', 'usuario_id', 'evento', 'id_evento', 'nome_arquivo', 'caminho',
        'tamanho', 'hash_md5', 'xml_conteudo', 'assinado', 'ind_retif', 'nr_recibo_original',
        'protocolo', 'nr_recibo_retornado',
    ];

    protected function casts(): array
    {
        return [
            'assinado' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(Competencia::class, 'competencia_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
