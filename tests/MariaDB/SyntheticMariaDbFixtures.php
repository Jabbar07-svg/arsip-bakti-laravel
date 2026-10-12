<?php

namespace Tests\MariaDB;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SyntheticMariaDbFixtures
{
    public static function user(string $role, ?string $division): User
    {
        $suffix = bin2hex(random_bytes(6));
        $now = now();

        $id = DB::table('users')->insertGetId([
            'nama' => 'Fixture '.$suffix,
            'username' => 'fixture-'.$suffix,
            'password' => bcrypt('fixture-only-password'),
            'role' => $role,
            'division' => $division,
            'status' => 'Aktif',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return User::query()->findOrFail($id);
    }

    public static function archive(User $creator, ?string $division, string $classification): int
    {
        $suffix = bin2hex(random_bytes(6));
        $now = now();

        $categoryId = DB::table('kategori_arsip')->insertGetId([
            'nama_kategori' => 'Fixture '.$suffix,
            'keterangan' => null,
            'status' => 'Aktif',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $locationId = DB::table('lokasi')->insertGetId([
            'gudang' => 'Fixture '.$suffix,
            'rak' => 'R-'.$suffix,
            'lemari' => 'L-'.$suffix,
            'box' => 'B-'.$suffix,
            'capacity_boxes' => null,
            'keterangan' => null,
            'status' => 'Aktif',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::table('arsip')->insertGetId([
            'nomor_berkas' => 'FIX-'.$suffix,
            'nama_berkas' => 'Fixture '.$suffix,
            'kode_arsip' => 'FIX-'.$suffix,
            'kategori_id' => $categoryId,
            'tahun' => 2026,
            'bulan' => 1,
            'tanggal_masuk' => '2026-01-01',
            'lokasi_id' => $locationId,
            'classification' => $classification,
            'division' => $division,
            'physical_condition' => 'Baik',
            'status' => 'Tersimpan',
            'qr_code' => 'FIX-'.$suffix,
            'keterangan' => null,
            'created_by' => $creator->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
