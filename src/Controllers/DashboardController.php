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

        $totalContribuintes  = $contribuintes->countByUser($uid);
        $totalCompetencias   = $competencias->countByUser($uid);
        $totalTransmitidos   = $competencias->countTransmitidosByUser($uid);
        $ultimasCompetencias = $competencias->listRecentByUser($uid, 5);

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