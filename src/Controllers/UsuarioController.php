<?php

namespace App\Controllers;

use App\Models\UsuarioRepository;

class UsuarioController extends BaseController
{
    private UsuarioRepository $repo;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->repo = new UsuarioRepository($this->db);
    }

    public function index(): void
    {
        $this->requireAdmin();
        $this->view('pages/usuarios/index', [
            'pageTitle' => 'Usuários',
            'usuarios'  => $this->repo->listAll(),
            'flash'     => $this->getFlash(),
        ]);
    }

    public function novo(): void
    {
        $this->requireAdmin();
        $this->view('pages/usuarios/form', [
            'pageTitle' => 'Novo Usuário',
            'usuario'   => null,
            'flash'     => $this->getFlash(),
        ]);
    }

    public function salvar(): void
    {
        $this->requireAdmin();
        $nome  = $this->sanitize($this->post('nome', ''));
        $email = $this->sanitize($this->post('email', ''));
        $senha = $this->post('senha', '');

        if (!$nome || !$email || !$senha) {
            $this->redirect('/usuarios/novo', 'Nome, e-mail e senha são obrigatórios.', 'erro');
        }

        $this->safeExecute(function () use ($nome, $email, $senha) {
            $this->repo->criar(
                $nome,
                $email,
                password_hash($senha, PASSWORD_BCRYPT),
                $this->post('perfil', 'usuario'),
                $this->post('trial_expira') ?: null
            );
            $this->redirect('/usuarios', 'Usuário criado!', 'sucesso');
        }, '/usuarios/novo');
    }

    public function perfil(): void
    {
        $this->requireLogin();
        $this->view('pages/usuarios/perfil', [
            'pageTitle' => 'Meu Perfil',
            'usuario'   => $this->repo->find($this->userId()),
            'flash'     => $this->getFlash(),
        ]);
    }

    public function salvarPerfil(): void
    {
        $this->requireLogin();
        $nome  = $this->sanitize($this->post('nome', ''));
        $senha = $this->post('senha', '');

        if (!$nome) {
            $this->redirect('/perfil', 'Nome é obrigatório.', 'erro');
        }

        $hash = $senha ? password_hash($senha, PASSWORD_BCRYPT) : null;
        if (!empty($_SESSION['usuario']['force_password_change']) && !$hash) {
            $this->redirect('/perfil', 'Informe a nova senha para continuar.', 'erro');
        }
        if ($hash && strlen($senha) < 8) {
            $this->redirect('/perfil', 'A nova senha deve ter pelo menos 8 caracteres.', 'erro');
        }

        $this->repo->atualizarPerfil($this->userId(), $nome, $hash);

        $_SESSION['usuario']['nome'] = $nome;
        if ($hash) {
            $_SESSION['usuario']['force_password_change'] = false;
        }
        $this->redirect($hash ? '/dashboard' : '/perfil', 'Perfil atualizado!', 'sucesso');
    }
}