<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ArchiveAccess;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmployeeLoanController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->authorizeEmployee($request->user());
        $query = $this->ownedLoansQuery($user);

        $total = (clone $query)->count();
        $active = (clone $query)->where('p.status', 'Dipinjam')->count();
        $returned = (clone $query)->where('p.status', 'Dikembalikan')->count();
        $loans = $query
            ->select([
                'p.id',
                'p.arsip_id',
                'p.nama_peminjam',
                'p.unit',
                'p.tanggal_pinjam',
                'p.tanggal_rencana_kembali',
                'p.tanggal_dikembalikan',
                'p.condition_return',
                'p.keperluan',
                'p.status',
                'a.nomor_berkas',
                'a.nama_berkas',
            ])
            ->orderByDesc('p.id')
            ->paginate(20)
            ->withQueryString();

        return view('loans.employee-index', compact('loans', 'total', 'active', 'returned'));
    }

    public function store(Request $request, int $archive): RedirectResponse
    {
        $user = $this->authorizeEmployee($request->user());
        $validated = $request->validate([
            'keperluan' => ['nullable', 'string', 'max:5000'],
            'tanggal_rencana_kembali' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);

        DB::transaction(function () use ($user, $archive, $validated): void {
            $record = DB::table('arsip')->where('id', $archive)->lockForUpdate()->first();
            abort_if($record === null, 404);
            abort_unless(ArchiveAccess::canView($user, $record), 403);
            abort_unless($record->status === 'Tersimpan', 409, 'Arsip tidak tersedia untuk dipinjam.');
            $hasPendingRequest = DB::table('loan_requests')
                ->where('user_id', $user->id)
                ->where('arsip_id', $archive)
                ->whereIn('status', ['Menunggu Persetujuan', 'Disetujui'])
                ->exists();
            abort_if($hasPendingRequest, 409, 'Permintaan peminjaman arsip ini masih diproses. Periksa statusnya sebelum mengajukan jalur lain.');

            $loanId = DB::table('peminjaman')->insertGetId([
                'arsip_id' => $archive,
                'nama_peminjam' => $user->nama,
                'unit' => trim((string) $user->division) ?: null,
                'tanggal_pinjam' => today()->toDateString(),
                'tanggal_rencana_kembali' => $validated['tanggal_rencana_kembali'] ?? null,
                'tanggal_dikembalikan' => null,
                'condition_return' => null,
                'keperluan' => trim((string) ($validated['keperluan'] ?? '')) ?: null,
                'status' => 'Dipinjam',
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $updated = DB::table('arsip')
                ->where('id', $archive)
                ->where('status', 'Tersimpan')
                ->update(['status' => 'Dipinjam', 'updated_at' => now()]);
            abort_unless($updated === 1, 409, 'Status arsip berubah. Muat ulang halaman sebelum mencoba lagi.');

            DB::table('riwayat_arsip')->insert([
                'arsip_id' => $archive,
                'user_id' => $user->id,
                'aktivitas' => 'Meminjam berkas',
                'keterangan' => 'Metode: Tanpa QR; Transaksi #'.$loanId.'; Peminjam: '.$user->nama.'; Unit: '.(trim((string) $user->division) ?: '—'),
                'created_at' => now(),
            ]);
        });

        return redirect()
            ->route('employee-loans.index')
            ->with('success', 'Peminjaman tanpa QR berhasil dicatat. Status arsip sekarang Dipinjam.');
    }

    private function ownedLoansQuery(User $user): Builder
    {
        $query = DB::table('peminjaman as p')
            ->join('arsip as a', 'a.id', '=', 'p.arsip_id')
            ->where(function (Builder $owned) use ($user): void {
                $owned
                    ->where('p.created_by', $user->id)
                    ->orWhereIn('p.id', DB::table('loan_requests')
                        ->select('loan_id')
                        ->where('user_id', $user->id)
                        ->where('status', 'Disetujui')
                        ->whereNotNull('loan_id'));
            });

        return ArchiveAccess::scope($query, $user, 'a');
    }

    private function authorizeEmployee(User $user): User
    {
        abort_unless($user->role === 'Pegawai', 403);

        return $user;
    }
}
