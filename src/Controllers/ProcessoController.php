<?php

namespace App\Controllers;

use App\Models\ProcessoRepository;
use App\Models\ContribuinteRepository;

class ProcessoController extends BaseController
{
    private ProcessoRepository $repo;
    private ContribuinteRepository $contribuintes;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->repo          = new ProcessoRepository($this->db);
        $this->contribuintes = new ContribuinteRepository($this->db);
    }

    public function index(): void
    {
        $this->requireLogin();
        $this->view('pages/processos/index', [
            'pageTitle' => 'R-1070 — Processos Administrativos/Judiciais',
            'processos' => $this->repo->listByUser($this->userId()),
            'flash'     => $this->getFlash(),
        ]);
    }

    public function novo(): void
    {
        $this->requireLogin();
        $this->view('pages/processos/form', [
            'pageTitle'     => 'Novo Processo',
            'processo'      => null,
            'contribuintes' => $this->contribuintes->listByUser($this->userId()),
            'flash'         => $this->getFlash(),
        ]);
    }

    public function editar(): void
    {
        $this->requireLogin();
        $proc = $this->repo->findByUser((int) $this->get('id'), $this->userId());
        if (!$proc) {
            $this->redirect('/processos', 'Processo não encontrado.', 'erro');
        }
        $this->view('pages/processos/form', [
            'pageTitle'     => 'Editar Processo',
            'processo'      => $proc,
            'contribuintes' => $this->contribuintes->listByUser($this->userId()),
            'flash'         => $this->getFlash(),
        ]);
    }

    public function salvar(): void
    {
        $this->requireLogin();
        $id  = (int) $this->post('id', 0);
        $uid = $this->userId();

        $contribId = (int) $this->post('contribuinte_id');
        if (!$this->contribuintes->findByUser($contribId, $uid)) {
            $this->redirect('/processos/novo', 'Contribuinte inválido.', 'erro');
        }

        $numProc = trim($this->post('numero_processo', ''));
        if (!$numProc) {
            $this->redirect('/processos/novo', 'Número do processo é obrigatório.', 'erro');
        }

        $dados = [
            'contribuinte_id'    => $contribId,
            'tipo_processo'      => (int) $this->post('tipo_processo', 1),
            'numero_processo'    => $numProc,
            'indicador_autoria'  => (int) $this->post('indicador_autoria', 1),
            'uf_vara'            => $this->post('uf_vara') ?: null,
            'cod_municipio'      => $this->post('cod_municipio') ?: null,
            'id_vara'            => $this->post('id_vara') ?: null,
            'indicador_susp_exig'=> (int) $this->post('indicador_susp_exig', 0),
            'data_decisao'       => $this->post('data_decisao') ?: null,
            'indicador_deposito' => (int) $this->post('indicador_deposito', 0),
            'data_inclusao'      => $this->post('data_inclusao', date('Y-m-d')),
            'descricao'          => $this->sanitize($this->post('descricao', '')),
            'status'             => $this->post('status', 'ativo'),
        ];

        $this->safeExecute(function () use ($id, $uid, $dados) {
            if ($id) {
                $proc = $this->repo->findByUser($id, $uid);
                if (!$proc) {
                    $this->redirect('/processos', 'Processo não encontrado.', 'erro');
                }
                $this->repo->update($id, $dados);
                $this->redirect('/processos', 'Processo atualizado!', 'sucesso');
            } else {
                $this->repo->insert($dados);
                $this->redirect('/processos', 'Processo cadastrado!', 'sucesso');
            }
        }, $id ? "/processos/editar?id={$id}" : '/processos/novo');
    }

    public function excluir(): void
    {
        $this->requireLogin();
        $id   = (int) $this->post('id');
        $proc = $this->repo->findByUser($id, $this->userId());
        if (!$proc) {
            $this->redirect('/processos', 'Processo não encontrado.', 'erro');
        }
        $this->repo->delete($id);
        $this->redirect('/processos', 'Processo excluído.', 'sucesso');
    }
}