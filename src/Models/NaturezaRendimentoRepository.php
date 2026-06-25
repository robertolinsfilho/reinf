<?php

namespace App\Models;

class NaturezaRendimentoRepository extends Repository
{
    protected string $table = 'naturezas_rendimento';

    /**
     * Lista naturezas aplicáveis ao tipo, agrupadas.
     * @param string $tipo 'pf' ou 'pj'
     */
    public function listarPorTipo(string $tipo): array
    {
        $coluna = $tipo === 'pj' ? 'aplicavel_pj' : 'aplicavel_pf';
        return $this->query("
            SELECT codigo, descricao, grupo
            FROM naturezas_rendimento
            WHERE ativo = 1 AND {$coluna} = 1
            ORDER BY grupo, codigo
        ");
    }

    /**
     * Retorna agrupado para usar em <optgroup>:
     * ['Grupo A' => [['codigo'=>..., 'descricao'=>...], ...], 'Grupo B' => ...]
     */
    public function agrupadoPorTipo(string $tipo): array
    {
        $registros = $this->listarPorTipo($tipo);
        $agrupado  = [];
        foreach ($registros as $r) {
            $agrupado[$r['grupo']][] = ['codigo' => $r['codigo'], 'descricao' => $r['descricao']];
        }
        return $agrupado;
    }

    public function findByCodigo(string $codigo): ?array
    {
        return $this->queryOne("SELECT * FROM naturezas_rendimento WHERE codigo = ?", [$codigo]);
    }
}