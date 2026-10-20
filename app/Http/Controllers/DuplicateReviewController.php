<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ArchiveAccess;
use App\Support\DuplicateCandidateDiscovery;
use App\Support\RolePermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DuplicateReviewController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorizeReviewer($user);

        $reviews = DB::table('duplicate_reviews as d')
            ->join('arsip as a', 'a.id', '=', 'd.archive_id')
            ->join('arsip as b', 'b.id', '=', 'd.candidate_id')
            ->leftJoin('users as reviewer', 'reviewer.id', '=', 'd.reviewed_by');
        ArchiveAccess::scope($reviews, $user, 'a');
        ArchiveAccess::scope($reviews, $user, 'b');
        $reviews = $reviews->select([
            'd.*',
            'a.nama_berkas as archive_name',
            'a.nomor_berkas as archive_number',
            'a.qr_code as archive_qr',
            'a.classification as archive_classification',
            'a.division as archive_division',
            'b.nama_berkas as candidate_name',
            'b.nomor_berkas as candidate_number',
            'b.qr_code as candidate_qr',
            'b.classification as candidate_classification',
            'b.division as candidate_division',
            'reviewer.nama as reviewer_name',
        ])
            ->orderByRaw("CASE WHEN d.status = 'Menunggu Verifikasi' THEN 0 ELSE 1 END")
            ->orderByDesc('d.created_at')
            ->orderBy('d.id')
            ->limit(300)
            ->get();

        return view('duplicates.index', [
            'reviews' => $reviews,
            'pendingCount' => $reviews->where('status', 'Menunggu Verifikasi')->count(),
        ]);
    }

    public function discover(Request $request, DuplicateCandidateDiscovery $discovery): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeReviewer($user);
        $inserted = $discovery->discover($user);
        $message = $inserted === 0
            ? 'Tidak ada kandidat duplikasi baru yang ditemukan.'
            : "Discovery selesai: {$inserted} kandidat baru ditambahkan. Maksimal 500 pasangan diproses per batch; jalankan kembali untuk melanjutkan bila masih ada pasangan.";

        return redirect()->route('duplicates.index')->with('success', $message);
    }

    public function decide(Request $request, int $review): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeReviewer($user);
        $data = $request->validate([
            'status' => ['required', Rule::in(['Duplikat', 'Bukan Duplikat'])],
            'reviewer_note' => ['nullable', 'string', 'max:5000', Rule::requiredIf($request->input('status') === 'Duplikat')],
        ]);
        $note = trim((string) ($data['reviewer_note'] ?? ''));
        abort_if($data['status'] === 'Duplikat' && $note === '', 422, 'Catatan wajib diisi saat pasangan arsip dipastikan duplikat.');

        DB::transaction(function () use ($user, $review, $data, $note): void {
            $record = DB::table('duplicate_reviews')->where('id', $review)->lockForUpdate()->first();
            abort_if($record === null, 404);
            abort_unless($record->status === 'Menunggu Verifikasi', 409, 'Pasangan arsip tidak lagi menunggu verifikasi.');
            $archive = DB::table('arsip')->where('id', $record->archive_id)->first();
            $candidate = DB::table('arsip')->where('id', $record->candidate_id)->first();
            abort_if($archive === null || $candidate === null, 404);
            abort_unless(
                ($archive->classification !== 'Dokumen Rahasia' && $candidate->classification !== 'Dokumen Rahasia')
                    || RolePermissions::isAdmin($user),
                403,
                'Hanya Admin yang dapat memverifikasi pasangan yang memuat arsip Dokumen Rahasia.'
            );
            abort_unless(ArchiveAccess::canView($user, $archive) && ArchiveAccess::canView($user, $candidate), 403);
            DB::table('duplicate_reviews')->where('id', $review)->update([
                'status' => $data['status'],
                'reviewer_note' => $note !== '' ? $note : null,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);
            DB::table('riwayat_arsip')->insert([
                'arsip_id' => $record->archive_id,
                'user_id' => $user->id,
                'aktivitas' => 'Memverifikasi potensi duplikasi',
                'keterangan' => 'Pasangan arsip #'.$record->candidate_id.': '.$data['status'].($note !== '' ? ' · '.$note : ''),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('duplicates.index')->with('success', 'Hasil verifikasi duplikasi berhasil disimpan.');
    }

    private function authorizeReviewer(User $user): void
    {
        abort_unless(RolePermissions::allows($user, RolePermissions::DUPLICATES_REVIEW), 403);
    }
}
