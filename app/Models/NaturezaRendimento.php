<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NaturezaRendimento extends Model
{
    protected $table = 'naturezas_rendimento';

    protected $primaryKey = 'codigo';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'codigo', 'descricao', 'aplicavel_pf', 'aplicavel_pj', 'grupo', 'ativo',
        'tributo', 'tabela_origem',
    ];

    protected function casts(): array
    {
        return [
            'aplicavel_pf' => 'boolean',
            'aplicavel_pj' => 'boolean',
            'ativo' => 'boolean',
        ];
    }
}
