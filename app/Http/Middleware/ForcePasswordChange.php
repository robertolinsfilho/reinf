<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $path = '/' . ltrim($request->path(), '/');
        if (
            $user->force_password_change
            && !str_starts_with($path, '/perfil')
            && $path !== '/logout'
        ) {
            return redirect('/perfil')
                ->with('flash', [
                    'tipo' => 'erro',
                    'mensagem' => 'Por segurança, defina uma nova senha antes de continuar.',
                ]);
        }

        return $next($request);
    }
}
