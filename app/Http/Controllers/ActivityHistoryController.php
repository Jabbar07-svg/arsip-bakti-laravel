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
