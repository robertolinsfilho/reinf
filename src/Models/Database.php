<?php

namespace App\Models;

class Database
{
    private static ?\PDO $instance = null;
    private static array $cachedConfig = [];

    /**
     * Aceita config como parâmetro OU usa o config cacheado da primeira chamada.
     * Assim funciona tanto $db = Database::getInstance($config) quanto Database::getInstance()
     */
    public static function getInstance(?array $config = null): \PDO
    {
        if ($config !== null) {
            self::$cachedConfig = $config;
        }

        if (self::$instance === null) {
            $c = self::$cachedConfig;
            if (empty($c)) {
                // Fallback: ler do config/app.php
                $config = \App\Models\AppConfig::get();
                $c = $appConfig['db'];
                self::$cachedConfig = $c;
            }

            $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
            self::$instance = new \PDO($dsn, $c['user'], $c['pass'], [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            ]);
        }

        return self::$instance;
    }
}