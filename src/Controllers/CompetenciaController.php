<?php

namespace App\Controllers;

class CompetenciaController extends BaseController
{
    public function index(): void
    {
        $this->requireLogin();
        $uid = $_SESSION['usuario']['id'];

        $contribuinteId = (int) $this->get('contribuinte_id', 0);

        $sql = "
            SELECT c.*, co.razao_social, co.cnpj,
                (SELECT COUNT(*) FROM r2010 WHERE competencia_id = c.id) as total_r2010,
                (SELECT COUNT(*) FROM r2020 WHERE competencia_id = c.id) as total_r2020,
                (SELECT COUNT(*) FROM r2060 WHERE competencia_id = c.id) as total_r2060,
                (SELECT COUNT(*) FROM r4010 WHERE competencia_id = c.id) as total_r4010,
                (SELECT COUNT(*) FROM r4020 WHERE competencia_id = c.id) as total_r4020
            FROM competencias c
            JOIN contribuintes co ON co.id = c.contribuinte_id
            WHERE co.usuario_id = ?
        ";
        $params = [$uid];

        if ($contribuinteId) {
            $sql .= " AND co.id = ?";
            $params[] = $contribuinteId;
        }
        $sql .= " ORDER BY c.periodo DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $competencias = $stmt->fetchAll();

        $stmt = $this->db->prepare("SELECT * FROM contribuintes WHERE usuario_id = ? ORDER BY razao_social");
        $stmt->execute([$uid]);
        $contribuintes = $stmt->fetchAll();

        $this->view('pages/competencias/index', [
            'pageTitle'       => 'Competências',
            'competencias'    => $competencias,
            'contribuintes'   => $contribuintes,
            'contribuinteId'  => $contribuinteId,
            'flash'           => $this->getFlash(),
        ]);
    }

    public function nova(): void
    {
        $this->requireLogin();
        $uid = $_SESSION['usuario']['id'];

        $stmt = $this->db->prepare("SELECT * FROM contribuintes WHERE usuario_id = ? ORDER BY razao_social");
        $stmt->execute([$uid]);
        $contribuintes = $stmt->fetchAll();

        $this->view('pages/competencias/form', [
            'pageTitle'    => 'Nova Competência',
            'contribuintes'=> $contribuintes,
            'flash'        => $this->getFlash(),
        ]);
    }

    public function detalhe(): void
    {
        $this->requireLogin();
        $uid = $_SESSION['usuario']['id'];
        $id  = (int) $this->get('id');

        $stmt = $this->db->prepare("
            SELECT c.*, co.razao_social, co.cnpj
            FROM competencias c
            JOIN contribuintes co ON co.id = c.contribuinte_id
            WHERE c.id = ? AND co.usuario_id = ?
        ");
        $stmt->execute([$id, $uid]);
        $competencia = $stmt->fetch();

        if (!$competencia) {
            $this->flash('erro', 'Competência não encontrada.');
            $this->redirect('/competencias');
        }

        $stmt = $this->db->prepare("SELECT * FROM r2010 WHERE competencia_id = ?");
        $stmt->execute([$id]);
        $r2010 = $stmt->fetchAll();

        $stmt = $this->db->prepare("SELECT * FROM r2020 WHERE competencia_id = ?");
        $stmt->execute([$id]);
        $r2020 = $stmt->fetchAll();

        $stmt = $this->db->prepare("SELECT * FROM r2060 WHERE competencia_id = ?");
        $stmt->execute([$id]);
        $r2060 = $stmt->fetchAll();

        $stmt = $this->db->prepare("SELECT * FROM r4010 WHERE competencia_id = ?");
        $stmt->execute([$id]);
        $r4010 = $stmt->fetchAll();

        $stmt = $this->db->prepare("SELECT * FROM r4020 WHERE competencia_id = ?");
        $stmt->execute([$id]);
        $r4020 = $stmt->fetchAll();

        $this->view('pages/competencias/detalhe', [
            'pageTitle'   => 'Detalhe da Competência',
            'competencia' => $competencia,
            'r2010'       => $r2010,
            'r2020'       => $r2020,
            'r2060'       => $r2060,
            'r4010'       => $r4010,
            'r4020'       => $r4020,
            'flash'       => $this->getFlash(),
        ]);
    }
    
    public function salvar(): void
    {
        $this->requireLogin();
        $uid = $_SESSION['usuario']['id'];

        $contribuinteId = (int) $this->post('contribuinte_id', 0);
        $periodo        = $this->post('periodo', '');

        if (!$contribuinteId || !$periodo) {
            $this->flash('erro', 'Selecione o contribuinte e o período.');
            $this->redirect('/competencias/nova');
        }

        // Verificar posse
        $stmt = $this->db->prepare("SELECT id FROM contribuintes WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$contribuinteId, $uid]);
        if (!$stmt->fetch()) {
            $this->flash('erro', 'Contribuinte inválido.');
            $this->redirect('/competencias/nova');
        }

        // Verificar duplicata
        $stmt = $this->db->prepare("SELECT id FROM competencias WHERE contribuinte_id = ? AND periodo = ?");
        $stmt->execute([$contribuinteId, $periodo]);
        if ($stmt->fetch()) {
            $this->flash('erro', 'Já existe uma competência para este período.');
            $this->redirect('/competencias/nova');
        }

        $stmt = $this->db->prepare("INSERT INTO competencias (contribuinte_id, periodo) VALUES (?, ?)");
        $stmt->execute([$contribuinteId, $periodo]);

        $this->flash('sucesso', 'Competência criada com sucesso!');
        $this->redirect('/competencias');
    }
}
