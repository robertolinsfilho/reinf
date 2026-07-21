<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base Eloquent dos repositories.
 * API pública continua retornando arrays no formato PDO FETCH_ASSOC (atributos crus).
 */
abstract class Repository
{
    protected string $table;

    /** @var class-string<Model> */
    protected string $modelClass;

    /** @return Builder<Model> */
    protected function newQuery(): Builder
    {
        return $this->modelClass::query();
    }

    /**
     * Converte Model/stdClass para array associativo sem aplicar casts.
     *
     * @return array<string, mixed>|null
     */
    protected function toRow(mixed $model): ?array
    {
        if ($model === null) {
            return null;
        }
        if ($model instanceof Model) {
            return $model->getAttributes();
        }

        return (array) $model;
    }

    /**
     * @param  iterable<mixed>  $models
     * @return list<array<string, mixed>>
     */
    protected function toRows(iterable $models): array
    {
        $out = [];
        foreach ($models as $model) {
            $row = $this->toRow($model);
            if ($row !== null) {
                $out[] = $row;
            }
        }

        return $out;
    }

    public function find(int $id): ?array
    {
        return $this->toRow($this->newQuery()->find($id));
    }

    public function findBy(string $column, mixed $value): array
    {
        $this->assertColumn($column);

        return $this->toRows($this->newQuery()->where($column, $value)->get());
    }

    public function findOneBy(string $column, mixed $value): ?array
    {
        $this->assertColumn($column);

        return $this->toRow($this->newQuery()->where($column, $value)->first());
    }

    public function insert(array $data): int
    {
        /** @var Model $model */
        $model = new $this->modelClass();
        $model->forceFill($data);
        $model->save();

        return (int) $model->getKey();
    }

    public function update(int $id, array $data): void
    {
        $this->newQuery()->whereKey($id)->update($data);
    }

    public function delete(int $id): void
    {
        $this->newQuery()->whereKey($id)->delete();
    }

    public function deleteWhere(string $column, mixed $value): void
    {
        $this->assertColumn($column);
        $this->newQuery()->where($column, $value)->delete();
    }

    public function count(string $column, mixed $value): int
    {
        $this->assertColumn($column);

        return (int) $this->newQuery()->where($column, $value)->count();
    }

    protected function assertColumn(string $column): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new \InvalidArgumentException("Coluna inválida: {$column}");
        }
    }
}
