<?php

namespace App\Http\Controllers;

use App\Support\ArchiveAccess;
use App\Support\RetentionReminderDispatcher;
use App\Support\RolePermissions;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RetentionController extends Controller
{
    private const FINAL_ACTIONS = ['Musnahkan', 'Pindahkan Arsip Inaktif', 'Permanen', 'Serah ke ANRI'];

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(RolePermissions::allows($user, RolePermissions::RETENTION_VIEW), 403);
        if (RolePermissions::allows($user, RolePermissions::RETENTION_SCHEDULE)) {
            app(RetentionReminderDispatcher::class)->dispatch();
        }

        $schedules = DB::table('jadwal_retensi as j')
            ->join('kategori_arsip as k', 'k.id', '=', 'j.kategori_id')
            ->join('users as u', 'u.id', '=', 'j.updated_by')
            ->select(['j.*', 'k.nama_kategori', 'u.nama as editor'])
            ->orderBy('k.nama_kategori')
            ->get();
        $scheduleByCategory = $schedules->keyBy('kategori_id');

        $archivesQuery = ArchiveAccess::scope(DB::table('arsip as a'), $user)
            ->join('kategori_arsip as k', 'k.id', '=', 'a.kategori_id')
            ->where('a.status', '<>', 'Nonaktif');
        $archives = $archivesQuery->select([
            'a.id', 'a.nama_berkas', 'a.nomor_berkas', 'a.tanggal_masuk',
            'a.status', 'a.kategori_id', 'k.nama_kategori',
        ])->orderBy('a.tanggal_masuk')->get();
        $dispositionStatusByArchive = DB::table('disposition_requests')
            ->whereIn('status', ['Menunggu Persetujuan', 'Disetujui', 'Dilaksanakan'])
            ->orderBy('id')
            ->pluck('status', 'arsip_id');
        $retentionRows = $archives->map(function (object $archive) use ($scheduleByCategory, $dispositionStatusByArchive): ?array {
            $schedule = $scheduleByCategory->get($archive->kategori_id);
            if ($schedule === null) {
                return null;
            }

            $dates = $this->retentionDates($archive->tanggal_masuk, (int) $schedule->active_months, (int) $schedule->inactive_months);

            return [
                'archive' => $archive,
                'schedule' => $schedule,
                ...$dates,
                'final_action' => $schedule->final_action,
                'disposition_status' => $dispositionStatusByArchive->get($archive->id),
            ];
        })->filter()->values();

        $dispositions = DB::table('disposition_requests as d')
            ->join('arsip as a', 'a.id', '=', 'd.arsip_id')
            ->join('users as requester', 'requester.id', '=', 'd.requested_by')
            ->leftJoin('users as reviewer', 'reviewer.id', '=', 'd.reviewed_by');
        ArchiveAccess::scope($dispositions, $user, 'a');
        $dispositions = $dispositions
            ->select([
                'd.*', 'a.nama_berkas', 'a.nomor_berkas', 'a.classification', 'a.division',
                'requester.nama as requester_name', 'reviewer.nama as reviewer_name',
            ])
            ->orderByDesc('d.created_at')
            ->limit(200)
            ->get();

        return view('retention.index', [
            'schedules' => $schedules,
            'categories' => DB::table('kategori_arsip')->orderBy('nama_kategori')->get(),
            'retentionRows' => $retentionRows,
            'dispositions' => $dispositions,
            'isAdmin' => RolePermissions::allows($user, RolePermissions::RETENTION_SCHEDULE),
            'isApprover' => RolePermissions::allows($user, RolePermissions::RETENTION_REVIEW),
            'canProposeRetention' => RolePermissions::allows($user, RolePermissions::RETENTION_PROPOSE),
            'finalActions' => self::FINAL_ACTIONS,
        ]);
    }

    public function saveSchedule(Request $request): RedirectResponse
    {
        abort_unless(RolePermissions::allows($request->user(), RolePermissions::RETENTION_SCHEDULE), 403);
        $data = $request->validate([
            'kategori_id' => ['required', 'integer', 'exists:kategori_arsip,id'],
            'active_months' => ['required', 'integer', 'min:0', 'max:65535'],
            'inactive_months' => ['required', 'integer', 'min:0', 'max:65535'],
            'alert_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'final_action' => ['required', Rule::in(self::FINAL_ACTIONS)],
        ]);
        abort_if((int) $data['active_months'] + (int) $data['inactive_months'] < 1, 422, 'Total masa retensi harus minimal satu bulan.');

        DB::transaction(function () use ($request, $data): void {
            DB::table('jadwal_retensi')->updateOrInsert(
                ['kategori_id' => $data['kategori_id']],
                [
                    'active_months' => $data['active_months'],
                    'inactive_months' => $data['inactive_months'],
                    'final_action' => $data['final_action'],
                    'alert_days' => $data['alert_days'],
                    'updated_by' => $request->user()->id,
                    'updated_at' => now(),
                ]
            );
            $this->addAudit(null, $request->user()->id, 'Mengubah jadwal retensi', 'Kategori #'.$data['kategori_id']);
        });

        return redirect()->route('retention.index')->with('success', 'Jadwal retensi berhasil disimpan.');
    }

    public function requestDisposition(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(RolePermissions::allows($user, RolePermissions::RETENTION_PROPOSE), 403);
        $data = $request->validate([
            'arsip_id' => ['required', 'integer', 'exists:arsip,id'],
            'reason' => ['required', 'string', 'max:5000'],
        ]);
        $id = DB::transaction(function () use ($user, $data): int {
            $archive = DB::table('arsip as a')
                ->join('jadwal_retensi as j', 'j.kategori_id', '=', 'a.kategori_id')
                ->where('a.id', $data['arsip_id'])
                ->lockForUpdate()
                ->first(['a.*', 'j.active_months', 'j.inactive_months', 'j.final_action']);
            abort_if($archive === null || $archive->status === 'Nonaktif', 422, 'Pilih arsip aktif dengan jadwal retensi.');
            abort_unless(ArchiveAccess::canView($user, $archive), 403);
            abort_unless($archive->status === 'Tersimpan', 422, 'Hanya arsip tersimpan yang dapat diusulkan untuk penyusutan.');

            $dates = $this->retentionDates($archive->tanggal_masuk, (int) $archive->active_months, (int) $archive->inactive_months);
            abort_unless($dates['phase'] === 'Jatuh Tempo', 422, 'Masa retensi arsip ini belum berakhir.');
            $pending = DB::table('disposition_requests')
                ->where('arsip_id', $archive->id)
                ->whereIn('status', ['Menunggu Persetujuan', 'Disetujui', 'Dilaksanakan'])
                ->exists();
            abort_if($pending, 409, 'Arsip ini sudah memiliki usulan penyusutan yang aktif atau telah dilaksanakan.');

            $id = DB::table('disposition_requests')->insertGetId([
                'arsip_id' => $archive->id,
                'action' => $archive->final_action,
                'reason' => trim($data['reason']),
                'status' => 'Menunggu Persetujuan',
                'requested_by' => $user->id,
                'reviewed_by' => null,
                'reviewer_note' => null,
                'reviewed_at' => null,
                'completed_at' => null,
                'minutes_number' => null,
                'minutes_note' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->addAudit((int) $archive->id, $user->id, 'Mengusulkan penyusutan arsip', 'Usulan #'.$id.': '.$archive->final_action);
            $this->notifyAdmins('Usulan penyusutan arsip', $archive->nama_berkas.' menunggu persetujuan.', ['disposition_id' => $id]);

            return $id;
        });

        return redirect()->route('retention.index')->with('success', 'Usulan penyusutan dikirim kepada Admin.');
    }

    public function reviewDisposition(Request $request, int $disposition): RedirectResponse
    {
        $reviewer = $request->user();
        abort_unless(RolePermissions::allows($reviewer, RolePermissions::RETENTION_REVIEW), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['Disetujui', 'Ditolak'])],
            'reviewer_note' => ['nullable', 'string', 'max:5000', Rule::requiredIf($request->input('status') === 'Ditolak')],
        ]);
        $note = trim((string) ($data['reviewer_note'] ?? ''));
        abort_if($data['status'] === 'Ditolak' && $note === '', 422, 'Catatan wajib diisi saat usulan ditolak.');

        DB::transaction(function () use ($reviewer, $disposition, $data, $note): void {
            $record = DB::table('disposition_requests')->where('id', $disposition)->lockForUpdate()->first();
            abort_if($record === null, 404);
            abort_unless($record->status === 'Menunggu Persetujuan', 409, 'Usulan tidak lagi menunggu persetujuan.');
            abort_if((int) $record->requested_by === (int) $reviewer->id, 403, 'Pengusul tidak dapat menyetujui usulannya sendiri.');
            $archive = DB::table('arsip')->where('id', $record->arsip_id)->first();
            abort_if($archive === null, 404);
            abort_unless(
                $archive->classification !== 'Dokumen Rahasia' || RolePermissions::isAdmin($reviewer),
                403,
                'Hanya Admin yang dapat memutuskan retensi arsip Dokumen Rahasia.'
            );
            abort_unless(ArchiveAccess::canView($reviewer, $archive), 403);
            DB::table('disposition_requests')->where('id', $disposition)->update([
                'status' => $data['status'],
                'reviewer_note' => $note !== '' ? $note : null,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);
            $this->addAudit((int) $record->arsip_id, $reviewer->id, 'Memeriksa usulan penyusutan', 'Usulan #'.$disposition.': '.$data['status']);
            DB::table('notifications')->insert([
                'user_id' => $record->requested_by,
                'title' => 'Usulan penyusutan '.mb_strtolower($data['status']),
                'body' => 'Usulan #'.$disposition.($note !== '' ? ' · '.$note : ''),
                'type' => $data['status'] === 'Disetujui' ? 'success' : 'warning',
                'url' => route('retention.index'),
                'data' => json_encode(['disposition_id' => $disposition], JSON_THROW_ON_ERROR),
                'is_sent' => 1,
                'created_at' => now(),
            ]);
        });

        return redirect()->route('retention.index')->with('success', 'Keputusan usulan berhasil disimpan.');
    }

    public function completeDisposition(Request $request, int $disposition): RedirectResponse
    {
        $user = $request->user();
        abort_unless(RolePermissions::allows($user, RolePermissions::RETENTION_REVIEW), 403);
        $pendingRecord = DB::table('disposition_requests')->where('id', $disposition)->first();
        abort_if($pendingRecord === null, 404);
        $requiresDestination = $pendingRecord->action !== 'Musnahkan';
        $requiresRecipient = $pendingRecord->action === 'Serah ke ANRI';
        $data = $request->validate([
            'minutes_number' => ['required', 'string', 'max:100'],
            'minutes_note' => ['required', 'string', 'max:10000'],
            'execution_date' => ['required', 'date', 'before_or_equal:today'],
            'destination' => ['nullable', 'string', 'max:255', Rule::requiredIf($requiresDestination)],
            'handover_recipient' => ['nullable', 'string', 'max:255', Rule::requiredIf($requiresRecipient)],
            'evidence' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $evidencePath = $request->file('evidence')->store('retention-evidence', 'local');
        if ($evidencePath === false) {
            throw new RuntimeException('Bukti pelaksanaan gagal disimpan. Retensi belum diubah.');
        }

        try {
            DB::transaction(function () use ($user, $disposition, $data, $evidencePath): void {
                $record = DB::table('disposition_requests')->where('id', $disposition)->lockForUpdate()->first();
                abort_if($record === null, 404);
                abort_unless($record->status === 'Disetujui', 409, 'Hanya usulan yang telah disetujui yang dapat dilaksanakan.');
                abort_if((int) $record->requested_by === (int) $user->id, 403, 'Pengusul tidak dapat melaksanakan usulannya sendiri.');
                abort_unless(in_array($record->action, self::FINAL_ACTIONS, true), 409, 'Jenis tindakan retensi tidak dikenali.');
                $archive = DB::table('arsip')->where('id', $record->arsip_id)->lockForUpdate()->first();
                abort_if($archive === null, 404);
                abort_unless($archive->status === 'Tersimpan', 409, 'Arsip harus berstatus tersimpan dan tidak sedang dipinjam atau diproses.');
                abort_unless(
                    $archive->classification !== 'Dokumen Rahasia' || RolePermissions::isAdmin($user),
                    403,
                    'Hanya Admin yang dapat melaksanakan retensi arsip Dokumen Rahasia.'
                );
                abort_unless(ArchiveAccess::canView($user, $archive), 403);

                DB::table('disposition_requests')->where('id', $disposition)->update([
                    'status' => 'Dilaksanakan',
                    'completed_at' => now(),
                    'minutes_number' => trim($data['minutes_number']),
                    'minutes_note' => trim($data['minutes_note']),
                    'executed_by' => $user->id,
                    'execution_date' => $data['execution_date'],
                    'destination' => trim((string) ($data['destination'] ?? '')) ?: null,
                    'handover_recipient' => trim((string) ($data['handover_recipient'] ?? '')) ?: null,
                    'evidence_path' => $evidencePath,
                    'updated_at' => now(),
                ]);
                if (in_array($record->action, ['Musnahkan', 'Pindahkan Arsip Inaktif', 'Serah ke ANRI'], true)) {
                    DB::table('arsip')->where('id', $archive->id)->update(['status' => 'Nonaktif', 'updated_at' => now()]);
                }
                $this->addAudit(
                    (int) $archive->id,
                    $user->id,
                    'Melaksanakan penyusutan arsip',
                    'Berita acara '.trim($data['minutes_number']).'; tindakan: '.$record->action.'; tanggal: '.$data['execution_date']
                    .(($data['destination'] ?? '') !== '' ? '; tujuan: '.trim($data['destination']) : '')
                    .(($data['handover_recipient'] ?? '') !== '' ? '; penerima: '.trim($data['handover_recipient']) : '')
                );
                DB::table('notifications')->insert([
                    'user_id' => $record->requested_by,
                    'title' => 'Pelaksanaan retensi selesai',
                    'body' => 'Usulan #'.$disposition.' telah dilaksanakan. Berita acara '.trim($data['minutes_number']).'.',
                    'type' => 'success',
                    'url' => route('retention.minutes', $disposition),
                    'data' => json_encode(['disposition_id' => $disposition], JSON_THROW_ON_ERROR),
                    'is_sent' => 1,
                    'created_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($evidencePath);

            throw $exception;
        }

        return redirect()->route('retention.minutes', $disposition)->with('success', 'Pelaksanaan dan berita acara berhasil dicatat.');
    }

    public function evidence(Request $request, int $disposition): StreamedResponse
    {
        abort_unless(RolePermissions::allows($request->user(), RolePermissions::RETENTION_VIEW), 403);
        $record = DB::table('disposition_requests as d')
            ->join('arsip as a', 'a.id', '=', 'd.arsip_id')
            ->where('d.id', $disposition)
            ->where('d.status', 'Dilaksanakan')
            ->select(['d.evidence_path', 'a.classification', 'a.division'])
            ->first();
        abort_if($record === null || $record->evidence_path === null, 404);
        abort_unless(ArchiveAccess::canView($request->user(), $record), 403);
        abort_unless(Storage::disk('local')->exists($record->evidence_path), 404);

        return Storage::disk('local')->download($record->evidence_path, 'bukti-retensi-'.$disposition);
    }

    public function minutes(Request $request, int $disposition): View
    {
        abort_unless(RolePermissions::allows($request->user(), RolePermissions::RETENTION_VIEW), 403);
        $record = DB::table('disposition_requests as d')
            ->join('arsip as a', 'a.id', '=', 'd.arsip_id')
            ->join('users as requester', 'requester.id', '=', 'd.requested_by')
            ->leftJoin('users as reviewer', 'reviewer.id', '=', 'd.reviewed_by')
            ->leftJoin('users as executor', 'executor.id', '=', 'd.executed_by')
            ->where('d.id', $disposition)
            ->where('d.status', 'Dilaksanakan')
            ->select(['d.*', 'a.nama_berkas', 'a.nomor_berkas', 'a.classification', 'a.division', 'requester.nama as requester_name', 'reviewer.nama as reviewer_name', 'executor.nama as executor_name'])
            ->first();
        abort_if($record === null, 404);
        abort_unless(ArchiveAccess::canView($request->user(), $record), 403);

        return view('retention.minutes', ['record' => $record]);
    }

    private function retentionDates(string $date, int $activeMonths, int $inactiveMonths): array
    {
        $start = new DateTimeImmutable($date);
        $activeUntil = $start->modify('+'.$activeMonths.' months');
        $retentionUntil = $activeUntil->modify('+'.$inactiveMonths.' months');
        $today = new DateTimeImmutable('today');

        return [
            'active_until' => $activeUntil,
            'retention_until' => $retentionUntil,
            'phase' => $today < $activeUntil ? 'Aktif' : ($today < $retentionUntil ? 'Inaktif' : 'Jatuh Tempo'),
        ];
    }

    private function addAudit(?int $archiveId, int $userId, string $activity, string $detail): void
    {
        DB::table('riwayat_arsip')->insert([
            'arsip_id' => $archiveId,
            'user_id' => $userId,
            'aktivitas' => $activity,
            'keterangan' => $detail,
            'created_at' => now(),
        ]);
    }

    private function notifyAdmins(string $title, string $body, array $data): void
    {
        $adminIds = DB::table('users')->where('role', 'Admin')->where('status', 'Aktif')->pluck('id');
        foreach ($adminIds as $adminId) {
            DB::table('notifications')->insert([
                'user_id' => $adminId,
                'title' => $title,
                'body' => $body,
                'type' => 'warning',
                'url' => route('retention.index'),
                'data' => json_encode($data, JSON_THROW_ON_ERROR),
                'is_sent' => 1,
                'created_at' => now(),
            ]);
        }
    }
}
