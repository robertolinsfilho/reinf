<?php

namespace App\Models;

abstract class Repository
{
    protected \PDO $db;
    protected string $table;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findBy(string $column, mixed $value): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$column} = ?");
        $stmt->execute([$value]);
        return $stmt->fetchAll();
    }

    public function findOneBy(string $column, mixed $value): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$column} = ? LIMIT 1");
        $stmt->execute([$value]);
        return $stmt->fetch() ?: null;
    }

    public function insert(array $data): int
    {
        $cols   = implode(', ', array_keys($data));
        $places = implode(', ', array_fill(0, count($data), '?'));
        $stmt   = $this->db->prepare("INSERT INTO {$this->table} ({$cols}) VALUES ({$places})");
        $stmt->execute(array_values($data));
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
        $stmt = $this->db->prepare("UPDATE {$this->table} SET {$sets} WHERE id = ?");
        $stmt->execute([...array_values($data), $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?")->execute([$id]);
    }

    public function deleteWhere(string $column, mixed $value): void
    {
        $this->db->prepare("DELETE FROM {$this->table} WHERE {$column} = ?")->execute([$value]);
    }

    public function count(string $column, mixed $value): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE {$column} = ?");
        $stmt->execute([$value]);
        return (int) $stmt->fetchColumn();
    }

    protected function query(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    protected function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}