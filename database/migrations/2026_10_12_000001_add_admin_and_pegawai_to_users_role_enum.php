<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LEGACY_ENUM = "enum('Super Admin','Petugas Gudang','Admin Unit/Petugas Arsip','Verifikator','Pimpinan/Approver','Customer','Pengguna Umum')";
    private const TARGET_ENUM = "enum('Super Admin','Petugas Gudang','Admin Unit/Petugas Arsip','Verifikator','Pimpinan/Approver','Customer','Pengguna Umum','Admin','Pegawai')";

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Penambahan role Admin dan Pegawai hanya didukung pada MySQL/MariaDB.');
        }
        if (strtolower((string) DB::connection()->getDatabaseName()) === 'arsip_bakti') {
            throw new RuntimeException('Migration role ditolak pada database produksi arsip_bakti.');
        }

        $column = DB::selectOne("SHOW FULL COLUMNS FROM `users` WHERE `Field` = ?", ['role']);
        if (
            $column === null
            || $column->Null !== 'NO'
            || $column->Default !== 'Customer'
            || $column->Collation !== 'utf8mb4_unicode_ci'
        ) {
            throw new RuntimeException('Schema users.role tidak cocok dengan schema legacy yang diharapkan; migration dibatalkan.');
        }

        $currentType = strtolower((string) $column->Type);
        if ($currentType === strtolower(self::TARGET_ENUM)) {
            return;
        }
        if ($currentType !== strtolower(self::LEGACY_ENUM)) {
            throw new RuntimeException('Nilai enum users.role tidak cocok dengan schema legacy yang diharapkan; migration dibatalkan.');
        }

        DB::statement(
            "ALTER TABLE `users` MODIFY `role` ENUM("
            ."'Super Admin','Petugas Gudang','Admin Unit/Petugas Arsip','Verifikator',"
            ."'Pimpinan/Approver','Customer','Pengguna Umum','Admin','Pegawai'"
            .") CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Customer'"
        );
    }

    public function down(): void
    {
        // Keep the added enum values so rollback cannot invalidate role values already assigned to accounts.
    }
};
