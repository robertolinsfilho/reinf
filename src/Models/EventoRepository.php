<?php

namespace App\Models;

class EventoRepository
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function listar(string $evento, int $competenciaId, string $orderBy = 'created_at DESC'): array
    {
        $tabela = $this->validarTabela($evento);
        $stmt   = $this->db->prepare("SELECT * FROM {$tabela} WHERE competencia_id = ? ORDER BY {$orderBy}");
        $stmt->execute([$competenciaId]);
        return $stmt->fetchAll();
    }

    public function contar(string $evento, int $competenciaId): int
    {
        $tabela = $this->validarTabela($evento);
        $stmt   = $this->db->prepare("SELECT COUNT(*) FROM {$tabela} WHERE competencia_id = ?");
        $stmt->execute([$competenciaId]);
        return (int) $stmt->fetchColumn();
    }

    public function inserir(string $evento, array $data): int
    {
        $tabela = $this->validarTabela($evento);
        $cols   = implode(', ', array_keys($data));
        $places = implode(', ', array_fill(0, count($data), '?'));
        $stmt   = $this->db->prepare("INSERT INTO {$tabela} ({$cols}) VALUES ({$places})");
        $stmt->execute(array_values($data));
        return (int) $this->db->lastInsertId();
    }

    public function atualizar(string $evento, int $id, int $competenciaId, array $data): void
    {
        $tabela = $this->validarTabela($evento);
        $sets   = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
        $this->db->prepare("UPDATE {$tabela} SET {$sets} WHERE id = ? AND competencia_id = ?")
            ->execute([...array_values($data), $id, $competenciaId]);
    }

    public function excluir(string $evento, int $id, int $competenciaId): void
    {
        $tabela = $this->validarTabela($evento);
        $this->db->prepare("DELETE FROM {$tabela} WHERE id = ? AND competencia_id = ?")
            ->execute([$id, $competenciaId]);
    }

    public function carregarTodos(int $competenciaId): array
    {
        return [
            'r2010' => $this->listar('r2010', $competenciaId),
            'r2020' => $this->listar('r2020', $competenciaId),
            'r2060' => $this->listar('r2060', $competenciaId),
            'r4010' => $this->listar('r4010', $competenciaId),
            'r4020' => $this->listar('r4020', $competenciaId),
        ];
    }

    private function validarTabela(string $evento): string
    {
        $tabela = strtolower(trim($evento));
        $validas = ['r2010', 'r2020', 'r2060', 'r4010', 'r4020'];
        if (!in_array($tabela, $validas)) {
            throw new \InvalidArgumentException("Tabela de evento inválida: {$evento}");
        }
        return $tabela;
    }
}