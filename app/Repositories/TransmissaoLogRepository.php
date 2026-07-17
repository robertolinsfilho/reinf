<?php

namespace App\Repositories;

class TransmissaoLogRepository extends Repository
{
    protected string $table = 'transmissoes';

    public function historicoByUser(int $userId, int $limit = 50): array
    {
        return $this->query("
            SELECT t.*, c.periodo, ct.cnpj, ct.razao_social
            FROM transmissoes t
            JOIN competencias c ON c.id = t.competencia_id
            JOIN contribuintes ct ON ct.id = c.contribuinte_id
            WHERE t.usuario_id = ? OR ct.usuario_id = ?
            ORDER BY t.created_at DESC
            LIMIT ?
        ", [$userId, $userId, $limit]);
    }

    public function registrarEnvio(int $compId, int $userId, string $evento, array $resultado): int
    {
        return $this->insert([
            'competencia_id'    => $compId,
            'usuario_id'        => $userId,
            'tipo_operacao'     => 'envio',
            'evento'            => $evento,
            'protocolo'         => $resultado['protocolo'] ?? '',
            'xml_enviado'       => $resultado['xml_enviado'] ?? '',
            'xml_retorno'       => $resultado['xml_retorno'] ?? '',
            'codigo_retorno'    => $resultado['codigo_retorno'] ?? '',
            'descricao_retorno' => $resultado['desc_retorno'] ?? $resultado['erro'] ?? '',
            'sucesso'           => ($resultado['sucesso'] ?? false) ? 1 : 0,
            'tempo_resposta_ms' => $resultado['tempo_ms'] ?? 0,
            'ambiente'          => $resultado['ambiente'] ?? 2,
        ]);
    }

    public function registrarConsulta(int $compId, int $userId, string $protocolo, array $resultado, int $ambiente): int
    {
        return $this->insert([
            'competencia_id'    => $compId,
            'usuario_id'        => $userId,
            'tipo_operacao'     => 'consulta',
            'evento'            => '',
            'protocolo'         => $protocolo,
            'numero_recibo'     => ($resultado['recibos'][0] ?? null),
            'xml_retorno'       => $resultado['xml_retorno'] ?? '',
            'codigo_retorno'    => $resultado['codigo_retorno'] ?? '',
            'descricao_retorno' => $resultado['desc_retorno'] ?? '',
            'sucesso'           => ($resultado['sucesso'] ?? false) ? 1 : 0,
            'tempo_resposta_ms' => $resultado['tempo_ms'] ?? 0,
            'ambiente'          => $ambiente,
        ]);
    }

    /**
     * @param list<int|string> $ids
     */
    public function excluirForUser(array $ids, int $userId): int
    {
        if (empty($ids)) {
            return 0;
        }
        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("
            DELETE t FROM transmissoes t
            JOIN competencias c ON c.id = t.competencia_id
            JOIN contribuintes co ON co.id = c.contribuinte_id
            WHERE t.id IN ({$placeholders})
              AND (t.usuario_id = ? OR co.usuario_id = ?)
        ");
        $stmt->execute([...$ids, $userId, $userId]);
        return $stmt->rowCount();
    }
}