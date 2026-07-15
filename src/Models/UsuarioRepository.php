<?php

namespace App\Models;

class UsuarioRepository extends Repository
{
    protected string $table = 'usuarios';

    public function findByEmail(string $email): ?array
    {
        return $this->queryOne(
            "SELECT * FROM usuarios WHERE email = ? AND ativo = 1",
            [$email]
        );
    }

    public function listAll(): array
    {
        return $this->query("SELECT id, nome, email, perfil, ativo, trial_expira, created_at FROM usuarios ORDER BY nome");
    }

    public function criar(string $nome, string $email, string $senhaHash, string $perfil, ?string $trialExpira): int
    {
        return $this->insert([
            'nome'          => $nome,
            'email'         => $email,
            'senha'         => $senhaHash,
            'perfil'        => $perfil,
            'trial_expira'  => $trialExpira,
        ]);
    }

    public function atualizarPerfil(int $id, string $nome, ?string $senhaHash = null): void
    {
        $data = ['nome' => $nome];
        if ($senhaHash) {
            $data['senha'] = $senhaHash;
            $data['force_password_change'] = 0;
        }
        $this->update($id, $data);
    }
}