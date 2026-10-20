<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LocalDatabaseMigrationGuard
{
    public static function assertApplicationDatabase(): Connection
    {
        $connection = DB::connection();
        $config = $connection->getConfig();

        if (
            ! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)
            || strtolower((string) $connection->getDatabaseName()) !== 'arsip_bakti_local'
            || ! in_array(strtolower((string) ($config['host'] ?? '')), ['127.0.0.1', 'localhost', '::1'], true)
            || (string) ($config['port'] ?? '') !== '3306'
            || ! empty($config['url'])
        ) {
            throw new RuntimeException('Database migrations are restricted to local MySQL arsip_bakti_local on port 3306.');
        }

        return $connection;
    }
}
