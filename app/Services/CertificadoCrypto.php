<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Criptografia da senha do certificado A1.
 * Formato novo: v2:base64(iv12 + tag16 + ciphertext) com AES-256-GCM e chave derivada.
 * Formato legado: base64(iv16 + ciphertext) AES-256-CBC (ainda descriptografável).
 */
class CertificadoCrypto
{
    private const INSECURE_DEFAULTS = [
        '',
        'change_me',
        'default_key_change_me_in_production',
        'mude_esta_chave_secreta_em_producao',
    ];

    public static function isInsecureSecret(string $secret): bool
    {
        $s = trim($secret);
        return $s === '' || in_array($s, self::INSECURE_DEFAULTS, true) || strlen($s) < 32;
    }

    public static function deriveKey(string $secret): string
    {
        return hash('sha256', $secret, true);
    }

    public static function encrypt(string $senha, string $secret): string
    {
        if (self::isInsecureSecret($secret)) {
            throw new \RuntimeException('APP_SECRET inseguro. Defina uma chave forte (≥32 chars) no .env.');
        }

        $key = self::deriveKey($secret);
        $iv  = random_bytes(12);
        $tag = '';
        $enc = openssl_encrypt($senha, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($enc === false || $tag === '') {
            throw new \RuntimeException('Falha ao criptografar senha do certificado.');
        }

        return 'v2:' . base64_encode($iv . $tag . $enc);
    }

    public static function decrypt(string $encrypted, string $secret): string
    {
        if ($encrypted === '') {
            return '';
        }

        if (str_starts_with($encrypted, 'v2:')) {
            $data = base64_decode(substr($encrypted, 3), true);
            if ($data === false || strlen($data) < 29) {
                return '';
            }
            $iv  = substr($data, 0, 12);
            $tag = substr($data, 12, 16);
            $enc = substr($data, 28);
            $plain = openssl_decrypt($enc, 'aes-256-gcm', self::deriveKey($secret), OPENSSL_RAW_DATA, $iv, $tag);
            return $plain === false ? '' : $plain;
        }

        // Legado AES-256-CBC (chave crua ou derivada)
        $data = base64_decode($encrypted, true);
        if ($data === false || strlen($data) < 17) {
            return '';
        }
        $iv  = substr($data, 0, 16);
        $enc = substr($data, 16);

        $plain = openssl_decrypt($enc, 'AES-256-CBC', self::deriveKey($secret), 0, $iv);
        if ($plain !== false && $plain !== '') {
            return $plain;
        }

        return openssl_decrypt($enc, 'AES-256-CBC', $secret, 0, $iv) ?: '';
    }

    public static function secretFromConfig(): string
    {
        return (string) config('reinf.secret', '');
    }
}
