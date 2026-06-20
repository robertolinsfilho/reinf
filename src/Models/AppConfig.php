<?php

namespace App\Models;

class AppConfig
{
    private static ?array $instance = null;

    public static function get(?string $key = null): mixed
    {
        if (self::$instance === null) {
            self::$instance = require BASE_PATH . '/config/app.php';
        }

        if ($key === null) {
            return self::$instance;
        }

        // Suporta dot notation: 'reinf.tp_amb'
        $keys  = explode('.', $key);
        $value = self::$instance;
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return null;
            }
            $value = $value[$k];
        }
        return $value;
    }
}