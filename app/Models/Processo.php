<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Processo extends Model
{
    protected $table = 'r1070_processos';

    protected $fillable = [
        'contribuinte_id', 'tipo_processo', 'numero_processo', 'indicador_autoria',
        'uf_vara', 'cod_municipio', 'id_vara', 'indicador_susp_exig', 'data_decisao',
        'indicador_deposito', 'data_inclusao', 'descricao', 'status',
    ];

    protected function casts(): array
    {
        return [
            'data_decisao' => 'date',
            'data_inclusao' => 'date',
        ];
    }

    public function contribuinte(): BelongsTo
    {
        return $this->belongsTo(Contribuinte::class, 'contribuinte_id');
    }
}
