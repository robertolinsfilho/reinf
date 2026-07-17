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

        $classTrib = preg_replace('/\D/', '', (string) $request->input('classificacao_tributos', '99')) ?? '99';
        $classTrib = str_pad(substr($classTrib, 0, 2), 2, '0', STR_PAD_LEFT);

        $dados = [
            'cnpj'                   => $this->postCnpj($request, 'cnpj'),
            'razao_social'           => $this->sanitize($request->input('razao_social', '')),
            'nome_fantasia'          => $this->sanitize($request->input('nome_fantasia', '')),
            'tipo_contribuinte'      => (string) $request->input('tipo_contribuinte', '1'),
            'classificacao_tributos' => $classTrib,
            'nome_contato'           => mb_substr($this->sanitize($request->input('nome_contato', '')), 0, 70),
            'cpf_contato'            => $this->postCpf($request, 'cpf_contato'),
            'email'                  => mb_substr(trim((string) $request->input('email', '')), 0, 60) ?: null,
            'telefone'               => preg_replace('/\D/', '', (string) $request->input('telefone', '')) ?: null,
            'ind_escrituracao'       => (int) $request->input('ind_escrituracao', 0),
            'ind_desoneracao'        => (int) $request->input('ind_desoneracao', 0),
            'ind_acordo_isen_multa'  => (int) $request->input('ind_acordo_isen_multa', 0),
            'ind_sit_pj'             => (int) $request->input('ind_sit_pj', 0),
        ];

        $redirect = $id ? "/contribuintes/editar?id={$id}" : '/contribuintes/novo';
        $classTribMap = config('reinf.class_trib', []);

        if ($dados['cnpj'] === '' || $dados['razao_social'] === '') {
            return $this->flashRedirect($redirect, 'CNPJ e Razão Social são obrigatórios.', 'erro');
        }

        if (!in_array($dados['tipo_contribuinte'], ['1', '2'], true)) {
            return $this->flashRedirect($redirect, 'Tipo de inscrição inválido.', 'erro');
        }

        if (!ValidacaoService::cnpjOuCpf($dados['cnpj'])) {
            return $this->flashRedirect($redirect, 'CNPJ/CPF inválido.', 'erro');
        }

        // array_key_exists: PHP cast keys "01"/"99" to int — in_array strict falha
        if (!is_array($classTribMap) || !array_key_exists($classTrib, $classTribMap)) {
            return $this->flashRedirect($redirect, 'Classificação tributária inválida (Tabela 08).', 'erro');
        }

        if (in_array($classTrib, ['21', '22'], true) && $dados['tipo_contribuinte'] !== '2') {
            return $this->flashRedirect($redirect, 'Códigos 21/22 exigem inscrição CPF (tpInsc=2).', 'erro');
        }
        if (!in_array($classTrib, ['21', '22'], true) && $dados['tipo_contribuinte'] !== '1') {
            return $this->flashRedirect($redirect, 'Esta classificação tributária exige inscrição CNPJ (tpInsc=1).', 'erro');
        }

        if (!in_array($dados['ind_escrituracao'], [0, 1], true)
            || !in_array($dados['ind_desoneracao'], [0, 1], true)
            || !in_array($dados['ind_acordo_isen_multa'], [0, 1], true)
            || !in_array($dados['ind_sit_pj'], [0, 1, 2, 3, 4], true)
        ) {
            return $this->flashRedirect($redirect, 'Indicadores do R-1000 inválidos.', 'erro');
        }

        if ($dados['ind_desoneracao'] === 1 && !in_array($classTrib, ['02', '03', '99'], true)) {
            return $this->flashRedirect($redirect, 'Desoneração da folha só é permitida com classTrib 02, 03 ou 99.', 'erro');
        }

        if ($dados['ind_acordo_isen_multa'] === 1 && $classTrib !== '60') {
            return $this->flashRedirect($redirect, 'Acordo de isenção de multa só é permitido com classTrib 60.', 'erro');
        }

        if ($dados['nome_contato'] === '' || $dados['cpf_contato'] === '') {
            return $this->flashRedirect($redirect, 'Nome e CPF do contato são obrigatórios para o R-1000.', 'erro');
        }

        if (!ValidacaoService::validarCpf($dados['cpf_contato'])) {
            return $this->flashRedirect($redirect, 'CPF do contato inválido.', 'erro');
        }

        if ($dados['telefone'] === null || strlen($dados['telefone']) < 10 || strlen($dados['telefone']) > 13) {
            return $this->flashRedirect($redirect, 'Telefone do contato deve ter DDD e no mínimo 10 dígitos.', 'erro');
        }

        if ($dados['email'] !== null && $dados['email'] !== '' && !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->flashRedirect($redirect, 'E-mail do contato inválido.', 'erro');
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
