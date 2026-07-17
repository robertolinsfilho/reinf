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

    /**
     * Competências agrupadas por contribuinte (para telas Gerar/Transmissão).
     *
     * @return list<array{contribuinte_id:int,razao_social:string,cnpj:string,competencias:list<array>}>
     */
    public function listGroupedByContribuinte(int $userId): array
    {
        $groups = [];
        foreach ($this->listByUser($userId) as $c) {
            $id = (int) $c['contribuinte_id'];
            if (!isset($groups[$id])) {
                $groups[$id] = [
                    'contribuinte_id' => $id,
                    'razao_social'    => (string) $c['razao_social'],
                    'cnpj'            => (string) ($c['cnpj'] ?? ''),
                    'competencias'    => [],
                ];
            }
            $groups[$id]['competencias'][] = $c;
        }

        uasort($groups, static fn(array $a, array $b): int => strcasecmp($a['razao_social'], $b['razao_social']));

        return array_values($groups);
    }

    public function countByUser(int $userId): int
    {
        return (int) $this->scalar(
            "SELECT COUNT(*) FROM competencias c
             JOIN contribuintes co ON co.id = c.contribuinte_id
             WHERE co.usuario_id = ?",
            [$userId]
        );
    }

    public function countTransmitidosByUser(int $userId): int
    {
        return (int) $this->scalar(
            "SELECT COUNT(*) FROM competencias c
             JOIN contribuintes co ON co.id = c.contribuinte_id
             WHERE co.usuario_id = ? AND c.status = 'transmitido'",
            [$userId]
        );
    }

    public function listRecentByUser(int $userId, int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));
        return $this->query(
            "SELECT c.*, co.razao_social, co.cnpj
             FROM competencias c
             JOIN contribuintes co ON co.id = c.contribuinte_id
             WHERE co.usuario_id = ?
             ORDER BY c.periodo DESC
             LIMIT {$limit}",
            [$userId]
        );
    }

    /** IDs de competências abertas/fechadas do usuário (mais recentes primeiro). */
    public function listIdsAbertasOuFechadas(int $userId, int $limit = 2): array
    {
        $limit = max(1, min(20, $limit));
        $rows = $this->query(
            "SELECT c.id FROM competencias c
             JOIN contribuintes co ON co.id = c.contribuinte_id
             WHERE co.usuario_id = ? AND c.status IN ('aberto', 'fechado')
             ORDER BY c.periodo DESC
             LIMIT {$limit}",
            [$userId]
        );
        return array_map(static fn(array $r): int => (int) $r['id'], $rows);
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

    /** Reabre competência se não houver mais XMLs com protocolo. */
    public function reabrirSeSemEnvio(int $id): void
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM arquivos_gerados
            WHERE competencia_id = ?
              AND protocolo IS NOT NULL AND protocolo <> ''
        ");
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->update($id, [
                'status'     => 'aberto',
                'data_envio' => null,
                'num_recibo' => null,
            ]);
        }
    }
}