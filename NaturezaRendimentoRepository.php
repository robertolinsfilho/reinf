<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\NaturezaRendimento;

class NaturezaRendimentoRepository extends Repository
{
    protected string $table = 'naturezas_rendimento';

    protected string $modelClass = NaturezaRendimento::class;

    /**
     * Lista naturezas aplicáveis ao tipo, agrupadas.
     *
     * @param  string  $tipo  'pf' ou 'pj'
     */
    public function listarPorTipo(string $tipo): array
    {
        $coluna = $tipo === 'pj' ? 'aplicavel_pj' : 'aplicavel_pf';

        return $this->toRows(
            $this->newQuery()
                ->select(['codigo', 'descricao', 'grupo'])
                ->where('ativo', 1)
                ->where($coluna, 1)
                ->orderBy('grupo')
                ->orderBy('codigo')
                ->get()
        );
    }

    /**
     * Retorna agrupado para usar em <optgroup>:
     * ['Grupo A' => [['codigo'=>..., 'descricao'=>...], ...], 'Grupo B' => ...]
     */
    public function agrupadoPorTipo(string $tipo): array
    {
        $registros = $this->listarPorTipo($tipo);
        $agrupado = [];
        foreach ($registros as $r) {
            $agrupado[$r['grupo']][] = ['codigo' => $r['codigo'], 'descricao' => $r['descricao']];
        }

        return $agrupado;
    }

    public function findByCodigo(string $codigo): ?array
    {
        return $this->toRow($this->newQuery()->whereKey($codigo)->first());
    }
}
