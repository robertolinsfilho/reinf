<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Processo;

class ProcessoRepository extends Repository
{
    protected string $table = 'r1070_processos';

    protected string $modelClass = Processo::class;

    public function listByContribuinte(int $contribuinteId): array
    {
        return $this->toRows(
            $this->newQuery()
                ->where('contribuinte_id', $contribuinteId)
                ->orderByDesc('data_inclusao')
                ->get()
        );
    }

    public function listAtivosByContribuinte(int $contribuinteId): array
    {
        return $this->toRows(
            $this->newQuery()
                ->where('contribuinte_id', $contribuinteId)
                ->where('status', 'ativo')
                ->orderByDesc('data_inclusao')
                ->get()
        );
    }

    public function listByUser(int $userId): array
    {
        return $this->toRows(
            $this->newQuery()
                ->from('r1070_processos as p')
                ->join('contribuintes as c', 'c.id', '=', 'p.contribuinte_id')
                ->where('c.usuario_id', $userId)
                ->select('p.*', 'c.razao_social', 'c.cnpj')
                ->orderByDesc('p.data_inclusao')
                ->get()
        );
    }

    public function findByUser(int $id, int $userId): ?array
    {
        return $this->toRow(
            $this->newQuery()
                ->from('r1070_processos as p')
                ->join('contribuintes as c', 'c.id', '=', 'p.contribuinte_id')
                ->where('p.id', $id)
                ->where('c.usuario_id', $userId)
                ->select('p.*', 'c.razao_social', 'c.cnpj')
                ->first()
        );
    }
}
