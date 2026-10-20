<?php

namespace App\Http\Controllers;

use App\Support\ArchiveAccess;
use App\Support\RolePermissions;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmployeeDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless(
            $user->role === 'Pegawai'
                && RolePermissions::allows($user, RolePermissions::DASHBOARD_VIEW),
            403
        );

        $archives = ArchiveAccess::scope(DB::table('arsip as a'), $user);
        $loanRequests = DB::table('loan_requests as r')
            ->join('arsip as a', 'a.id', '=', 'r.arsip_id')
            ->where('r.user_id', $user->id);
        ArchiveAccess::scope($loanRequests, $user, 'a');
        $requestStatuses = (clone $loanRequests)
            ->select('r.status')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('r.status')
            ->pluck('total', 'r.status')
            ->map(static fn ($total): int => (int) $total)
            ->all();

        $employeeLoans = DB::table('peminjaman as p')
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
        ArchiveAccess::scope($employeeLoans, $user, 'a');
        $loanStatuses = (clone $employeeLoans)
            ->select('p.status')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('p.status')
            ->pluck('total', 'p.status')
            ->map(static fn ($total): int => (int) $total)
            ->all();
        $recentEmployeeLoans = (clone $employeeLoans)
            ->select([
                'p.id',
                'p.arsip_id',
                'p.keperluan',
                'p.status',
                'p.tanggal_pinjam',
                'p.tanggal_rencana_kembali',
                'p.tanggal_dikembalikan',
                'p.condition_return',
                'a.nama_berkas',
                'a.nomor_berkas',
            ])
            ->orderByDesc('p.id')
            ->limit(5)
            ->get();

        $recentRequests = (clone $loanRequests)
            ->select([
                'r.id',
                'r.status',
                'r.created_at',
                'r.requested_due_date',
                'a.id as archive_id',
                'a.nama_berkas',
                'a.nomor_berkas',
            ])
            ->orderByDesc('r.created_at')
            ->orderByDesc('r.id')
            ->limit(5)
            ->get();

        $recentActivities = DB::table('riwayat_arsip as h')
            ->join('arsip as a', 'a.id', '=', 'h.arsip_id')
            ->where('h.user_id', $user->id);
        ArchiveAccess::scope($recentActivities, $user, 'a');
        $recentActivities = $recentActivities
            ->select(['h.aktivitas', 'h.keterangan', 'h.created_at', 'a.nama_berkas'])
            ->orderByDesc('h.created_at')
            ->orderByDesc('h.id')
            ->limit(5)
            ->get();

        $notifications = DB::table('notifications')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['title', 'body', 'created_at', 'read_at']);

        return view('dashboard.pegawai', [
            'archiveCount' => $archives->count(),
            'loanCount' => (clone $employeeLoans)->count(),
            'activeLoanCount' => $loanStatuses['Dipinjam'] ?? 0,
            'returnedLoanCount' => $loanStatuses['Dikembalikan'] ?? 0,
            'recentLoans' => $recentEmployeeLoans,
            'pendingRequestCount' => $requestStatuses['Menunggu Persetujuan'] ?? 0,
            'approvedRequestCount' => $requestStatuses['Disetujui'] ?? 0,
            'rejectedRequestCount' => $requestStatuses['Ditolak'] ?? 0,
            'recentRequests' => $recentRequests,
            'unreadNotificationCount' => DB::table('notifications')
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->count(),
            'recentActivities' => $recentActivities,
            'notifications' => $notifications,
        ]);
    }
}
