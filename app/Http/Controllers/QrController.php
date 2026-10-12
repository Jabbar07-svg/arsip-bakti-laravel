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

class QrController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorizeManager($user);
        $filters = $request->validate([
            'tahun' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'bulan' => ['nullable', 'integer', 'min:0', 'max:12'],
            'kategori' => ['nullable', 'integer', 'exists:kategori_arsip,id'],
        ]);
        $year = (int) ($filters['tahun'] ?? now()->year);
        $month = (int) ($filters['bulan'] ?? 0);
        $categoryId = (int) ($filters['kategori'] ?? 0);
        $categories = DB::table('kategori_arsip');
        if (RolePermissions::isDivisionScoped($user)) {
            $visibleArchives = ArchiveAccess::scope(DB::table('arsip as visible'), $user, 'visible');
            $categories->whereIn('id', (clone $visibleArchives)->select('visible.kategori_id'));
        }
        $query = ArchiveAccess::scope(DB::table('arsip as a'), $user)
            ->join('lokasi as l', 'l.id', '=', 'a.lokasi_id')
            ->leftJoin('kategori_arsip as k', 'k.id', '=', 'a.kategori_id')
            ->where('a.tahun', $year);
        if ($month > 0) {
            $query->where('a.bulan', $month);
        }
        if ($categoryId > 0) {
            $query->where('a.kategori_id', $categoryId);
        }
        $archiveCount = (clone $query)->count();
        $selectedColumns = [
            'a.id', 'a.qr_code', 'a.nama_berkas', 'a.nomor_berkas', 'a.status',
            'a.tanggal_masuk', 'l.gudang', 'l.rak', 'l.lemari', 'l.box', 'k.nama_kategori',
        ];
        $printAll = $request->boolean('cetak');
        $query->select($selectedColumns)->orderBy('a.bulan')->orderBy('a.id');
        $archives = $printAll
            ? $query->get()
            : $query->paginate(48)->withQueryString();

        return view('qr.index', [
            'archives' => $archives,
            'archiveCount' => $archiveCount,
            'categories' => $categories->orderBy('nama_kategori')->get(),
            'filters' => compact('year', 'month', 'categoryId'),
            'printAll' => $printAll,
        ]);
    }

    public function scanner(Request $request): View
    {
        abort_unless(RolePermissions::allows($request->user(), RolePermissions::QR_SCAN), 403);

        return view('qr.scanner');
    }

    public function lookup(Request $request): RedirectResponse|View
    {
        abort_unless(RolePermissions::allows($request->user(), RolePermissions::QR_SCAN), 403);
        $validated = $request->validate(['code' => ['required', 'string', 'max:180']]);
        $code = trim($validated['code']);
        if (preg_match('/\ABOX-(\d{8})\z/i', $code, $matches) === 1) {
            $this->authorizeManager($request->user());
            $locationId = (int) $matches[1];
            abort_unless(DB::table('lokasi')->where('id', $locationId)->exists(), 404);

            return redirect()->route('boxes.show', $locationId);
        }

        $archive = DB::table('arsip')->where(function (Builder $matching) use ($code): void {
            $matching->where('qr_code', $code)->orWhere('nomor_berkas', $code);
            if (ctype_digit($code)) {
                $matching->orWhere('id', (int) $code);
            }
        })->first();

        if ($archive === null) {
            return response()->view('qr.not-found', ['code' => $code], 404);
        }
        abort_unless(ArchiveAccess::canView($request->user(), $archive), 403);
        DB::table('riwayat_arsip')->insert([
            'arsip_id' => $archive->id,
            'user_id' => $request->user()->id,
            'aktivitas' => 'Scan QR',
            'keterangan' => 'Kode: '.$code,
            'created_at' => now(),
        ]);

        return redirect()->route('archives.show', $archive->id);
    }

    public function archiveLabel(Request $request, int $archive): View
    {
        $this->authorizeManager($request->user());
        $record = DB::table('arsip as a')
            ->join('lokasi as l', 'l.id', '=', 'a.lokasi_id')
            ->where('a.id', $archive)
            ->select(['a.*', 'l.gudang', 'l.rak', 'l.lemari', 'l.box'])
            ->first();
        abort_if($record === null, 404);
        abort_unless(ArchiveAccess::canView($request->user(), $record), 403);
        DB::table('riwayat_arsip')->insert([
            'arsip_id' => $archive,
            'user_id' => $request->user()->id,
            'aktivitas' => 'Mencetak QR',
            'keterangan' => 'Membuka label QR arsip.',
            'created_at' => now(),
        ]);

        return view('qr.archive-label', ['archive' => $record]);
    }

    public function box(Request $request, int $location): View
    {
        $this->authorizeManager($request->user());
        $place = DB::table('lokasi')->where('id', $location)->first();
        abort_if($place === null, 404);
        $visibleArchives = ArchiveAccess::scope(DB::table('arsip as visible'), $request->user(), 'visible')
            ->where('visible.lokasi_id', $location);
        if (RolePermissions::isDivisionScoped($request->user())) {
            abort_unless((clone $visibleArchives)->exists(), 404);
        }
        $archives = ArchiveAccess::scope(DB::table('arsip as a'), $request->user())
            ->leftJoin('kategori_arsip as k', 'k.id', '=', 'a.kategori_id')
            ->where('a.lokasi_id', $location)
            ->select(['a.id', 'a.qr_code', 'a.nomor_berkas', 'a.nama_berkas', 'a.status', 'k.nama_kategori'])
            ->orderBy('a.nama_berkas')
            ->paginate(30)
            ->withQueryString();

        return view('qr.box', [
            'location' => $place,
            'boxCode' => sprintf('BOX-%08d', $location),
            'archives' => $archives,
        ]);
    }

    public function boxLabel(Request $request, int $location): View
    {
        $this->authorizeManager($request->user());
        $place = DB::table('lokasi')->where('id', $location)->first();
        abort_if($place === null, 404);
        $countQuery = ArchiveAccess::scope(DB::table('arsip as a'), $request->user());
        $archiveCount = $countQuery->where('a.lokasi_id', $location)->count();
        if (RolePermissions::isDivisionScoped($request->user())) {
            abort_unless($archiveCount > 0, 404);
        }

        return view('qr.box-label', [
            'location' => $place,
            'boxCode' => sprintf('BOX-%08d', $location),
            'archiveCount' => $archiveCount,
        ]);
    }

    private function authorizeManager(User $user): void
    {
        abort_unless(RolePermissions::allows($user, RolePermissions::QR_MANAGE), 403);
    }
}
