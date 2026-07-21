<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ArquivoGerado;

class ArquivoGeradoRepository extends Repository
{
    protected string $table = 'arquivos_gerados';

    protected string $modelClass = ArquivoGerado::class;

    public function listByCompetencia(int $competenciaId): array
    {
        return $this->toRows(
            $this->newQuery()
                ->where('competencia_id', $competenciaId)
                ->orderByDesc('created_at')
                ->get()
        );
    }

    public function listByCompetenciaForUser(int $competenciaId, int $userId): array
    {
        return $this->toRows(
            $this->newQuery()
                ->from('arquivos_gerados as a')
                ->join('competencias as c', 'c.id', '=', 'a.competencia_id')
                ->join('contribuintes as co', 'co.id', '=', 'c.contribuinte_id')
                ->where('a.competencia_id', $competenciaId)
                ->where('co.usuario_id', $userId)
                ->select('a.*')
                ->orderByDesc('a.created_at')
                ->get()
        );
    }

    public function findForUser(int $id, int $userId): ?array
    {
        return $this->toRow(
            $this->newQuery()
                ->from('arquivos_gerados as a')
                ->join('competencias as c', 'c.id', '=', 'a.competencia_id')
                ->join('contribuintes as co', 'co.id', '=', 'c.contribuinte_id')
                ->where('a.id', $id)
                ->where('co.usuario_id', $userId)
                ->select('a.*')
                ->first()
        );
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->toRows(
            $this->newQuery()->whereIn('id', array_map('intval', $ids))->get()
        );
    }

