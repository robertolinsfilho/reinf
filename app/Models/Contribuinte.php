<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contribuinte extends Model
{
    protected $table = 'contribuintes';

    protected $fillable = [
        'usuario_id', 'cnpj', 'razao_social', 'nome_fantasia', 'tipo_contribuinte',
        'classificacao_tributos', 'ie', 'cnae_principal', 'logradouro', 'municipio',
        'uf', 'cep', 'email', 'telefone', 'nome_contato', 'cpf_contato',
        'ind_escrituracao', 'ind_desoneracao', 'ind_acordo_isen_multa', 'ind_sit_pj',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function competencias(): HasMany
    {
        return $this->hasMany(Competencia::class, 'contribuinte_id');
    }

    public function processos(): HasMany
    {
        return $this->hasMany(Processo::class, 'contribuinte_id');
    }

    public function certificados(): HasMany
    {
        return $this->hasMany(Certificado::class, 'contribuinte_id');
    }
}
