<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeManagement($request->user());
        $search = trim((string) $request->query('q', ''));
        $editId = (int) $request->query('edit', 0);
        $editing = $editId > 0 ? DB::table('lokasi')->where('id', $editId)->first() : null;
        abort_if($editId > 0 && $editing === null, 404);

        $query = DB::table('lokasi as l')
            ->select('l.*')
            ->selectSub(
                DB::table('arsip as a')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('a.lokasi_id', 'l.id'),
                'total_arsip'
            );

        if ($search !== '') {
            $query->where(function (Builder $matching) use ($search): void {
                foreach (['gudang', 'rak', 'lemari', 'box', 'keterangan'] as $column) {
                    $matching->orWhere('l.'.$column, 'like', '%'.$search.'%');
                }
            });
        }

        $locations = $query
            ->orderBy('l.gudang')
            ->orderBy('l.rak')
            ->orderBy('l.lemari')
            ->orderBy('l.box')
            ->get();

        return view('locations.index', [
            'locations' => $locations,
            'search' => $search,
            'editing' => $editing,
            'totalArchives' => $locations->sum(fn (object $location): int => (int) $location->total_arsip),
            'activeLocations' => $locations->where('status', 'Aktif')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request->user());
        $validated = $this->validatedLocation($request);

        DB::table('lokasi')->insert($validated + ['status' => 'Aktif']);

        return redirect()->route('locations.index')->with('success', 'Lokasi gudang berhasil ditambahkan.');
    }

    public function update(Request $request, int $location): RedirectResponse
    {
        $this->authorizeManagement($request->user());
        abort_unless(DB::table('lokasi')->where('id', $location)->exists(), 404);
        $validated = $this->validatedLocation($request, $location);
        DB::table('lokasi')->where('id', $location)->update($validated);

        return redirect()->route('locations.index')->with('success', 'Lokasi gudang berhasil diperbarui.');
    }

    public function toggleStatus(Request $request, int $location): RedirectResponse
    {
        $this->authorizeManagement($request->user());
        $record = DB::table('lokasi')->where('id', $location)->first();
        abort_if($record === null, 404);

        DB::table('lokasi')->where('id', $location)->update([
            'status' => $record->status === 'Aktif' ? 'Nonaktif' : 'Aktif',
        ]);

        return redirect()->route('locations.index')->with('success', 'Status lokasi berhasil diperbarui.');
    }

    private function validatedLocation(Request $request, ?int $locationId = null): array
    {
        $validated = $request->validate([
            'gudang' => ['required', 'string', 'max:150'],
            'rak' => ['required', 'string', 'max:100'],
            'lemari' => ['required', 'string', 'max:100'],
            'box' => [
                'required',
                'string',
                'max:100',
                Rule::unique('lokasi', 'box')
                    ->where(fn (Builder $query) => $query
                        ->where('gudang', $request->input('gudang'))
                        ->where('rak', $request->input('rak'))
                        ->where('lemari', $request->input('lemari')))
                    ->ignore($locationId),
            ],
            'capacity_boxes' => ['nullable', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ], [
            'box.unique' => 'Kombinasi gudang, rak, lemari, dan box tersebut sudah terdaftar.',
            'capacity_boxes.min' => 'Kapasitas harus lebih dari nol, atau dikosongkan jika belum diketahui.',
        ]);

        foreach (['gudang', 'rak', 'lemari', 'box'] as $field) {
            $validated[$field] = trim($validated[$field]);
        }
        $validated['keterangan'] = isset($validated['keterangan']) ? trim($validated['keterangan']) : null;
        $validated['capacity_boxes'] = isset($validated['capacity_boxes']) ? (int) $validated['capacity_boxes'] : null;

        return $validated;
    }

    private function authorizeManagement(User $user): void
    {
        abort_unless(RolePermissions::allows($user, RolePermissions::LOCATIONS_MANAGE), 403);
    }
}
