<?php

declare(strict_types=1);

namespace App\Models;

class CertificadoRepository extends Repository
{
    protected string $table = 'certificados';

    public function listByUser(int $userId): array
    {
        return $this->query("
            SELECT cert.*, co.razao_social, co.cnpj AS cnpj_contribuinte
            FROM certificados cert
            JOIN contribuintes co ON co.id = cert.contribuinte_id
            WHERE co.usuario_id = ?
            ORDER BY cert.ativo DESC, cert.created_at DESC
        ", [$userId]);
    }

    public function findAtivoByUser(int $userId): ?array
    {
        return $this->queryOne("
            SELECT cert.*
            FROM certificados cert
            JOIN contribuintes co ON co.id = cert.contribuinte_id
            WHERE co.usuario_id = ? AND cert.ativo = 1
            ORDER BY cert.id DESC
            LIMIT 1
        ", [$userId]);
    }

    /** Certificado ativo do contribuinte (escopo correto para transmissão). */
    public function findAtivoByContribuinte(int $contribuinteId, ?int $userId = null): ?array
    {
        $sql = "
            SELECT cert.*
            FROM certificados cert
            JOIN contribuintes co ON co.id = cert.contribuinte_id
            WHERE cert.contribuinte_id = ?
              AND cert.ativo = 1
        ";
        $params = [$contribuinteId];
        if ($userId !== null) {
            $sql .= ' AND co.usuario_id = ?';
            $params[] = $userId;
        }
        $sql .= ' ORDER BY cert.id DESC LIMIT 1';
        return $this->queryOne($sql, $params);
    }

    public function desativarTodosDoUsuario(int $userId, ?int $contribuinteId = null): void
    {
        if ($contribuinteId) {
            $this->db->prepare("
                UPDATE certificados cert
                JOIN contribuintes co ON co.id = cert.contribuinte_id
                SET cert.ativo = 0
                WHERE co.usuario_id = ? AND cert.contribuinte_id = ?
            ")->execute([$userId, $contribuinteId]);
            return;
        }

        $this->db->prepare("
            UPDATE certificados cert
            JOIN contribuintes co ON co.id = cert.contribuinte_id
            SET cert.ativo = 0
            WHERE co.usuario_id = ?
        ")->execute([$userId]);
    }

    public function criarComSenha(
        int $contribId,
        string $nomeArq,
        string $caminho,
        string $senhaEnc,
        string $cnpj,
        string $titular,
        string $validade
    ): int {
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
