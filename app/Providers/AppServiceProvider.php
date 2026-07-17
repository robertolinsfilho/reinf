<?php

namespace App\Providers;

use App\Services\CertificadoCrypto;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Guarda de segurança portada de public/index.php (legado): em produção,
        // um APP_SECRET fraco impede a criptografia da senha do certificado A1.
        $secret = (string) config('reinf.secret', '');
        if (CertificadoCrypto::isInsecureSecret($secret)) {
            if ($this->app->environment('production')) {
                throw new \RuntimeException(
                    'Configuração insegura: defina APP_SECRET forte (≥32 caracteres) no .env antes de usar em produção. '
                    . 'Gere com: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"'
                );
            }
            error_log('WARNING: APP_SECRET ausente ou fraco. Defina um valor forte no .env.');
        }
    }
}
