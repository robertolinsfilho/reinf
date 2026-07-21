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
        $this->repo = new ContribuinteRepository();
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

        $erro = function (string $msg) use ($redirect) {
            return $this->flashRedirect($redirect, $msg, 'erro', withInput: true);
        };

        if ($dados['cnpj'] === '' || $dados['razao_social'] === '') {
            return $erro('CNPJ e Razão Social são obrigatórios.');
        }

        if (!in_array($dados['tipo_contribuinte'], ['1', '2'], true)) {
            return $erro('Tipo de inscrição inválido.');
        }

        if (!ValidacaoService::cnpjOuCpf($dados['cnpj'])) {
            return $erro('CNPJ/CPF inválido.');
        }

        if (!is_array($classTribMap) || !array_key_exists($classTrib, $classTribMap)) {
            return $erro('Classificação tributária inválida (Tabela 08).');
        }

        if (in_array($classTrib, ['21', '22'], true) && $dados['tipo_contribuinte'] !== '2') {
            return $erro('Códigos 21/22 exigem inscrição CPF (tpInsc=2).');
        }
        if (!in_array($classTrib, ['21', '22'], true) && $dados['tipo_contribuinte'] !== '1') {
            return $erro('Esta classificação tributária exige inscrição CNPJ (tpInsc=1).');
        }

        if (!in_array($dados['ind_escrituracao'], [0, 1], true)
            || !in_array($dados['ind_desoneracao'], [0, 1], true)
            || !in_array($dados['ind_acordo_isen_multa'], [0, 1], true)
            || !in_array($dados['ind_sit_pj'], [0, 1, 2, 3, 4], true)
        ) {
            return $erro('Indicadores do R-1000 inválidos.');
        }

        if ($dados['ind_desoneracao'] === 1 && !in_array($classTrib, ['02', '03', '99'], true)) {
            return $erro('Desoneração da folha só é permitida com classTrib 02, 03 ou 99.');
        }

        if ($dados['ind_acordo_isen_multa'] === 1 && $classTrib !== '60') {
            return $erro('Acordo de isenção de multa só é permitido com classTrib 60.');
        }

        if ($dados['nome_contato'] === '' || $dados['cpf_contato'] === '') {
            return $erro('Nome e CPF do contato são obrigatórios para o R-1000.');
        }

        if (!ValidacaoService::validarCpf($dados['cpf_contato'])) {
            return $erro('CPF do contato inválido.');
        }

        if ($dados['telefone'] === null || strlen($dados['telefone']) < 10 || strlen($dados['telefone']) > 13) {
            return $erro('Telefone do contato deve ter DDD e no mínimo 10 dígitos.');
        }

        if ($dados['email'] !== null && $dados['email'] !== '' && !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            return $erro('E-mail do contato inválido.');
        }

        return $this->safeExecute(function () use ($id, $uid, $dados) {
            if ($id) {
                $this->repo->atualizar($id, $uid, $dados);
                return $this->flashRedirect('/contribuintes', 'Contribuinte atualizado.', 'sucesso');
            }
            $this->repo->criar($uid, $dados);
            return $this->flashRedirect('/contribuintes', 'Contribuinte cadastrado.', 'sucesso');
        }, $redirect, 'Erro', withInput: true);
    }

    public function excluir(Request $request)
    {
        return $this->safeExecute(function () use ($request) {
            $this->repo->excluir((int) $request->input('id'), $this->userId());
            return $this->flashRedirect('/contribuintes', 'Contribuinte excluído.', 'sucesso');
        }, '/contribuintes');
    }
}
