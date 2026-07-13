<?php

namespace App\Controllers;

use App\Models\ContribuinteRepository;
use App\Services\ValidacaoService;

class ContribuinteController extends BaseController
{
    private ContribuinteRepository $repo;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->repo = new ContribuinteRepository($this->db);
    }

    public function index(): void
    {
        $this->requireLogin();
        $this->view('pages/contribuintes/index', [
            'pageTitle'     => 'Contribuintes',
            'contribuintes' => $this->repo->listByUser($this->userId()),
            'flash'         => $this->getFlash(),
        ]);
    }

    public function novo(): void
    {
        $this->requireLogin();
        $this->view('pages/contribuintes/form', [
            'pageTitle'    => 'Novo Contribuinte',
            'contribuinte' => null,
            'flash'        => $this->getFlash(),
        ]);
    }

    public function editar(): void
    {
        $this->requireLogin();
        $contribuinte = $this->repo->findByUser((int) $this->get('id'), $this->userId());
        if (!$contribuinte) {
            $this->redirect('/contribuintes', 'Contribuinte não encontrado.', 'erro');
        }
        $this->view('pages/contribuintes/form', [
            'pageTitle'    => 'Editar Contribuinte',
            'contribuinte' => $contribuinte,
            'flash'        => $this->getFlash(),
        ]);
    }

    public function salvar(): void
    {
        $this->requireLogin();
        $uid = $this->userId();
        $id  = (int) $this->post('id', 0);

        $dados = [
            'cnpj'                   => $this->postCnpj('cnpj'),
            'razao_social'           => $this->sanitize($this->post('razao_social', '')),
            'nome_fantasia'          => $this->sanitize($this->post('nome_fantasia', '')),
            'tipo_contribuinte'      => $this->post('tipo_contribuinte', '1'),
            'classificacao_tributos' => $this->sanitize($this->post('classificacao_tributos', '')),
        ];

        $redirect = $id ? "/contribuintes/editar?id={$id}" : '/contribuintes/novo';

        if (empty($dados['cnpj']) || empty($dados['razao_social'])) {
            $this->redirect($redirect, 'CNPJ e Razão Social são obrigatórios.', 'erro');
        }

        if (in_array($dados['tipo_contribuinte'], ['1', '2'])) {
            if (!ValidacaoService::cnpjOuCpf($dados['cnpj'])) {
                $this->redirect($redirect, 'CNPJ/CPF inválido.', 'erro');
            }
        }

        $this->safeExecute(function () use ($id, $uid, $dados) {
            if ($id) {
                $this->repo->atualizar($id, $uid, $dados);
                $this->redirect('/contribuintes', 'Contribuinte atualizado.', 'sucesso');
            } else {
                $this->repo->criar($uid, $dados);
                $this->redirect('/contribuintes', 'Contribuinte cadastrado.', 'sucesso');
            }
        }, $redirect);
    }

    public function excluir(): void
    {
        $this->requireLogin();
        $this->safeExecute(function () {
            $this->repo->excluir((int) $this->post('id'), $this->userId());
            $this->redirect('/contribuintes', 'Contribuinte excluído.', 'sucesso');
        }, '/contribuintes');
    }
}