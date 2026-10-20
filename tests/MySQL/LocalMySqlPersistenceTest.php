<?php

namespace Tests\MySQL;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

final class LocalMySqlPersistenceTest extends TestCase
{
    private bool $committed = false;

    private array $ids = [];

    private array $filePaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $connection = DB::connection();
        $config = $connection->getConfig();
        if (
            $connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'arsip_bakti_local'
            || ! in_array($config['host'] ?? null, ['127.0.0.1', 'localhost'], true)
            || (string) ($config['port'] ?? '') !== '3306'
        ) {
            throw new RuntimeException('This test may only write to local MySQL arsip_bakti_local on port 3306.');
        }

        $connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        try {
            if (! $this->committed && DB::connection()->transactionLevel() > 0) {
                DB::connection()->rollBack();
            }
            if ($this->committed) {
                $this->cleanFixtures();
            }
            Storage::disk('local')->delete($this->filePaths);
        } finally {
            parent::tearDown();
        }
    }

    public function test_application_records_persist_and_relations_round_trip_on_mysql(): void
    {
        $suffix = bin2hex(random_bytes(8));
        $now = now();

        $employee = User::query()->create([
            'nama' => 'MySQL Fixture Employee '.$suffix,
            'username' => 'mysql-fixture-'.$suffix,
            'password' => Hash::make('fixture-password-never-used'),
            'role' => 'Pegawai',
            'division' => 'Keuangan',
            'status' => 'Aktif',
        ]);
        $approver = User::query()->create([
            'nama' => 'MySQL Fixture Approver '.$suffix,
            'username' => 'mysql-approver-'.$suffix,
            'password' => Hash::make('fixture-password-never-used'),
            'role' => 'Kepala Divisi',
            'division' => 'Keuangan',
            'status' => 'Aktif',
        ]);
        $this->ids['users'] = [$employee->id, $approver->id];

        $categoryId = DB::table('kategori_arsip')->insertGetId([
            'nama_kategori' => 'MySQL Fixture '.$suffix,
            'keterangan' => 'temporary integration fixture',
            'status' => 'Aktif',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->ids['kategori_arsip'] = [$categoryId];

        $locationId = DB::table('lokasi')->insertGetId([
            'gudang' => 'MySQL Fixture '.$suffix,
            'rak' => 'R-'.$suffix,
            'lemari' => 'L-'.$suffix,
            'box' => 'B-'.$suffix,
            'status' => 'Aktif',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->ids['lokasi'] = [$locationId];

        $archiveIds = [];
        foreach ([1, 2] as $index) {
            $archiveIds[] = DB::table('arsip')->insertGetId([
                'nomor_berkas' => 'MYSQL-'.$suffix.'-'.$index,
                'nama_berkas' => 'MySQL Fixture Archive '.$index.' '.$suffix,
                'kode_arsip' => 'MYSQL-'.$suffix.'-'.$index,
                'kategori_id' => $categoryId,
                'tahun' => 2026,
                'bulan' => 10,
                'tanggal_masuk' => '2026-10-15',
                'lokasi_id' => $locationId,
                'classification' => 'Internal Divisi',
                'division' => 'Keuangan',
                'physical_condition' => 'Baik',
                'status' => 'Tersimpan',
                'qr_code' => 'MYSQL-'.$suffix.'-'.$index,
                'created_by' => $employee->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $this->ids['arsip'] = $archiveIds;

        $this->filePaths = [
            'mysql-integration/'.$suffix.'/attachment-one.txt',
            'mysql-integration/'.$suffix.'/attachment-two.txt',
        ];
        Storage::disk('local')->put($this->filePaths[0], 'first fixture attachment');
        Storage::disk('local')->put($this->filePaths[1], 'second fixture attachment');

        $fileIds = [];
        foreach ($this->filePaths as $index => $path) {
            $fileIds[] = DB::table('arsip_files')->insertGetId([
                'arsip_id' => $archiveIds[0],
                'nama_file' => 'attachment-'.($index + 1).'.txt',
                'file_path' => $path,
                'tipe_file' => 'text/plain',
                'uploaded_by' => $employee->id,
                'created_at' => $now,
            ]);
        }
        $this->ids['arsip_files'] = $fileIds;

        $loanRequestId = DB::table('loan_requests')->insertGetId([
            'user_id' => $employee->id,
            'arsip_id' => $archiveIds[0],
            'purpose' => 'MySQL persistence fixture',
            'requested_due_date' => '2026-10-20',
            'status' => 'Menunggu Persetujuan',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->ids['loan_requests'] = [$loanRequestId];

        $loanId = DB::table('peminjaman')->insertGetId([
            'arsip_id' => $archiveIds[0],
            'nama_peminjam' => $employee->nama,
            'unit' => $employee->division,
            'tanggal_pinjam' => '2026-10-15',
            'tanggal_rencana_kembali' => '2026-10-20',
            'keperluan' => 'MySQL persistence fixture',
            'status' => 'Dipinjam',
            'created_by' => $employee->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->ids['peminjaman'] = [$loanId];

        DB::table('loan_requests')->where('id', $loanRequestId)->update([
            'status' => 'Disetujui',
            'reviewed_by' => $approver->id,
            'reviewed_at' => $now,
            'loan_id' => $loanId,
            'updated_at' => $now,
        ]);
        DB::table('arsip')->where('id', $archiveIds[0])->update([
            'status' => 'Dipinjam',
            'physical_condition' => 'Baik',
            'updated_at' => $now,
        ]);

        $notificationId = DB::table('notifications')->insertGetId([
            'user_id' => $employee->id,
            'title' => 'MySQL fixture notification',
            'body' => 'Integration fixture',
            'type' => 'info',
            'url' => './',
            'is_sent' => 1,
            'read_at' => null,
            'created_at' => $now,
        ]);
        $this->ids['notifications'] = [$notificationId];

        $historyId = DB::table('riwayat_arsip')->insertGetId([
            'arsip_id' => $archiveIds[0],
            'user_id' => $employee->id,
            'aktivitas' => 'MySQL persistence fixture',
            'keterangan' => 'created and reviewed',
            'created_at' => $now,
        ]);
        $this->ids['riwayat_arsip'] = [$historyId];

        $qrBatchId = DB::table('qr_batches')->insertGetId([
            'tahun' => 2026,
            'bulan' => 10,
            'jumlah_qr' => 1,
            'created_by' => $employee->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->ids['qr_batches'] = [$qrBatchId];

        $retentionId = DB::table('jadwal_retensi')->insertGetId([
            'kategori_id' => $categoryId,
            'active_months' => 12,
            'inactive_months' => 24,
            'final_action' => 'Permanen',
            'alert_days' => 90,
            'updated_by' => $approver->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->ids['jadwal_retensi'] = [$retentionId];

        $dispositionId = DB::table('disposition_requests')->insertGetId([
            'arsip_id' => $archiveIds[1],
            'action' => 'Permanen',
            'reason' => 'MySQL persistence fixture',
            'status' => 'Disetujui',
            'requested_by' => $employee->id,
            'reviewed_by' => $approver->id,
            'reviewed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->ids['disposition_requests'] = [$dispositionId];

        $duplicateId = DB::table('duplicate_reviews')->insertGetId([
            'archive_id' => $archiveIds[0],
            'candidate_id' => $archiveIds[1],
            'match_reason' => 'MySQL integration fixture',
            'status' => 'Bukan Duplikat',
            'reviewed_by' => $approver->id,
            'reviewed_at' => $now,
            'created_at' => $now,
        ]);
        $this->ids['duplicate_reviews'] = [$duplicateId];

        $reminderId = DB::table('retention_reminders')->insertGetId([
            'arsip_id' => $archiveIds[0],
            'reminder_type' => 'Mendekati Retensi',
            'due_date' => '2027-10-15',
            'created_at' => $now,
        ]);
        $this->ids['retention_reminders'] = [$reminderId];

        DB::connection()->commit();
        $this->committed = true;

        DB::purge('mysql');
        $freshConnection = DB::connection('mysql');
        $this->assertSame('arsip_bakti_local', $freshConnection->getDatabaseName());

        $this->assertSame('Pegawai', User::query()->findOrFail($employee->id)->role);
        $archive = DB::table('arsip as a')
            ->join('kategori_arsip as c', 'c.id', '=', 'a.kategori_id')
            ->join('lokasi as l', 'l.id', '=', 'a.lokasi_id')
            ->where('a.id', $archiveIds[0])
            ->select(['a.status', 'a.division', 'c.nama_kategori', 'l.gudang'])
            ->first();
        $this->assertSame('Dipinjam', $archive->status);
        $this->assertSame('Keuangan', $archive->division);
        $this->assertSame('Disetujui', DB::table('loan_requests')->where('id', $loanRequestId)->value('status'));
        $this->assertSame($loanId, (int) DB::table('loan_requests')->where('id', $loanRequestId)->value('loan_id'));
        $this->assertSame(2, DB::table('arsip_files')->where('arsip_id', $archiveIds[0])->count());
        $this->assertSame('MySQL fixture notification', DB::table('notifications')->where('id', $notificationId)->value('title'));
        $this->assertSame('Permanen', DB::table('jadwal_retensi')->where('id', $retentionId)->value('final_action'));
        $this->assertSame('Disetujui', DB::table('disposition_requests')->where('id', $dispositionId)->value('status'));
        $this->assertSame('Bukan Duplikat', DB::table('duplicate_reviews')->where('id', $duplicateId)->value('status'));
        $this->assertSame(1, DB::table('riwayat_arsip')->where('id', $historyId)->count());
        $this->assertSame(1, DB::table('qr_batches')->where('id', $qrBatchId)->value('jumlah_qr'));
        $this->assertSame('first fixture attachment', Storage::disk('local')->get($this->filePaths[0]));
        $this->assertSame('second fixture attachment', Storage::disk('local')->get($this->filePaths[1]));
    }

    private function cleanFixtures(): void
    {
        DB::purge('mysql');
        $connection = DB::connection('mysql');
        if ($connection->getDatabaseName() !== 'arsip_bakti_local') {
            throw new RuntimeException('Refusing to clean fixtures outside arsip_bakti_local.');
        }

        $connection->transaction(function (): void {
            foreach ([
                'loan_requests',
                'notifications',
                'disposition_requests',
                'duplicate_reviews',
                'retention_reminders',
                'arsip_files',
                'riwayat_arsip',
                'peminjaman',
                'qr_batches',
                'jadwal_retensi',
                'arsip',
                'lokasi',
                'kategori_arsip',
                'users',
            ] as $table) {
                if (isset($this->ids[$table])) {
                    DB::table($table)->whereIn('id', $this->ids[$table])->delete();
                }
            }
        });
    }
}
