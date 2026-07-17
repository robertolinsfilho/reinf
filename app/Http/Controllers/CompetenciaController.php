<?php

namespace App\Http\Controllers;

use App\Repositories\CompetenciaRepository;
use App\Repositories\ContribuinteRepository;
use App\Repositories\EventoRepository;
use Illuminate\Http\Request;

class CompetenciaController extends Controller
{
    private CompetenciaRepository $repo;
    private ContribuinteRepository $contribuintes;

    public function __construct()
    {
        parent::__construct();
        $this->repo          = new CompetenciaRepository($this->db);
        $this->contribuintes = new ContribuinteRepository($this->db);
    }

    public function index(Request $request)
    {
        $uid = $this->userId();
        $cid = (int) $request->query('contribuinte_id', 0);

        return $this->render('pages.competencias.index', [
            'pageTitle'      => 'Competências',
            'competencias'   => $this->repo->listByUser($uid, $cid ?: null),
            'contribuintes'  => $this->contribuintes->listByUser($uid),
            'contribuinteId' => $cid,
        ]);
    }

    public function nova()
    {
        return $this->render('pages.competencias.form', [
            'pageTitle'     => 'Nova Competência',
            'contribuintes' => $this->contribuintes->listByUser($this->userId()),
        ]);
    }

    public function detalhe(Request $request)
    {
        $id   = (int) $request->query('id');
        $comp = $this->repo->findWithContribuinte($id, $this->userId());

        if (!$comp) {
            return $this->flashRedirect('/competencias', 'Competência não encontrada.', 'erro');
        }

        // Paginação: cada tab tem seu próprio "?page_r2010=", "?page_r4020=" etc.
        $limit      = 20;
        $eventoRepo = new EventoRepository($this->db);
        $eventos    = [];

        foreach (['r2010', 'r2020', 'r2055', 'r2060', 'r4010', 'r4020'] as $tab) {
            $page   = max(1, (int) $request->query("page_{$tab}", 1));
            $offset = ($page - 1) * $limit;
            $total  = $eventoRepo->contar($tab, $id);
            $order  = in_array($tab, ['r4010', 'r4020']) ? 'data_pagamento DESC' : 'created_at DESC';

            $eventos[$tab]           = $eventoRepo->listar($tab, $id, $order, $limit, $offset);
            $eventos["{$tab}_total"] = $total;
            $eventos["{$tab}_page"]  = $page;
            $eventos["{$tab}_pages"] = (int) ceil($total / $limit);
        }

        return $this->render('pages.competencias.detalhe', [
            'pageTitle'   => 'Detalhe da Competência',
            'competencia' => $comp,
            ...$eventos,
        ]);
    }

    public function salvar(Request $request)
    {
        $uid            = $this->userId();
        $contribuinteId = (int) $request->input('contribuinte_id');
        $periodo        = $request->input('periodo', '');

        if (!$contribuinteId || !$periodo) {
            return $this->flashRedirect('/competencias/nova', 'Selecione contribuinte e período.', 'erro');
        }

        if (!$this->contribuintes->findByUser($contribuinteId, $uid)) {
            return $this->flashRedirect('/competencias/nova', 'Contribuinte inválido.', 'erro');
        }

        if ($this->repo->exists($contribuinteId, $periodo)) {
            return $this->flashRedirect('/competencias/nova', 'Já existe competência para este período.', 'erro');
        }

        return $this->safeExecute(function () use ($contribuinteId, $periodo) {
            $this->repo->insert(['contribuinte_id' => $contribuinteId, 'periodo' => $periodo]);
            return $this->flashRedirect('/competencias', 'Competência criada!', 'sucesso');
        }, '/competencias/nova');
    }
}
