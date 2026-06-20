<?php

namespace App\Controllers;

use App\Models\ContribuinteRepository;
use App\Models\CompetenciaRepository;

class DashboardController extends BaseController
{
    public function index(): void
    {
        $this->requireLogin();
        $uid = $this->userId();

        $contribuintes  = new ContribuinteRepository($this->db);
        $competencias   = new CompetenciaRepository($this->db);

        $totalContribuintes = $contribuintes->countByUser($uid);
        $totalCompetencias  = (int) $competencias->scalar(
            "SELECT COUNT(*) FROM competencias c JOIN contribuintes co ON co.id = c.contribuinte_id WHERE co.usuario_id = ?",
            [$uid]
        );
        $totalTransmitidos  = (int) $competencias->scalar(
            "SELECT COUNT(*) FROM competencias c JOIN contribuintes co ON co.id = c.contribuinte_id WHERE co.usuario_id = ? AND c.status = 'transmitido'",
            [$uid]
        );

        $ultimasCompetencias = $competencias->query(
            "SELECT c.*, co.razao_social, co.cnpj FROM competencias c JOIN contribuintes co ON co.id = c.contribuinte_id WHERE co.usuario_id = ? ORDER BY c.periodo DESC LIMIT 5",
            [$uid]
        );

        $this->view('pages/dashboard', [
            'pageTitle'          => 'Dashboard',
            'totalContribuintes' => $totalContribuintes,
            'totalCompetencias'  => $totalCompetencias,
            'totalTransmitidos'  => $totalTransmitidos,
            'competencias'       => $ultimasCompetencias,
            'flash'              => $this->getFlash(),
        ]);
    }
}