<?php

namespace App\Controllers;

use App\Models\ContribuinteRepository;
use App\Models\CompetenciaRepository;
use App\Models\ImportacaoLogRepository;
use App\Services\ImportacaoService;

class ImportacaoController extends BaseController
{
    public function index(): void
    {
        $this->requireLogin();
        $uid = $this->userId();

        $this->view('pages/importacao/index', [
            'pageTitle'     => 'Importar Planilha Excel',
            'competencias'  => (new CompetenciaRepository($this->db))->listByUser($uid),
            'contribuintes' => (new ContribuinteRepository($this->db))->listByUser($uid),
            'historico'     => (new ImportacaoLogRepository($this->db))->historicoByUser($uid),
            'flash'         => $this->getFlash(),
        ]);
    }

    public function processar(): void
    {
        $this->requireLogin();
        $uid = $this->userId();

        if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            $this->redirect('/importar', 'Erro ao fazer upload do arquivo.', 'erro');
        }

        $evento = $this->post('evento', '');
        $modo   = $this->post('modo', 'manual');

        if (!$evento) {
            $this->redirect('/importar', 'Selecione o evento.', 'erro');
        }

        $arquivo = $_FILES['arquivo'];
        $maxSize = (int) ($this->config['upload']['max_size'] ?? (50 * 1024 * 1024));
        $allowed = $this->config['upload']['allowed'] ?? ['xlsx', 'xls', 'xlsm'];

        try {
            $ext = $this->assertUploadedFile($arquivo, $maxSize, $allowed);
        } catch (\RuntimeException $e) {
            $this->redirect('/importar', $e->getMessage(), 'erro');
        }

        $uploadDir = $this->config['upload']['path'] ?? BASE_PATH . '/storage/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $destino = $uploadDir . uniqid('reinf_', true) . '.' . $ext;

        if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
            $this->redirect('/importar', 'Falha ao salvar arquivo.', 'erro');
        }

        $logRepo = new ImportacaoLogRepository($this->db);
        $service = new ImportacaoService($this->db);
        $maxRows = (int) ($this->config['security']['max_import_rows'] ?? 5000);

        try {
            if ($modo === 'auto') {
                $eventosAuto = ['R2010', 'R2055', 'R4020'];
                if (!in_array($evento, $eventosAuto, true)) {
                    $this->redirect(
                        '/importar',
                        'Criação automática disponível por enquanto para R-2010, R-2055 e R-4020.',
                        'erro'
                    );
                }

                $contribId = (int) $this->post('contribuinte_id', 0) ?: null;

                $result = $service->processarPorPeriodo($destino, $evento, $uid, $contribId, $maxRows);

                if (empty($result['log_competencia_ids'])) {
                    $msg = 'Nenhum registro importado.';
                    if (!empty($result['erros'])) {
                        $msg .= ' ' . implode('; ', array_slice($result['erros'], 0, 3));
                    }
                    $this->redirect('/importar', $msg, 'erro');
                }

                foreach ($result['log_competencia_ids'] as $compId) {
                    $importacaoId = $logRepo->registrar((int) $compId, $uid, $arquivo['name'], $evento);
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
                $this->redirect('/importar', $msg, 'sucesso');
            }

            // Modo manual (comportamento original)
            $compId = (int) $this->post('competencia_id');
            if (!$compId) {
                $this->redirect('/importar', 'Selecione a competência.', 'erro');
            }

            $comp = (new CompetenciaRepository($this->db))->findWithContribuinte($compId, $uid);
            if (!$comp) {
                $this->redirect('/importar', 'Competência não encontrada.', 'erro');
            }

            $importacaoId = $logRepo->registrar($compId, $uid, $arquivo['name'], $evento);
            $result       = $service->processar($destino, $evento, $compId, $maxRows);
            $logRepo->marcarSucesso($importacaoId, $result['total'], $result['importados']);

            $msg = "{$result['importados']} de {$result['total']} registros importados!";
            if (!empty($result['erros'])) {
                $msg .= ' Erros: ' . implode('; ', array_slice($result['erros'], 0, 3));
            }
            $this->redirect('/importar', $msg, 'sucesso');
        } catch (\Throwable $e) {
            error_log('Import error: ' . $e->getMessage());
            $msg = $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Não foi possível importar a planilha.';
            $this->redirect('/importar', $msg, 'erro');
        }
    }
}
