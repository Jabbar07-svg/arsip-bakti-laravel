<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ArchiveAccess;
use App\Support\RolePermissions;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LoanController extends Controller
{
    private const RETURN_CONDITIONS = ['Baik', 'Rusak Ringan', 'Rusak Berat', 'Tidak Lengkap'];

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(RolePermissions::allows($user, RolePermissions::LOANS_MANAGE), 403);
        $query = DB::table('peminjaman as p')
            ->join('arsip as a', 'a.id', '=', 'p.arsip_id')
            ->leftJoin('lokasi as l', 'l.id', '=', 'a.lokasi_id');
        ArchiveAccess::scope($query, $user, 'a');

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        if ($search !== '') {
            $query->where(function (Builder $matching) use ($search): void {
                $matching
                    ->where('p.nama_peminjam', 'like', '%'.$search.'%')
                    ->orWhere('p.unit', 'like', '%'.$search.'%')
                    ->orWhere('a.nomor_berkas', 'like', '%'.$search.'%')
                    ->orWhere('a.qr_code', 'like', '%'.$search.'%')
                    ->orWhere('a.nama_berkas', 'like', '%'.$search.'%');
            });
        }
        if (in_array($status, ['Dipinjam', 'Dikembalikan'], true)) {
            $query->where('p.status', $status);
        }

        $total = (clone $query)->count();
        $active = (clone $query)->where('p.status', 'Dipinjam')->count();
        $returned = (clone $query)->where('p.status', 'Dikembalikan')->count();
        $due = (clone $query)->where('p.status', 'Dipinjam')
            ->whereNotNull('p.tanggal_rencana_kembali')
            ->whereDate('p.tanggal_rencana_kembali', '<', today())
            ->count();

        $loans = $query
            ->select([
                'p.id', 'p.arsip_id', 'p.nama_peminjam', 'p.unit', 'p.tanggal_pinjam',
                'p.tanggal_rencana_kembali', 'p.tanggal_dikembalikan', 'p.condition_return',
                'p.keperluan', 'p.status', 'a.qr_code', 'a.nomor_berkas', 'a.nama_berkas',
            ])
            ->orderByDesc('p.id')
            ->paginate(20)
            ->withQueryString();

        return view('loans.index', compact('loans', 'total', 'active', 'returned', 'due', 'search', 'status'));
    }

    public function create(Request $request): View
    {
        abort_unless(RolePermissions::allows($request->user(), RolePermissions::LOANS_MANAGE), 403);
        $archives = ArchiveAccess::scope(DB::table('arsip as a'), $request->user())
            ->join('kategori_arsip as k', 'k.id', '=', 'a.kategori_id')
            ->leftJoin('lokasi as l', 'l.id', '=', 'a.lokasi_id')
            ->where('a.status', 'Tersimpan')
            ->orderBy('a.nama_berkas')
            ->get([
                'a.id', 'a.qr_code', 'a.nomor_berkas', 'a.nama_berkas',
                'k.nama_kategori', 'l.gudang', 'l.rak', 'l.lemari', 'l.box',
            ]);

        return view('loans.create', compact('archives'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(RolePermissions::allows($user, RolePermissions::LOANS_MANAGE), 403);
        $validated = $request->validate([
            'arsip_id' => ['required', 'integer', 'exists:arsip,id'],
            'nama_peminjam' => ['required', 'string', 'max:150'],
            'unit' => ['nullable', 'string', 'max:150'],
            'tanggal_pinjam' => ['required', 'date_format:Y-m-d'],
            'tanggal_rencana_kembali' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_pinjam'],
            'keperluan' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($user, $validated): void {
            $archive = DB::table('arsip')->where('id', $validated['arsip_id'])->lockForUpdate()->first();
            abort_if($archive === null, 404);
            abort_unless(ArchiveAccess::canView($user, $archive), 403);
            abort_unless($archive->status === 'Tersimpan', 409, 'Arsip tidak tersedia untuk dipinjam.');

            $loanId = DB::table('peminjaman')->insertGetId([
                'arsip_id' => $archive->id,
                'nama_peminjam' => trim($validated['nama_peminjam']),
                'unit' => isset($validated['unit']) ? trim($validated['unit']) : null,
                'tanggal_pinjam' => $validated['tanggal_pinjam'],
                'tanggal_rencana_kembali' => $validated['tanggal_rencana_kembali'] ?? null,
                'tanggal_dikembalikan' => null,
                'condition_return' => null,
                'keperluan' => isset($validated['keperluan']) ? trim($validated['keperluan']) : null,
                'status' => 'Dipinjam',
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('arsip')->where('id', $archive->id)->update([
                'status' => 'Dipinjam',
                'updated_at' => now(),
            ]);
            $this->addAudit($archive->id, $user->id, 'Meminjam berkas', 'Transaksi peminjaman #'.$loanId);
        });

        return redirect()->route('loans.index')->with('success', 'Transaksi peminjaman berhasil dibuat.');
    }

    public function returnLoan(Request $request, int $loan): RedirectResponse
    {
        $user = $request->user();
        abort_unless(RolePermissions::allows($user, RolePermissions::LOANS_MANAGE), 403);
        $validated = $request->validate([
            'condition_return' => ['required', Rule::in(self::RETURN_CONDITIONS)],
        ]);

        DB::transaction(function () use ($user, $loan, $validated): void {
            $record = DB::table('peminjaman')->where('id', $loan)->lockForUpdate()->first();
            abort_if($record === null, 404);
            abort_unless($record->status === 'Dipinjam', 409, 'Transaksi ini sudah tidak aktif.');

            $archive = DB::table('arsip')->where('id', $record->arsip_id)->lockForUpdate()->first();
            abort_if($archive === null, 404);
            abort_unless(ArchiveAccess::canView($user, $archive), 403);
            abort_unless($archive->status === 'Dipinjam', 409, 'Status arsip tidak sesuai dengan transaksi peminjaman.');

            DB::table('peminjaman')->where('id', $loan)->update([
                'status' => 'Dikembalikan',
                'tanggal_dikembalikan' => today()->toDateString(),
                'condition_return' => $validated['condition_return'],
                'updated_at' => now(),
            ]);
            DB::table('arsip')->where('id', $archive->id)->update([
                'status' => 'Tersimpan',
                'physical_condition' => $validated['condition_return'],
                'updated_at' => now(),
            ]);
            $this->addAudit($archive->id, $user->id, 'Mengembalikan berkas', 'Transaksi peminjaman #'.$loan);

            $requestOwnerId = DB::table('loan_requests')
                ->where('loan_id', $loan)
                ->where('status', 'Disetujui')
                ->value('user_id');
            $borrower = User::query()
                ->whereKey($requestOwnerId ?? $record->created_by)
                ->where('role', 'Pegawai')
                ->first(['id']);
            if ($borrower !== null) {
                DB::table('notifications')->insert([
                    'user_id' => $borrower->id,
                    'title' => 'Peminjaman dikembalikan',
                    'body' => 'Arsip '.$archive->nama_berkas.' telah dikembalikan dengan kondisi '.$validated['condition_return'].'.',
                    'type' => 'success',
                    'url' => route('employee-loans.index'),
                    'data' => json_encode(['loan_id' => $loan, 'archive_id' => $archive->id], JSON_THROW_ON_ERROR),
                    'is_sent' => 1,
                    'created_at' => now(),
                ]);
            }
        });

        return redirect()->route('loans.index')->with('success', 'Arsip berhasil dikembalikan dan kondisinya diperbarui.');
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
