<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\R2010;
use App\Models\R2020;
use App\Models\R2055;
use App\Models\R2060;
use App\Models\R4010;
use App\Models\R4020;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EventoRepository
{
    private const TABELAS = ['r2010', 'r2020', 'r2055', 'r2060', 'r4010', 'r4020'];

    private const MODELS = [
        'r2010' => R2010::class,
        'r2020' => R2020::class,
        'r2055' => R2055::class,
        'r2060' => R2060::class,
        'r4010' => R4010::class,
        'r4020' => R4020::class,
    ];

    private const ORDER_WHITELIST = [
        'created_at ASC',
        'created_at DESC',
        'data_pagamento ASC',
        'data_pagamento DESC',
        'data_emissao ASC',
        'data_emissao DESC',
        'id ASC',
        'id DESC',
        'cnpj_prestador, data_emissao, id',
        'cnpj_beneficiario, natureza_rendimento, data_pagamento',
        'nr_insc_adquirente, nr_insc_produtor, ind_aquis, id',
    ];

    /** Todos os registros da competência (geração XML), sem limite artificial. */
    public function listarParaGeracao(string $evento, int $competenciaId, string $orderBy = 'id ASC'): array
    {
        $q = $this->queryEvento($evento)->where('competencia_id', $competenciaId);
        $this->applyOrderBy($q, $orderBy);

        return $this->toRows($q->get());
    }

    public function listar(string $evento, int $competenciaId, string $orderBy = 'created_at DESC', int $limit = 100, int $offset = 0): array
    {
        $q = $this->queryEvento($evento)->where('competencia_id', $competenciaId);
        $this->applyOrderBy($q, $orderBy);

        return $this->toRows(
            $q->limit(max(0, $limit))->offset(max(0, $offset))->get()
        );
    }

    public function contar(string $evento, int $competenciaId): int
    {
        return (int) $this->queryEvento($evento)
            ->where('competencia_id', $competenciaId)
            ->count();
    }

    public function find(string $evento, int $id, int $competenciaId): ?array
    {
        return $this->toRow(
            $this->queryEvento($evento)
                ->whereKey($id)
                ->where('competencia_id', $competenciaId)
                ->first()
        );
    }

    public function inserir(string $evento, array $data): int
    {
        $class = $this->modelClass($evento);
        /** @var Model $model */
        $model = new $class();
        $model->forceFill($data);
        $model->save();

        return (int) $model->getKey();
    }

    public function atualizar(string $evento, int $id, int $competenciaId, array $data): void
    {
        $this->queryEvento($evento)
            ->whereKey($id)
            ->where('competencia_id', $competenciaId)
            ->update($data);
    }

    public function excluir(string $evento, int $id, int $competenciaId): void
    {
        $this->queryEvento($evento)
            ->whereKey($id)
            ->where('competencia_id', $competenciaId)
            ->delete();
    }

    public function carregarTodos(int $competenciaId, int $limitPorEvento = 20): array
    {
        return [
            'r2010' => $this->listar('r2010', $competenciaId, 'created_at DESC', $limitPorEvento),
            'r2020' => $this->listar('r2020', $competenciaId, 'created_at DESC', $limitPorEvento),
            'r2055' => $this->listar('r2055', $competenciaId, 'created_at DESC', $limitPorEvento),
            'r2060' => $this->listar('r2060', $competenciaId, 'created_at DESC', $limitPorEvento),
            'r4010' => $this->listar('r4010', $competenciaId, 'data_pagamento DESC', $limitPorEvento),
            'r4020' => $this->listar('r4020', $competenciaId, 'data_pagamento DESC', $limitPorEvento),
        ];
    }

    /** @return class-string<Model> */
    private function modelClass(string $evento): string
    {
        $tabela = $this->validarTabela($evento);

        return self::MODELS[$tabela];
    }

    /** @return Builder<Model> */
    private function queryEvento(string $evento): Builder
    {
        $class = $this->modelClass($evento);

        return $class::query();
    }

    private function validarTabela(string $evento): string
    {
        $tabela = strtolower(trim($evento));
        if (!in_array($tabela, self::TABELAS, true)) {
            throw new \InvalidArgumentException("Tabela de evento inválida: {$evento}");
        }

        return $tabela;
    }

    private function validarOrderBy(string $orderBy): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($orderBy)) ?? '';
        if (!in_array($normalized, self::ORDER_WHITELIST, true)) {
            return 'created_at DESC';
        }

        return $normalized;
    }

    /** @param Builder<Model> $q */
    private function applyOrderBy(Builder $q, string $orderBy): void
    {
        $orderBy = $this->validarOrderBy($orderBy);
        foreach (explode(',', $orderBy) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^([a-zA-Z0-9_]+)\s+(ASC|DESC)$/i', $part, $m)) {
                $q->orderBy($m[1], strtolower($m[2]));
            } else {
                $q->orderBy($part);
            }
        }
    }

    private function toRow(mixed $model): ?array
    {
        if ($model === null) {
            return null;
        }
        if ($model instanceof Model) {
            return $model->getAttributes();
        }

        return (array) $model;
    }

    /** @param iterable<mixed> $models */
    private function toRows(iterable $models): array
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
}
