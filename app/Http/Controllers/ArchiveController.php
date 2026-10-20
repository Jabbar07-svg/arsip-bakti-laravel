<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ArchiveAccess;
use App\Support\ArchiveQr;
use App\Support\RolePermissions;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ArchiveController extends Controller
{
    private const STATUSES = ['Tersimpan', 'Dipinjam', 'Diproses', 'Hilang', 'Nonaktif'];

    private const CLASSIFICATIONS = ['Publik', 'Internal', 'Internal Divisi', 'Dokumen Rahasia'];

    private const CONDITIONS = ['Baik', 'Rusak Ringan', 'Rusak Berat', 'Tidak Lengkap'];

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(ArchiveAccess::canAccessListing($user), 403);

        $query = ArchiveAccess::scope(DB::table('arsip as a'), $user);
        $query
            ->leftJoin('kategori_arsip as k', 'k.id', '=', 'a.kategori_id')
            ->leftJoin('lokasi as l', 'l.id', '=', 'a.lokasi_id');
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $locationId = $request->query('lokasi');
        if ($locationId !== null && $locationId !== '') {
            $validated = $request->validate([
                'lokasi' => ['integer', 'exists:lokasi,id'],
            ]);
            $locationId = (int) $validated['lokasi'];
            $query->where('a.lokasi_id', $locationId);
        } else {
            $locationId = null;
        }

        if ($search !== '') {
            $query->where(function (Builder $matching) use ($search): void {
                $matching
                    ->where('a.nomor_berkas', 'like', '%'.$search.'%')
                    ->orWhere('a.nama_berkas', 'like', '%'.$search.'%')
                    ->orWhere('a.kode_arsip', 'like', '%'.$search.'%')
                    ->orWhere('a.qr_code', 'like', '%'.$search.'%')
                    ->orWhere('a.division', 'like', '%'.$search.'%')
                    ->orWhere('l.gudang', 'like', '%'.$search.'%')
                    ->orWhere('l.box', 'like', '%'.$search.'%')
                    ->orWhere('k.nama_kategori', 'like', '%'.$search.'%');
            });
        }

        if (in_array($status, self::STATUSES, true)) {
            $query->where('a.status', $status);
        }

        $archiveCount = (clone $query)->count();
        $archives = $query
            ->select([
                'a.id',
                'a.nomor_berkas',
                'a.nama_berkas',
                'a.kode_arsip',
                'a.qr_code',
                'a.tanggal_masuk',
                'a.status',
                'k.nama_kategori',
                'l.gudang',
                'l.rak',
                'l.lemari',
                'l.box',
            ])
            ->orderByDesc('a.tanggal_masuk')
            ->orderByDesc('a.id')
            ->paginate(15)
            ->withQueryString();

        return view('archives.index', [
            'archives' => $archives,
            'archiveCount' => $archiveCount,
            'legacyUrl' => rtrim((string) config('app.legacy_url'), '/'),
            'canManageArchives' => ArchiveAccess::canManage($user),
            'statusOptions' => self::STATUSES,
            'locations' => DB::table('lokasi')
                ->where('status', 'Aktif')
                ->orderBy('gudang')
                ->orderBy('rak')
                ->orderBy('lemari')
                ->orderBy('box')
                ->get(),
            'filters' => ['q' => $search, 'status' => $status, 'lokasi' => $locationId],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeManagement($request->user());

        return view('archives.form', $this->formData($request->user()));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request->user());
        $validated = $this->validatedArchiveData($request);
        $uploadPaths = [];

        try {
            $archiveId = DB::transaction(function () use ($request, $validated, &$uploadPaths): int {
                $date = Carbon::createFromFormat('!Y-m-d', $validated['tanggal_masuk']);
                $year = (int) $date->format('Y');
                $month = (int) $date->format('n');
                $qrCode = ArchiveQr::next($request->user()->id, $year, $month);
                $now = now();

                $archiveId = DB::table('arsip')->insertGetId([
                    'nomor_berkas' => $validated['nomor_berkas'],
                    'nama_berkas' => $validated['nama_berkas'],
                    'kode_arsip' => $validated['kode_arsip'],
                    'kategori_id' => $validated['kategori_id'],
                    'tahun' => $year,
                    'bulan' => $month,
                    'tanggal_masuk' => $date->format('Y-m-d'),
                    'lokasi_id' => $validated['lokasi_id'],
                    'classification' => $validated['classification'],
                    'division' => $validated['division'],
                    'physical_condition' => $validated['physical_condition'],
                    'status' => $validated['status'],
                    'qr_code' => $qrCode,
                    'keterangan' => $validated['keterangan'],
                    'created_by' => $request->user()->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $this->addAudit($archiveId, $request->user()->id, 'Menambahkan arsip', 'QR ID: '.$qrCode);
                $this->storeAttachments($validated['files'], $archiveId, $request->user()->id, $uploadPaths);

                return $archiveId;
            });
        } catch (Throwable $exception) {
            $this->removeStoredFiles($uploadPaths);
            throw $exception;
        }

        return redirect()
            ->route('archives.show', $archiveId)
            ->with('success', 'Arsip berhasil ditambahkan.');
    }

    public function show(Request $request, int $archive): View
    {
        $record = DB::table('arsip as a')
            ->join('kategori_arsip as k', 'k.id', '=', 'a.kategori_id')
            ->join('lokasi as l', 'l.id', '=', 'a.lokasi_id')
            ->join('users as creator', 'creator.id', '=', 'a.created_by')
            ->select([
                'a.*',
                'k.nama_kategori',
                'l.gudang',
                'l.rak',
                'l.lemari',
                'l.box',
                'creator.nama as pembuat',
            ])
            ->where('a.id', $archive)
            ->first();

        abort_if($record === null, 404);
        abort_unless(ArchiveAccess::canView($request->user(), $record), 403);

        $history = DB::table('riwayat_arsip as h')
            ->join('users as u', 'u.id', '=', 'h.user_id')
            ->where('h.arsip_id', $archive);
        if ($request->user()->role === 'Pegawai') {
            $employee = $request->user();
            $returnedLoanDetails = DB::table('peminjaman')
                ->where('arsip_id', $archive)
                ->where('status', 'Dikembalikan')
                ->where(function (Builder $owned) use ($employee): void {
                    $owned
                        ->where('created_by', $employee->id)
                        ->orWhereIn('id', DB::table('loan_requests')
                            ->select('loan_id')
                            ->where('user_id', $employee->id)
                            ->where('status', 'Disetujui')
                            ->whereNotNull('loan_id'));
                })
                ->pluck('id')
                ->map(static fn ($id): string => 'Transaksi peminjaman #'.$id)
                ->all();

            $history->where(function (Builder $ownedActivity) use ($employee, $returnedLoanDetails): void {
                $ownedActivity->where('h.user_id', $employee->id);
                if ($returnedLoanDetails !== []) {
                    $ownedActivity->orWhere(function (Builder $returnActivity) use ($returnedLoanDetails): void {
                        $returnActivity
                            ->where('h.aktivitas', 'Mengembalikan berkas')
                            ->whereIn('h.keterangan', $returnedLoanDetails);
                    });
                }
            });
        }
        $history = $history
            ->orderByDesc('h.id')
            ->select(['h.aktivitas', 'h.keterangan', 'h.created_at', 'u.nama'])
            ->get();
        $files = DB::table('arsip_files')
            ->where('arsip_id', $archive)
            ->orderByDesc('id')
            ->get();
        $loanQuery = DB::table('peminjaman')->where('arsip_id', $archive);
        if ($request->user()->role === 'Pegawai') {
            $loanQuery->where(function (Builder $owned) use ($request): void {
                $owned
                    ->where('created_by', $request->user()->id)
                    ->orWhereIn('id', DB::table('loan_requests')
                        ->select('loan_id')
                        ->where('user_id', $request->user()->id)
                        ->where('status', 'Disetujui')
                        ->whereNotNull('loan_id'));
            });
        }
        $lastLoan = $loanQuery->orderByDesc('id')->first();

        return view('archives.show', [
            'archive' => $record,
            'history' => $history,
            'files' => $files,
            'lastLoan' => $lastLoan,
            'canManageArchives' => ArchiveAccess::canManage($request->user()),
            'canRequestLoan' => RolePermissions::allows($request->user(), RolePermissions::LOANS_REQUEST)
                && $record->classification === 'Publik'
                && $record->status === 'Tersimpan',
            'canBorrowWithoutQr' => $request->user()->role === 'Pegawai'
                && $record->status === 'Tersimpan'
                && ArchiveAccess::canView($request->user(), $record),
            'legacyUrl' => rtrim((string) config('app.legacy_url'), '/'),
        ]);
    }

    public function edit(Request $request, int $archive): View
    {
        $this->authorizeManagement($request->user());
        $record = DB::table('arsip')->where('id', $archive)->first();

        abort_if($record === null, 404);
        abort_unless(ArchiveAccess::canView($request->user(), $record), 403);

        return view('archives.form', $this->formData($request->user(), $record));
    }

    public function update(Request $request, int $archive): RedirectResponse
    {
        $this->authorizeManagement($request->user());
        $validated = $this->validatedArchiveData($request);
        $uploadPaths = [];

        try {
            DB::transaction(function () use ($request, $validated, $archive, &$uploadPaths): void {
                $old = DB::table('arsip')->where('id', $archive)->lockForUpdate()->first();

                abort_if($old === null, 404);
                abort_unless(ArchiveAccess::canView($request->user(), $old), 403);

                $changeDetails = ['Perubahan data arsip: '.$old->nama_berkas.' → '.$validated['nama_berkas']];
                if ((int) $old->lokasi_id !== (int) $validated['lokasi_id']) {
                    $locations = DB::table('lokasi')
                        ->whereIn('id', [$old->lokasi_id, $validated['lokasi_id']])
                        ->get(['id', 'gudang', 'rak', 'lemari', 'box'])
                        ->keyBy('id');
                    $oldLocation = $locations->get($old->lokasi_id);
                    $newLocation = $locations->get($validated['lokasi_id']);
                    abort_if($oldLocation === null || $newLocation === null, 409, 'Lokasi arsip tidak dapat diverifikasi.');
                    $formatLocation = static fn (object $location): string => implode(' / ', array_filter([
                        $location->gudang,
                        $location->rak,
                        $location->lemari,
                        $location->box,
                    ]));
                    $changeDetails[] = 'Pemindahan lokasi: '.$formatLocation($oldLocation).' → '.$formatLocation($newLocation);
                }

                $date = Carbon::createFromFormat('!Y-m-d', $validated['tanggal_masuk']);
                DB::table('arsip')->where('id', $archive)->update([
                    'nomor_berkas' => $validated['nomor_berkas'],
                    'nama_berkas' => $validated['nama_berkas'],
                    'kode_arsip' => $validated['kode_arsip'],
                    'kategori_id' => $validated['kategori_id'],
                    'tahun' => (int) $date->format('Y'),
                    'bulan' => (int) $date->format('n'),
                    'tanggal_masuk' => $date->format('Y-m-d'),
                    'lokasi_id' => $validated['lokasi_id'],
                    'classification' => $validated['classification'],
                    'division' => $validated['division'],
                    'physical_condition' => $validated['physical_condition'],
                    'status' => $validated['status'],
                    'keterangan' => $validated['keterangan'],
                    'updated_at' => now(),
                ]);

                $this->addAudit(
                    $archive,
                    $request->user()->id,
                    'Mengubah arsip',
                    implode('; ', $changeDetails)
                );
                $this->storeAttachments($validated['files'], $archive, $request->user()->id, $uploadPaths);
            });
        } catch (Throwable $exception) {
            $this->removeStoredFiles($uploadPaths);
            throw $exception;
        }

        return redirect()
            ->route('archives.show', $archive)
            ->with('success', 'Arsip berhasil diperbarui.');
    }

    public function attachment(Request $request, int $file): mixed
    {
        $document = DB::table('arsip_files as f')
            ->join('arsip as a', 'a.id', '=', 'f.arsip_id')
            ->where('f.id', $file)
            ->select(['f.*', 'a.classification', 'a.division'])
            ->first();

        abort_if($document === null, 404);
        abort_unless(ArchiveAccess::canView($request->user(), $document), 403);

        if (preg_match('#\Auploads/arsip/[a-f0-9]{24}\.(pdf|jpg|jpeg|png|doc|docx|xls|xlsx)\z#i', $document->file_path)) {
            $path = substr($document->file_path, strlen('uploads/'));
            $disk = Storage::disk('legacy_uploads');
        } elseif (preg_match('#\Alaravel/customer-submissions/[a-f0-9]{36}\.(pdf|jpg|jpeg|png|webp)\z#i', $document->file_path)) {
            $path = substr($document->file_path, strlen('laravel/'));
            $disk = Storage::disk('local');
        } else {
            abort(404);
        }

        abort_unless($disk->exists($path), 404);

        $stream = $disk->readStream($path);
        abort_unless(is_resource($stream), 404);

        $filename = str_replace(["\r", "\n", '"'], ['', '', '_'], basename($document->nama_file));
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response()->stream(
            static function () use ($stream): void {
                fpassthru($stream);
                fclose($stream);
            },
            200,
            [
                'Cache-Control' => 'no-store, private',
                'Content-Type' => $document->tipe_file ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Disposition' => $disposition.'; filename="'.addslashes($filename).'"',
            ]
        );
    }

    private function authorizeManagement(User $user): void
    {
        abort_unless(ArchiveAccess::canManage($user), 403);
    }

    private function formData(User $user, ?object $archive = null): array
    {
        $categories = DB::table('kategori_arsip')
            ->where(function (Builder $query) use ($archive): void {
                $query->where('status', 'Aktif');
                if ($archive !== null) {
                    $query->orWhere('id', $archive->kategori_id);
                }
            })
            ->orderBy('nama_kategori')
            ->get();
        $locations = DB::table('lokasi')
            ->where(function (Builder $query) use ($archive): void {
                $query->where('status', 'Aktif');
                if ($archive !== null) {
                    $query->orWhere('id', $archive->lokasi_id);
                }
            })
            ->orderBy('gudang')
            ->orderBy('rak')
            ->orderBy('lemari')
            ->orderBy('box')
            ->get();
        $divisions = RolePermissions::isDivisionScoped($user)
            ? (trim((string) $user->division) === '' ? [] : [trim((string) $user->division)])
            : DB::table('users')
                ->whereNotNull('division')
                ->where('division', '<>', '')
                ->distinct()
                ->orderBy('division')
                ->pluck('division')
                ->all();

        if ($user->division && ! in_array($user->division, $divisions, true)) {
            $divisions[] = $user->division;
            sort($divisions, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return [
            'archive' => $archive,
            'categories' => $categories,
            'locations' => $locations,
            'divisions' => $divisions,
            'currentDivision' => trim((string) $user->division),
            'classifications' => RolePermissions::allows($user, RolePermissions::ARCHIVES_MANAGE_CONFIDENTIAL)
                ? self::CLASSIFICATIONS
                : array_slice(self::CLASSIFICATIONS, 0, 3),
            'statuses' => self::STATUSES,
            'conditions' => self::CONDITIONS,
        ];
    }

    private function validatedArchiveData(Request $request): array
    {
        if (RolePermissions::isDivisionScoped($request->user())) {
            $request->merge(['division' => $request->user()->division]);
        }

        if (
            $request->input('classification') === 'Internal Divisi'
            && ! RolePermissions::allows($request->user(), RolePermissions::ARCHIVES_MANAGE_CONFIDENTIAL)
        ) {
            $request->merge(['division' => $request->user()->division]);
        }

        $validated = $request->validate([
            'nomor_berkas' => ['required', 'string', 'max:150'],
            'nama_berkas' => ['required', 'string', 'max:255'],
            'kode_arsip' => ['required', 'string', 'max:150'],
            'kategori_id' => ['required', 'integer', Rule::exists('kategori_arsip', 'id')->where('status', 'Aktif')],
            'lokasi_id' => ['required', 'integer', Rule::exists('lokasi', 'id')->where('status', 'Aktif')],
            'tanggal_masuk' => ['required', 'date_format:Y-m-d', 'after_or_equal:1901-01-01', 'before_or_equal:2155-12-31'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'physical_condition' => ['required', Rule::in(self::CONDITIONS)],
            'classification' => ['required', Rule::in(self::CLASSIFICATIONS)],
            'division' => ['nullable', 'required_if:classification,Internal Divisi', 'string', 'max:150'],
            'keterangan' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ], [
            'kategori_id.exists' => 'Pilih kategori yang masih aktif.',
            'lokasi_id.exists' => 'Pilih lokasi yang masih aktif.',
            'division.required_if' => 'Divisi wajib diisi untuk klasifikasi Internal Divisi.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
            'file.mimes' => 'Tipe file tidak diizinkan. Gunakan PDF, JPG, PNG, DOC, DOCX, XLS, atau XLSX.',
            'files.*.max' => 'Ukuran setiap file maksimal 10 MB.',
            'files.*.mimes' => 'Tipe file tidak diizinkan. Gunakan PDF, JPG, PNG, DOC, DOCX, XLS, atau XLSX.',
        ]);

        $uploads = $request->file('files', []);
        $validated['files'] = is_array($uploads) ? array_values($uploads) : [];
        if ($request->file('file') instanceof UploadedFile) {
            $validated['files'][] = $request->file('file');
        }

        if (
            $validated['classification'] === 'Dokumen Rahasia'
            && ! RolePermissions::allows($request->user(), RolePermissions::ARCHIVES_MANAGE_CONFIDENTIAL)
        ) {
            abort(403, 'Hanya Admin yang dapat mengatur arsip rahasia.');
        }

        if (
            $validated['classification'] !== 'Internal Divisi'
            && ! in_array($request->user()->role, ['Admin', 'Petugas Arsip'], true)
        ) {
            $validated['division'] = null;
        }

        return $validated;
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

    private function storeAttachments(array $files, int $archiveId, int $userId, array &$storedPaths): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $extension = strtolower((string) $file->guessExtension());
            if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'], true)) {
                throw ValidationException::withMessages([
                    'files' => 'Tipe file tidak diizinkan. Periksa kembali format dokumen.',
                ]);
            }

            $storedPath = $file->storeAs(
                'arsip',
                bin2hex(random_bytes(12)).'.'.$extension,
                'legacy_uploads'
            );

            if ($storedPath === false) {
                throw new RuntimeException('File tidak dapat disimpan.');
            }

            $storedPaths[] = $storedPath;
            $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
            $originalName = str_replace(["\r", "\n"], '', $originalName);
            DB::table('arsip_files')->insert([
                'arsip_id' => $archiveId,
                'nama_file' => $originalName,
                'file_path' => 'uploads/'.str_replace('\\', '/', $storedPath),
                'tipe_file' => $file->getMimeType() ?: 'application/octet-stream',
                'uploaded_by' => $userId,
                'created_at' => now(),
            ]);
        }
    }

    private function removeStoredFiles(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk('legacy_uploads')->delete($path);
        }
    }
}
