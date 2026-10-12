<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ArchiveQr
{
    public static function next(int $userId, int $year, int $month): string
    {
        $usesMysqlLock = DB::connection()->getDriverName() === 'mysql';

        if ($usesMysqlLock) {
            $lock = DB::selectOne("SELECT GET_LOCK('arsip_bakti_qr', 10) AS acquired");
            if ((int) ($lock->acquired ?? 0) !== 1) {
                throw new RuntimeException('Nomor QR sedang digunakan. Coba simpan kembali.');
            }
        }

        try {
            $batch = DB::table('qr_batches')
                ->where('tahun', $year)
                ->where('bulan', $month)
                ->lockForUpdate()
                ->first();

            if ($batch === null) {
                $number = 1;
                DB::table('qr_batches')->insert([
                    'tahun' => $year,
                    'bulan' => $month,
                    'jumlah_qr' => $number,
                    'created_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $number = (int) $batch->jumlah_qr + 1;
                DB::table('qr_batches')
                    ->where('id', $batch->id)
                    ->update(['jumlah_qr' => $number, 'updated_at' => now()]);
            }

            return sprintf('ARSIP-%04d-%02d-%06d', $year, $month, $number);
        } finally {
            if ($usesMysqlLock) {
                DB::selectOne("SELECT RELEASE_LOCK('arsip_bakti_qr')");
            }
        }
    }
}
