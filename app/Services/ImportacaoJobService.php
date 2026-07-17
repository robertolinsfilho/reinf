<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Jobs de importação em chunks (barra de progresso).
 */
class ImportacaoJobService
{
    private string $baseDir;

    public function __construct(private ImportacaoService $importacao)
    {
        $this->baseDir = storage_path('app/imports');
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0755, true);
        }
    }

    /** @return array{token: string, total: int} */
    public function criar(
        string $arquivoPath,
        string $originalName,
        string $evento,
        string $modo,
        int $userId,
        ?int $competenciaId,
        ?int $contribuinteId,
        int $maxRows = 0
    ): array {
        $prep = $this->importacao->prepararLinhas($arquivoPath, $evento, $maxRows);
        $token = bin2hex(random_bytes(16));
        $dir = $this->baseDir . '/' . $token;
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível criar pasta do job de importação.');
        }

        $fh = fopen($dir . '/rows.jsonl', 'wb');
        if ($fh === false) {
            throw new \RuntimeException('Falha ao gravar linhas da planilha.');
        }
        foreach ($prep['rows'] as $row) {
            fwrite($fh, json_encode($row, JSON_UNESCAPED_UNICODE) . "\n");
        }
        fclose($fh);
        @unlink($arquivoPath);

        $meta = [
            'token'           => $token,
            'user_id'         => $userId,
            'evento'          => $evento,
            'modo'            => $modo,
            'layout'          => $prep['layout'],
            'original_name'   => $originalName,
            'competencia_id'  => $competenciaId,
            'contribuinte_id' => $contribuinteId,
            'total'           => count($prep['rows']),
            'offset'          => 0,
            'importados'      => 0,
            'uteis'           => 0,
            'erros'           => [],
            'cache_comp'      => [],
            'resumo'          => [],
            'status'          => 'ready',
            'message'         => '',
        ];
        $this->salvarMeta($token, $meta);

        return ['token' => $token, 'total' => $meta['total']];
    }

    /** @return array<string, mixed> */
    public function processarChunk(string $token, int $userId, int $chunkSize = 250): array
    {
        $meta = $this->carregarMeta($token);
        if ((int) ($meta['user_id'] ?? 0) !== $userId) {
            throw new \RuntimeException('Job de importação inválido.');
        }
        if (in_array($meta['status'] ?? '', ['done', 'error'], true)) {
            return $this->resposta($meta, true);
        }

        $meta['status'] = 'running';
        $offset = (int) $meta['offset'];
        $total  = (int) $meta['total'];
        $chunkSize = max(50, min(500, $chunkSize));

        $rowsFile = $this->baseDir . '/' . $token . '/rows.jsonl';
        if (!is_file($rowsFile)) {
            throw new \RuntimeException('Arquivo de linhas do job não encontrado.');
        }

        $file = new \SplFileObject($rowsFile, 'r');
        $file->seek($offset);
        $batch = [];
        $lidos = 0;
        while ($lidos < $chunkSize && !$file->eof()) {
            $line = $file->current();
            $file->next();
            if (!is_string($line) || trim($line) === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }
            $batch[] = $decoded;
            $lidos++;
        }

        $result = $this->importacao->processarLoteLinhas(
            (string) $meta['evento'],
            $batch,
            $userId,
            [
                'layout'          => (string) ($meta['layout'] ?? 'simples'),
                'modo'            => (string) ($meta['modo'] ?? 'manual'),
                'competencia_id'  => $meta['competencia_id'] ?? null,
                'contribuinte_id' => $meta['contribuinte_id'] ?? null,
                'cache_comp'      => $meta['cache_comp'] ?? [],
                'resumo'          => $meta['resumo'] ?? [],
            ]
        );

        $meta['offset']     = $offset + count($batch);
        $meta['importados'] = (int) $meta['importados'] + (int) $result['importados'];
        $meta['uteis']      = (int) $meta['uteis'] + (int) $result['uteis'];
        $meta['erros']      = array_slice(array_merge($meta['erros'] ?? [], $result['erros']), 0, 50);
        $meta['cache_comp'] = $result['cache_comp'];
        $meta['resumo']     = $result['resumo'];

        $done = $meta['offset'] >= $total;
        if ($done) {
            $meta['status']  = 'done';
            $meta['message'] = $this->mensagemFinal($meta);
            @unlink($rowsFile);
        }

        $this->salvarMeta($token, $meta);
        return $this->resposta($meta, $done);
    }

    /** @param array<string, mixed> $meta */
    private function resposta(array $meta, bool $done): array
    {
        $total = max(1, (int) $meta['total']);
        $processed = min((int) $meta['offset'], (int) $meta['total']);
        return [
            'done'          => $done,
            'processed'     => $processed,
            'total'         => (int) $meta['total'],
            'importados'    => (int) $meta['importados'],
            'uteis'         => (int) ($meta['uteis'] ?? 0),
            'percent'       => round(($processed / $total) * 100, 1),
            'status'        => (string) ($meta['status'] ?? ''),
            'message'       => (string) ($meta['message'] ?? ''),
            'erros'         => array_values($meta['erros'] ?? []),
            'competencias'  => array_values($meta['resumo'] ?? []),
            'original_name' => (string) ($meta['original_name'] ?? ''),
            'evento'        => (string) ($meta['evento'] ?? ''),
            'modo'          => (string) ($meta['modo'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $meta */
    private function mensagemFinal(array $meta): string
    {
        $importados = (int) $meta['importados'];
        $resumo = array_values($meta['resumo'] ?? []);
        if (($meta['modo'] ?? '') === 'auto' && $resumo !== []) {
            $periodos = [];
            $criadas = 0;
            foreach ($resumo as $c) {
                $periodos[] = ($c['periodo'] ?? '?') . ' (' . (int) ($c['importados'] ?? 0) . ')';
                if (!empty($c['criada'])) {
                    $criadas++;
                }
            }
            $msg = "{$importados} registro(s) em " . count($resumo) . ' competência(s)';
            if ($criadas > 0) {
                $msg .= " — {$criadas} criada(s)";
            }
            $msg .= ': ' . implode(', ', $periodos);
        } else {
            $uteis = (int) ($meta['uteis'] ?? $meta['total'] ?? 0);
            $msg = "{$importados} de {$uteis} registros importados!";
        }
        if (!empty($meta['erros'])) {
            $msg .= ' | Avisos: ' . implode('; ', array_slice($meta['erros'], 0, 3));
        }
        return $msg;
    }

    /** @param array<string, mixed> $meta */
    private function salvarMeta(string $token, array $meta): void
    {
        file_put_contents($this->baseDir . '/' . $token . '/meta.json', json_encode($meta, JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string, mixed> */
    private function carregarMeta(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            throw new \RuntimeException('Token inválido.');
        }
        $path = $this->baseDir . '/' . $token . '/meta.json';
        if (!is_file($path)) {
            throw new \RuntimeException('Job de importação não encontrado.');
        }
        $meta = json_decode((string) file_get_contents($path), true);
        if (!is_array($meta)) {
            throw new \RuntimeException('Meta do job corrompida.');
        }
        return $meta;
    }
}
