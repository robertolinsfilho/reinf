<?php

namespace App\Http\Controllers;

use App\Repositories\ContribuinteRepository;
use App\Repositories\ProcessoRepository;
use Illuminate\Http\Request;

class ProcessoController extends Controller
{
    private ProcessoRepository $repo;
    private ContribuinteRepository $contribuintes;

    public function __construct()
    {
        $this->repo          = new ProcessoRepository();
        $this->contribuintes = new ContribuinteRepository();
    }

    public function index()
    {
        return $this->render('pages.processos.index', [
            'pageTitle' => 'R-1070 — Processos Administrativos/Judiciais',
            'processos' => $this->repo->listByUser($this->userId()),
        ]);
    }

    public function novo()
    {
        return $this->render('pages.processos.form', [
            'pageTitle'     => 'Novo Processo',
            'processo'      => null,
            'contribuintes' => $this->contribuintes->listByUser($this->userId()),
        ]);
    }

    public function editar(Request $request)
    {
        $proc = $this->repo->findByUser((int) $request->query('id'), $this->userId());
        if (!$proc) {
            return $this->flashRedirect('/processos', 'Processo não encontrado.', 'erro');
        }
        return $this->render('pages.processos.form', [
            'pageTitle'     => 'Editar Processo',
            'processo'      => $proc,
            'contribuintes' => $this->contribuintes->listByUser($this->userId()),
        ]);
    }

    public function salvar(Request $request)
    {
        $id  = (int) $request->input('id', 0);
        $uid = $this->userId();

        $contribId = (int) $request->input('contribuinte_id');
        if (!$this->contribuintes->findByUser($contribId, $uid)) {
            return $this->flashRedirect('/processos/novo', 'Contribuinte inválido.', 'erro');
        }

        $numProc = trim((string) $request->input('numero_processo', ''));
        if (!$numProc) {
            return $this->flashRedirect('/processos/novo', 'Número do processo é obrigatório.', 'erro');
        }

        $dados = [
            'contribuinte_id'     => $contribId,
            'tipo_processo'       => (int) $request->input('tipo_processo', 1),
            'numero_processo'     => $numProc,
            'indicador_autoria'   => (int) $request->input('indicador_autoria', 1),
            'uf_vara'             => $request->input('uf_vara') ?: null,
            'cod_municipio'       => $request->input('cod_municipio') ?: null,
            'id_vara'             => $request->input('id_vara') ?: null,
            'indicador_susp_exig' => (int) $request->input('indicador_susp_exig', 0),
            'data_decisao'        => $request->input('data_decisao') ?: null,
            'indicador_deposito'  => (int) $request->input('indicador_deposito', 0),
            'data_inclusao'       => $request->input('data_inclusao', date('Y-m-d')),
            'descricao'           => $this->sanitize($request->input('descricao', '')),
            'status'              => $request->input('status', 'ativo'),
        ];

        return $this->safeExecute(function () use ($id, $uid, $dados) {
            if ($id) {
                $proc = $this->repo->findByUser($id, $uid);
                if (!$proc) {
                    return $this->flashRedirect('/processos', 'Processo não encontrado.', 'erro');
                }
                $this->repo->update($id, $dados);
                return $this->flashRedirect('/processos', 'Processo atualizado!', 'sucesso');
            }
            $this->repo->insert($dados);
            return $this->flashRedirect('/processos', 'Processo cadastrado!', 'sucesso');
        }, $id ? "/processos/editar?id={$id}" : '/processos/novo');
    }

    public function excluir(Request $request)
    {
        $id   = (int) $request->input('id');
        $proc = $this->repo->findByUser($id, $this->userId());
        if (!$proc) {
            return $this->flashRedirect('/processos', 'Processo não encontrado.', 'erro');
        }
        $this->repo->delete($id);
        return $this->flashRedirect('/processos', 'Processo excluído.', 'sucesso');
    }
}
