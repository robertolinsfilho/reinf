<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Transmissao;

class TransmissaoLogRepository extends Repository
{
    protected string $table = 'transmissoes';

    protected string $modelClass = Transmissao::class;

    public function historicoByUser(int $userId, int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));

        return $this->toRows(
            $this->newQuery()
                ->from('transmissoes as t')
                ->join('competencias as c', 'c.id', '=', 't.competencia_id')
                ->join('contribuintes as ct', 'ct.id', '=', 'c.contribuinte_id')
                ->where(function ($q) use ($userId) {
                    $q->where('t.usuario_id', $userId)
                        ->orWhere('ct.usuario_id', $userId);
                })
                ->select('t.*', 'c.periodo', 'ct.cnpj', 'ct.razao_social')
                ->orderByDesc('t.created_at')
                ->limit($limit)
                ->get()
        );
    }

    public function registrarEnvio(int $compId, int $userId, string $evento, array $resultado): int
    {
        return $this->insert([
            'competencia_id' => $compId,
            'usuario_id' => $userId,
            'tipo_operacao' => 'envio',
            'evento' => $evento,
            'protocolo' => $resultado['protocolo'] ?? '',
            'xml_enviado' => $resultado['xml_enviado'] ?? '',
            'xml_retorno' => $resultado['xml_retorno'] ?? '',
            'codigo_retorno' => $resultado['codigo_retorno'] ?? '',
            'descricao_retorno' => $resultado['desc_retorno'] ?? $resultado['erro'] ?? '',
            'sucesso' => ($resultado['sucesso'] ?? false) ? 1 : 0,
            'tempo_resposta_ms' => $resultado['tempo_ms'] ?? 0,
            'ambiente' => $resultado['ambiente'] ?? 2,
        ]);
    }

    public function registrarConsulta(int $compId, int $userId, string $protocolo, array $resultado, int $ambiente): int
    {
        return $this->insert([
            'competencia_id' => $compId,
            'usuario_id' => $userId,
            'tipo_operacao' => 'consulta',
            'evento' => '',
            'protocolo' => $protocolo,
            'numero_recibo' => ($resultado['recibos'][0] ?? null),
            'xml_retorno' => $resultado['xml_retorno'] ?? '',
            'codigo_retorno' => $resultado['codigo_retorno'] ?? '',
            'descricao_retorno' => $resultado['desc_retorno'] ?? '',
            'sucesso' => ($resultado['sucesso'] ?? false) ? 1 : 0,
            'tempo_resposta_ms' => $resultado['tempo_ms'] ?? 0,
            'ambiente' => $ambiente,
        ]);
    }

    /**
     * @param  list<int|string>  $ids
     */
    public function excluirForUser(array $ids, int $userId): int
    {
        if ($ids === []) {
            return 0;
        }
        $ids = array_map('intval', $ids);

        $idsOk = $this->newQuery()
            ->from('transmissoes as t')
            ->join('competencias as c', 'c.id', '=', 't.competencia_id')
            ->join('contribuintes as co', 'co.id', '=', 'c.contribuinte_id')
            ->whereIn('t.id', $ids)
            ->where(function ($q) use ($userId) {
                $q->where('t.usuario_id', $userId)
                    ->orWhere('co.usuario_id', $userId);
            })
            ->pluck('t.id');

        if ($idsOk->isEmpty()) {
            return 0;
        }

        return (int) $this->newQuery()->whereIn('id', $idsOk)->delete();
    }
}
