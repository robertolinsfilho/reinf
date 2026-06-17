<?php

namespace App\Controllers;

class AuthController extends BaseController
{
    public function loginForm(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirect('/dashboard');
        }
        $flash = $this->getFlash();
        $this->view('pages/login', ['flash' => $flash, 'pageTitle' => 'Login']);
    }

    public function login(): void
    {
        $email = trim($this->post('email', ''));
        $senha = $this->post('senha', '');

        if (empty($email) || empty($senha)) {
            $this->flash('erro', 'Preencha e-mail e senha.');
            $this->redirect('/login');
        }

        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if (!$usuario || !password_verify($senha, $usuario['senha'])) {
            $this->flash('erro', 'E-mail ou senha incorretos.');
            $this->redirect('/login');
        }

        // Verificar trial
        if ($usuario['trial_expira'] && $usuario['trial_expira'] < date('Y-m-d')) {
            $this->flash('erro', 'Seu período de teste expirou. Entre em contato.');
            $this->redirect('/login');
        }

        $_SESSION['usuario'] = [
            'id'     => $usuario['id'],
            'nome'   => $usuario['nome'],
            'email'  => $usuario['email'],
            'perfil' => $usuario['perfil'],
        ];

        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        session_destroy();
        $this->redirect('/login');
    }
}
