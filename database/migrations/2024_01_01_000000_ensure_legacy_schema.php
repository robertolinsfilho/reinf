<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema is applied by MySQL init (database/migrations/init.sql + alters).
 * This migration only records that the app schema is present for Laravel's migrator.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('usuarios')) {
            throw new RuntimeException(
                'Tabela usuarios ausente. O schema deve ser criado via database/migrations/init.sql no MySQL.'
            );
        }
    }

    public function down(): void
    {
        // Schema legado não é removido por esta migration.
    }
};
