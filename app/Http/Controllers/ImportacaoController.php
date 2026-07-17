<?php

namespace App\Http\Controllers;

use App\Repositories\CompetenciaRepository;
use App\Repositories\ContribuinteRepository;
use App\Repositories\ImportacaoLogRepository;
use App\Services\ImportacaoService;
use Illuminate\Http\Request;

class ImportacaoController extends Controller
{
    public function index()
    {
        $uid = $this->userId();

        return $this->render('pages.importacao.index', [
            'pageTitle'     => 'Importar Planilha Excel',
            'competencias'  => (new CompetenciaRepository($this->db))->listByUser($uid),
            'contribuintes' => (new ContribuinteRepository($this->db))->listByUser($uid),
            'historico'     => (new ImportacaoLogRepository($this->db))->historicoByUser($uid),
        ]);
    }

    public function processar(Request $request)
    {
        $uid = $this->userId();

        $uploaded = $request->file('arquivo');
        if (!$uploaded || !$uploaded->isValid()) {
            return $this->flashRedirect('/importar', 'Erro ao fazer upload do arquivo.', 'erro');
        }

        $evento = $request->input('evento', '');
        $modo   = $request->input('modo', 'manual');

        if (!$evento) {
            return $this->flashRedirect('/importar', 'Selecione o evento.', 'erro');
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
            return $this->flashRedirect('/importar', $e->getMessage(), 'erro');
        }

        $uploadDir = config('reinf.upload.path', storage_path('uploads'));
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $nomeArquivo = uniqid('reinf_', true) . '.' . $ext;
        $destino     = rtrim($uploadDir, '/') . '/' . $nomeArquivo;

        try {
            $uploaded->move($uploadDir, $nomeArquivo);
        } catch (\Throwable $e) {
            return $this->flashRedirect('/importar', 'Falha ao salvar arquivo.', 'erro');
        }

        $logRepo = new ImportacaoLogRepository($this->db);
        $service = new ImportacaoService($this->db);
        $maxRows = (int) config('reinf.security.max_import_rows', 5000);

        try {
            if ($modo === 'auto') {
                $eventosAuto = ['R2010', 'R2055', 'R4020'];
                if (!in_array($evento, $eventosAuto, true)) {
                    return $this->flashRedirect(
                        '/importar',
                        'Criação automática disponível por enquanto para R-2010, R-2055 e R-4020.',
                        'erro'
                    );
                }

                $contribId = (int) $request->input('contribuinte_id', 0) ?: null;

                $result = $service->processarPorPeriodo($destino, $evento, $uid, $contribId, $maxRows);

                if (empty($result['log_competencia_ids'])) {
                    $msg = 'Nenhum registro importado.';
                    if (!empty($result['erros'])) {
                        $msg .= ' ' . implode('; ', array_slice($result['erros'], 0, 3));
                    }
                    return $this->flashRedirect('/importar', $msg, 'erro');
                }

                foreach ($result['log_competencia_ids'] as $compId) {
                    $importacaoId = $logRepo->registrar((int) $compId, $uid, $originalName, $evento);
                    $qtdComp = 0;
                    foreach ($result['competencias'] as $c) {
                        if ((int) $c['id'] === (int) $compId) {
                            $qtdComp = (int) $c['importados'];
                            break;
                        }
                    }
                    $logRepo->marcarSucesso($importacaoId, $result['total'], $qtdComp);
                }

                $periodos = [];
                $criadas  = 0;
                foreach ($result['competencias'] as $c) {
                    $periodos[] = $c['periodo'] . ' (' . $c['importados'] . ')';
                    if (!empty($c['criada'])) {
                        $criadas++;
                    }
                }

                $msg = "{$result['importados']} registro(s) em " . count($result['competencias'])
                     . " competência(s)";
                if ($criadas > 0) {
                    $msg .= " — {$criadas} criada(s)";
                }
                $msg .= ': ' . implode(', ', $periodos);
                if (!empty($result['erros'])) {
                    $msg .= ' | Avisos: ' . implode('; ', array_slice($result['erros'], 0, 3));
                }
                return $this->flashRedirect('/importar', $msg, 'sucesso');
            }

            // Modo manual (comportamento original)
            $compId = (int) $request->input('competencia_id');
            if (!$compId) {
                return $this->flashRedirect('/importar', 'Selecione a competência.', 'erro');
            }

            $comp = (new CompetenciaRepository($this->db))->findWithContribuinte($compId, $uid);
            if (!$comp) {
                return $this->flashRedirect('/importar', 'Competência não encontrada.', 'erro');
            }

            $importacaoId = $logRepo->registrar($compId, $uid, $originalName, $evento);
            $result       = $service->processar($destino, $evento, $compId, $maxRows);
            $logRepo->marcarSucesso($importacaoId, $result['total'], $result['importados']);

            $msg = "{$result['importados']} de {$result['total']} registros importados!";
            if (!empty($result['erros'])) {
                $msg .= ' Erros: ' . implode('; ', array_slice($result['erros'], 0, 3));
            }
            return $this->flashRedirect('/importar', $msg, 'sucesso');
        } catch (\Throwable $e) {
            report($e);
            $msg = $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Não foi possível importar a planilha.';
            return $this->flashRedirect('/importar', $msg, 'erro');
        }
    }
}
