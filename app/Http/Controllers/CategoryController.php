<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeManagement($request->user());

        $categories = DB::table('kategori_arsip as k')
            ->select('k.*')
            ->selectSub(
                DB::table('arsip as a')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('a.kategori_id', 'k.id'),
                'jumlah_arsip'
            )
            ->orderBy('k.nama_kategori')
            ->get();

        return view('categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request->user());
        $validated = $request->validate([
            'nama_kategori' => ['required', 'string', 'max:150'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            DB::table('kategori_arsip')->insert([
                'nama_kategori' => trim($validated['nama_kategori']),
                'status' => 'Aktif',
                'created_at' => now(),
            ]);
            $this->addAudit($request->user()->id, 'Tambah Kategori', 'Menambahkan kategori: '.trim($validated['nama_kategori']));
        });

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function destroy(Request $request, int $category): RedirectResponse
    {
        $this->authorizeManagement($request->user());

        $message = DB::transaction(function () use ($request, $category): string {
            $record = DB::table('kategori_arsip')->where('id', $category)->lockForUpdate()->first();
            abort_if($record === null, 404);

            $archiveCount = DB::table('arsip')->where('kategori_id', $category)->count();
            if ($archiveCount > 0) {
                DB::table('kategori_arsip')->where('id', $category)->update(['status' => 'Nonaktif']);
                $activity = 'Menonaktifkan Kategori';
                $message = 'Kategori dipakai arsip, jadi dinonaktifkan agar data tetap terjaga.';
            } else {
                DB::table('kategori_arsip')->where('id', $category)->delete();
                $activity = 'Hapus Kategori';
                $message = 'Kategori dihapus.';
            }

            $this->addAudit($request->user()->id, $activity, 'ID kategori: '.$category);

            return $message;
        });

        return redirect()->route('categories.index')->with('success', $message);
    }

    private function authorizeManagement(User $user): void
    {
        abort_unless(RolePermissions::allows($user, RolePermissions::CATEGORIES_MANAGE), 403);
    }

    private function addAudit(int $userId, string $activity, string $detail): void
    {
        DB::table('riwayat_arsip')->insert([
            'arsip_id' => null,
            'user_id' => $userId,
            'aktivitas' => $activity,
            'keterangan' => $detail,
            'created_at' => now(),
        ]);
    }
}
