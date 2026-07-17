<?php

namespace App\Http\Controllers;

use App\Repositories\CompetenciaRepository;
use App\Repositories\ContribuinteRepository;

class DashboardController extends Controller
{
    public function index()
    {
        $uid = $this->userId();

        $contribuintes = new ContribuinteRepository($this->db);
        $competencias  = new CompetenciaRepository($this->db);

        return $this->render('pages.dashboard', [
            'pageTitle'          => 'Dashboard',
            'totalContribuintes' => $contribuintes->countByUser($uid),
            'totalCompetencias'  => $competencias->countByUser($uid),
            'totalTransmitidos'  => $competencias->countTransmitidosByUser($uid),
            'competencias'       => $competencias->listRecentByUser($uid, 5),
        ]);
    }
}
