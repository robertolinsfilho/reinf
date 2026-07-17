<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class Database
{
    private static ?\PDO $instance = null;

    public static function getInstance(?array $config = null): \PDO
    {
        if (self::$instance === null) {
            self::$instance = DB::connection()->getPdo();
            self::$instance->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        }

        return self::$instance;
    }
}
