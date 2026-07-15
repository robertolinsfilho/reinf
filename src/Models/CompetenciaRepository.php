<?php

namespace App\Models;

class CompetenciaRepository extends Repository
{
    protected string $table = 'competencias';

    public function findWithContribuinte(int $id, int $userId): ?array
    {
        return $this->queryOne("
            SELECT c.*, co.razao_social, co.cnpj, co.classificacao_tributos
            FROM competencias c
            JOIN contribuintes co ON co.id = c.contribuinte_id
            WHERE c.id = ? AND co.usuario_id = ?
        ", [$id, $userId]);
    }

    public function listByUser(int $userId, ?int $contribuinteId = null): array
    {
        $sql = "
            SELECT c.*, co.razao_social, co.cnpj,
                (SELECT COUNT(*) FROM r2010 WHERE competencia_id = c.id) as total_r2010,
                (SELECT COUNT(*) FROM r2020 WHERE competencia_id = c.id) as total_r2020,
                (SELECT COUNT(*) FROM r2055 WHERE competencia_id = c.id) as total_r2055,
                (SELECT COUNT(*) FROM r2060 WHERE competencia_id = c.id) as total_r2060,
                (SELECT COUNT(*) FROM r4010 WHERE competencia_id = c.id) as total_r4010,
                (SELECT COUNT(*) FROM r4020 WHERE competencia_id = c.id) as total_r4020
            FROM competencias c
            JOIN contribuintes co ON co.id = c.contribuinte_id
            WHERE co.usuario_id = ?
        ";
        $params = [$userId];

        if ($contribuinteId) {
            $sql .= " AND co.id = ?";
            $params[] = $contribuinteId;
        }
        $sql .= " ORDER BY c.periodo DESC";

        return $this->query($sql, $params);
    }

    public function exists(int $contribuinteId, string $periodo): bool
    {
        return (bool) $this->queryOne(
            "SELECT id FROM competencias WHERE contribuinte_id = ? AND periodo = ?",
            [$contribuinteId, $periodo]
        );
    }

    /**
     * Retorna id da competência; cria se ainda não existir.
     */
    public function findOrCreate(int $contribuinteId, string $periodo): array
    {
        $periodo = substr(trim($periodo), 0, 7);
        if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            throw new \InvalidArgumentException("Período inválido: {$periodo}");
        }

        $existente = $this->queryOne(
            "SELECT * FROM competencias WHERE contribuinte_id = ? AND periodo = ?",
            [$contribuinteId, $periodo]
        );
        if ($existente) {
            return ['competencia' => $existente, 'criada' => false];
        }

        $id = $this->insert([
            'contribuinte_id' => $contribuinteId,
            'periodo'         => $periodo,
            'status'          => 'aberto',
        ]);

        $nova = $this->find($id);
        return ['competencia' => $nova, 'criada' => true];
    }

    public function marcarTransmitido(int $id, string $protocolo): void
    {
        $this->update($id, [
            'status'    => 'transmitido',
            'data_envio' => date('Y-m-d H:i:s'),
            'num_recibo' => $protocolo,
        ]);
    }
}