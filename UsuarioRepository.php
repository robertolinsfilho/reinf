<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Usuario;

class UsuarioRepository extends Repository
{
    protected string $table = 'usuarios';

    protected string $modelClass = Usuario::class;

    public function findByEmail(string $email): ?array
    {
        return $this->toRow(
            $this->newQuery()
                ->where('email', $email)
                ->where('ativo', 1)
                ->first()
        );
    }

    public function listAll(): array
    {
        return $this->toRows(
            $this->newQuery()
                ->select(['id', 'nome', 'email', 'perfil', 'ativo', 'trial_expira', 'created_at'])
                ->orderBy('nome')
                ->get()
        );
    }

    public function criar(string $nome, string $email, string $senhaHash, string $perfil, ?string $trialExpira): int
    {
        return $this->insert([
            'nome' => $nome,
            'email' => $email,
            'senha' => $senhaHash,
            'perfil' => $perfil,
            'trial_expira' => $trialExpira,
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
