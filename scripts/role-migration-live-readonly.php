<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();

if ($connection->getDatabaseName() !== 'arsip_bakti' || $connection->getDriverName() !== 'mysql') {
    throw new RuntimeException('Read-only live verification stopped: expected the configured arsip_bakti MySQL database.');
}

$roleColumn = DB::selectOne(
    'select column_type, is_nullable, column_default, character_set_name, collation_name, extra '
    .'from information_schema.columns where table_schema = database() '
    .'and table_name = ? and column_name = ?',
    ['users', 'role']
);
$roleCounts = DB::table('users')
    ->select('role')
    ->selectRaw('COUNT(*) AS account_count')
    ->groupBy('role')
    ->orderBy('role')
    ->pluck('account_count', 'role')
    ->map(static fn ($count): int => (int) $count)
    ->all();

echo json_encode([
    'database' => $connection->getDatabaseName(),
    'driver' => $connection->getDriverName(),
    'server_version' => DB::selectOne('select version() as v')->v,
    'role_column' => (array) $roleColumn,
    'role_counts' => $roleCounts,
    'tables' => (int) DB::selectOne(
        'select COUNT(*) as c from information_schema.tables where table_schema = database() '
        .'and table_type = ?',
        ['BASE TABLE']
    )->c,
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
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
