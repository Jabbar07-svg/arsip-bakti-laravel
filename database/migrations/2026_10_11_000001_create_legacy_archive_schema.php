<?php

use App\Support\LocalDatabaseMigrationGuard;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $connection = LocalDatabaseMigrationGuard::assertApplicationDatabase();

        $schemaPath = database_path('schema/legacy_mysql.sql');
        $schema = file_get_contents($schemaPath);
        if ($schema === false) {
            throw new RuntimeException('The legacy MySQL schema file could not be read.');
        }

        preg_match_all(
            '/CREATE TABLE IF NOT EXISTS `[^`]+` \([\s\S]*?\) ENGINE=[^;]+;/i',
            $schema,
            $matches,
        );
        if (count($matches[0]) !== 19) {
            throw new RuntimeException('The legacy MySQL schema must define exactly 19 business tables.');
        }

        foreach ($matches[0] as $statement) {
            $connection->unprepared($statement);
        }
    }

    public function down(): void
    {
        LocalDatabaseMigrationGuard::assertApplicationDatabase();

        // Keep the legacy schema and its data intact during rollback.
    }
};
