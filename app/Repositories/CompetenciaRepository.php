<?php

namespace App\Repositories;

class CompetenciaRepository extends Repository
{
    protected string $table = 'competencias';

    public function findWithContribuinte(int $id, int $userId): ?array
    {
        return $this->queryOne("
            SELECT c.*,
                   co.razao_social, co.cnpj, co.tipo_contribuinte, co.classificacao_tributos,
                   co.nome_contato, co.cpf_contato, co.email, co.telefone,
                   co.ind_escrituracao, co.ind_desoneracao, co.ind_acordo_isen_multa, co.ind_sit_pj
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

    /**
     * Eventos de tabela (R-1000/R-1070) e exclusão (R-9000) NÃO fecham a competência.
     * Status "transmitido" só quando todos os periódicos com dados locais já foram enviados.
     *
     * @param list<string> $eventosEnviadosNesteLote ex.: ['R1000'] ou ['R2010','R2010']
     */
    public function sincronizarStatusTransmissao(int $id, array $eventosEnviadosNesteLote = [], ?string $protocolo = null): void
    {
        $comp = $this->find($id);
        if (!$comp) {
            return;
        }

        // Nunca promover só por evento de tabela/exclusão
        $soTabelaOuExclusao = $eventosEnviadosNesteLote !== []
            && $this->apenasEventosNaoPeriodicos($eventosEnviadosNesteLote);

        $pendentes = $this->eventosPeriodicosPendentesDeEnvio($id);

        if ($pendentes === []) {
            $temPeriodicoComDados = $this->eventosPeriodicosComDados($id) !== [];
            if ($temPeriodicoComDados) {
                // Todos os periódicos com dados já têm protocolo → transmitido
                $this->marcarTransmitido($id, $protocolo ?: (string) ($comp['num_recibo'] ?? ''));
                return;
            }
            // Só R-1000 (ou sem periódicos): mantém/reabre como aberto (não "transmitido")
            if (($comp['status'] ?? '') === 'transmitido' || $soTabelaOuExclusao) {
                $this->update($id, [
                    'status'     => 'aberto',
                    'data_envio' => null,
                    'num_recibo' => null,
                ]);
            }
            return;
        }

        // Ainda falta enviar algum periódico → não fica como transmitido
        if (($comp['status'] ?? '') === 'transmitido') {
            $this->update($id, [
                'status'     => 'aberto',
                'data_envio' => null,
                'num_recibo' => null,
            ]);
        }
    }

    /** @return list<string> ex.: ['R2010','R4020'] */
    public function eventosPeriodicosComDados(int $competenciaId): array
    {
        $map = [
            'R2010' => 'r2010',
            'R2020' => 'r2020',
            'R2055' => 'r2055',
            'R2060' => 'r2060',
            'R4010' => 'r4010',
            'R4020' => 'r4020',
        ];
        $comDados = [];
        foreach ($map as $evento => $tabela) {
            $stmt = $this->db->prepare("SELECT 1 FROM {$tabela} WHERE competencia_id = ? LIMIT 1");
            $stmt->execute([$competenciaId]);
            if ($stmt->fetchColumn()) {
                $comDados[] = $evento;
            }
        }
        return $comDados;
    }

    /**
     * Periódicos com dados locais que ainda não têm XML aceito (recibo RFB).
     *
     * @return list<string>
     */
    public function eventosPeriodicosPendentesDeEnvio(int $competenciaId): array
    {
        $pendentes = [];
        foreach ($this->eventosPeriodicosComDados($competenciaId) as $evento) {
            $stmt = $this->db->prepare("
                SELECT 1 FROM arquivos_gerados
                WHERE competencia_id = ?
                  AND evento = ?
                  AND nr_recibo_retornado IS NOT NULL
                  AND nr_recibo_retornado <> ''
                LIMIT 1
            ");
            $stmt->execute([$competenciaId, $evento]);
            if (!$stmt->fetchColumn()) {
                $pendentes[] = $evento;
            }
        }
        return $pendentes;
    }

    /** @param list<string> $eventos */
    private function apenasEventosNaoPeriodicos(array $eventos): bool
    {
        $naoPeriodicos = ['R1000', 'R1070', 'R9000'];
        foreach ($eventos as $ev) {
            $norm = strtoupper(str_replace('-', '', trim((string) $ev)));
            if ($norm !== '' && !str_starts_with($norm, 'R')) {
                $norm = 'R' . preg_replace('/\D/', '', $norm);
            }
            if (!in_array($norm, $naoPeriodicos, true)) {
                return false;
            }
        }
        return true;
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
            return;
        }
        // Ainda há envios, mas talvez só R-1000 — recalcula
        $this->sincronizarStatusTransmissao($id);
    }
}