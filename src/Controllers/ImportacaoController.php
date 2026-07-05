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

        $competencias = (new CompetenciaRepository($this->db))->listByUser($uid);

        $this->view('pages/importacao/index', [
            'pageTitle'     => 'Importar Planilha Excel',
            'competencias'  => $competencias,
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
        $compId = (int) $this->post('competencia_id');

        if (!$compId || !$evento) {
            $this->redirect('/importar', 'Selecione competência e evento.', 'erro');
        }

        $comp = (new CompetenciaRepository($this->db))->findWithContribuinte($compId, $uid);
        if (!$comp) {
            $this->redirect('/importar', 'Competência não encontrada.', 'erro');
        }

        $arquivo = $_FILES['arquivo'];
        $ext     = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['xlsx', 'xlsm', 'xls'])) {
            $this->redirect('/importar', 'Arquivo deve ser .xlsx, .xlsm ou .xls.', 'erro');
        }

        $uploadDir = $this->config['upload']['path'] ?? BASE_PATH . '/public/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $destino = $uploadDir . uniqid('reinf_') . '.' . $ext;

        if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
            $this->redirect('/importar', 'Falha ao salvar arquivo.', 'erro');
        }

        $logRepo      = new ImportacaoLogRepository($this->db);
        $importacaoId = $logRepo->registrar($compId, $uid, $arquivo['name'], $evento);

        try {
            $result = (new ImportacaoService($this->db))->processar($destino, $evento, $compId);
            $logRepo->marcarSucesso($importacaoId, $result['total'], $result['importados']);

            $msg = "{$result['importados']} de {$result['total']} registros importados!";
            if (!empty($result['erros'])) {
                $msg .= ' Erros: ' . implode('; ', array_slice($result['erros'], 0, 3));
            }
            $this->redirect('/importar', $msg, 'sucesso');
        } catch (\Exception $e) {
            $logRepo->marcarErro($importacaoId, $e->getMessage());
            $this->redirect('/importar', 'Erro: ' . $e->getMessage(), 'erro');
        }
    }
}