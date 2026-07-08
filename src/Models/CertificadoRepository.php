<?php

namespace App\Models;

class CertificadoRepository extends Repository
{
    protected string $table = 'certificados';

    public function listAll(): array
    {
        return $this->query("SELECT * FROM certificados ORDER BY created_at DESC");
    }

    public function findAtivo(): ?array
    {
        return $this->queryOne("SELECT * FROM certificados WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
    }

    public function desativarTodos(int $contribuinteId): void
    {
        $this->db->prepare("UPDATE certificados SET ativo = 0 WHERE contribuinte_id = ?")->execute([$contribuinteId]);
    }

    public function criar(int $contribId, string $nomeArq, string $caminho, string $cnpj, string $titular, string $validade): int
    {
        return $this->insert([
            'contribuinte_id'  => $contribId,
            'nome_arquivo'     => $nomeArq,
            'caminho'          => $caminho,
            'cnpj_certificado' => $cnpj,
            'titular'          => $titular,
            'validade'         => $validade,
            'ativo'            => 1,
        ]);
    }

    public function criarComSenha(int $contribId, string $nomeArq, string $caminho, string $senhaEnc, string $cnpj, string $titular, string $validade): int
    {
        return $this->insert([
            'contribuinte_id'  => $contribId,
            'nome_arquivo'     => $nomeArq,
            'caminho'          => $caminho,
            'senha_encrypted'  => $senhaEnc,
            'cnpj_certificado' => $cnpj,
            'titular'          => $titular,
            'validade'         => $validade,
            'ativo'            => 1,
        ]);
    }
}