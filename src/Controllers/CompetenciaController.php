<?php

namespace App\Controllers;

use App\Models\CompetenciaRepository;
use App\Models\ContribuinteRepository;
use App\Models\EventoRepository;

class CompetenciaController extends BaseController
{
    private CompetenciaRepository $repo;
    private ContribuinteRepository $contribuintes;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->repo          = new CompetenciaRepository($this->db);
        $this->contribuintes = new ContribuinteRepository($this->db);
    }

    public function index(): void
    {
        $this->requireLogin();
        $uid = $this->userId();
        $cid = (int) $this->get('contribuinte_id', 0);

        $this->view('pages/competencias/index', [
            'pageTitle'      => 'Competências',
            'competencias'   => $this->repo->listByUser($uid, $cid ?: null),
            'contribuintes'  => $this->contribuintes->listByUser($uid),
            'contribuinteId' => $cid,
            'flash'          => $this->getFlash(),
        ]);
    }

    public function nova(): void
    {
        $this->requireLogin();
        $this->view('pages/competencias/form', [
            'pageTitle'     => 'Nova Competência',
            'contribuintes' => $this->contribuintes->listByUser($this->userId()),
            'flash'         => $this->getFlash(),
        ]);
    }

    public function detalhe(): void
    {
        $this->requireLogin();
        $id   = (int) $this->get('id');
        $comp = $this->repo->findWithContribuinte($id, $this->userId());

        if (!$comp) {
            $this->redirect('/competencias', 'Competência não encontrada.', 'erro');
        }

        $eventos = (new EventoRepository($this->db))->carregarTodos($id);

        $this->view('pages/competencias/detalhe', [
            'pageTitle'   => 'Detalhe da Competência',
            'competencia' => $comp,
            ...$eventos,
            'flash'       => $this->getFlash(),
        ]);
    }

    public function salvar(): void
    {
        $this->requireLogin();
        $uid            = $this->userId();
        $contribuinteId = (int) $this->post('contribuinte_id');
        $periodo        = $this->post('periodo', '');

        if (!$contribuinteId || !$periodo) {
            $this->redirect('/competencias/nova', 'Selecione contribuinte e período.', 'erro');
        }

        if (!$this->contribuintes->findByUser($contribuinteId, $uid)) {
            $this->redirect('/competencias/nova', 'Contribuinte inválido.', 'erro');
        }

        if ($this->repo->exists($contribuinteId, $periodo)) {
            $this->redirect('/competencias/nova', 'Já existe competência para este período.', 'erro');
        }

        $this->safeExecute(function () use ($contribuinteId, $periodo) {
            $this->repo->insert(['contribuinte_id' => $contribuinteId, 'periodo' => $periodo]);
            $this->redirect('/competencias', 'Competência criada!', 'sucesso');
        }, '/competencias/nova');
    }
}