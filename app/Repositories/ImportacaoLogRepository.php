<?php

namespace App\Repositories;

class ImportacaoLogRepository extends Repository
{
    protected string $table = 'importacoes';

    public function historicoByUser(int $userId, int $limit = 20): array
    {
        return $this->query("
            SELECT i.*, c.periodo, co.razao_social
            FROM importacoes i
            JOIN competencias c ON c.id = i.competencia_id
            JOIN contribuintes co ON co.id = c.contribuinte_id
            WHERE i.usuario_id = ?
            ORDER BY i.created_at DESC
            LIMIT ?
        ", [$userId, $limit]);
    }

    public function registrar(int $compId, int $userId, string $nomeArq, string $evento): int
    {
        return $this->insert([
            'competencia_id' => $compId,
            'usuario_id'     => $userId,
            'arquivo_nome'   => $nomeArq,
            'evento'         => $evento,
            'status'         => 'processando',
        ]);
    }

    public function marcarSucesso(int $id, int $total, int $importados): void
    {
        $this->update($id, [
            'status'              => 'sucesso',
            'total_registros'     => $total,
            'registros_importados'=> $importados,
        ]);
    }

    public function marcarErro(int $id, string $erro): void
    {
        $this->update($id, ['status' => 'erro', 'log_erros' => $erro]);
    }
}
