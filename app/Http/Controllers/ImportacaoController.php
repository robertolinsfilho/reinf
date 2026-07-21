<?php

namespace App\Http\Controllers;

use App\Repositories\CompetenciaRepository;
use App\Repositories\ContribuinteRepository;
use App\Repositories\ImportacaoLogRepository;
use App\Services\ImportacaoJobService;
use App\Services\ImportacaoService;
use Illuminate\Http\Request;

class ImportacaoController extends Controller
{
    public function index()
    {
        $uid = $this->userId();

        return $this->render('pages.importacao.index', [
            'pageTitle'     => 'Importar Planilha Excel',
            'competencias'  => (new CompetenciaRepository())->listByUser($uid),
            'contribuintes' => (new ContribuinteRepository())->listByUser($uid),
            'historico'     => (new ImportacaoLogRepository())->historicoByUser($uid),
        ]);
    }

    public function processar(Request $request)
    {
        // Mantido para compatibilidade; a UI usa iniciar/chunk com progresso.
        return $this->flashRedirect('/importar', 'Use o formulário de importação da tela.', 'erro');
    }

    public function iniciar(Request $request)
    {
        $uid = $this->userId();
        $uploaded = $request->file('arquivo');
        if (!$uploaded || !$uploaded->isValid()) {
            return response()->json(['ok' => false, 'erro' => 'Erro ao fazer upload do arquivo.'], 422);
        }

        $evento = (string) $request->input('evento', '');
        $modo   = (string) $request->input('modo', 'manual');
        if ($evento === '') {
            return response()->json(['ok' => false, 'erro' => 'Selecione o evento.'], 422);
        }

        $originalName = $uploaded->getClientOriginalName();
        $maxSize      = (int) config('reinf.upload.max_size', 50 * 1024 * 1024);
        $allowed      = config('reinf.upload.allowed', ['xlsx', 'xls', 'xlsm']);
        $fileArr = [
            'error'    => $uploaded->getError(),
            'size'     => $uploaded->getSize(),
            'name'     => $originalName,
            'tmp_name' => $uploaded->getPathname(),
        ];

        try {
            $ext = $this->assertUploadedFile($fileArr, $maxSize, $allowed);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        }

        if ($modo === 'auto' && !in_array($evento, ['R2010', 'R2055', 'R4020'], true)) {
            return response()->json([
                'ok' => false,
                'erro' => 'Criação automática disponível para R-2010, R-2055 e R-4020.',
            ], 422);
        }

        $compId = (int) $request->input('competencia_id', 0) ?: null;
        $contribId = (int) $request->input('contribuinte_id', 0) ?: null;

        if ($modo !== 'auto') {
            if (!$compId) {
                return response()->json(['ok' => false, 'erro' => 'Selecione a competência.'], 422);
            }
            $comp = (new CompetenciaRepository())->findWithContribuinte($compId, $uid);
            if (!$comp) {
                return response()->json(['ok' => false, 'erro' => 'Competência não encontrada.'], 422);
            }
        }

        $uploadDir = config('reinf.upload.path', storage_path('uploads'));
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $nomeArquivo = uniqid('reinf_', true) . '.' . $ext;
        $destino     = rtrim($uploadDir, '/') . '/' . $nomeArquivo;

        try {
            $uploaded->move($uploadDir, $nomeArquivo);
            $job = new ImportacaoJobService(new ImportacaoService());
            $criado = $job->criar(
                $destino,
                $originalName,
                $evento,
                $modo === 'auto' ? 'auto' : 'manual',
                $uid,
                $compId,
                $contribId,
                (int) config('reinf.security.max_import_rows', 0)
            );
            return response()->json([
                'ok'    => true,
                'token' => $criado['token'],
                'total' => $criado['total'],
            ]);
        } catch (\Throwable $e) {
            report($e);
            @unlink($destino);
            $msg = $e instanceof \RuntimeException ? $e->getMessage() : 'Não foi possível preparar a importação.';
            return response()->json(['ok' => false, 'erro' => $msg], 500);
        }
    }

    public function chunk(Request $request)
    {
        $uid = $this->userId();
        $token = (string) $request->input('token', '');
        if ($token === '') {
            return response()->json(['ok' => false, 'erro' => 'Token ausente.'], 422);
        }

        try {
            $job = new ImportacaoJobService(new ImportacaoService());
            $progress = $job->processarChunk($token, $uid, 250);

            if (!empty($progress['done'])) {
                $logRepo = new ImportacaoLogRepository();
                $comps = $progress['competencias'] ?? [];
                if (($progress['modo'] ?? '') === 'auto' && $comps !== []) {
                    foreach ($comps as $c) {
                        $compId = (int) ($c['id'] ?? 0);
                        if ($compId <= 0) {
                            continue;
                        }
                        $importacaoId = $logRepo->registrar(
                            $compId,
                            $uid,
                            (string) ($progress['original_name'] ?? ''),
                            (string) ($progress['evento'] ?? '')
                        );
                        $logRepo->marcarSucesso(
                            $importacaoId,
                            (int) ($progress['uteis'] ?? $progress['total'] ?? 0),
                            (int) ($c['importados'] ?? 0)
                        );
                    }
                } elseif (!empty($comps[0]['id'])) {
                    $importacaoId = $logRepo->registrar(
                        (int) $comps[0]['id'],
                        $uid,
                        (string) ($progress['original_name'] ?? ''),
                        (string) ($progress['evento'] ?? '')
                    );
                    $logRepo->marcarSucesso(
                        $importacaoId,
                        (int) ($progress['uteis'] ?? $progress['total'] ?? 0),
                        (int) ($progress['importados'] ?? 0)
                    );
                }
            }

            return response()->json(['ok' => true] + $progress);
        } catch (\Throwable $e) {
            report($e);
            $msg = $e instanceof \RuntimeException ? $e->getMessage() : 'Falha ao processar lote.';
            return response()->json(['ok' => false, 'erro' => $msg], 500);
        }
    }
}
