<?php

namespace App\Models;

class Database
{
    private static ?\PDO $instance = null;

    public static function getInstance(array $config): \PDO
    {
        if (self::$instance === null) {
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset={$config['charset']}";
            self::$instance = new \PDO($dsn, $config['user'], $config['pass'], [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            ]);
        }
        return self::$instance;
    }
}
