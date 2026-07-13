<?php

namespace App\Controllers;

use App\Models\UsuarioRepository;

class AuthController extends BaseController
{
    public function loginForm(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirect('/dashboard');
        }
        $this->view('pages/login', ['flash' => $this->getFlash(), 'pageTitle' => 'Login']);
    }

    public function login(): void
    {
        $email = trim($this->post('email', ''));
        $senha = $this->post('senha', '');

        if (empty($email) || empty($senha)) {
            $this->redirect('/login', 'Preencha e-mail e senha.', 'erro');
        }

        $repo    = new UsuarioRepository($this->db);
        $usuario = $repo->findByEmail($email);

        if (!$usuario || !password_verify($senha, $usuario['senha'])) {
            $this->redirect('/login', 'E-mail ou senha incorretos.', 'erro');
        }

        if (!empty($usuario['trial_expira']) && $usuario['trial_expira'] < date('Y-m-d')) {
            $this->redirect('/login', 'Seu período de teste expirou. Entre em contato.', 'erro');
        }

        $_SESSION['usuario'] = [
            'id'     => $usuario['id'],
            'nome'   => $usuario['nome'],
            'email'  => $usuario['email'],
            'perfil' => $usuario['perfil'],
        ];

        session_regenerate_id(true);
        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        session_destroy();
        $this->redirect('/login');
    }
}