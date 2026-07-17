<?php

namespace App\Http\Controllers;

use App\Repositories\ContribuinteRepository;
use App\Services\ValidacaoService;
use Illuminate\Http\Request;

class ContribuinteController extends Controller
{
    private ContribuinteRepository $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new ContribuinteRepository($this->db);
    }

    public function index()
    {
        return $this->render('pages.contribuintes.index', [
            'pageTitle'     => 'Contribuintes',
            'contribuintes' => $this->repo->listByUser($this->userId()),
        ]);
    }

    public function novo()
    {
        return $this->render('pages.contribuintes.form', [
            'pageTitle'    => 'Novo Contribuinte',
            'contribuinte' => null,
        ]);
    }

    public function editar(Request $request)
    {
        $contribuinte = $this->repo->findByUser((int) $request->query('id'), $this->userId());
        if (!$contribuinte) {
            return $this->flashRedirect('/contribuintes', 'Contribuinte não encontrado.', 'erro');
        }
        return $this->render('pages.contribuintes.form', [
            'pageTitle'    => 'Editar Contribuinte',
            'contribuinte' => $contribuinte,
        ]);
    }

    public function salvar(Request $request)
    {
        $uid = $this->userId();
        $id  = (int) $request->input('id', 0);

        $dados = [
            'cnpj'                   => $this->postCnpj($request, 'cnpj'),
            'razao_social'           => $this->sanitize($request->input('razao_social', '')),
            'nome_fantasia'          => $this->sanitize($request->input('nome_fantasia', '')),
            'tipo_contribuinte'      => $request->input('tipo_contribuinte', '1'),
            'classificacao_tributos' => $this->sanitize($request->input('classificacao_tributos', '')),
            'nome_contato'           => mb_substr($this->sanitize($request->input('nome_contato', '')), 0, 70),
            'cpf_contato'            => $this->postCpf($request, 'cpf_contato'),
            'email'                  => mb_substr($this->sanitize($request->input('email', '')), 0, 60) ?: null,
            'telefone'               => preg_replace('/\D/', '', (string) $request->input('telefone', '')) ?: null,
        ];

        $redirect = $id ? "/contribuintes/editar?id={$id}" : '/contribuintes/novo';

        if (empty($dados['cnpj']) || empty($dados['razao_social'])) {
            return $this->flashRedirect($redirect, 'CNPJ e Razão Social são obrigatórios.', 'erro');
        }

        if ($dados['nome_contato'] === '' || $dados['cpf_contato'] === '') {
            return $this->flashRedirect($redirect, 'Nome e CPF do contato são obrigatórios para o R-1000.', 'erro');
        }

        if (!ValidacaoService::validarCpf($dados['cpf_contato'])) {
            return $this->flashRedirect($redirect, 'CPF do contato inválido.', 'erro');
        }

        if ($dados['email'] !== null && $dados['email'] !== '' && !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->flashRedirect($redirect, 'E-mail do contato inválido.', 'erro');
        }

        if (in_array($dados['tipo_contribuinte'], ['1', '2'])) {
            if (!ValidacaoService::cnpjOuCpf($dados['cnpj'])) {
                return $this->flashRedirect($redirect, 'CNPJ/CPF inválido.', 'erro');
            }
        }

        return $this->safeExecute(function () use ($id, $uid, $dados) {
            if ($id) {
                $this->repo->atualizar($id, $uid, $dados);
                return $this->flashRedirect('/contribuintes', 'Contribuinte atualizado.', 'sucesso');
            }
            $this->repo->criar($uid, $dados);
            return $this->flashRedirect('/contribuintes', 'Contribuinte cadastrado.', 'sucesso');
        }, $redirect);
    }

    public function excluir(Request $request)
    {
        return $this->safeExecute(function () use ($request) {
            $this->repo->excluir((int) $request->input('id'), $this->userId());
            return $this->flashRedirect('/contribuintes', 'Contribuinte excluído.', 'sucesso');
        }, '/contribuintes');
    }
}