    public function findByIdsForUser(array $ids, int $userId): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->toRows(
            $this->newQuery()
                ->from('arquivos_gerados as a')
                ->join('competencias as c', 'c.id', '=', 'a.competencia_id')
                ->join('contribuintes as co', 'co.id', '=', 'c.contribuinte_id')
                ->whereIn('a.id', array_map('intval', $ids))
                ->where('co.usuario_id', $userId)
                ->select('a.*')
                ->get()
        );
    }

    public function salvar(int $competenciaId, int $userId, array $arq, bool $assinado, int $indRetif = 1, ?string $nrRecibo = null): int
    {
        $idEvento = null;
        if (!empty($arq['xml']) && preg_match('/\bid="(ID[^"]+)"/', (string) $arq['xml'], $m)) {
            $idEvento = $m[1];
        }

        return $this->insert([
            'competencia_id' => $competenciaId,
            'usuario_id' => $userId,
            'evento' => $arq['evento'],
            'id_evento' => $idEvento,
            'nome_arquivo' => $arq['nome'],
            'caminho' => $arq['caminho'],
            'tamanho' => $arq['tamanho'],
            'hash_md5' => $arq['hash'],
            'xml_conteudo' => $arq['xml'],
            'assinado' => $assinado ? 1 : 0,
            'ind_retif' => $indRetif,
            'nr_recibo_original' => $nrRecibo ?? ($arq['nr_recibo_original'] ?? null),
        ]);
    }

    public function marcarProtocolo(array $ids, string $protocolo): void
    {
        if ($ids === [] || $protocolo === '') {
            return;
        }
        $this->newQuery()
            ->whereIn('id', array_map('intval', $ids))
            ->update(['protocolo' => $protocolo]);
    }

    /**
     * Atualiza nr_recibo_retornado a partir do mapa id_evento => recibo.
     * Fallback: aplica recibos em ordem aos arquivos do protocolo sem id.
     */
    public function aplicarRecibos(int $competenciaId, string $protocolo, array $recibosPorIdEvento, array $recibosOrdem = []): int
    {
        $atualizados = 0;

        foreach ($recibosPorIdEvento as $idEvento => $recibo) {
            $atualizados += $this->newQuery()
                ->where('competencia_id', $competenciaId)
                ->where('id_evento', $idEvento)
                ->where(function ($q) use ($protocolo) {
                    $q->where('protocolo', $protocolo)
                        ->orWhereNull('protocolo')
                        ->orWhere('protocolo', '');
                })
                ->update(['nr_recibo_retornado' => $recibo]);
        }

        if ($atualizados === 0 && $recibosOrdem !== []) {
            $arquivos = $this->toRows(
                $this->newQuery()
                    ->where('competencia_id', $competenciaId)
                    ->where('protocolo', $protocolo)
                    ->where(function ($q) {
                        $q->whereNull('nr_recibo_retornado')
                            ->orWhere('nr_recibo_retornado', '');
                    })
                    ->orderBy('id')
                    ->select('id')
                    ->get()
            );
            $i = 0;
            foreach ($arquivos as $arq) {
                if (!isset($recibosOrdem[$i])) {
                    break;
                }
                $this->update((int) $arq['id'], ['nr_recibo_retornado' => $recibosOrdem[$i]]);
                $atualizados++;
                $i++;
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
        $q = $this->newQuery()->from('arquivos_gerados as a');

        if ($userId !== null) {
            $q->join('competencias as c', 'c.id', '=', 'a.competencia_id')
                ->join('contribuintes as co', 'co.id', '=', 'c.contribuinte_id')
                ->where('a.competencia_id', $competenciaId)
                ->where('co.usuario_id', $userId);
        } else {
            $q->where('a.competencia_id', $competenciaId);
        }

        $q->whereNotNull('a.nr_recibo_retornado')
            ->where('a.nr_recibo_retornado', '<>', '')
            ->select(
                'a.id',
                'a.evento',
                'a.nome_arquivo',
                'a.id_evento',
                'a.nr_recibo_retornado',
                'a.protocolo',
                'a.created_at',
                'a.xml_conteudo'
            );

        if ($evento !== null && $evento !== '') {
            $q->where('a.evento', $evento);
        }

        return $this->toRows($q->orderByDesc('a.created_at')->get());
    }

    /** Último recibo retornado de um evento na competência. */
    public function ultimoReciboEvento(int $competenciaId, string $evento): ?string
    {
        $row = $this->toRow(
            $this->newQuery()
                ->where('competencia_id', $competenciaId)
                ->where('evento', $evento)
                ->whereNotNull('nr_recibo_retornado')
                ->where('nr_recibo_retornado', '<>', '')
                ->orderByDesc('id')
                ->select('nr_recibo_retornado')
                ->first()
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
        return $this->toRows(
            $this->newQuery()
                ->where('competencia_id', $competenciaId)
                ->where('evento', $evento)
                ->whereNotNull('nr_recibo_retornado')
                ->where('nr_recibo_retornado', '<>', '')
                ->orderByDesc('id')
                ->select('nr_recibo_retornado', 'xml_conteudo')
                ->get()
        );
    }

    /**
     * Exclui XMLs gerados do usuário (banco + arquivo em disco).
     * Não remove nada na RFB — para isso use R-9000.
     *
     * @param  list<int|string>  $ids
     * @return array{excluidos: int, com_recibo: int, competencia_ids?: list<int>}
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
            'excluidos' => $excluidos,
            'com_recibo' => $comRecibo,
            'competencia_ids' => array_keys($competencias),
        ];
    }

    public function countComProtocolo(int $competenciaId): int
    {
        return (int) $this->newQuery()
            ->where('competencia_id', $competenciaId)
            ->whereNotNull('protocolo')
            ->where('protocolo', '<>', '')
            ->count();
    }

    /** Arquivos com recibo RFB (candidatos a R-9000), exceto o próprio R-9000. */
    public function listComReciboForUser(int $competenciaId, int $userId): array
    {
        return $this->toRows(
            $this->newQuery()
                ->from('arquivos_gerados as a')
                ->join('competencias as c', 'c.id', '=', 'a.competencia_id')
                ->join('contribuintes as co', 'co.id', '=', 'c.contribuinte_id')
                ->where('a.competencia_id', $competenciaId)
                ->where('co.usuario_id', $userId)
                ->where('a.evento', '<>', 'R9000')
                ->whereNotNull('a.nr_recibo_retornado')
                ->where('a.nr_recibo_retornado', '<>', '')
                ->select('a.*')
                ->orderByDesc('a.created_at')
                ->get()
        );
    }

    /**
     * Após R-9000 aceito: remove localmente os eventos excluídos (pelo recibo original).
     *
     * @return array{originais: int, r9000: int}
     */
    public function limparAposExclusaoR9000(int $competenciaId, string $protocoloR9000, int $userId): array
    {
        $r9000s = $this->toRows(
            $this->newQuery()
                ->from('arquivos_gerados as a')
                ->join('competencias as c', 'c.id', '=', 'a.competencia_id')
                ->join('contribuintes as co', 'co.id', '=', 'c.contribuinte_id')
                ->where('a.competencia_id', $competenciaId)
                ->where('co.usuario_id', $userId)
                ->where('a.evento', 'R9000')
                ->where('a.protocolo', $protocoloR9000)
                ->whereNotNull('a.nr_recibo_original')
                ->where('a.nr_recibo_original', '<>', '')
                ->select('a.*')
                ->get()
        );

        $idsOriginais = [];
        $idsR9000 = [];
        foreach ($r9000s as $r) {
            $idsR9000[] = (int) $r['id'];
            $recibo = (string) $r['nr_recibo_original'];
            $origs = $this->toRows(
                $this->newQuery()
                    ->from('arquivos_gerados as a')
                    ->join('competencias as c', 'c.id', '=', 'a.competencia_id')
                    ->join('contribuintes as co', 'co.id', '=', 'c.contribuinte_id')
                    ->where('a.competencia_id', $competenciaId)
                    ->where('co.usuario_id', $userId)
                    ->where('a.evento', '<>', 'R9000')
                    ->where('a.nr_recibo_retornado', $recibo)
                    ->select('a.id')
                    ->get()
            );
            foreach ($origs as $o) {
                $idsOriginais[] = (int) $o['id'];
            }
        }

        $r1 = $this->excluirForUser(array_values(array_unique($idsOriginais)), $userId);
        $r2 = $this->excluirForUser(array_values(array_unique($idsR9000)), $userId);

        return [
            'originais' => $r1['excluidos'],
            'r9000' => $r2['excluidos'],
        ];
    }
}
