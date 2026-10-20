<?php

use App\Support\LocalDatabaseMigrationGuard;
use App\Support\RolePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const AMBIGUOUS_LEGACY_ROLES = [
        'Verifikator',
        'Pimpinan/Approver',
        'Customer',
        'Pengguna Umum',
        'Kepala Divisi',
    ];

    public function up(): void
    {
        LocalDatabaseMigrationGuard::assertApplicationDatabase();

        $column = DB::selectOne('SHOW FULL COLUMNS FROM `users` WHERE `Field` = ?', ['role']);
        if (
            $column === null
            || $column->Null !== 'NO'
            || $column->Collation !== 'utf8mb4_unicode_ci'
        ) {
            throw new RuntimeException('Schema users.role tidak cocok; penyederhanaan role dibatalkan.');
        }

        $columnType = strtolower((string) $column->Type);
        $currentValues = $this->enumValues((string) $column->Type);
        $targetValues = RolePermissions::ROLES;
        if ($currentValues === $targetValues) {
            if ($column->Default !== 'Pegawai') {
                DB::statement(
                    "ALTER TABLE `users` MODIFY `role` ENUM('Admin','Petugas Arsip','Pegawai') "
                    ."CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pegawai'"
                );
            }

            return;
        }

        $knownValues = array_values(array_unique([
            ...RolePermissions::LEGACY_ROLES,
            'Admin',
            'Pegawai',
        ]));
        if (
            ! str_starts_with($columnType, 'enum(')
            || $column->Default !== 'Customer'
            || array_diff($currentValues, $knownValues) !== []
            || array_diff($knownValues, $currentValues) !== []
        ) {
            throw new RuntimeException('Enum users.role tidak cocok dengan skema legacy yang dikenali; role tidak diubah.');
        }

        $ambiguousCounts = DB::table('users')
            ->select('role')
            ->selectRaw('COUNT(*) AS total')
            ->whereIn('role', self::AMBIGUOUS_LEGACY_ROLES)
            ->groupBy('role')
            ->orderBy('role')
            ->pluck('total', 'role')
            ->all();
        if ($ambiguousCounts !== []) {
            $counts = collect($ambiguousCounts)
                ->map(static fn ($total, $role): string => $role.': '.(int) $total)
                ->implode(', ');

            throw new RuntimeException(
                'Role legacy memerlukan audit akun sebelum pemetaan otomatis ('.$counts.'). '
                .'Tidak ada data yang diubah; tetapkan role target secara manual setelah verifikasi.'
            );
        }

        $adminCount = DB::table('users')->whereIn('role', ['Super Admin', 'Admin'])->count();
        if ($adminCount > 1) {
            throw new RuntimeException(
                'Ditemukan lebih dari satu akun Admin/Super Admin. Tidak ada data yang diubah; '
                .'audit akun Admin utama sebelum menjalankan migration.'
            );
        }

        DB::statement(
            "ALTER TABLE `users` MODIFY `role` ENUM("
            ."'Super Admin','Petugas Gudang','Admin Unit/Petugas Arsip','Verifikator',"
            ."'Pimpinan/Approver','Customer','Pengguna Umum','Admin','Pegawai','Kepala Divisi','Petugas Arsip'"
            .") CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pegawai'"
        );

        DB::table('users')->where('role', 'Super Admin')->update(['role' => 'Admin']);
        DB::table('users')
            ->whereIn('role', ['Petugas Gudang', 'Admin Unit/Petugas Arsip'])
            ->update(['role' => 'Petugas Arsip']);

        DB::statement(
            "ALTER TABLE `users` MODIFY `role` ENUM('Admin','Petugas Arsip','Pegawai') "
            ."CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pegawai'"
        );
    }

    public function down(): void
    {
        LocalDatabaseMigrationGuard::assertApplicationDatabase();
    }

    /**
     * @return list<string>
     */
    private function enumValues(string $columnType): array
    {
        if (! str_starts_with(strtolower($columnType), 'enum(') || ! str_ends_with($columnType, ')')) {
            return [];
        }

        return str_getcsv(substr($columnType, 5, -1), ',', "'", '\\');
    }
};
