<?php

namespace App\Models;

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
            'descricao_retorno' => $resultado['desc_retorno'] ?? '',
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
}