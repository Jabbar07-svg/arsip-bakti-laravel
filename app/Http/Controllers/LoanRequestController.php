<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ArchiveAccess;
use App\Support\RolePermissions;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LoanRequestController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->canRequestLoan($request->user()), 403);
        $requests = DB::table('loan_requests as r')
            ->join('arsip as a', 'a.id', '=', 'r.arsip_id');
        ArchiveAccess::scope($requests, $request->user(), 'a');
        $requests = $requests
            ->where('r.user_id', $request->user()->id)
            ->orderByDesc('r.id')
            ->paginate(20)
            ->withQueryString();

        return view('loans.requests', compact('requests'));
    }

    public function store(Request $request, int $archive): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canRequestLoan($user), 403);
        $validated = $request->validate([
            'purpose' => ['required', 'string', 'max:5000'],
            'requested_due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);

        $requestId = DB::transaction(function () use ($user, $archive, $validated): int {
            $record = DB::table('arsip')->where('id', $archive)->lockForUpdate()->first();
            abort_if($record === null, 404);
            abort_unless(ArchiveAccess::canView($user, $record), 403);
            abort_unless($record->classification === 'Publik' && $record->status === 'Tersimpan', 403);

            $duplicate = DB::table('loan_requests')
                ->where('user_id', $user->id)
                ->where('arsip_id', $archive)
                ->where('status', 'Menunggu Persetujuan')
                ->exists();
            abort_if($duplicate, 409, 'Permintaan untuk arsip ini masih menunggu persetujuan.');

            $id = DB::table('loan_requests')->insertGetId([
                'user_id' => $user->id,
                'arsip_id' => $archive,
                'purpose' => trim($validated['purpose']),
                'requested_due_date' => $validated['requested_due_date'] ?? null,
                'status' => 'Menunggu Persetujuan',
                'reviewer_note' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'loan_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->notifyActiveAdmins(
                'Permintaan peminjaman baru',
                $user->nama.' meminta peminjaman untuk arsip '.$record->nomor_berkas.'.',
                route('loan-approvals.index'),
                ['request_id' => $id]
            );

            return $id;
        });

        return redirect()->route('loan-requests.index')->with('success', 'Permintaan peminjaman berhasil dikirim.');
    }

    public function approvals(Request $request): View
    {
        $this->authorizeApprover($request->user());
        $requests = DB::table('loan_requests as r')
            ->join('users as requester', 'requester.id', '=', 'r.user_id')
            ->join('arsip as a', 'a.id', '=', 'r.arsip_id')
            ->leftJoin('users as reviewer', 'reviewer.id', '=', 'r.reviewed_by');
        ArchiveAccess::scope($requests, $request->user(), 'a');
        $requests = $requests->select([
                'r.*',
                'requester.nama as requester_name',
                'requester.username',
                'requester.division',
                'a.nama_berkas',
                'a.nomor_berkas',
                'a.status as archive_status',
                'reviewer.nama as reviewer_name',
            ])
            ->orderByRaw("CASE WHEN r.status = 'Menunggu Persetujuan' THEN 0 ELSE 1 END")
            ->orderByDesc('r.created_at')
            ->limit(200)
            ->get();

        return view('loans.approvals', [
            'requests' => $requests,
            'pendingCount' => $requests->where('status', 'Menunggu Persetujuan')->count(),
        ]);
    }

    public function decide(Request $request, int $loanRequest): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeApprover($user);
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['Disetujui', 'Ditolak'])],
            'reviewer_note' => ['nullable', 'string', 'max:5000', Rule::requiredIf($request->input('decision') === 'Ditolak')],
        ]);

        DB::transaction(function () use ($user, $loanRequest, $validated): void {
            $submission = DB::table('loan_requests')
                ->where('id', $loanRequest)
                ->lockForUpdate()
                ->first();
            abort_if($submission === null, 404);
            abort_if((int) $submission->user_id === (int) $user->id, 403, 'Pemohon tidak dapat memutuskan permintaannya sendiri.');
            abort_unless($submission->status === 'Menunggu Persetujuan', 409, 'Permintaan tidak lagi menunggu persetujuan.');
            $archive = DB::table('arsip')->where('id', $submission->arsip_id)->lockForUpdate()->first();
            abort_if($archive === null, 404);
            abort_unless(ArchiveAccess::canView($user, $archive), 403);

            $createdLoanId = null;
            if ($validated['decision'] === 'Disetujui') {
                abort_unless(
                    $archive->classification !== 'Dokumen Rahasia' || RolePermissions::isSuperAdmin($user),
                    403,
                    'Hanya Super Admin yang dapat menyetujui peminjaman arsip Dokumen Rahasia.'
                );
                abort_unless($archive->classification === 'Publik' && $archive->status === 'Tersimpan', 409, 'Arsip sudah tidak tersedia; permintaan tidak dapat disetujui.');
                $requester = DB::table('users')->where('id', $submission->user_id)->first();
                abort_if($requester === null, 404);

                $createdLoanId = DB::table('peminjaman')->insertGetId([
                    'arsip_id' => $archive->id,
                    'nama_peminjam' => $requester->nama,
                    'unit' => $requester->division,
                    'tanggal_pinjam' => today()->toDateString(),
                    'tanggal_rencana_kembali' => $submission->requested_due_date,
                    'tanggal_dikembalikan' => null,
                    'condition_return' => null,
                    'keperluan' => $submission->purpose,
                    'status' => 'Dipinjam',
                    'created_by' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('arsip')->where('id', $archive->id)->update([
                    'status' => 'Dipinjam',
                    'updated_at' => now(),
                ]);
                $this->addAudit($archive->id, $user->id, 'Memeriksa permintaan peminjaman', 'Permintaan #'.$loanRequest.': Disetujui');
            } else {
                $this->addAudit($submission->arsip_id, $user->id, 'Memeriksa permintaan peminjaman', 'Permintaan #'.$loanRequest.': Ditolak');
            }

            DB::table('loan_requests')->where('id', $loanRequest)->update([
                'status' => $validated['decision'],
                'reviewer_note' => $validated['reviewer_note'] ?? null,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'loan_id' => $createdLoanId,
                'updated_at' => now(),
            ]);

            DB::table('notifications')->insert([
                'user_id' => $submission->user_id,
                'title' => 'Permintaan peminjaman '.strtolower($validated['decision']),
                'body' => 'Keputusan untuk arsip '.$submission->arsip_id.': '.$validated['decision']
                    .(empty($validated['reviewer_note']) ? '' : ' · '.$validated['reviewer_note']),
                'type' => $validated['decision'] === 'Disetujui' ? 'success' : 'warning',
                'url' => route('loan-requests.index'),
                'data' => json_encode(['loan_request_id' => $loanRequest, 'loan_id' => $createdLoanId], JSON_THROW_ON_ERROR),
                'is_sent' => 1,
                'created_at' => now(),
            ]);
        });

        return redirect()->route('loan-approvals.index')->with(
            'success',
            $validated['decision'] === 'Disetujui'
                ? 'Peminjaman disetujui dan transaksi berhasil dibuat.'
                : 'Permintaan peminjaman ditolak.'
        );
    }

    private function isCustomer(User $user): bool
    {
        return RolePermissions::allows($user, RolePermissions::CUSTOMER_PORTAL);
    }

    private function canRequestLoan(User $user): bool
    {
        return RolePermissions::allows($user, RolePermissions::LOANS_REQUEST);
    }

    private function authorizeApprover(User $user): void
    {
        abort_unless(RolePermissions::allows($user, RolePermissions::LOANS_APPROVE), 403);
    }

    private function notifyActiveAdmins(string $title, string $body, string $url, array $data): void
    {
        $admins = DB::table('users')
            ->where('role', 'Super Admin')
            ->where('status', 'Aktif')
            ->pluck('id');

        foreach ($admins as $adminId) {
            DB::table('notifications')->insert([
                'user_id' => $adminId,
                'title' => $title,
                'body' => $body,
                'type' => 'info',
                'url' => $url,
                'data' => json_encode($data, JSON_THROW_ON_ERROR),
                'is_sent' => 1,
                'created_at' => now(),
            ]);
        }
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
