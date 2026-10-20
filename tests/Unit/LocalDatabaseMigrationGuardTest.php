<?php

namespace Tests\Unit;

use App\Support\LocalDatabaseMigrationGuard;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class LocalDatabaseMigrationGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::purge('mysql');

        parent::tearDown();
    }

    public function test_database_migrations_reject_the_protected_legacy_database(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => '3306',
            'database.connections.mysql.database' => 'arsip_bakti',
            'database.connections.mysql.url' => null,
        ]);
        DB::purge('mysql');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('restricted to local MySQL arsip_bakti_local');

        LocalDatabaseMigrationGuard::assertApplicationDatabase();
    }

    public function test_database_migrations_accept_only_the_local_application_database(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => '3306',
            'database.connections.mysql.database' => 'arsip_bakti_local',
            'database.connections.mysql.url' => null,
        ]);
        DB::purge('mysql');

        $connection = LocalDatabaseMigrationGuard::assertApplicationDatabase();

        $this->assertSame('arsip_bakti_local', $connection->getDatabaseName());
    }
}
