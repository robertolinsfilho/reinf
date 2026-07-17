<?php

namespace App\Models;

class ArquivoGeradoRepository extends Repository
{
    protected string $table = 'arquivos_gerados';

    public function listByCompetencia(int $competenciaId): array
    {
        return $this->query(
            "SELECT * FROM arquivos_gerados WHERE competencia_id = ? ORDER BY created_at DESC",
            [$competenciaId]
        );
    }

    public function listByCompetenciaForUser(int $competenciaId, int $userId): array
    {
        return $this->query(
            "SELECT a.*
             FROM arquivos_gerados a
             JOIN competencias c ON c.id = a.competencia_id
             JOIN contribuintes co ON co.id = c.contribuinte_id
             WHERE a.competencia_id = ? AND co.usuario_id = ?
             ORDER BY a.created_at DESC",
            [$competenciaId, $userId]
        );
    }

    public function findForUser(int $id, int $userId): ?array
    {
        return $this->queryOne(
            "SELECT a.*
             FROM arquivos_gerados a
             JOIN competencias c ON c.id = a.competencia_id
             JOIN contribuintes co ON co.id = c.contribuinte_id
             WHERE a.id = ? AND co.usuario_id = ?",
            [$id, $userId]
        );
    }

    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return $this->query("SELECT * FROM arquivos_gerados WHERE id IN ({$placeholders})", $ids);
    }

    public function findByIdsForUser(array $ids, int $userId): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return $this->query(
            "SELECT a.*
             FROM arquivos_gerados a
             JOIN competencias c ON c.id = a.competencia_id
             JOIN contribuintes co ON co.id = c.contribuinte_id
             WHERE a.id IN ({$placeholders}) AND co.usuario_id = ?",
            [...array_map('intval', $ids), $userId]
        );
    }

    public function salvar(int $competenciaId, int $userId, array $arq, bool $assinado, int $indRetif = 1, ?string $nrRecibo = null): int
    {
        $idEvento = null;
        if (!empty($arq['xml']) && preg_match('/\bid="(ID[^"]+)"/', (string) $arq['xml'], $m)) {
            $idEvento = $m[1];
        }

        return $this->insert([
            'competencia_id'      => $competenciaId,
            'usuario_id'          => $userId,
            'evento'              => $arq['evento'],
            'id_evento'           => $idEvento,
            'nome_arquivo'        => $arq['nome'],
            'caminho'             => $arq['caminho'],
            'tamanho'             => $arq['tamanho'],
            'hash_md5'            => $arq['hash'],
            'xml_conteudo'        => $arq['xml'],
            'assinado'            => $assinado ? 1 : 0,
            'ind_retif'           => $indRetif,
            'nr_recibo_original'  => $nrRecibo ?? ($arq['nr_recibo_original'] ?? null),
        ]);
    }

    public function marcarProtocolo(array $ids, string $protocolo): void
    {
        if (empty($ids) || $protocolo === '') {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "UPDATE arquivos_gerados SET protocolo = ? WHERE id IN ({$placeholders})"
        );
        $stmt->execute([$protocolo, ...array_map('intval', $ids)]);
    }

    /**
     * Atualiza nr_recibo_retornado a partir do mapa id_evento => recibo.
     * Fallback: aplica recibos em ordem aos arquivos do protocolo sem id.
     */
    public function aplicarRecibos(int $competenciaId, string $protocolo, array $recibosPorIdEvento, array $recibosOrdem = []): int
    {
        $atualizados = 0;

        foreach ($recibosPorIdEvento as $idEvento => $recibo) {
            $stmt = $this->db->prepare("
                UPDATE arquivos_gerados
                SET nr_recibo_retornado = ?
                WHERE competencia_id = ?
                  AND id_evento = ?
                  AND (protocolo = ? OR protocolo IS NULL OR protocolo = '')
            ");
            $stmt->execute([$recibo, $competenciaId, $idEvento, $protocolo]);
            $atualizados += $stmt->rowCount();
        }

        if ($atualizados === 0 && !empty($recibosOrdem)) {
            $arquivos = $this->query(
                "SELECT id FROM arquivos_gerados
                 WHERE competencia_id = ? AND protocolo = ?
                 ORDER BY id ASC",
                [$competenciaId, $protocolo]
            );
            foreach ($arquivos as $i => $arq) {
                if (!isset($recibosOrdem[$i])) {
                    break;
                }
                $this->update((int) $arq['id'], ['nr_recibo_retornado' => $recibosOrdem[$i]]);
                $atualizados++;
            }
        }

        return $atualizados;
    }

    public function listRecibosR4020(int $competenciaId): array
    {
        return $this->listRecibos($competenciaId, 'R4020');
    }

    public function listRecibos(int $competenciaId, ?string $evento = null, ?int $userId = null): array
    {
        $sql = "SELECT a.id, a.evento, a.nome_arquivo, a.id_evento, a.nr_recibo_retornado, a.protocolo, a.created_at, a.xml_conteudo
                FROM arquivos_gerados a";
        $params = [];

        if ($userId !== null) {
            $sql .= " JOIN competencias c ON c.id = a.competencia_id
                      JOIN contribuintes co ON co.id = c.contribuinte_id
                      WHERE a.competencia_id = ? AND co.usuario_id = ?";
            $params = [$competenciaId, $userId];
        } else {
            $sql .= ' WHERE a.competencia_id = ?';
            $params = [$competenciaId];
        }

        $sql .= " AND a.nr_recibo_retornado IS NOT NULL
                  AND a.nr_recibo_retornado <> ''";

        if ($evento !== null && $evento !== '') {
            $sql .= ' AND a.evento = ?';
            $params[] = $evento;
        }

        $sql .= ' ORDER BY a.created_at DESC';

        return $this->query($sql, $params);
    }

    /** Último recibo retornado de um evento na competência. */
    public function ultimoReciboEvento(int $competenciaId, string $evento): ?string
    {
        $row = $this->queryOne(
            "SELECT nr_recibo_retornado
             FROM arquivos_gerados
             WHERE competencia_id = ?
               AND evento = ?
               AND nr_recibo_retornado IS NOT NULL
               AND nr_recibo_retornado <> ''
             ORDER BY id DESC
             LIMIT 1",
            [$competenciaId, $evento]
        );
        return $row ? (string) $row['nr_recibo_retornado'] : null;
    }

    /**
     * XMLs com recibo (para montar mapa de retificação no serviço de geração).
     *
     * @return list<array{nr_recibo_retornado: string, xml_conteudo: ?string}>
     */
    public function listXmlsComRecibo(int $competenciaId, string $evento): array
    {
        return $this->query(
            "SELECT nr_recibo_retornado, xml_conteudo
             FROM arquivos_gerados
             WHERE competencia_id = ?
               AND evento = ?
               AND nr_recibo_retornado IS NOT NULL
               AND nr_recibo_retornado <> ''
             ORDER BY id DESC",
            [$competenciaId, $evento]
        );
    }

    /**
     * Exclui XMLs gerados do usuário (banco + arquivo em disco).
     * Não remove nada na RFB — para isso use R-9000.
     *
     * @param list<int|string> $ids
     * @return array{excluidos: int, com_recibo: int}
     */
    public function excluirForUser(array $ids, int $userId): array
    {
        $arquivos = $this->findByIdsForUser($ids, $userId);
        $excluidos = 0;
        $comRecibo = 0;
        $competencias = [];

        foreach ($arquivos as $arq) {
            if (!empty($arq['nr_recibo_retornado'])) {
                $comRecibo++;
            }
            $caminho = (string) ($arq['caminho'] ?? '');
            if ($caminho !== '' && is_file($caminho)) {
                @unlink($caminho);
            }
            $this->delete((int) $arq['id']);
            $excluidos++;
            $competencias[(int) $arq['competencia_id']] = true;
        }

        return [
            'excluidos'     => $excluidos,
            'com_recibo'    => $comRecibo,
            'competencia_ids' => array_keys($competencias),
        ];
    }

    public function countComProtocolo(int $competenciaId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM arquivos_gerados
            WHERE competencia_id = ?
              AND protocolo IS NOT NULL AND protocolo <> ''
        ");
        $stmt->execute([$competenciaId]);
        return (int) $stmt->fetchColumn();
    }

    /** Arquivos com recibo RFB (candidatos a R-9000), exceto o próprio R-9000. */
    public function listComReciboForUser(int $competenciaId, int $userId): array
    {
        return $this->query(
            "SELECT a.*
             FROM arquivos_gerados a
             JOIN competencias c ON c.id = a.competencia_id
             JOIN contribuintes co ON co.id = c.contribuinte_id
             WHERE a.competencia_id = ?
               AND co.usuario_id = ?
               AND a.evento <> 'R9000'
               AND a.nr_recibo_retornado IS NOT NULL
               AND a.nr_recibo_retornado <> ''
             ORDER BY a.created_at DESC",
            [$competenciaId, $userId]
        );
    }

    /**
     * Após R-9000 aceito: remove localmente os eventos excluídos (pelo recibo original).
     *
     * @return array{originais: int, r9000: int}
     */
    public function limparAposExclusaoR9000(int $competenciaId, string $protocoloR9000, int $userId): array
    {
        $r9000s = $this->query(
            "SELECT a.*
             FROM arquivos_gerados a
             JOIN competencias c ON c.id = a.competencia_id
             JOIN contribuintes co ON co.id = c.contribuinte_id
             WHERE a.competencia_id = ?
               AND co.usuario_id = ?
               AND a.evento = 'R9000'
               AND a.protocolo = ?
               AND a.nr_recibo_original IS NOT NULL
               AND a.nr_recibo_original <> ''",
            [$competenciaId, $userId, $protocoloR9000]
        );

        $idsOriginais = [];
        $idsR9000 = [];
        foreach ($r9000s as $r) {
            $idsR9000[] = (int) $r['id'];
            $recibo = (string) $r['nr_recibo_original'];
            $origs = $this->query(
                "SELECT a.id
                 FROM arquivos_gerados a
                 JOIN competencias c ON c.id = a.competencia_id
                 JOIN contribuintes co ON co.id = c.contribuinte_id
                 WHERE a.competencia_id = ?
                   AND co.usuario_id = ?
                   AND a.evento <> 'R9000'
                   AND a.nr_recibo_retornado = ?",
                [$competenciaId, $userId, $recibo]
            );
            foreach ($origs as $o) {
                $idsOriginais[] = (int) $o['id'];
            }
        }

        $r1 = $this->excluirForUser(array_values(array_unique($idsOriginais)), $userId);
        $r2 = $this->excluirForUser(array_values(array_unique($idsR9000)), $userId);

        return [
            'originais' => $r1['excluidos'],
            'r9000'     => $r2['excluidos'],
        ];
    }
}
