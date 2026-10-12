<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DuplicateCandidateDiscovery
{
    private const BATCH_SIZE = 500;

    public function discover(User $user): int
    {
        $normalA = "UPPER(REPLACE(REPLACE(REPLACE(TRIM(a.nomor_berkas), ' ', ''), '/', ''), '-', ''))";
        $normalB = "UPPER(REPLACE(REPLACE(REPLACE(TRIM(b.nomor_berkas), ' ', ''), '/', ''), '-', ''))";

        try {
            return DB::transaction(function () use ($user, $normalA, $normalB): int {
                $candidates = DB::table('arsip as a')
                    ->join('arsip as b', function ($join): void {
                        $join->on('a.id', '<', 'b.id');
                    })
                    ->where('a.status', '<>', 'Nonaktif')
                    ->where('b.status', '<>', 'Nonaktif')
                    ->where(function ($matching) use ($normalA, $normalB): void {
                        $matching->where(function ($number) use ($normalA, $normalB): void {
                            $number->whereRaw("TRIM(a.nomor_berkas) <> ''")
                                ->whereRaw($normalA.' = '.$normalB);
                        })->orWhere(function ($name): void {
                            $name->whereColumn('a.kategori_id', 'b.kategori_id')
                                ->whereColumn('a.tahun', 'b.tahun')
                                ->whereRaw('UPPER(TRIM(a.nama_berkas)) = UPPER(TRIM(b.nama_berkas))');
                        });
                    })
                    ->whereNotExists(function ($existing): void {
                        $existing->selectRaw('1')
                            ->from('duplicate_reviews as existing')
                            ->whereColumn('existing.archive_id', 'a.id')
                            ->whereColumn('existing.candidate_id', 'b.id');
                    });
                ArchiveAccess::scope($candidates, $user, 'a');
                ArchiveAccess::scope($candidates, $user, 'b');

                $rows = $candidates->selectRaw(
                    'a.id as archive_id, b.id as candidate_id, CASE WHEN '.$normalA.' = '.$normalB.' THEN ? ELSE ? END as match_reason',
                    ['Nomor berkas sama', 'Nama, kategori, dan tahun sama']
                )
                    ->orderBy('a.id')
                    ->orderBy('b.id')
                    ->limit(self::BATCH_SIZE)
                    ->get();

                $inserted = 0;
                foreach ($rows as $row) {
                    $inserted += DB::table('duplicate_reviews')->insertOrIgnore([
                        'archive_id' => $row->archive_id,
                        'candidate_id' => $row->candidate_id,
                        'match_reason' => $row->match_reason,
                        'status' => 'Menunggu Verifikasi',
                        'reviewer_note' => null,
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                        'created_at' => now(),
                    ]);
                }

                return $inserted;
            });
        } catch (QueryException $exception) {
            Log::error('Duplicate candidate discovery batch failed.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }
}
