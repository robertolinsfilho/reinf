<?php

namespace App\Controllers;

use App\Services\ImportacaoService;

class ImportacaoController extends BaseController
{
    public function index(): void
    {
        $this->requireLogin();
        $uid = $_SESSION['usuario']['id'];

        // Buscar contribuintes do usuário
        $stmt = $this->db->prepare("SELECT * FROM contribuintes WHERE usuario_id = ? ORDER BY razao_social");
        $stmt->execute([$uid]);
        $contribuintes = $stmt->fetchAll();

        // Buscar histórico de importações
        $stmt = $this->db->prepare("
            SELECT i.*, c.periodo, co.razao_social
            FROM importacoes i
            JOIN competencias c ON c.id = i.competencia_id
            JOIN contribuintes co ON co.id = c.contribuinte_id
            WHERE i.usuario_id = ?
            ORDER BY i.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$uid]);
        $historico = $stmt->fetchAll();

        $this->view('pages/importacao/index', [
            'pageTitle'    => 'Importar Planilha Excel',
            'contribuintes'=> $contribuintes,
            'historico'    => $historico,
            'flash'        => $this->getFlash(),
        ]);
    }

    public function processar(): void
    {
        $this->requireLogin();
        $uid = $_SESSION['usuario']['id'];

        if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('erro', 'Erro ao fazer upload do arquivo.');
            $this->redirect('/importar');
        }

        $evento        = $this->post('evento', '');
        $competenciaId = (int) $this->post('competencia_id', 0);

        if (!$competenciaId || !$evento) {
            $this->flash('erro', 'Selecione a competência e o evento.');
            $this->redirect('/importar');
        }

        // Verificar se competência pertence ao usuário
        $stmt = $this->db->prepare("
            SELECT c.id FROM competencias c
            JOIN contribuintes co ON co.id = c.contribuinte_id
            WHERE c.id = ? AND co.usuario_id = ?
        ");
        $stmt->execute([$competenciaId, $uid]);
        if (!$stmt->fetch()) {
            $this->flash('erro', 'Competência não encontrada.');
            $this->redirect('/importar');
        }

        $arquivo   = $_FILES['arquivo'];
        $ext       = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        $uploadDir = $this->config['upload']['path'];
        $nomeArq   = uniqid('reinf_') . '.' . $ext;
        $destino   = $uploadDir . $nomeArq;

        if (!in_array($ext, ['xlsx', 'xls'])) {
            $this->flash('erro', 'Arquivo deve ser .xlsx ou .xls');
            $this->redirect('/importar');
        }

        if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
            $this->flash('erro', 'Falha ao salvar arquivo. Verifique permissões.');
            $this->redirect('/importar');
        }

        // Registrar importação
        $stmt = $this->db->prepare("
            INSERT INTO importacoes (competencia_id, usuario_id, arquivo_nome, evento, status)
            VALUES (?, ?, ?, ?, 'processando')
        ");
        $stmt->execute([$competenciaId, $uid, $arquivo['name'], $evento]);
        $importacaoId = $this->db->lastInsertId();

        try {
            $service = new ImportacaoService($this->db);
            $result  = $service->processar($destino, $evento, $competenciaId);

            $stmt = $this->db->prepare("
                UPDATE importacoes SET status='sucesso', total_registros=?, registros_importados=?
                WHERE id=?
            ");
            $stmt->execute([$result['total'], $result['importados'], $importacaoId]);

            $this->flash('sucesso', "Importação concluída! {$result['importados']} de {$result['total']} registros importados.");
        } catch (\Exception $e) {
            $stmt = $this->db->prepare("UPDATE importacoes SET status='erro', log_erros=? WHERE id=?");
            $stmt->execute([$e->getMessage(), $importacaoId]);
            $this->flash('erro', 'Erro na importação: ' . $e->getMessage());
        }

        $this->redirect('/importar');
    }
}
