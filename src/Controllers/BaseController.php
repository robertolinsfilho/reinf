<?php

declare(strict_types=1);

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
        $csrfField = $this->csrfField();

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

        // Obrigar troca de senha (exceto na própria tela de perfil)
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        if (
            !empty($_SESSION['usuario']['force_password_change'])
            && !str_starts_with($uri, '/perfil')
            && $uri !== '/logout'
        ) {
            $this->redirect('/perfil', 'Por segurança, defina uma nova senha antes de continuar.', 'erro');
        }
    }

    protected function requireAdmin(): void
    {
        $this->requireLogin();
        if (($_SESSION['usuario']['perfil'] ?? '') !== 'admin') {
            $this->flash('erro', 'Acesso restrito a administradores.');
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

    // ─── CSRF ────────────────────────────────────────────────

    protected function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function csrfField(): string
    {
        return '<input type="hidden" name="_token" value="' . $this->csrfToken() . '">';
    }

    public function verifyCsrf(): void
    {
        $token = (string) $this->post('_token', '');
        if ($token === '' || !hash_equals($this->csrfToken(), $token)) {
            $this->flash('erro', 'Token de segurança inválido. Tente novamente.');
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/dashboard');
        }
    }

    // ─── Error handling ──────────────────────────────────────

    protected function safeExecute(callable $fn, string $redirectUrl, string $errorPrefix = 'Erro'): void
    {
        try {
            $fn();
        } catch (\PDOException $e) {
            error_log('DB Error: ' . $e->getMessage());
            $this->redirect($redirectUrl, "{$errorPrefix}: falha ao gravar dados.", 'erro');
        } catch (\RuntimeException $e) {
            // Mensagens de negócio controladas (importação, validação etc.)
            error_log('Runtime: ' . $e->getMessage());
            $this->redirect($redirectUrl, "{$errorPrefix}: " . $e->getMessage(), 'erro');
        } catch (\Exception $e) {
            error_log('Error: ' . $e->getMessage());
            $this->redirect($redirectUrl, "{$errorPrefix}: não foi possível concluir a operação.", 'erro');
        }
    }

    protected function assertUploadedFile(array $file, int $maxSize, array $allowedExt): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Falha no upload do arquivo.');
        }
        if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > $maxSize) {
            $mb = (int) round($maxSize / 1024 / 1024);
            throw new \RuntimeException("Arquivo excede o tamanho máximo de {$mb}MB.");
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            throw new \RuntimeException('Tipo de arquivo não permitido.');
        }
        return $ext;
    }

    // ─── Input helpers ───────────────────────────────────────

    protected function postMoney(string $key): float
    {
        $val = $this->post($key, '0');
        return (float) str_replace(['.', ','], ['', '.'], $val);
    }

    protected function postCnpj(string $key): string
    {
        return preg_replace('/\D/', '', $this->post($key, ''));
    }

    protected function postCpf(string $key): string
    {
        return preg_replace('/\D/', '', $this->post($key, ''));
    }

    protected function userId(): int
    {
        return (int) ($_SESSION['usuario']['id'] ?? 0);
    }
}