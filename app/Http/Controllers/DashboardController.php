<?php

namespace App\Http\Controllers;

use App\Support\ArchiveAccess;
use App\Support\RolePermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $landingRoute = RolePermissions::landingRouteName($user);
        if ($landingRoute !== 'dashboard') {
            return redirect()->route($landingRoute);
        }

        if (! RolePermissions::allows($user, RolePermissions::DASHBOARD_VIEW)) {
            abort(403);
        }

        $year = now()->year;
        $visibleArchives = fn () => ArchiveAccess::scope(DB::table('arsip as a'), $user);

        $totalArchives = (int) $visibleArchives()->count();
        $thisMonth = (int) $visibleArchives()
            ->whereYear('a.tanggal_masuk', $year)
            ->whereMonth('a.tanggal_masuk', now()->month)
            ->count();
        $thisYear = (int) $visibleArchives()
            ->whereYear('a.tanggal_masuk', $year)
            ->count();

        $statusCounts = $visibleArchives()
            ->select('a.status')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('a.status')
            ->pluck('total', 'a.status')
            ->map(fn ($total): int => (int) $total)
            ->all();

        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', a.tanggal_masuk) AS INTEGER)"
            : 'MONTH(a.tanggal_masuk)';

        $monthlyCounts = $visibleArchives()
            ->selectRaw($monthExpression.' AS month_number, COUNT(*) AS total')
            ->whereYear('a.tanggal_masuk', $year)
            ->groupByRaw($monthExpression)
            ->pluck('total', 'month_number');

        $monthly = array_fill(1, 12, 0);
        foreach ($monthlyCounts as $month => $total) {
            $monthly[(int) $month] = (int) $total;
        }

        $loanMonthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', p.tanggal_pinjam) AS INTEGER)"
            : 'MONTH(p.tanggal_pinjam)';
        $monthlyLoanCounts = DB::table('peminjaman as p')
            ->join('arsip as a', 'a.id', '=', 'p.arsip_id')
            ->tap(fn ($query) => ArchiveAccess::scope($query, $user, 'a'))
            ->selectRaw($loanMonthExpression.' AS month_number, COUNT(*) AS total')
            ->whereYear('p.tanggal_pinjam', $year)
            ->groupByRaw($loanMonthExpression)
            ->pluck('total', 'month_number');
        $monthlyLoans = array_fill(1, 12, 0);
        foreach ($monthlyLoanCounts as $month => $total) {
            $monthlyLoans[(int) $month] = (int) $total;
        }

        $activeLoans = DB::table('peminjaman as p')
            ->join('arsip as a', 'a.id', '=', 'p.arsip_id')
            ->tap(fn ($query) => ArchiveAccess::scope($query, $user, 'a'))
            ->where('p.status', 'Dipinjam')
            ->count();
        $overdueLoans = DB::table('peminjaman as p')
            ->join('arsip as a', 'a.id', '=', 'p.arsip_id')
            ->tap(fn ($query) => ArchiveAccess::scope($query, $user, 'a'))
            ->where('p.status', 'Dipinjam')
            ->whereNotNull('p.tanggal_rencana_kembali')
            ->whereDate('p.tanggal_rencana_kembali', '<', today())
            ->count();

        $categoryDistribution = $visibleArchives()
            ->join('kategori_arsip as k', 'k.id', '=', 'a.kategori_id')
            ->select('k.nama_kategori')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('k.id', 'k.nama_kategori')
            ->orderByDesc('total')
            ->orderBy('k.nama_kategori')
            ->limit(6)
            ->get()
            ->map(static fn ($category): array => [
                'name' => (string) $category->nama_kategori,
                'total' => (int) $category->total,
            ])
            ->all();
        $categorizedArchives = array_sum(array_column($categoryDistribution, 'total'));
        $otherCategories = max(0, $totalArchives - $categorizedArchives);
        if ($otherCategories > 0) {
            $categoryDistribution[] = ['name' => 'Kategori lainnya', 'total' => $otherCategories];
        }

        $recentArchives = $visibleArchives()
            ->leftJoin('kategori_arsip as k', 'k.id', '=', 'a.kategori_id')
            ->select([
                'a.id',
                'a.qr_code',
                'a.nomor_berkas',
                'a.nama_berkas',
                'a.tanggal_masuk',
                'a.status',
                'k.nama_kategori',
            ])
            ->orderByDesc('a.created_at')
            ->orderByDesc('a.id')
            ->limit(6)
            ->get();
        $locationCount = (int) $visibleArchives()
            ->distinct()
            ->count('a.lokasi_id');

        return view('dashboard.home', [
            'totalArchives' => $totalArchives,
            'thisMonth' => $thisMonth,
            'thisYear' => $thisYear,
            'statusCounts' => $statusCounts,
            'locationCount' => $locationCount,
            'monthly' => $monthly,
            'monthlyLoans' => $monthlyLoans,
            'activeLoans' => $activeLoans,
            'overdueLoans' => $overdueLoans,
            'categoryDistribution' => $categoryDistribution,
            'recentArchives' => $recentArchives,
            'year' => $year,
            'currentMonth' => now()->month,
            'canViewReports' => RolePermissions::allows($user, RolePermissions::REPORTS_VIEW),
            'canManageArchives' => ArchiveAccess::canManage($user),
            'canManageLoans' => RolePermissions::allows($user, RolePermissions::LOANS_MANAGE),
            'canManageQrLabels' => RolePermissions::allows($user, RolePermissions::QR_MANAGE),
        ]);
    }
}
