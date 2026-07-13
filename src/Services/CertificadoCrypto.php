<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Criptografia da senha do certificado A1 (AES-256-CBC).
 */
class CertificadoCrypto
{
    public static function encrypt(string $senha, string $secret): string
    {
        $iv  = random_bytes(16);
        $enc = openssl_encrypt($senha, 'AES-256-CBC', $secret, 0, $iv);
        if ($enc === false) {
            throw new \RuntimeException('Falha ao criptografar senha do certificado.');
        }
        return base64_encode($iv . $enc);
    }

    public static function decrypt(string $encrypted, string $secret): string
    {
        $data = base64_decode($encrypted, true);
        if ($data === false || strlen($data) < 17) {
            return '';
        }
        $iv  = substr($data, 0, 16);
        $enc = substr($data, 16);
        return openssl_decrypt($enc, 'AES-256-CBC', $secret, 0, $iv) ?: '';
    }

    public static function secretFromConfig(?array $config = null): string
    {
        $config ??= \App\Models\AppConfig::get();
        $secret = (string) ($config['app']['secret'] ?? '');
        if ($secret === '' || in_array($secret, ['change_me', 'default_key_change_me_in_production'], true)) {
            error_log('WARNING: APP_SECRET is using an insecure default. Set a strong value in .env.');
            return $secret !== '' ? $secret : 'change_me';
        }
        return $secret;
    }
}
