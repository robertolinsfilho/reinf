<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ArquivoGerado;
use App\Models\Competencia;
use App\Models\R2010;
use App\Models\R2020;
use App\Models\R2055;
use App\Models\R2060;
use App\Models\R4010;
use App\Models\R4020;

class CompetenciaRepository extends Repository
{
    protected string $table = 'competencias';

    protected string $modelClass = Competencia::class;

    private const EVENTO_TABELAS = [
        'R2010' => R2010::class,
        'R2020' => R2020::class,
        'R2055' => R2055::class,
        'R2060' => R2060::class,
        'R4010' => R4010::class,
        'R4020' => R4020::class,
    ];

    public function findWithContribuinte(int $id, int $userId): ?array
    {
        return $this->toRow(
            $this->newQuery()
                ->from('competencias as c')
                ->join('contribuintes as co', 'co.id', '=', 'c.contribuinte_id')
                ->where('c.id', $id)
                ->where('co.usuario_id', $userId)
                ->select(
                    'c.*',
                    'co.razao_social',
                    'co.cnpj',
                    'co.tipo_contribuinte',
                    'co.classificacao_tributos',
                    'co.nome_contato',
                    'co.cpf_contato',
                    'co.email',
                    'co.telefone',
                    'co.ind_escrituracao',
                    'co.ind_desoneracao',
                    'co.ind_acordo_isen_multa',
                    'co.ind_sit_pj'
                )
                ->first()
        );
    }

