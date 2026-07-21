<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Importacao;

class ImportacaoLogRepository extends Repository
{
    protected string $table = 'importacoes';

    protected string $modelClass = Importacao::class;

    public function historicoByUser(int $userId, int $limit = 20): array
    {
        $limit = max(1, min(500, $limit));

        return $this->toRows(
            $this->newQuery()
                ->from('importacoes as i')
                ->join('competencias as c', 'c.id', '=', 'i.competencia_id')
                ->join('contribuintes as co', 'co.id', '=', 'c.contribuinte_id')
                ->where('i.usuario_id', $userId)
                ->select('i.*', 'c.periodo', 'co.razao_social')
                ->orderByDesc('i.created_at')
                ->limit($limit)
                ->get()
        );
    }

    public function registrar(int $compId, int $userId, string $nomeArq, string $evento): int
    {
        return $this->insert([
            'competencia_id' => $compId,
            'usuario_id' => $userId,
            'arquivo_nome' => $nomeArq,
            'evento' => $evento,
            'status' => 'processando',
        ]);
    }

    public function marcarSucesso(int $id, int $total, int $importados): void
    {
        $this->update($id, [
            'status' => 'sucesso',
            'total_registros' => $total,
            'registros_importados' => $importados,
        ]);
    }

    public function marcarErro(int $id, string $erro): void
    {
        $this->update($id, ['status' => 'erro', 'log_erros' => $erro]);
    }
}
