<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ArchiveAccess;
use App\Support\RolePermissions;
use App\Support\ArchiveQr;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class ImportExportController extends Controller
{
    private const REQUIRED_COLUMNS = [
        'kategori' => ['nama_kategori'],
        'lokasi' => ['gudang', 'rak', 'lemari', 'box'],
        'arsip' => ['nomor_berkas', 'nama_berkas', 'kode_arsip', 'nama_kategori', 'tanggal_masuk', 'gudang', 'rak', 'lemari', 'box'],
    ];

    private const EXPORT_COLUMNS = [
        'kategori' => ['nama_kategori', 'keterangan', 'status'],
        'lokasi' => ['gudang', 'rak', 'lemari', 'box', 'capacity_boxes', 'keterangan', 'status'],
        'arsip' => ['nomor_berkas', 'nama_berkas', 'kode_arsip', 'nama_kategori', 'tanggal_masuk', 'gudang', 'rak', 'lemari', 'box', 'classification', 'division', 'physical_condition', 'keterangan'],
    ];

    public function index(Request $request): View
    {
        $this->authorizeAdministrator($request->user());

        return view('imports.index', [
            'stagedImport' => $request->session()->get('csvImport'),
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $this->authorizeAdministrator($request->user());
        $validated = $request->validate([
            'type' => ['required', 'in:kategori,lokasi,arsip'],
            'csv_file' => ['required', 'file', 'max:10240', 'mimes:csv,txt'],
        ], [
            'csv_file.max' => 'Ukuran file CSV maksimal 10 MB.',
            'csv_file.mimes' => 'Pilih file berformat CSV.',
        ]);

        $file = $request->file('csv_file');
        if ($file === null || ! $file->isValid()) {
            return back()->withErrors(['csv_file' => 'Unggahan gagal atau tidak lengkap. Pilih file kembali.']);
        }

        $this->discardStagedImport($request);
        $path = $file->storeAs('imports', Str::uuid().'.csv', 'local');
        if ($path === false) {
            throw new RuntimeException('File impor tidak dapat disimpan sementara.');
        }

        $parsed = $this->readCsv(Storage::disk('local')->path($path), $validated['type'], $request->user());
        if ($parsed['fatal'] !== null) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['csv_file' => $parsed['fatal']]);
        }

        $request->session()->put('csvImport', [
            'path' => $path,
            'type' => $validated['type'],
            'name' => basename($file->getClientOriginalName()),
        ]);

        return redirect()->route('imports.index')->with('csvImportPreview', [
            'type' => $validated['type'],
            'name' => basename($file->getClientOriginalName()),
            'valid' => count(array_filter($parsed['rows'], fn (array $row): bool => $row['error'] === null)),
            'errors' => array_values(array_filter(array_column($parsed['rows'], 'error'))),
            'rowCount' => count($parsed['rows']),
        ]);
    }

    public function commit(Request $request): RedirectResponse
    {
        $this->authorizeAdministrator($request->user());
        $staged = $request->session()->get('csvImport');
        abort_unless(
            is_array($staged)
            && isset($staged['path'], $staged['type'])
            && in_array($staged['type'], array_keys(self::REQUIRED_COLUMNS), true)
            && str_starts_with($staged['path'], 'imports/')
            && Storage::disk('local')->exists($staged['path']),
            422,
            'Berkas pratinjau sudah tidak tersedia. Unggah kembali untuk membuat pratinjau.'
        );

        $parsed = $this->readCsv(Storage::disk('local')->path($staged['path']), $staged['type'], $request->user());
        if ($parsed['fatal'] !== null) {
            return back()->withErrors(['csv_file' => $parsed['fatal']]);
        }

        $counts = ['imported' => 0, 'updated' => 0, 'skipped' => 0];
        $rowErrors = array_values(array_filter(array_column($parsed['rows'], 'error')));

        DB::transaction(function () use ($parsed, $request, $staged, &$counts, &$rowErrors): void {
            foreach ($parsed['rows'] as $row) {
                if ($row['error'] !== null) {
                    $counts['skipped']++;
                    continue;
                }

                try {
                    $action = match ($staged['type']) {
                        'kategori' => $this->importCategory($row['data']),
                        'lokasi' => $this->importLocation($row['data']),
                        'arsip' => $this->importArchive($row['data'], $request->user()),
                    };
                    $counts[$action]++;
                } catch (QueryException) {
                    $counts['skipped']++;
                    $rowErrors[] = "Baris {$row['line']}: gagal disimpan karena kendala database; baris ini dilewati.";
                }
            }
        });

        $this->discardStagedImport($request);

        return redirect()->route('imports.index')->with('csvImportResult', [
            ...$counts,
            'errors' => $rowErrors,
        ]);
    }

    public function discard(Request $request): RedirectResponse
    {
        $this->authorizeAdministrator($request->user());
        $this->discardStagedImport($request);

        return redirect()->route('imports.index')->with('success', 'Pratinjau impor dibatalkan.');
    }

    public function export(Request $request, string $type)
    {
        $this->authorizeAdministrator($request->user());
        abort_unless(isset(self::EXPORT_COLUMNS[$type]), 404);

        $queries = [
            'kategori' => DB::table('kategori_arsip')->select(self::EXPORT_COLUMNS['kategori'])->orderBy('nama_kategori'),
            'lokasi' => DB::table('lokasi')->select(self::EXPORT_COLUMNS['lokasi'])->orderBy('gudang')->orderBy('rak')->orderBy('lemari')->orderBy('box'),
            'arsip' => $this->archiveExportQuery($request->user()),
        ];
        $columns = self::EXPORT_COLUMNS[$type];
        $filename = 'arsip-bakti-'.$type.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($queries, $columns, $type): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new RuntimeException('Ekspor CSV gagal dibuka.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $columns);
            foreach ($queries[$type]->cursor() as $record) {
                fputcsv($output, array_map(fn (string $column) => $record->{$column}, $columns));
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function readCsv(string $path, string $type, User $user): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return ['fatal' => 'File CSV tidak dapat dibaca.', 'rows' => []];
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false || $header === [null]) {
                return ['fatal' => 'File CSV kosong.', 'rows' => []];
            }
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            $headers = array_map(fn ($column): string => strtolower(trim((string) $column)), $header);
            if (count(array_unique($headers)) !== count($headers)) {
                return ['fatal' => 'Nama kolom CSV tidak boleh duplikat.', 'rows' => []];
            }

            $missing = array_diff(self::REQUIRED_COLUMNS[$type], $headers);
            if ($missing !== []) {
                return ['fatal' => 'Kolom wajib tidak ditemukan: '.implode(', ', $missing).'.', 'rows' => []];
            }

            $rows = [];
            $line = 1;
            $seenArchiveNumbers = [];
            while (($values = fgetcsv($handle)) !== false) {
                $line++;
                if (count($values) === 1 && trim((string) $values[0]) === '') {
                    continue;
                }
                if (count($values) !== count($headers)) {
                    $rows[] = ['line' => $line, 'data' => [], 'error' => "Baris {$line}: jumlah kolom tidak sama dengan header."];
                    continue;
                }

                $data = array_combine($headers, array_map(fn ($value): string => trim((string) $value), $values));
                $error = $this->validateRow($type, $data, $line, $user);
                if ($type === 'arsip' && $error === null) {
                    $number = $data['nomor_berkas'];
                    if (isset($seenArchiveNumbers[$number])) {
                        $error = "Baris {$line}: nomor_berkas duplikat di dalam CSV; baris pertama dipertahankan.";
                    } else {
                        $seenArchiveNumbers[$number] = true;
                    }
                }

                $rows[] = [
                    'line' => $line,
                    'data' => $data,
                    'error' => $error,
                ];
            }

            return ['fatal' => null, 'rows' => $rows];
        } finally {
            fclose($handle);
        }
    }

    private function validateRow(string $type, array $row, int $line, User $user): ?string
    {
        $fail = fn (string $message): string => "Baris {$line}: {$message}";

        if ($type === 'kategori') {
            return trim($row['nama_kategori'] ?? '') === '' || mb_strlen($row['nama_kategori']) > 150
                ? $fail('nama_kategori wajib diisi dan maksimal 150 karakter.')
                : null;
        }

        if ($type === 'lokasi') {
            foreach (['gudang', 'rak', 'lemari', 'box'] as $field) {
                if (trim($row[$field] ?? '') === '') {
                    return $fail("{$field} wajib diisi.");
                }
            }
            if (mb_strlen($row['gudang']) > 100 || mb_strlen($row['rak']) > 100 || mb_strlen($row['lemari']) > 100 || mb_strlen($row['box']) > 100) {
                return $fail('Panjang komponen lokasi melebihi batas kolom database.');
            }
            $capacity = trim($row['capacity_boxes'] ?? '');
            if ($capacity !== '' && (! ctype_digit($capacity) || (int) $capacity < 1)) {
                return $fail('capacity_boxes harus angka lebih dari nol.');
            }

            return null;
        }

        foreach (self::REQUIRED_COLUMNS['arsip'] as $field) {
            if (trim($row[$field] ?? '') === '') {
                return $fail("{$field} wajib diisi.");
            }
        }
        if (mb_strlen($row['nomor_berkas']) > 150 || mb_strlen($row['nama_berkas']) > 255 || mb_strlen($row['kode_arsip']) > 150) {
            return $fail('Panjang nomor, nama, atau kode arsip melebihi batas database.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $row['tanggal_masuk']);
        if ($date === false || $date->format('Y-m-d') !== $row['tanggal_masuk'] || (int) $date->format('Y') < 1901 || (int) $date->format('Y') > 2155) {
            return $fail('tanggal_masuk harus berformat YYYY-MM-DD dan berada pada rentang tahun arsip.');
        }
        if (DB::table('arsip')->where('nomor_berkas', $row['nomor_berkas'])->exists()) {
            return $fail('nomor_berkas sudah ada; baris dilewati agar tidak membuat data ganda.');
        }
        $classification = $row['classification'] ?? 'Internal';
        if (! in_array($classification, ['Publik', 'Internal', 'Internal Divisi', 'Dokumen Rahasia'], true)) {
            return $fail('classification tidak valid.');
        }
        if ($classification === 'Internal Divisi' && trim($row['division'] ?? '') === '') {
            return $fail('division wajib untuk Internal Divisi.');
        }
        if (
            $classification === 'Dokumen Rahasia'
            && ! RolePermissions::allows($user, RolePermissions::ARCHIVES_MANAGE_CONFIDENTIAL)
        ) {
            return $fail('hanya Super Admin yang dapat mengimpor Dokumen Rahasia.');
        }
        $condition = trim($row['physical_condition'] ?? '') ?: 'Baik';
        if (! in_array($condition, ['Baik', 'Rusak Ringan', 'Rusak Berat', 'Tidak Lengkap'], true)) {
            return $fail('physical_condition tidak valid.');
        }
        $categoryExists = DB::table('kategori_arsip')
            ->where('nama_kategori', $row['nama_kategori'])
            ->where('status', 'Aktif')
            ->exists();
        $locationExists = DB::table('lokasi')
            ->where('gudang', $row['gudang'])
            ->where('rak', $row['rak'])
            ->where('lemari', $row['lemari'])
            ->where('box', $row['box'])
            ->where('status', 'Aktif')
            ->exists();
        if (! $categoryExists || ! $locationExists) {
            return $fail('Kategori atau lokasi aktif belum ada. Impor kategori dan lokasi terlebih dahulu.');
        }

        return null;
    }

    private function importCategory(array $row): string
    {
        $existing = DB::table('kategori_arsip')->where('nama_kategori', $row['nama_kategori'])->lockForUpdate()->first();
        if ($existing !== null) {
            DB::table('kategori_arsip')->where('id', $existing->id)->update([
                'keterangan' => $row['keterangan'] ?? null,
                'updated_at' => now(),
            ]);

            return 'updated';
        }

        DB::table('kategori_arsip')->insert([
            'nama_kategori' => $row['nama_kategori'],
            'keterangan' => $row['keterangan'] ?? null,
            'status' => 'Aktif',
            'created_at' => now(),
        ]);

        return 'imported';
    }

    private function importLocation(array $row): string
    {
        $location = array_intersect_key($row, array_flip(['gudang', 'rak', 'lemari', 'box']));
        $existing = DB::table('lokasi')->where($location)->lockForUpdate()->first();
        $values = [
            'capacity_boxes' => ($row['capacity_boxes'] ?? '') !== '' ? (int) $row['capacity_boxes'] : null,
            'keterangan' => $row['keterangan'] ?? null,
            'updated_at' => now(),
        ];
        if ($existing !== null) {
            DB::table('lokasi')->where('id', $existing->id)->update($values);

            return 'updated';
        }

        DB::table('lokasi')->insert($location + $values + ['status' => 'Aktif', 'created_at' => now()]);

        return 'imported';
    }

    private function importArchive(array $row, User $user): string
    {
        $date = new \DateTimeImmutable($row['tanggal_masuk']);
        $categoryId = DB::table('kategori_arsip')->where('nama_kategori', $row['nama_kategori'])->where('status', 'Aktif')->value('id');
        $locationId = DB::table('lokasi')
            ->where('gudang', $row['gudang'])->where('rak', $row['rak'])->where('lemari', $row['lemari'])->where('box', $row['box'])
            ->where('status', 'Aktif')->value('id');
        if ($categoryId === null || $locationId === null) {
            throw new RuntimeException('Relasi kategori/lokasi berubah setelah pratinjau; buat pratinjau kembali.');
        }

        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');
        $qrCode = ArchiveQr::next($user->id, $year, $month);
        $archiveId = DB::table('arsip')->insertGetId([
            'nomor_berkas' => $row['nomor_berkas'],
            'nama_berkas' => $row['nama_berkas'],
            'kode_arsip' => $row['kode_arsip'],
            'kategori_id' => $categoryId,
            'tahun' => $year,
            'bulan' => $month,
            'tanggal_masuk' => $date->format('Y-m-d'),
            'lokasi_id' => $locationId,
            'classification' => $row['classification'] ?? 'Internal',
            'division' => ($row['classification'] ?? 'Internal') === 'Internal Divisi' ? trim($row['division']) : null,
            'physical_condition' => trim($row['physical_condition'] ?? '') ?: 'Baik',
            'status' => 'Tersimpan',
            'qr_code' => $qrCode,
            'keterangan' => $row['keterangan'] ?? null,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('riwayat_arsip')->insert([
            'arsip_id' => $archiveId,
            'user_id' => $user->id,
            'aktivitas' => 'Mengimpor arsip',
            'keterangan' => 'Import CSV; QR ID: '.$qrCode,
            'created_at' => now(),
        ]);

        return 'imported';
    }

    private function discardStagedImport(Request $request): void
    {
        $staged = $request->session()->pull('csvImport');
        if (is_array($staged) && isset($staged['path']) && str_starts_with($staged['path'], 'imports/')) {
            Storage::disk('local')->delete($staged['path']);
        }
    }

    private function authorizeAdministrator(User $user): void
    {
        abort_unless(RolePermissions::allows($user, RolePermissions::IMPORTS_MANAGE), 403);
    }

    private function archiveExportQuery(User $user): Builder
    {
        $query = DB::table('arsip as a')
            ->join('kategori_arsip as k', 'k.id', '=', 'a.kategori_id')
            ->join('lokasi as l', 'l.id', '=', 'a.lokasi_id')
            ->select([
                'a.nomor_berkas', 'a.nama_berkas', 'a.kode_arsip', 'k.nama_kategori', 'a.tanggal_masuk',
                'l.gudang', 'l.rak', 'l.lemari', 'l.box', 'a.classification', 'a.division',
                'a.physical_condition', 'a.keterangan',
            ])
            ->orderBy('a.id');
        ArchiveAccess::scope($query, $user, 'a');

        return $query;
    }
}
