<?php

namespace App\Models;

class ArquivoGeradoRepository extends Repository
{
    protected string $table = 'arquivos_gerados';

    public function listByCompetencia(int $competenciaId): array
    {
        return $this->query(
            "SELECT * FROM arquivos_gerados WHERE competencia_id = ? ORDER BY created_at DESC",
            [$competenciaId]
        );
    }

    public function findByIds(array $ids): array
    {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return $this->query("SELECT * FROM arquivos_gerados WHERE id IN ({$placeholders})", $ids);
    }

    public function salvar(int $competenciaId, int $userId, array $arq, bool $assinado): int
    {
        return $this->insert([
            'competencia_id' => $competenciaId,
            'usuario_id'     => $userId,
            'evento'         => $arq['evento'],
            'nome_arquivo'   => $arq['nome'],
            'caminho'        => $arq['caminho'],
            'tamanho'        => $arq['tamanho'],
            'hash_md5'       => $arq['hash'],
            'xml_conteudo'   => $arq['xml'],
            'assinado'       => $assinado ? 1 : 0,
        ]);
    }
}