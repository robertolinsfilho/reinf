<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Certificado;

class CertificadoRepository extends Repository
{
    protected string $table = 'certificados';

    protected string $modelClass = Certificado::class;

    public function listByUser(int $userId): array
    {
        return $this->toRows(
            $this->newQuery()
                ->from('certificados as cert')
                ->join('contribuintes as co', 'co.id', '=', 'cert.contribuinte_id')
                ->where('co.usuario_id', $userId)
                ->select('cert.*', 'co.razao_social', 'co.cnpj as cnpj_contribuinte')
                ->orderByDesc('cert.ativo')
                ->orderByDesc('cert.created_at')
                ->get()
        );
    }

    public function findAtivoByUser(int $userId): ?array
    {
        return $this->toRow(
            $this->newQuery()
                ->from('certificados as cert')
                ->join('contribuintes as co', 'co.id', '=', 'cert.contribuinte_id')
                ->where('co.usuario_id', $userId)
                ->where('cert.ativo', 1)
                ->select('cert.*')
                ->orderByDesc('cert.id')
                ->first()
        );
    }

    /** Certificado ativo do contribuinte (escopo correto para transmissão). */
    public function findAtivoByContribuinte(int $contribuinteId, ?int $userId = null): ?array
    {
        $q = $this->newQuery()
            ->from('certificados as cert')
            ->join('contribuintes as co', 'co.id', '=', 'cert.contribuinte_id')
            ->where('cert.contribuinte_id', $contribuinteId)
            ->where('cert.ativo', 1)
            ->select('cert.*');

        if ($userId !== null) {
            $q->where('co.usuario_id', $userId);
        }

        return $this->toRow($q->orderByDesc('cert.id')->first());
    }

    public function desativarTodosDoUsuario(int $userId, ?int $contribuinteId = null): void
    {
        $q = $this->newQuery()
            ->from('certificados as cert')
            ->join('contribuintes as co', 'co.id', '=', 'cert.contribuinte_id')
            ->where('co.usuario_id', $userId);

        if ($contribuinteId) {
            $q->where('cert.contribuinte_id', $contribuinteId);
        }

        $ids = $q->pluck('cert.id');
        if ($ids->isEmpty()) {
            return;
        }

        $this->newQuery()->whereIn('id', $ids)->update(['ativo' => 0]);
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
            'contribuinte_id' => $contribId,
            'nome_arquivo' => $nomeArq,
            'caminho' => $caminho,
            'senha_encrypted' => $senhaEnc,
            'cnpj_certificado' => $cnpj,
            'titular' => $titular,
            'validade' => $validade,
            'ativo' => 1,
        ]);
    }
}
