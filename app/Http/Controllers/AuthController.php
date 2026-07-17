<?php

namespace App\Http\Controllers;

use App\Services\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function loginForm()
    {
        if (auth()->check()) {
            return redirect('/dashboard');
        }
        return $this->render('pages.login', ['pageTitle' => 'Login'], true);
    }

    public function login(Request $request)
    {
        $email = trim((string) $request->input('email', ''));
        $senha = (string) $request->input('senha', '');

        if ($email === '' || $senha === '') {
            return $this->flashRedirect('/login', 'Preencha e-mail e senha.', 'erro');
        }

        $maxAttempts = (int) config('reinf.security.login_max_attempts', 5);
        $lockSec     = (int) config('reinf.security.login_lockout_sec', 900);
        $ip          = (string) ($request->ip() ?? 'unknown');
        $bucket      = 'login|' . strtolower($email) . '|' . $ip;
        $limiter     = new RateLimiter(storage_path('rate_limit'));
        $limit       = $limiter->hit($bucket, $maxAttempts, $lockSec);

        if (!$limit['allowed']) {
            $min = max(1, (int) ceil($limit['retry_after'] / 60));
            return $this->flashRedirect('/login', "Muitas tentativas. Aguarde {$min} minuto(s) e tente novamente.", 'erro');
        }

        if (!Auth::attempt(['email' => $email, 'password' => $senha, 'ativo' => 1])) {
            return $this->flashRedirect('/login', 'E-mail ou senha incorretos.', 'erro');
        }

        $usuario = Auth::user();

        if (!empty($usuario->trial_expira) && $usuario->trial_expira->format('Y-m-d') < date('Y-m-d')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return $this->flashRedirect('/login', 'Seu período de teste expirou. Entre em contato.', 'erro');
        }

        $limiter->clear($bucket);

        $usuario->forceFill(['ultimo_acesso' => now()])->save();

        $request->session()->regenerate();

        if ($usuario->force_password_change) {
            return $this->flashRedirect('/perfil', 'Por segurança, defina uma nova senha antes de continuar.', 'erro');
        }

        return redirect('/dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