    public function listByUser(int $userId, ?int $contribuinteId = null): array
    {
        $q = $this->newQuery()
            ->with(['contribuinte' => static fn ($q) => $q->select('id', 'razao_social', 'cnpj')])
            ->withCount([
                'r2010 as total_r2010',
                'r2020 as total_r2020',
                'r2055 as total_r2055',
                'r2060 as total_r2060',
                'r4010 as total_r4010',
                'r4020 as total_r4020',
            ])
            ->whereHas('contribuinte', static function ($q) use ($userId, $contribuinteId): void {
                $q->where('usuario_id', $userId);
                if ($contribuinteId) {
                    $q->where('id', $contribuinteId);
                }
            })
            ->orderByDesc('periodo');

        $out = [];
        foreach ($q->get() as $model) {
            $row = $model->getAttributes();
            $row['razao_social'] = $model->contribuinte?->razao_social;
            $row['cnpj'] = $model->contribuinte?->cnpj;
            // withCount garante os totais mesmo se getAttributes omitir em algum driver
            $row['total_r2010'] = (int) ($model->total_r2010 ?? 0);
            $row['total_r2020'] = (int) ($model->total_r2020 ?? 0);
            $row['total_r2055'] = (int) ($model->total_r2055 ?? 0);
            $row['total_r2060'] = (int) ($model->total_r2060 ?? 0);
            $row['total_r4010'] = (int) ($model->total_r4010 ?? 0);
            $row['total_r4020'] = (int) ($model->total_r4020 ?? 0);
            $out[] = $row;
        }

        return $out;
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
                    'razao_social' => (string) $c['razao_social'],
                    'cnpj' => (string) ($c['cnpj'] ?? ''),
                    'competencias' => [],
                ];
            }
            $groups[$id]['competencias'][] = $c;
        }

        uasort($groups, static fn (array $a, array $b): int => strcasecmp($a['razao_social'], $b['razao_social']));

        return array_values($groups);
    }

    public function countByUser(int $userId): int
    {
        return (int) $this->newQuery()
            ->from('competencias as c')
            ->join('contribuintes as co', 'co.id', '=', 'c.contribuinte_id')
            ->where('co.usuario_id', $userId)
            ->count();
    }

    public function countTransmitidosByUser(int $userId): int
    {
        return (int) $this->newQuery()
            ->from('competencias as c')
            ->join('contribuintes as co', 'co.id', '=', 'c.contribuinte_id')
            ->where('co.usuario_id', $userId)
            ->where('c.status', 'transmitido')
            ->count();
    }

    public function listRecentByUser(int $userId, int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));

        return $this->toRows(
            $this->newQuery()
                ->from('competencias as c')
                ->join('contribuintes as co', 'co.id', '=', 'c.contribuinte_id')
                ->where('co.usuario_id', $userId)
                ->select('c.*', 'co.razao_social', 'co.cnpj')
                ->orderByDesc('c.periodo')
                ->limit($limit)
                ->get()
        );
    }

    /** IDs de competências abertas/fechadas do usuário (mais recentes primeiro). */
    public function listIdsAbertasOuFechadas(int $userId, int $limit = 2): array
    {
        $limit = max(1, min(20, $limit));
        $rows = $this->toRows(
            $this->newQuery()
                ->from('competencias as c')
                ->join('contribuintes as co', 'co.id', '=', 'c.contribuinte_id')
                ->where('co.usuario_id', $userId)
                ->whereIn('c.status', ['aberto', 'fechado'])
                ->select('c.id')
                ->orderByDesc('c.periodo')
                ->limit($limit)
                ->get()
        );

        return array_map(static fn (array $r): int => (int) $r['id'], $rows);
    }

    public function exists(int $contribuinteId, string $periodo): bool
    {
        return $this->newQuery()
            ->where('contribuinte_id', $contribuinteId)
            ->where('periodo', $periodo)
            ->exists();
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

        $existente = $this->toRow(
            $this->newQuery()
                ->where('contribuinte_id', $contribuinteId)
                ->where('periodo', $periodo)
                ->first()
        );
        if ($existente) {
            return ['competencia' => $existente, 'criada' => false];
        }

        $id = $this->insert([
            'contribuinte_id' => $contribuinteId,
            'periodo' => $periodo,
            'status' => 'aberto',
        ]);

        $nova = $this->find($id);

        return ['competencia' => $nova, 'criada' => true];
    }

    public function marcarTransmitido(int $id, string $protocolo): void
    {
        $this->update($id, [
            'status' => 'transmitido',
            'data_envio' => date('Y-m-d H:i:s'),
            'num_recibo' => $protocolo,
        ]);
    }

    /**
     * Eventos de tabela (R-1000/R-1070) e exclusão (R-9000) NÃO fecham a competência.
     * Status "transmitido" só quando todos os periódicos com dados locais já foram enviados.
     *
     * @param  list<string>  $eventosEnviadosNesteLote  ex.: ['R1000'] ou ['R2010','R2010']
     */
    public function sincronizarStatusTransmissao(int $id, array $eventosEnviadosNesteLote = [], ?string $protocolo = null): void
    {
        $comp = $this->find($id);
        if (!$comp) {
            return;
        }

        $soTabelaOuExclusao = $eventosEnviadosNesteLote !== []
            && $this->apenasEventosNaoPeriodicos($eventosEnviadosNesteLote);

        $pendentes = $this->eventosPeriodicosPendentesDeEnvio($id);

        if ($pendentes === []) {
            $temPeriodicoComDados = $this->eventosPeriodicosComDados($id) !== [];
            if ($temPeriodicoComDados) {
                $this->marcarTransmitido($id, $protocolo ?: (string) ($comp['num_recibo'] ?? ''));

                return;
            }
            if (($comp['status'] ?? '') === 'transmitido' || $soTabelaOuExclusao) {
                $this->update($id, [
                    'status' => 'aberto',
                    'data_envio' => null,
                    'num_recibo' => null,
                ]);
            }

            return;
        }

        if (($comp['status'] ?? '') === 'transmitido') {
            $this->update($id, [
                'status' => 'aberto',
                'data_envio' => null,
                'num_recibo' => null,
            ]);
        }
    }

    /** @return list<string> ex.: ['R2010','R4020'] */
    public function eventosPeriodicosComDados(int $competenciaId): array
    {
        $comDados = [];
        foreach (self::EVENTO_TABELAS as $evento => $class) {
            if ($class::query()->where('competencia_id', $competenciaId)->exists()) {
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
            $temRecibo = ArquivoGerado::query()
                ->where('competencia_id', $competenciaId)
                ->where('evento', $evento)
                ->whereNotNull('nr_recibo_retornado')
                ->where('nr_recibo_retornado', '<>', '')
                ->exists();
            if (!$temRecibo) {
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
        $count = ArquivoGerado::query()
            ->where('competencia_id', $id)
            ->whereNotNull('protocolo')
            ->where('protocolo', '<>', '')
            ->count();

        if ($count === 0) {
            $this->update($id, [
                'status' => 'aberto',
                'data_envio' => null,
                'num_recibo' => null,
            ]);

            return;
        }
        $this->sincronizarStatusTransmissao($id);
    }
}
