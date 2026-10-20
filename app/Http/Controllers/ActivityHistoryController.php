<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ArchiveAccess;
use App\Support\RolePermissions;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ActivityHistoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(RolePermissions::allows($request->user(), RolePermissions::HISTORY_VIEW), 403);
        $search = trim((string) $request->query('q', ''));

        return view('history.index', [
            'activities' => $this->historyQuery($request->user(), $search)
                ->orderByDesc('r.created_at')
                ->orderByDesc('r.id')
                ->limit(300)
                ->get(),
            'search' => $search,
        ]);
    }

    private function historyQuery(User $user, string $search): Builder
    {
        $query = DB::table('riwayat_arsip as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('arsip as a', 'a.id', '=', 'r.arsip_id')
            ->select([
                'r.id',
                'r.arsip_id',
                'r.aktivitas',
                'r.keterangan',
                'r.created_at',
                'u.username',
                'u.nama',
            ]);
        ArchiveAccess::scope($query, $user, 'a');
        if ($user->role === 'Pegawai') {
            $returnedLoanDetails = DB::table('peminjaman')
                ->where(function (Builder $owned) use ($user): void {
                    $owned
                        ->where('created_by', $user->id)
                        ->orWhereIn('id', DB::table('loan_requests')
                            ->select('loan_id')
                            ->where('user_id', $user->id)
                            ->where('status', 'Disetujui')
                            ->whereNotNull('loan_id'));
                })
                ->where('status', 'Dikembalikan')
                ->orderByDesc('id')
                ->limit(300)
                ->pluck('id')
                ->map(static fn ($id): string => 'Transaksi peminjaman #'.$id)
                ->all();
            $reviewedRequestDetails = DB::table('loan_requests')
                ->where('user_id', $user->id)
                ->whereIn('status', ['Disetujui', 'Ditolak'])
                ->orderByDesc('id')
                ->limit(300)
                ->get(['id', 'status'])
                ->map(static fn (object $request): string => 'Permintaan #'.$request->id.': '.$request->status)
                ->all();

            $query->where(function (Builder $ownActivity) use ($user, $returnedLoanDetails, $reviewedRequestDetails): void {
                $ownActivity->where('r.user_id', $user->id);
                if ($returnedLoanDetails !== []) {
                    $ownActivity->orWhere(function (Builder $returns) use ($returnedLoanDetails): void {
                        $returns
                            ->where('r.aktivitas', 'Mengembalikan berkas')
                            ->whereIn('r.keterangan', $returnedLoanDetails);
                    });
                }
                if ($reviewedRequestDetails !== []) {
                    $ownActivity->orWhere(function (Builder $reviews) use ($reviewedRequestDetails): void {
                        $reviews
                            ->where('r.aktivitas', 'Memeriksa permintaan peminjaman')
                            ->whereIn('r.keterangan', $reviewedRequestDetails);
                    });
                }
            });
        }

        if ($search !== '') {
            $query->where(function (Builder $matching) use ($search): void {
                foreach (['r.aktivitas', 'r.keterangan', 'u.username', 'u.nama'] as $column) {
                    $matching->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        return $query;
    }
}
