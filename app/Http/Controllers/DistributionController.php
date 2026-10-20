<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ArchiveAccess;
use App\Support\RolePermissions;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DistributionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->canAccessDistributionPage($user), 403);

        $query = DB::table('archive_distributions as d')
            ->join('arsip as a', 'a.id', '=', 'd.archive_id')
            ->join('users as requester', 'requester.id', '=', 'd.requested_by')
            ->join('users as recipient', 'recipient.id', '=', 'd.recipient_id')
            ->leftJoin('users as approver', 'approver.id', '=', 'd.approved_by')
            ->leftJoin('users as dispatcher', 'dispatcher.id', '=', 'd.dispatched_by')
            ->leftJoin('users as receiver', 'receiver.id', '=', 'd.received_by');
        if (! RolePermissions::isAdmin($user)) {
            $query->where('a.classification', '<>', 'Dokumen Rahasia')
                ->where(function (Builder $visible) use ($user): void {
                    $visible
                        ->where('d.requested_by', $user->id)
                        ->orWhere('d.recipient_id', $user->id);
                    if (trim((string) $user->division) !== '') {
                        $division = mb_strtolower(trim((string) $user->division));
                        $visible
                            ->orWhereRaw('LOWER(TRIM(d.origin_division)) = ?', [$division])
                            ->orWhereRaw('LOWER(TRIM(d.destination_division)) = ?', [$division]);
                    }
                });
        }

        $distributions = $query
            ->select([
                'd.*',
                'a.nama_berkas',
                'a.nomor_berkas',
                'a.classification',
                'a.division as archive_division',
                'requester.nama as requester_name',
                'recipient.nama as recipient_name',
                'approver.nama as approver_name',
                'dispatcher.nama as dispatcher_name',
                'receiver.nama as receiver_name',
            ])
            ->orderByRaw("CASE WHEN d.status = 'Menunggu Persetujuan' THEN 0 WHEN d.status = 'Dalam Pengiriman' THEN 1 ELSE 2 END")
            ->orderByDesc('d.created_at')
            ->paginate(20)
            ->withQueryString();

        return view('distributions.index', [
            'distributions' => $distributions,
            'canRequest' => RolePermissions::allows($user, RolePermissions::DISTRIBUTIONS_REQUEST),
            'canApprove' => RolePermissions::allows($user, RolePermissions::DISTRIBUTIONS_APPROVE),
            'canDispatch' => RolePermissions::allows($user, RolePermissions::DISTRIBUTIONS_DISPATCH),
            'canReceive' => RolePermissions::allows($user, RolePermissions::DISTRIBUTIONS_RECEIVE),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        abort_unless(RolePermissions::allows($user, RolePermissions::DISTRIBUTIONS_REQUEST), 403);

        $archives = ArchiveAccess::scope(DB::table('arsip as a'), $user)
            ->where('a.status', 'Tersimpan')
            ->orderBy('a.nama_berkas')
            ->get(['a.id', 'a.nama_berkas', 'a.nomor_berkas', 'a.division', 'a.classification']);
        $recipients = DB::table('users')
            ->where('status', 'Aktif')
            ->whereNotNull('division')
            ->where('division', '<>', '')
            ->where('division', '<>', trim((string) $user->division))
            ->whereIn('role', ['Admin', 'Petugas Arsip', 'Pegawai'])
            ->orderBy('division')
            ->orderBy('nama')
            ->get(['id', 'nama', 'role', 'division']);

        return view('distributions.create', compact('archives', 'recipients'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(RolePermissions::allows($user, RolePermissions::DISTRIBUTIONS_REQUEST), 403);
        $data = $request->validate([
            'archive_id' => ['required', 'integer', 'exists:arsip,id'],
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($user, $data): void {
            $archive = DB::table('arsip')->where('id', $data['archive_id'])->lockForUpdate()->first();
            abort_if($archive === null, 404);
            abort_unless(ArchiveAccess::canView($user, $archive), 403);
            abort_unless($archive->status === 'Tersimpan', 409, 'Hanya arsip berstatus Tersimpan yang dapat didistribusikan.');

            $recipient = User::query()->whereKey($data['recipient_id'])->where('status', 'Aktif')->first();
            abort_if($recipient === null || trim((string) $recipient->division) === '', 422, 'Pilih penerima aktif yang memiliki divisi.');
            abort_unless(in_array($recipient->role, ['Admin', 'Petugas Arsip', 'Pegawai'], true), 422, 'Role penerima tidak dapat menerima distribusi.');
            abort_unless(
                $archive->classification !== 'Dokumen Rahasia' || RolePermissions::isAdmin($recipient),
                403,
                'Arsip rahasia hanya dapat didistribusikan kepada penerima Admin.'
            );

            $originDivision = trim((string) ($archive->division ?: $user->division));
            $destinationDivision = trim((string) $recipient->division);
            abort_if($originDivision === '', 422, 'Divisi asal arsip belum ditentukan.');
            abort_if(mb_strtolower($originDivision) === mb_strtolower($destinationDivision), 422, 'Divisi tujuan harus berbeda dari divisi asal.');
            if (RolePermissions::isDivisionScoped($user)) {
                abort_unless(RolePermissions::matchesDivision($user, $originDivision), 403);
            }

            $activeDistribution = DB::table('archive_distributions')
                ->where('archive_id', $archive->id)
                ->whereIn('status', ['Menunggu Persetujuan', 'Disetujui', 'Dalam Pengiriman'])
                ->exists();
            abort_if($activeDistribution, 409, 'Arsip sudah memiliki proses distribusi yang belum selesai.');

            $id = DB::table('archive_distributions')->insertGetId([
                'archive_id' => $archive->id,
                'origin_division' => $originDivision,
                'destination_division' => $destinationDivision,
                'requested_by' => $user->id,
                'sender_id' => null,
                'recipient_id' => $recipient->id,
                'approved_by' => null,
                'dispatched_by' => null,
                'received_by' => null,
                'status' => 'Menunggu Persetujuan',
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'rejection_note' => null,
                'sent_at' => null,
                'received_at' => null,
                'receipt_note' => null,
                'receipt_attachment' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('arsip')->where('id', $archive->id)->update([
                'status' => 'Diproses',
                'updated_at' => now(),
            ]);

            $this->addAudit($archive->id, $user->id, 'Mengajukan distribusi arsip', 'Distribusi #'.$id.'; '.$originDivision.' → '.$destinationDivision);
            $this->notifyApprovers($archive->nama_berkas, $originDivision, $id);
        });

        return redirect()->route('distributions.index')->with('success', 'Permintaan distribusi dikirim untuk persetujuan.');
    }

    public function review(Request $request, int $distribution): RedirectResponse
    {
        $user = $request->user();
        abort_unless(RolePermissions::allows($user, RolePermissions::DISTRIBUTIONS_APPROVE), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['Disetujui', 'Ditolak'])],
            'rejection_note' => ['nullable', 'string', 'max:5000', Rule::requiredIf($request->input('decision') === 'Ditolak')],
        ]);
        $note = trim((string) ($data['rejection_note'] ?? ''));
        abort_if($data['decision'] === 'Ditolak' && $note === '', 422, 'Catatan wajib diisi saat permintaan ditolak.');

        DB::transaction(function () use ($user, $distribution, $data, $note): void {
            $record = DB::table('archive_distributions')->where('id', $distribution)->lockForUpdate()->first();
            abort_if($record === null, 404);
            abort_unless($record->status === 'Menunggu Persetujuan', 409, 'Permintaan distribusi sudah diproses.');
            abort_if((int) $record->requested_by === (int) $user->id, 403, 'Pengusul tidak dapat menyetujui permintaannya sendiri.');
            $archive = DB::table('arsip')->where('id', $record->archive_id)->first();
            abort_if($archive === null, 404);
            abort_unless(ArchiveAccess::canView($user, $archive), 403);
            $this->assertApproverDivision($user, $record->origin_division);

            $status = $data['decision'] === 'Disetujui' ? 'Disetujui' : 'Ditolak';
            DB::table('archive_distributions')->where('id', $distribution)->update([
                'status' => $status,
                'approved_by' => $user->id,
                'rejection_note' => $status === 'Ditolak' ? $note : null,
                'updated_at' => now(),
            ]);
            if ($status === 'Ditolak') {
                DB::table('arsip')->where('id', $record->archive_id)
                    ->where('status', 'Diproses')
                    ->update(['status' => 'Tersimpan', 'updated_at' => now()]);
            }
            $this->addAudit((int) $record->archive_id, $user->id, 'Memutuskan distribusi arsip', 'Distribusi #'.$distribution.'; Status: '.$status.($note !== '' ? '; Catatan: '.$note : ''));
            $this->notifyUser((int) $record->requested_by, 'Distribusi '.$status, 'Distribusi arsip #'.$distribution.' '.$status.'.'.($note !== '' ? ' '.$note : ''), route('distributions.index'), ['distribution_id' => $distribution]);
            if ($status === 'Disetujui') {
                $this->notifyUser((int) $record->recipient_id, 'Distribusi disetujui', 'Distribusi arsip #'.$distribution.' telah disetujui dan menunggu pengiriman.', route('distributions.index'), ['distribution_id' => $distribution]);
            }
        });

        return redirect()->route('distributions.index')->with('success', 'Keputusan distribusi berhasil dicatat.');
    }

    public function dispatch(Request $request, int $distribution): RedirectResponse
    {
        $user = $request->user();
        abort_unless(RolePermissions::allows($user, RolePermissions::DISTRIBUTIONS_DISPATCH), 403);

        DB::transaction(function () use ($user, $distribution): void {
            $record = DB::table('archive_distributions')->where('id', $distribution)->lockForUpdate()->first();
            abort_if($record === null, 404);
            abort_unless($record->status === 'Disetujui', 409, 'Hanya distribusi yang disetujui yang dapat dikirim.');
            $archive = DB::table('arsip')->where('id', $record->archive_id)->first();
            abort_if($archive === null, 404);
            abort_unless(ArchiveAccess::canView($user, $archive), 403);
            $this->assertOriginOperator($user, $record->origin_division);
            abort_unless($archive->status === 'Diproses', 409, 'Arsip tidak lagi tersedia untuk dikirim.');

            DB::table('archive_distributions')->where('id', $distribution)->update([
                'status' => 'Dalam Pengiriman',
                'sender_id' => $user->id,
                'dispatched_by' => $user->id,
                'sent_at' => now(),
                'updated_at' => now(),
            ]);
            $this->addAudit((int) $record->archive_id, $user->id, 'Mengirim distribusi arsip', 'Distribusi #'.$distribution.'; Tujuan: '.$record->destination_division);
            $this->notifyUser((int) $record->recipient_id, 'Arsip dalam pengiriman', 'Arsip '.$archive->nama_berkas.' sedang dikirim dari '.$record->origin_division.'.', route('distributions.index'), ['distribution_id' => $distribution]);
        });

        return redirect()->route('distributions.index')->with('success', 'Distribusi ditandai dalam pengiriman.');
    }

    public function receive(Request $request, int $distribution): RedirectResponse
    {
        $user = $request->user();
        abort_unless((int) DB::table('archive_distributions')->where('id', $distribution)->value('recipient_id') === (int) $user->id
            || RolePermissions::allows($user, RolePermissions::DISTRIBUTIONS_RECEIVE), 403);
        $data = $request->validate([
            'receipt_note' => ['required', 'string', 'max:5000'],
            'receipt_attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $attachmentPath = null;
        if ($request->file('receipt_attachment') !== null) {
            $attachmentPath = $request->file('receipt_attachment')->store('distribution-evidence', 'local');
            if ($attachmentPath === false) {
                throw new RuntimeException('Bukti penerimaan gagal disimpan. Distribusi belum diubah.');
            }
        }

        try {
            DB::transaction(function () use ($user, $distribution, $data, $attachmentPath): void {
                $record = DB::table('archive_distributions')->where('id', $distribution)->lockForUpdate()->first();
                abort_if($record === null, 404);
                abort_unless($record->status === 'Dalam Pengiriman', 409, 'Hanya arsip yang sedang dikirim yang dapat dikonfirmasi penerimaannya.');
                abort_unless((int) $record->recipient_id === (int) $user->id
                    || RolePermissions::allows($user, RolePermissions::DISTRIBUTIONS_RECEIVE), 403);
                abort_unless(
                    RolePermissions::isAdmin($user)
                    || RolePermissions::matchesDivision($user, $record->destination_division),
                    403,
                    'Penerima harus berasal dari divisi tujuan.'
                );
                $archive = DB::table('arsip')->where('id', $record->archive_id)->lockForUpdate()->first();
                abort_if($archive === null, 404);
                $isAssignedRecipient = (int) $record->recipient_id === (int) $user->id;
                $isDestinationOperator = RolePermissions::allows($user, RolePermissions::DISTRIBUTIONS_RECEIVE)
                    && RolePermissions::matchesDivision($user, $record->destination_division);
                abort_unless(
                    ArchiveAccess::canView($user, $archive)
                    || (($isAssignedRecipient || $isDestinationOperator) && $archive->classification !== 'Dokumen Rahasia'),
                    403
                );
                abort_unless($archive->status === 'Diproses', 409, 'Status arsip tidak sesuai dengan distribusi dalam pengiriman.');

                DB::table('archive_distributions')->where('id', $distribution)->update([
                    'status' => 'Diterima',
                    'received_by' => $user->id,
                    'received_at' => now(),
                    'receipt_note' => trim($data['receipt_note']),
                    'receipt_attachment' => $attachmentPath,
                    'updated_at' => now(),
                ]);
                if ($archive->classification === 'Internal Divisi') {
                    DB::table('arsip')->where('id', $archive->id)->update([
                        'division' => $record->destination_division,
                        'status' => 'Tersimpan',
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('arsip')->where('id', $archive->id)->update([
                        'status' => 'Tersimpan',
                        'updated_at' => now(),
                    ]);
                }

                $this->addAudit((int) $archive->id, $user->id, 'Menerima distribusi arsip', 'Distribusi #'.$distribution.'; '.$record->origin_division.' → '.$record->destination_division.'; '.$data['receipt_note']);
                $this->notifyUser((int) $record->requested_by, 'Arsip telah diterima', 'Penerima mengonfirmasi penerimaan arsip '.$archive->nama_berkas.'.', route('distributions.index'), ['distribution_id' => $distribution]);
                if ((int) $record->recipient_id !== (int) $user->id) {
                    $this->notifyUser((int) $record->recipient_id, 'Arsip telah diterima', 'Arsip '.$archive->nama_berkas.' telah dikonfirmasi diterima oleh '.$user->nama.'.', route('distributions.index'), ['distribution_id' => $distribution]);
                }
            });
        } catch (Throwable $exception) {
            if ($attachmentPath !== null) {
                Storage::disk('local')->delete($attachmentPath);
            }

            throw $exception;
        }

        return redirect()->route('distributions.index')->with('success', 'Penerimaan arsip dan bukti serah-terima berhasil dicatat.');
    }

    public function receiptEvidence(Request $request, int $distribution): StreamedResponse
    {
        $user = $request->user();
        $record = DB::table('archive_distributions')->where('id', $distribution)->first();
        abort_if($record === null || $record->receipt_attachment === null, 404);
        $archive = DB::table('arsip')->where('id', $record->archive_id)->first();
        abort_if($archive === null, 404);
        $isParticipant = in_array((int) $user->id, [
            (int) $record->requested_by,
            (int) $record->recipient_id,
            (int) $record->sender_id,
            (int) $record->approved_by,
            (int) $record->dispatched_by,
            (int) $record->received_by,
        ], true);
        $isRelatedDivisionOperator = RolePermissions::matchesDivision($user, $record->origin_division)
            || RolePermissions::matchesDivision($user, $record->destination_division);
        abort_unless(
            RolePermissions::isAdmin($user)
            || ArchiveAccess::canView($user, $archive)
            || ($archive->classification !== 'Dokumen Rahasia' && ($isParticipant || ($isRelatedDivisionOperator && $this->canAccessDistributionPage($user)))),
            403
        );
        abort_unless(Storage::disk('local')->exists($record->receipt_attachment), 404);

        return Storage::disk('local')->download($record->receipt_attachment, 'bukti-distribusi-'.$distribution);
    }

    private function canAccessDistributionPage(User $user): bool
    {
        foreach ([
            RolePermissions::DISTRIBUTIONS_REQUEST,
            RolePermissions::DISTRIBUTIONS_APPROVE,
            RolePermissions::DISTRIBUTIONS_DISPATCH,
            RolePermissions::DISTRIBUTIONS_RECEIVE,
        ] as $permission) {
            if (RolePermissions::allows($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    private function assertApproverDivision(User $user, string $originDivision): void
    {
        abort_unless(
            RolePermissions::isAdmin($user)
            || RolePermissions::matchesDivision($user, $originDivision),
            403
        );
    }

    private function assertOriginOperator(User $user, string $originDivision): void
    {
        abort_unless(
            RolePermissions::isAdmin($user)
            || RolePermissions::matchesDivision($user, $originDivision),
            403
        );
    }

    private function notifyApprovers(string $archiveName, string $originDivision, int $distributionId): void
    {
        $approvers = DB::table('users')
            ->where('status', 'Aktif')
            ->whereIn('role', ['Admin', 'Petugas Arsip'])
            ->where(function (Builder $scope) use ($originDivision): void {
                $scope->where('role', 'Admin')->orWhere('division', $originDivision);
            })
            ->pluck('id');

        foreach ($approvers as $approverId) {
            $this->notifyUser((int) $approverId, 'Permintaan distribusi arsip', $archiveName.' menunggu persetujuan distribusi.', route('distributions.index'), ['distribution_id' => $distributionId]);
        }
    }

    private function notifyUser(int $userId, string $title, string $body, string $url, array $data): void
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
