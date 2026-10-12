<?php

namespace Tests\MariaDB;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

abstract class MariaDbTestCase extends TestCase
{
    protected Connection $mariaDb;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDefaultConnection() !== 'mariadb_test') {
            throw new RuntimeException('MariaDB integration tests require the mariadb_test default connection.');
        }

        $this->mariaDb = DB::connection('mariadb_test');
        $identity = $this->mariaDb->selectOne(
            'SELECT DATABASE() AS database_name, VERSION() AS server_version'
        );

        if (($identity->database_name ?? null) !== 'arsip_bakti_test') {
            throw new RuntimeException('Connected database does not match the MariaDB test allowlist.');
        }

        $expectedVersion = (string) getenv('MARIADB_TEST_EXPECTED_VERSION');
        if (! str_starts_with((string) ($identity->server_version ?? ''), $expectedVersion)) {
            throw new RuntimeException('Connected MariaDB version does not match the approved test version.');
        }

        $schema = $this->mariaDb->selectOne(
            'SELECT DEFAULT_COLLATION_NAME AS collation_name
             FROM information_schema.SCHEMATA
             WHERE SCHEMA_NAME = DATABASE()'
        );
        if (($schema->collation_name ?? null) !== getenv('MARIADB_TEST_COLLATION')) {
            throw new RuntimeException('Connected database collation does not match the approved test collation.');
        }

        $divisionCollations = $this->mariaDb->select(
            'SELECT TABLE_NAME AS table_name, COLLATION_NAME AS collation_name
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND COLUMN_NAME = ?
               AND ((TABLE_NAME = ? AND COLUMN_NAME = ?)
                 OR (TABLE_NAME = ? AND COLUMN_NAME = ?))',
            ['division', 'users', 'division', 'arsip', 'division'],
        );

        if (count($divisionCollations) !== 2) {
            throw new RuntimeException('The approved schema must expose both division columns.');
        }

        foreach ($divisionCollations as $column) {
            if ($column->collation_name !== getenv('MARIADB_TEST_COLLATION')) {
                throw new RuntimeException('A division column collation does not match the approved test collation.');
            }
        }

        $this->assertSchemaReady();
        $this->mariaDb->beginTransaction();
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->mariaDb) && $this->mariaDb->transactionLevel() > 0) {
                $this->mariaDb->rollBack();
            }
        } finally {
            parent::tearDown();
        }
    }

    private function assertSchemaReady(): void
    {
        $tables = $this->mariaDb->select(
            'SELECT TABLE_NAME AS table_name, ENGINE AS engine
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN (?, ?, ?, ?)',
            ['users', 'arsip', 'kategori_arsip', 'lokasi'],
        );

        $engines = [];
        foreach ($tables as $table) {
            $engines[$table->table_name] = strtoupper((string) $table->engine);
        }

        foreach (['users', 'arsip', 'kategori_arsip', 'lokasi'] as $tableName) {
            if (($engines[$tableName] ?? null) !== 'INNODB') {
                throw new RuntimeException('A required MariaDB test table is missing or is not InnoDB.');
            }
        }

        $columns = $this->mariaDb->select(
            'SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN (?, ?, ?, ?)',
            ['users', 'arsip', 'kategori_arsip', 'lokasi'],
        );

        $availableColumns = [];
        foreach ($columns as $column) {
            $availableColumns[$column->table_name][$column->column_name] = true;
        }

        $requiredColumns = [
            'users' => ['id', 'nama', 'username', 'password', 'role', 'division', 'status', 'created_at', 'updated_at'],
            'kategori_arsip' => ['id', 'nama_kategori', 'keterangan', 'status', 'created_at', 'updated_at'],
            'lokasi' => [
                'id', 'gudang', 'rak', 'lemari', 'box', 'capacity_boxes', 'keterangan', 'status',
                'created_at', 'updated_at',
            ],
            'arsip' => [
                'id', 'nomor_berkas', 'nama_berkas', 'kode_arsip', 'kategori_id', 'tahun', 'bulan',
                'tanggal_masuk', 'lokasi_id', 'classification', 'division', 'physical_condition',
                'status', 'qr_code', 'keterangan', 'created_by', 'created_at', 'updated_at',
            ],
        ];

        foreach ($requiredColumns as $tableName => $tableColumns) {
            foreach ($tableColumns as $columnName) {
                if (! isset($availableColumns[$tableName][$columnName])) {
                    throw new RuntimeException('The approved MariaDB test schema is missing a required column.');
                }
            }
        }

        $roleColumn = $this->mariaDb->selectOne(
            'SELECT DATA_TYPE AS data_type
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?',
            ['users', 'role'],
        );

        if (! in_array(strtolower((string) ($roleColumn->data_type ?? '')), ['char', 'varchar', 'text'], true)) {
            throw new RuntimeException('The approved test schema must support target roles without altering the legacy enum.');
        }
    }
}
