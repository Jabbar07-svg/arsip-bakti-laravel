<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\RolePermissions;
use App\Support\ArchiveQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerReviewController extends Controller
{
    private const REVIEW_STATUSES = ['Disetujui', 'Ditolak', 'Perlu Perbaikan'];

    private const CLASSIFICATIONS = ['Publik', 'Internal', 'Internal Divisi', 'Dokumen Rahasia'];

    public function index(Request $request): View
    {
        $this->authorizeReviewer($request->user());

        return view('customer.reviews', [
            'submissions' => DB::table('customer_submissions as s')
                ->join('users as u', 'u.id', '=', 's.user_id')
                ->leftJoin('kategori_arsip as k', 'k.id', '=', 's.kategori_id')
                ->leftJoin('arsip as a', 'a.id', '=', 's.arsip_id')
                ->select([
                    's.*', 'u.nama as customer_name', 'u.username',
                    'k.nama_kategori', 'a.classification as archive_classification',
                ])
                ->orderByRaw("CASE WHEN s.status = 'Menunggu Verifikasi' THEN 0 ELSE 1 END")
                ->orderByDesc('s.created_at')
                ->limit(100)
                ->get(),
            'accessRequests' => DB::table('customer_requests as r')
                ->join('users as u', 'u.id', '=', 'r.user_id')
                ->select(['r.*', 'u.nama as customer_name', 'u.username'])
                ->orderByRaw("CASE WHEN r.status = 'Menunggu Verifikasi' THEN 0 ELSE 1 END")
                ->orderByDesc('r.created_at')
                ->limit(100)
                ->get(),
            'locations' => DB::table('lokasi')->where('status', 'Aktif')
                ->orderBy('gudang')->orderBy('rak')->orderBy('lemari')->orderBy('box')->get(),
            'divisions' => DB::table('users')->whereNotNull('division')->where('division', '<>', '')
                ->distinct()->orderBy('division')->pluck('division'),
            'reviewStatuses' => self::REVIEW_STATUSES,
            'classifications' => self::CLASSIFICATIONS,
        ]);
    }

    public function reviewSubmission(Request $request, int $submission): RedirectResponse
    {
        $reviewer = $request->user();
        $this->authorizeReviewer($reviewer);
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::REVIEW_STATUSES)],
            'reviewer_note' => [
                'nullable', 'string', 'max:5000',
                Rule::requiredIf(in_array($request->input('status'), ['Ditolak', 'Perlu Perbaikan'], true)),
            ],
            'lokasi_id' => ['required_if:status,Disetujui', 'nullable', 'integer', Rule::exists('lokasi', 'id')->where('status', 'Aktif')],
            'classification' => ['required_if:status,Disetujui', 'nullable', Rule::in(self::CLASSIFICATIONS)],
            'division' => ['nullable', 'string', 'max:150', 'required_if:classification,Internal Divisi'],
        ]);
        $note = trim((string) ($validated['reviewer_note'] ?? ''));
        if (in_array($validated['status'], ['Ditolak', 'Perlu Perbaikan'], true) && $note === '') {
            throw ValidationException::withMessages(['reviewer_note' => 'Catatan wajib diisi untuk penolakan atau permintaan perbaikan.']);
        }
        $archiveId = DB::transaction(function () use ($reviewer, $submission, $validated, $note): ?int {
            $record = DB::table('customer_submissions')
                ->where('id', $submission)
                ->lockForUpdate()
                ->first();
            abort_if($record === null, 404);
            abort_if((int) $record->user_id === (int) $reviewer->id, 403, 'Pengaju tidak dapat meninjau pengajuannya sendiri.');
            abort_unless($record->status === 'Menunggu Verifikasi', 409, 'Pengajuan sudah diproses. Muat ulang daftar.');
            abort_if($record->arsip_id !== null && $validated['status'] !== 'Disetujui', 409, 'Dokumen yang sudah menjadi arsip resmi tidak dapat ditolak atau direvisi.');

            $archiveId = $record->arsip_id === null ? null : (int) $record->arsip_id;
            if ($validated['status'] === 'Disetujui' && $archiveId === null) {
                abort_unless(
                    $validated['classification'] !== 'Dokumen Rahasia' || RolePermissions::isAdmin($reviewer),
                    403,
                    'Hanya Admin yang dapat menyetujui arsip Dokumen Rahasia.'
                );
                $location = DB::table('lokasi')->where('id', $validated['lokasi_id'])->where('status', 'Aktif')->first();
                abort_if($location === null, 422, 'Pilih lokasi aktif sebelum menyetujui dokumen.');
                $classification = $validated['classification'];
                $division = trim((string) ($validated['division'] ?? ''));
                if ($classification === 'Internal Divisi' && $division === '') {
                    throw ValidationException::withMessages(['division' => 'Pilih divisi untuk arsip Internal Divisi.']);
                }
                if ($classification !== 'Internal Divisi') {
                    $division = '';
                }
                $date = \Illuminate\Support\Carbon::parse($record->tanggal_dokumen);
                $year = (int) $date->format('Y');
                $month = (int) $date->format('n');
                $qrCode = ArchiveQr::next($reviewer->id, $year, $month);
                $archiveId = (int) DB::table('arsip')->insertGetId([
                    'nomor_berkas' => $record->nomor_dokumen,
                    'nama_berkas' => $record->nama_dokumen,
                    'kode_arsip' => 'CUST-'.strtoupper(substr(hash('sha256', (string) $record->id), 0, 10)),
                    'kategori_id' => $record->kategori_id,
                    'tahun' => $year,
                    'bulan' => $month,
                    'tanggal_masuk' => $date->toDateString(),
                    'lokasi_id' => $location->id,
                    'classification' => $classification,
                    'division' => $division !== '' ? $division : null,
                    'physical_condition' => 'Baik',
                    'status' => 'Tersimpan',
                    'qr_code' => $qrCode,
                    'keterangan' => $record->keterangan,
                    'created_by' => $record->user_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('arsip_files')->insert([
                    'arsip_id' => $archiveId,
                    'nama_file' => $record->nama_file,
                    'file_path' => $record->file_path,
                    'tipe_file' => $record->mime_type,
                    'uploaded_by' => $record->user_id,
                    'created_at' => now(),
                ]);
                $this->addAudit($archiveId, $reviewer->id, 'Menyetujui pengajuan customer', 'Dokumen: '.$record->nama_dokumen);
            } elseif ($validated['status'] === 'Disetujui' && $archiveId !== null) {
                $existingArchive = DB::table('arsip')->where('id', $archiveId)->first();
                abort_if($existingArchive === null, 404);
                abort_unless(
                    $existingArchive->classification !== 'Dokumen Rahasia' || RolePermissions::isAdmin($reviewer),
                    403,
                    'Hanya Admin yang dapat menyetujui arsip Dokumen Rahasia.'
                );
            }

            DB::table('customer_submissions')->where('id', $submission)->update([
                'status' => $validated['status'],
                'reviewer_note' => $note !== '' ? $note : null,
                'reviewed_by' => $reviewer->id,
                'arsip_id' => $archiveId,
                'updated_at' => now(),
            ]);
            $isPublic = $validated['status'] === 'Disetujui'
                && $archiveId !== null
                && DB::table('arsip')->where('id', $archiveId)->value('classification') === 'Publik';
            $this->notifyCustomer(
                (int) $record->user_id,
                'Status dokumen: '.$validated['status'],
                $record->nama_dokumen.($note !== '' ? ' · '.$note : ''),
                $isPublic ? route('archives.show', $archiveId) : route('customer.portal'),
                ['submission_id' => $submission, 'archive_id' => $archiveId]
            );

            return $archiveId;
        });

        return redirect()->route('customer-reviews.index')->with(
            'success',
            $validated['status'] === 'Disetujui'
                ? 'Dokumen disetujui dan dicatat sebagai arsip.'
                : 'Status pengajuan dokumen berhasil diperbarui.'
        );
    }

    public function reviewAccessRequest(Request $request, int $accessRequest): RedirectResponse
    {
        $reviewer = $request->user();
        $this->authorizeReviewer($reviewer);
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::REVIEW_STATUSES)],
            'reviewer_note' => [
                'nullable', 'string', 'max:5000',
                Rule::requiredIf(in_array($request->input('status'), ['Ditolak', 'Perlu Perbaikan'], true)),
            ],
        ]);
        $note = trim((string) ($validated['reviewer_note'] ?? ''));
        if (in_array($validated['status'], ['Ditolak', 'Perlu Perbaikan'], true) && $note === '') {
            throw ValidationException::withMessages(['reviewer_note' => 'Catatan wajib diisi untuk penolakan atau permintaan perbaikan.']);
        }

        DB::transaction(function () use ($reviewer, $accessRequest, $validated, $note): void {
            $record = DB::table('customer_requests')
                ->where('id', $accessRequest)
                ->lockForUpdate()
                ->first();
            abort_if($record === null, 404);
            abort_if((int) $record->user_id === (int) $reviewer->id, 403, 'Pemohon tidak dapat meninjau permintaannya sendiri.');
            abort_unless($record->status === 'Menunggu Verifikasi', 409, 'Permintaan sudah diproses. Muat ulang daftar.');
            $archive = null;
            if ($validated['status'] === 'Disetujui') {
                $archive = DB::table('arsip')
                    ->where(function ($query) use ($record): void {
                        $query->where('qr_code', $record->requested_code)
                            ->orWhere('nomor_berkas', $record->requested_code);
                    })
                    ->where('classification', 'Publik')
                    ->first();
                abort_if($archive === null, 422, 'Permintaan hanya dapat disetujui untuk arsip berklasifikasi Publik.');
            }
            DB::table('customer_requests')->where('id', $accessRequest)->update([
                'status' => $validated['status'],
                'reviewer_note' => $note !== '' ? $note : null,
                'reviewed_by' => $reviewer->id,
                'updated_at' => now(),
            ]);
            $body = $archive !== null
                ? 'Arsip Publik '.$archive->nama_berkas.' tersedia untuk Customer.'
                : ($note !== '' ? $note : 'Permintaan akses Anda telah diperiksa.');
            $this->notifyCustomer(
                (int) $record->user_id,
                'Permintaan akses: '.$validated['status'],
                $body,
                $archive !== null ? route('archives.show', $archive->id) : route('customer.portal'),
                ['customer_request_id' => $accessRequest, 'archive_id' => $archive->id ?? null]
            );
        });

        return redirect()->route('customer-reviews.index')->with('success', 'Permintaan akses berhasil diproses.');
    }

    private function authorizeReviewer(User $user): void
    {
        abort_unless(RolePermissions::allows($user, RolePermissions::CUSTOMER_REVIEW), 403);
    }

    private function notifyCustomer(int $userId, string $title, string $body, string $url, array $data): void
    {
        DB::table('notifications')->insert([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'type' => 'info',
            'url' => $url,
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
            'is_sent' => 1,
            'created_at' => now(),
        ]);
    }

    private function addAudit(int $archiveId, int $userId, string $activity, string $detail): void
    {
        DB::table('riwayat_arsip')->insert([
            'arsip_id' => $archiveId,
            'user_id' => $userId,
            'aktivitas' => $activity,
            'keterangan' => $detail,
            'created_at' => now(),
        ]);
    }
}
