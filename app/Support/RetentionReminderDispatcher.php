<?php

namespace App\Support;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

class RetentionReminderDispatcher
{
    public function dispatch(): int
    {
        $rows = DB::table('arsip as a')
            ->join('jadwal_retensi as j', 'j.kategori_id', '=', 'a.kategori_id')
            ->where('a.status', '<>', 'Nonaktif')
            ->select(['a.id', 'a.nama_berkas', 'a.tanggal_masuk', 'j.active_months', 'j.inactive_months', 'j.alert_days'])
            ->get();
        $adminIds = DB::table('users')
            ->where('role', 'Super Admin')
            ->where('status', 'Aktif')
            ->pluck('id');
        $created = 0;

        foreach ($rows as $row) {
            $retentionUntil = (new DateTimeImmutable($row->tanggal_masuk))
                ->modify('+'.(int) $row->active_months.' months')
                ->modify('+'.(int) $row->inactive_months.' months');
            $daysLeft = (int) (new DateTimeImmutable('today'))->diff($retentionUntil)->format('%r%a');
            if ($daysLeft > (int) $row->alert_days) {
                continue;
            }

            $type = $daysLeft < 0 ? 'Retensi Berakhir' : 'Mendekati Retensi';
            $dueDate = $retentionUntil->format('Y-m-d');
            $created += DB::transaction(function () use ($adminIds, $dueDate, $row, $type): int {
                $inserted = DB::table('retention_reminders')->insertOrIgnore([
                    'arsip_id' => $row->id,
                    'reminder_type' => $type,
                    'due_date' => $dueDate,
                    'created_at' => now(),
                ]);
                if ($inserted === 0) {
                    return 0;
                }

                foreach ($adminIds as $adminId) {
                    DB::table('notifications')->insert([
                        'user_id' => $adminId,
                        'title' => $type,
                        'body' => $row->nama_berkas.' · jatuh tempo '.$dueDate,
                        'type' => 'warning',
                        'url' => route('retention.index'),
                        'data' => json_encode(['arsip_id' => $row->id, 'due_date' => $dueDate], JSON_THROW_ON_ERROR),
                        'is_sent' => 1,
                        'created_at' => now(),
                    ]);
                }

                return 1;
            });
        }

        return $created;
    }
}
