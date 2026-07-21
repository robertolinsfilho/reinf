<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Contribuinte;

class ContribuinteRepository extends Repository
{
    protected string $table = 'contribuintes';

    protected string $modelClass = Contribuinte::class;

    public function listByUser(int $userId): array
    {
        return $this->toRows(
            $this->newQuery()
                ->where('usuario_id', $userId)
                ->orderBy('razao_social')
                ->get()
        );
    }

    public function findByUser(int $id, int $userId): ?array
    {
        return $this->toRow(
            $this->newQuery()
                ->whereKey($id)
                ->where('usuario_id', $userId)
                ->first()
        );
    }

    public function findByCnpjAndUser(string $cnpj, int $userId): ?array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj) ?? '';
        if ($cnpj === '') {
            return null;
        }

        return $this->toRow(
            $this->newQuery()
                ->where('usuario_id', $userId)
                ->where('cnpj', $cnpj)
                ->first()
        );
    }

    public function criar(int $userId, array $dados): int
    {
        return $this->insert(['usuario_id' => $userId, ...$dados]);
    }

    public function atualizar(int $id, int $userId, array $dados): void
    {
        foreach (array_keys($dados) as $col) {
            $this->assertColumn($col);
        }
        $this->newQuery()
            ->whereKey($id)
            ->where('usuario_id', $userId)
            ->update($dados);
    }

    public function excluir(int $id, int $userId): void
    {
        $this->newQuery()
            ->whereKey($id)
            ->where('usuario_id', $userId)
            ->delete();
    }

    public function countByUser(int $userId): int
    {
        return (int) $this->newQuery()->where('usuario_id', $userId)->count();
    }
}
