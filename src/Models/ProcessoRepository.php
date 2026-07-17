<?php

namespace App\Models;

class ProcessoRepository extends Repository
{
    protected string $table = 'r1070_processos';

    public function listByContribuinte(int $contribuinteId): array
    {
        return $this->query(
            "SELECT * FROM r1070_processos WHERE contribuinte_id = ? ORDER BY data_inclusao DESC",
            [$contribuinteId]
        );
    }

    public function listAtivosByContribuinte(int $contribuinteId): array
    {
        return $this->query(
            "SELECT * FROM r1070_processos WHERE contribuinte_id = ? AND status = 'ativo' ORDER BY data_inclusao DESC",
            [$contribuinteId]
        );
    }

    public function listByUser(int $userId): array
    {
        return $this->query("
            SELECT p.*, c.razao_social, c.cnpj
            FROM r1070_processos p
            JOIN contribuintes c ON c.id = p.contribuinte_id
            WHERE c.usuario_id = ?
            ORDER BY p.data_inclusao DESC
        ", [$userId]);
    }

    public function findByUser(int $id, int $userId): ?array
    {
        return $this->queryOne("
            SELECT p.*, c.razao_social, c.cnpj
            FROM r1070_processos p
            JOIN contribuintes c ON c.id = p.contribuinte_id
            WHERE p.id = ? AND c.usuario_id = ?
        ", [$id, $userId]);
    }
}