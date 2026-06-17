<?php

namespace App\Controllers;

use App\Models\Database;

abstract class BaseController
{
    protected array $config;
    protected \PDO $db;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->db     = Database::getInstance($config['db']);
    }

    protected function view(string $template, array $data = []): void
    {
        extract($data);
        $config    = $this->config;
        $baseUrl   = $this->config['app']['url'];
        $appName   = $this->config['app']['name'];
        $usuario   = $_SESSION['usuario'] ?? null;

        $viewPath = BASE_PATH . '/src/Views/' . $template . '.php';
        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View não encontrada: {$template}");
        }

        ob_start();
        include $viewPath;
        $content = ob_get_clean();

        if (!empty($GLOBALS['_no_layout'])) {
            echo $content;
            return;
        }

        $layout = BASE_PATH . '/src/Views/layouts/main.php';
        include $layout;
    }

    /**
     * Redireciona. Aceita mensagem flash opcional.
     * Compatível com: redirect('/url') e redirect('/url', 'msg', 'tipo')
     */
    protected function redirect(string $url, ?string $mensagem = null, string $tipo = 'info'): void
    {
        if ($mensagem !== null) {
            $this->flash($tipo, $mensagem);
        }
        header("Location: {$url}");
        exit;
    }

    protected function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function isLoggedIn(): bool
    {
        return isset($_SESSION['usuario']);
    }

    protected function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            $this->redirect('/login');
        }
    }

    /** Alias para requireLogin — compatibilidade com novos controllers */
    protected function requireAuth(): void
    {
        $this->requireLogin();
    }

    protected function requireAdmin(): void
    {
        $this->requireLogin();
        if (($_SESSION['usuario']['perfil'] ?? '') !== 'admin') {
            $this->redirect('/dashboard');
        }
    }

    protected function flash(string $tipo, string $mensagem): void
    {
        $_SESSION['flash'] = ['tipo' => $tipo, 'mensagem' => $mensagem];
    }

    protected function getFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }

    protected function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    protected function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    protected function sanitize(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }
}