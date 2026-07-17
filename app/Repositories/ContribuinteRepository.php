<?php

namespace App\Repositories;

class ContribuinteRepository extends Repository
{
    protected string $table = 'contribuintes';

    public function listByUser(int $userId): array
    {
        return $this->query(
            "SELECT * FROM contribuintes WHERE usuario_id = ? ORDER BY razao_social",
            [$userId]
        );
    }

    public function findByUser(int $id, int $userId): ?array
    {
        return $this->queryOne(
            "SELECT * FROM contribuintes WHERE id = ? AND usuario_id = ?",
            [$id, $userId]
        );
    }

    public function findByCnpjAndUser(string $cnpj, int $userId): ?array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj) ?? '';
        if ($cnpj === '') {
            return null;
        }
        return $this->queryOne(
            "SELECT * FROM contribuintes WHERE usuario_id = ? AND cnpj = ? LIMIT 1",
            [$userId, $cnpj]
        );
    }

    public function criar(int $userId, array $dados): int
    {
        return $this->insert(['usuario_id' => $userId, ...$dados]);
    }

    public function atualizar(int $id, int $userId, array $dados): void
    {
        $sets   = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($dados)));
        $params = [...array_values($dados), $id, $userId];
        $this->db->prepare("UPDATE contribuintes SET {$sets} WHERE id = ? AND usuario_id = ?")->execute($params);
    }

    public function excluir(int $id, int $userId): void
    {
        $this->db->prepare("DELETE FROM contribuintes WHERE id = ? AND usuario_id = ?")->execute([$id, $userId]);
    }

    public function countByUser(int $userId): int
    {
        return (int) $this->scalar("SELECT COUNT(*) FROM contribuintes WHERE usuario_id = ?", [$userId]);
    }
}