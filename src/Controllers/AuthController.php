<?php

namespace App\Controllers;

use App\Models\UsuarioRepository;
use App\Services\RateLimiter;

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

        $maxAttempts = (int) ($this->config['security']['login_max_attempts'] ?? 5);
        $lockSec     = (int) ($this->config['security']['login_lockout_sec'] ?? 900);
        $ip          = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $bucket      = 'login|' . strtolower($email) . '|' . $ip;
        $limiter     = new RateLimiter(BASE_PATH . '/storage/rate_limit');
        $limit       = $limiter->hit($bucket, $maxAttempts, $lockSec);

        if (!$limit['allowed']) {
            $min = max(1, (int) ceil($limit['retry_after'] / 60));
            $this->redirect('/login', "Muitas tentativas. Aguarde {$min} minuto(s) e tente novamente.", 'erro');
        }

        $repo    = new UsuarioRepository($this->db);
        $usuario = $repo->findByEmail($email);

        if (!$usuario || !password_verify($senha, $usuario['senha'])) {
            $this->redirect('/login', 'E-mail ou senha incorretos.', 'erro');
        }

        if (!empty($usuario['trial_expira']) && $usuario['trial_expira'] < date('Y-m-d')) {
            $this->redirect('/login', 'Seu período de teste expirou. Entre em contato.', 'erro');
        }

        $limiter->clear($bucket);

        $_SESSION['usuario'] = [
            'id'     => $usuario['id'],
            'nome'   => $usuario['nome'],
            'email'  => $usuario['email'],
            'perfil' => $usuario['perfil'],
            'force_password_change' => !empty($usuario['force_password_change']),
        ];

        session_regenerate_id(true);

        if (!empty($usuario['force_password_change'])) {
            $this->redirect('/perfil', 'Por segurança, defina uma nova senha antes de continuar.', 'erro');
        }

        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
        $this->redirect('/login');
    }
}
