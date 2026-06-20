<?php

namespace App\Controllers;

class DashboardController extends BaseController
{
    public function index(): void
    {
        $this->requireLogin();
        $uid = $_SESSION['usuario']['id'];

        // Totais
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM contribuintes WHERE usuario_id = ?");
        $stmt->execute([$uid]);
        $totalContribuintes = $stmt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM competencias c
            JOIN contribuintes co ON co.id = c.contribuinte_id
            WHERE co.usuario_id = ?
        ");
        $stmt->execute([$uid]);
        $totalCompetencias = $stmt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM competencias c
            JOIN contribuintes co ON co.id = c.contribuinte_id
            WHERE co.usuario_id = ? AND c.status = 'transmitido'
        ");
        $stmt->execute([$uid]);
        $totalTransmitidos = $stmt->fetchColumn();

        // Últimas competências
        $stmt = $this->db->prepare("
            SELECT c.*, co.razao_social, co.cnpj
            FROM competencias c
            JOIN contribuintes co ON co.id = c.contribuinte_id
            WHERE co.usuario_id = ?
            ORDER BY c.periodo DESC
            LIMIT 5
        ");
        $stmt->execute([$uid]);
        $ultimasCompetencias = $stmt->fetchAll();

        // Eventos por competência (últimos 6 meses)
        $stmt = $this->db->prepare("
            SELECT c.periodo,
                (SELECT COUNT(*) FROM r2010 WHERE competencia_id = c.id) as r2010,
                (SELECT COUNT(*) FROM r2020 WHERE competencia_id = c.id) as r2020,
                (SELECT COUNT(*) FROM r2060 WHERE competencia_id = c.id) as r2060
            FROM competencias c
            JOIN contribuintes co ON co.id = c.contribuinte_id
            WHERE co.usuario_id = ?
            ORDER BY c.periodo DESC
            LIMIT 6
        ");
        $stmt->execute([$uid]);
        $graficoDados = $stmt->fetchAll();

        $this->view('pages/dashboard', [
            'pageTitle'          => 'Dashboard',
            'totalContribuintes' => $totalContribuintes,
            'totalCompetencias'  => $totalCompetencias,
            'totalTransmitidos'  => $totalTransmitidos,
            'competencias'       => $ultimasCompetencias,
            'graficoDados'       => $graficoDados,
            'flash'              => $this->getFlash(),
        ]);
    }
}
