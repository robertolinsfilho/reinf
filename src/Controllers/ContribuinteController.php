<?php

namespace App\Controllers;

use App\Services\ValidacaoService;

class ContribuinteController extends BaseController
{
    public function index(): void
    {
        $this->requireLogin();
        $uid = $_SESSION['usuario']['id'];

        $stmt = $this->db->prepare("SELECT * FROM contribuintes WHERE usuario_id = ? ORDER BY razao_social");
        $stmt->execute([$uid]);
        $contribuintes = $stmt->fetchAll();

        $this->view('pages/contribuintes/index', [
            'pageTitle'     => 'Contribuintes',
            'contribuintes' => $contribuintes,
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
        $id  = (int) $this->get('id');
        $uid = $_SESSION['usuario']['id'];

        $stmt = $this->db->prepare("SELECT * FROM contribuintes WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$id, $uid]);
        $contribuinte = $stmt->fetch();

        if (!$contribuinte) {
            $this->flash('erro', 'Contribuinte não encontrado.');
            $this->redirect('/contribuintes');
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
        $uid = $_SESSION['usuario']['id'];
        $id  = (int) $this->post('id', 0);

        $dados = [
            'cnpj'                   => preg_replace('/\D/', '', $this->post('cnpj', '')),
            'razao_social'           => $this->sanitize($this->post('razao_social', '')),
            'nome_fantasia'          => $this->sanitize($this->post('nome_fantasia', '')),
            'tipo_contribuinte'      => $this->post('tipo_contribuinte', '1'),
            'classificacao_tributos' => $this->sanitize($this->post('classificacao_tributos', '')),
        ];

        if (empty($dados['cnpj']) || empty($dados['razao_social'])) {
            $this->flash('erro', 'CNPJ e Razão Social são obrigatórios.');
            $this->redirect($id ? "/contribuintes/editar?id={$id}" : '/contribuintes/novo');
        }

        // Valida dígito verificador apenas para CNPJ e CPF (pula CAEPF, CNO, CEI)
        if (in_array($dados['tipo_contribuinte'], ['1', '2'])) {
            if (!ValidacaoService::cnpjOuCpf($dados['cnpj'])) {
                $this->flash('erro', 'CNPJ/CPF inválido. Verifique os dígitos verificadores.');
                $this->redirect($id ? "/contribuintes/editar?id={$id}" : '/contribuintes/novo');
            }
        }

        if ($id) {
            $stmt = $this->db->prepare("
                UPDATE contribuintes
                SET cnpj=?, razao_social=?, nome_fantasia=?, tipo_contribuinte=?, classificacao_tributos=?
                WHERE id=? AND usuario_id=?
            ");
            $stmt->execute([...array_values($dados), $id, $uid]);
            $this->flash('sucesso', 'Contribuinte atualizado com sucesso.');
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO contribuintes (usuario_id, cnpj, razao_social, nome_fantasia, tipo_contribuinte, classificacao_tributos)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$uid, ...array_values($dados)]);
            $this->flash('sucesso', 'Contribuinte cadastrado com sucesso.');
        }

        $this->redirect('/contribuintes');
    }

    public function excluir(): void
    {
        $this->requireLogin();
        $id  = (int) $this->get('id');
        $uid = $_SESSION['usuario']['id'];

        $stmt = $this->db->prepare("DELETE FROM contribuintes WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$id, $uid]);

        $this->flash('sucesso', 'Contribuinte excluído com sucesso.');
        $this->redirect('/contribuintes');
    }
}