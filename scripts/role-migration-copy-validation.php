<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (getenv('DB_DATABASE') !== 'arsip_bakti_test_copy') {
    throw new RuntimeException('DB_DATABASE must explicitly target arsip_bakti_test_copy.');
}

config([
    'database.default' => 'mysql',
    'database.connections.mysql.database' => 'arsip_bakti_test_copy',
]);
DB::purge('mysql');

$connection = DB::connection('mysql');
if (
    $connection->getDatabaseName() !== 'arsip_bakti_test_copy'
    || $connection->getDatabaseName() === 'arsip_bakti'
    || $connection->getDriverName() !== 'mysql'
    || (string) config('database.connections.mysql.host') !== '127.0.0.1'
    || (string) config('database.connections.mysql.port') !== '3306'
) {
    throw new RuntimeException('Connection did not resolve to the expected isolated local test database.');
}

$run = static function (string $label): array {
    $role = DB::selectOne(
        'select column_type, is_nullable, column_default, character_set_name, collation_name, extra '
        .'from information_schema.columns where table_schema = database() '
        .'and table_name = ? and column_name = ?',
        ['users', 'role']
    );
    $roles = DB::table('users')
        ->select('role')
        ->selectRaw('COUNT(*) AS account_count')
        ->groupBy('role')
        ->orderBy('role')
        ->pluck('account_count', 'role')
        ->map(static fn ($count): int => (int) $count)
        ->all();
    $tables = DB::select(
        'select table_name from information_schema.tables where table_schema = database() '
        .'and table_type = ? order by table_name',
        ['BASE TABLE']
    );
    $rowCounts = [];
    foreach ($tables as $table) {
        $name = str_replace('`', '``', $table->table_name);
        $rowCounts[$table->table_name] = (int) DB::selectOne("select count(*) as c from `{$name}`")->c;
    }
    $schema = [
        'tables' => count($tables),
        'columns' => (int) DB::selectOne(
            'select COUNT(*) as c from information_schema.columns where table_schema = database()'
        )->c,
        'indexes' => (int) DB::selectOne(
            'select COUNT(DISTINCT CONCAT(table_name, ".", index_name)) as c '
            .'from information_schema.statistics where table_schema = database()'
        )->c,
        'foreign_keys' => (int) DB::selectOne(
            'select COUNT(*) as c from information_schema.key_column_usage '
            .'where table_schema = database() and referenced_table_name is not null'
        )->c,
    ];

    return [
        'phase' => $label,
        'database' => DB::connection()->getDatabaseName(),
        'server_version' => DB::selectOne('select version() as v')->v,
        'role_column' => (array) $role,
        'role_counts' => $roles,
        'schema_counts' => $schema,
        'table_row_counts' => $rowCounts,
    ];
};

$snapshotBefore = $run('before');
$roleColumn = $snapshotBefore['role_column'];
echo json_encode([
    'verified_target_database' => $connection->getDatabaseName(),
    'database_server_version' => $snapshotBefore['server_version'],
    'test_copy_role_column_metadata' => $roleColumn,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
if (
    $roleColumn['column_type'] !== "enum('Super Admin','Petugas Gudang','Admin Unit/Petugas Arsip','Verifikator','Pimpinan/Approver','Customer','Pengguna Umum')"
    || $roleColumn['is_nullable'] !== 'NO'
    || $roleColumn['column_default'] !== "'Customer'"
    || $roleColumn['character_set_name'] !== 'utf8mb4'
    || $roleColumn['collation_name'] !== 'utf8mb4_unicode_ci'
) {
    throw new RuntimeException('The test copy role column differs from the schema documented for the live database.');
}

$originalEnum = "ENUM('Super Admin','Petugas Gudang','Admin Unit/Petugas Arsip','Verifikator','Pimpinan/Approver','Customer','Pengguna Umum')";
$targetEnum = "ENUM('Super Admin','Petugas Gudang','Admin Unit/Petugas Arsip','Verifikator','Pimpinan/Approver','Customer','Pengguna Umum','Admin','Kepala Divisi','Pegawai')";
$forwardSql = "ALTER TABLE `users` MODIFY COLUMN `role` {$targetEnum} "
    ."CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Customer'";
DB::statement($forwardSql);
$snapshotAfterForward = $run('after_forward');

if (
    $snapshotAfterForward['schema_counts'] !== $snapshotBefore['schema_counts']
    || $snapshotAfterForward['table_row_counts'] !== $snapshotBefore['table_row_counts']
    || $snapshotAfterForward['role_counts'] !== $snapshotBefore['role_counts']
    || $snapshotAfterForward['role_column']['column_default'] !== $roleColumn['column_default']
) {
    throw new RuntimeException('Forward ALTER changed schema metadata outside the expected enum or changed data.');
}

DB::table('users')->insert([
    'nama' => 'Temporary role migration guard fixture',
    'username' => '__role_migration_rollback_guard__',
    'password' => 'not-a-real-account',
    'role' => 'Admin',
    'division' => null,
    'status' => 'Nonaktif',
    'created_at' => now(),
    'updated_at' => now(),
]);

$rollbackGuardRefused = false;
try {
    $targetUseCount = DB::table('users')->whereIn('role', ['Admin', 'Kepala Divisi', 'Pegawai'])->count();
    if ($targetUseCount > 0) {
        throw new RuntimeException('Rollback refused because target-role values are in use.');
    }
} catch (RuntimeException $exception) {
    if ($exception->getMessage() !== 'Rollback refused because target-role values are in use.') {
        throw $exception;
    }
    $rollbackGuardRefused = true;
}

DB::table('users')->where('username', '__role_migration_rollback_guard__')->delete();
if (! $rollbackGuardRefused) {
    throw new RuntimeException('Rollback guard did not reject a target role in use.');
}

$targetUseCount = DB::table('users')->whereIn('role', ['Admin', 'Kepala Divisi', 'Pegawai'])->count();
if ($targetUseCount !== 0) {
    throw new RuntimeException('Temporary target-role fixture was not fully removed from the test copy.');
}

$rollbackSql = "ALTER TABLE `users` MODIFY COLUMN `role` {$originalEnum} "
    ."CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Customer'";
DB::statement($rollbackSql);
$snapshotAfterRollback = $run('after_rollback');

if (
    $snapshotAfterRollback['role_column'] !== $snapshotBefore['role_column']
    || $snapshotAfterRollback['role_counts'] !== $snapshotBefore['role_counts']
    || $snapshotAfterRollback['schema_counts'] !== $snapshotBefore['schema_counts']
    || $snapshotAfterRollback['table_row_counts'] !== $snapshotBefore['table_row_counts']
) {
    throw new RuntimeException('Rollback did not restore the original role schema and data snapshot.');
}

echo json_encode([
    'connection' => [
        'database' => $connection->getDatabaseName(),
        'driver' => $connection->getDriverName(),
        'server_version' => $snapshotBefore['server_version'],
    ],
    'before' => $snapshotBefore,
    'after_forward' => $snapshotAfterForward,
    'rollback_guard_refused_when_target_role_in_use' => $rollbackGuardRefused,
    'after_rollback' => $snapshotAfterRollback,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
