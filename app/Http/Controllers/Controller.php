<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function render(string $template, array $data = [], bool $noLayout = false)
    {
        $user = auth()->user();
        $shared = [
            'config' => [
                'app' => [
                    'name' => config('app.name'),
                    'url' => config('app.url'),
                    'env' => config('app.env'),
                    'secret' => config('reinf.secret'),
                ],
                'upload' => config('reinf.upload'),
                'security' => config('reinf.security'),
                'reinf' => [
                    'tp_amb' => config('reinf.tp_amb'),
                    'ver_proc' => config('reinf.ver_proc'),
                    'proc_emi' => config('reinf.proc_emi'),
                    'cert_path' => config('reinf.cert_path'),
                    'cert_pass' => config('reinf.cert_pass'),
                    'ws_envio' => config('reinf.ws_envio'),
                    'ws_consulta' => config('reinf.ws_consulta'),
                    'user_agent' => config('reinf.user_agent'),
                ],
            ],
            'baseUrl' => rtrim((string) config('app.url'), '/'),
            'appName' => config('app.name'),
            'usuario' => $user ? [
                'id' => $user->id,
                'nome' => $user->nome,
                'email' => $user->email,
                'perfil' => $user->perfil,
                'force_password_change' => (bool) $user->force_password_change,
            ] : null,
            'flash' => session('flash'),
        ];

        session()->forget('flash');

        $viewData = array_merge($shared, $data);

        if ($noLayout) {
            return view($template, $viewData);
        }

        return view('layouts.main', array_merge($viewData, [
            'contentView' => $template,
        ]));
    }

    protected function flashRedirect(string $url, string $mensagem, string $tipo = 'info', bool $withInput = false): RedirectResponse
    {
        $redirect = redirect($url)->with('flash', ['tipo' => $tipo, 'mensagem' => $mensagem]);
        return $withInput ? $redirect->withInput() : $redirect;
    }

    protected function flash(string $tipo, string $mensagem): void
    {
        session()->flash('flash', ['tipo' => $tipo, 'mensagem' => $mensagem]);
    }

    protected function userId(): int
    {
        return (int) (auth()->id() ?? 0);
    }

    protected function post(Request $request, string $key, mixed $default = null): mixed
    {
        return $request->input($key, $default);
    }

    protected function postMoney(Request $request, string $key): float
    {
        $val = (string) $request->input($key, '0');
        return (float) str_replace(['.', ','], ['', '.'], $val);
    }

    protected function postCnpj(Request $request, string $key): string
    {
        return preg_replace('/\D/', '', (string) $request->input($key, '')) ?? '';
    }

    protected function postCpf(Request $request, string $key): string
    {
        return preg_replace('/\D/', '', (string) $request->input($key, '')) ?? '';
    }

    protected function sanitize(?string $value): string
    {
        return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
    }

    protected function safeExecute(callable $fn, string $redirectUrl, string $errorPrefix = 'Erro', bool $withInput = false): mixed
    {
        try {
            return $fn();
        } catch (QueryException $e) {
            report($e);
            return $this->flashRedirect($redirectUrl, "{$errorPrefix}: falha ao gravar dados.", 'erro', $withInput);
        } catch (\RuntimeException $e) {
            report($e);
            return $this->flashRedirect($redirectUrl, "{$errorPrefix}: " . $e->getMessage(), 'erro', $withInput);
        } catch (\Exception $e) {
            report($e);
            return $this->flashRedirect($redirectUrl, "{$errorPrefix}: não foi possível concluir a operação.", 'erro', $withInput);
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
}
