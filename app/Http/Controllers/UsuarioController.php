<?php

namespace App\Http\Controllers;

use App\Repositories\UsuarioRepository;
use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    private UsuarioRepository $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new UsuarioRepository($this->db);
    }

    public function index()
    {
        return $this->render('pages.usuarios.index', [
            'pageTitle' => 'Usuários',
            'usuarios'  => $this->repo->listAll(),
        ]);
    }

    public function novo()
    {
        return $this->render('pages.usuarios.form', [
            'pageTitle' => 'Novo Usuário',
            'usuario'   => null,
        ]);
    }

    public function salvar(Request $request)
    {
        $nome  = $this->sanitize($request->input('nome', ''));
        $email = $this->sanitize($request->input('email', ''));
        $senha = $request->input('senha', '');

        if (!$nome || !$email || !$senha) {
            return $this->flashRedirect('/usuarios/novo', 'Nome, e-mail e senha são obrigatórios.', 'erro');
        }

        return $this->safeExecute(function () use ($request, $nome, $email, $senha) {
            $this->repo->criar(
                $nome,
                $email,
                password_hash($senha, PASSWORD_BCRYPT),
                $request->input('perfil', 'usuario'),
                $request->input('trial_expira') ?: null
            );
            return $this->flashRedirect('/usuarios', 'Usuário criado!', 'sucesso');
        }, '/usuarios/novo');
    }

    public function perfil()
    {
        return $this->render('pages.usuarios.perfil', [
            'pageTitle' => 'Meu Perfil',
            'usuario'   => $this->repo->find($this->userId()),
        ]);
    }

    public function salvarPerfil(Request $request)
    {
        $nome  = $this->sanitize($request->input('nome', ''));
        $senha = $request->input('senha', '');

        if (!$nome) {
            return $this->flashRedirect('/perfil', 'Nome é obrigatório.', 'erro');
        }

        $hash = $senha ? password_hash($senha, PASSWORD_BCRYPT) : null;
        if (auth()->user()->force_password_change && !$hash) {
            return $this->flashRedirect('/perfil', 'Informe a nova senha para continuar.', 'erro');
        }
        if ($hash && strlen($senha) < 8) {
            return $this->flashRedirect('/perfil', 'A nova senha deve ter pelo menos 8 caracteres.', 'erro');
        }

        $this->repo->atualizarPerfil($this->userId(), $nome, $hash);

        return $this->flashRedirect($hash ? '/dashboard' : '/perfil', 'Perfil atualizado!', 'sucesso');
    }
}
