<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Rate limit simples em arquivo (login, etc.).
 *
 * Mantido file-based para preservar o comportamento legado. Injete o diretório
 * de armazenamento — normalmente storage_path('rate_limit'). Caso queira migrar
 * para o RateLimiter nativo do Laravel (Illuminate\Support\Facades\RateLimiter),
 * substitua as chamadas hit()/clear() pelos equivalentes do facade.
 */
class RateLimiter
{
    public function __construct(private string $storageDir)
    {
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0750, true);
        }
    }

    /**
     * @return array{allowed: bool, retry_after: int, remaining: int}
     */
    public function hit(string $bucket, int $maxAttempts, int $windowSeconds): array
    {
        $file = $this->storageDir . '/' . hash('sha256', $bucket) . '.json';
        $now  = time();
        $data = ['attempts' => [], 'locked_until' => 0];

        if (is_file($file)) {
            $raw = json_decode((string) file_get_contents($file), true);
            if (is_array($raw)) {
                $data = array_merge($data, $raw);
            }
        }

        if (($data['locked_until'] ?? 0) > $now) {
            return [
                'allowed'     => false,
                'retry_after' => (int) $data['locked_until'] - $now,
                'remaining'   => 0,
            ];
        }

        $attempts = array_values(array_filter(
            $data['attempts'] ?? [],
            static fn($t) => is_int($t) && $t > ($now - $windowSeconds)
        ));

        if (count($attempts) >= $maxAttempts) {
            $data['locked_until'] = $now + $windowSeconds;
            $data['attempts']     = $attempts;
            file_put_contents($file, json_encode($data), LOCK_EX);
            return [
                'allowed'     => false,
                'retry_after' => $windowSeconds,
                'remaining'   => 0,
            ];
        }

        $attempts[] = $now;
        $data['attempts'] = $attempts;
        $data['locked_until'] = 0;
        file_put_contents($file, json_encode($data), LOCK_EX);

        return [
            'allowed'     => true,
            'retry_after' => 0,
            'remaining'   => max(0, $maxAttempts - count($attempts)),
        ];
    }

    public function clear(string $bucket): void
    {
        $file = $this->storageDir . '/' . hash('sha256', $bucket) . '.json';
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
