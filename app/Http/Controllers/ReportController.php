<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ArchiveAccess;
use App\Support\RolePermissions;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;
use ZipArchive;

class ReportController extends Controller
{
    private const STATUSES = ['Tersimpan', 'Dipinjam', 'Diproses', 'Hilang', 'Nonaktif'];

    public function index(Request $request): View
    {
        $this->authorizeReports($request);
        $filters = $this->filters($request);
        $categories = DB::table('kategori_arsip');
        $locations = DB::table('lokasi');
        if (RolePermissions::isDivisionScoped($request->user())) {
            $visibleArchives = ArchiveAccess::scope(DB::table('arsip as a'), $request->user());
            $categories->whereIn('id', (clone $visibleArchives)->select('a.kategori_id'));
            $locations->whereIn('id', (clone $visibleArchives)->select('a.lokasi_id'));
        }

        return view('reports.index', [
            'archives' => $this->reportQuery($filters, $request->user())->get(),
            'categories' => $categories->orderBy('nama_kategori')->get(['id', 'nama_kategori']),
            'locations' => $locations->orderBy('gudang')->orderBy('rak')->orderBy('lemari')->orderBy('box')->get(),
            'filters' => $filters,
            'statuses' => self::STATUSES,
            'monthNames' => $this->monthNames(),
        ]);
    }

    public function print(Request $request): View
    {
        $this->authorizeReports($request);
        $filters = $this->filters($request);

        return view('reports.print', [
            'archives' => $this->reportQuery($filters, $request->user())->get(),
            'filters' => $filters,
            'monthNames' => $this->monthNames(),
            'generatedAt' => now(),
            'user' => $request->user(),
        ]);
    }

    public function export(Request $request)
    {
        $this->authorizeReports($request);
        $filters = $this->filters($request);
        $type = $request->query('type', 'xlsx');
        abort_unless(in_array($type, ['csv', 'xlsx', 'pdf'], true), 422, 'Jenis ekspor tidak didukung.');

        $archives = $this->reportQuery($filters, $request->user())->get();
        $headings = ['QR ID', 'Nomor Berkas', 'Nama/Judul', 'Kode', 'Kategori', 'Tahun', 'Bulan', 'Tanggal Masuk', 'Lokasi', 'Status'];

        if ($type === 'pdf') {
            $logoPath = public_path('images/bakti-logo-pdf.jpg');
            if (! is_file($logoPath) || ! is_readable($logoPath)) {
                throw new RuntimeException('Logo BAKTI tidak ditemukan atau tidak dapat dibaca.');
            }

            $logoContents = file_get_contents($logoPath);
            if ($logoContents === false) {
                throw new RuntimeException('Gagal membaca file logo BAKTI.');
            }

            return Pdf::loadView('reports.pdf', [
                'archives' => $archives,
                'filters' => $filters,
                'generatedAt' => now()->timezone(config('app.timezone')),
                'user' => $request->user(),
                'logoDataUri' => 'data:image/jpeg;base64,'.base64_encode($logoContents),
            ])->setPaper('a4', 'landscape')->download('laporan-arsip.pdf');
        }

        if ($type === 'csv') {
            return response()->streamDownload(function () use ($archives, $headings): void {
                $output = fopen('php://output', 'w');
                if ($output === false) {
                    throw new RuntimeException('Gagal menyiapkan file CSV.');
                }

                echo "\xEF\xBB\xBF";
                fputcsv($output, $headings, ';');
                foreach ($archives as $archive) {
                    fputcsv($output, $this->exportRow($archive), ';');
                }
                fclose($output);
            }, 'laporan-arsip.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        abort_unless(class_exists(ZipArchive::class), 500, 'Ekstensi PHP ZIP diperlukan untuk membuat file Excel.');

        $tempPath = tempnam(storage_path('framework'), 'arsip-report-');
        if ($tempPath === false) {
            throw new RuntimeException('Gagal menyiapkan file Excel sementara.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tempPath, ZipArchive::OVERWRITE) !== true) {
            unlink($tempPath);
            throw new RuntimeException('Gagal membuat file Excel.');
        }

        $rows = [$headings];
        foreach ($archives as $archive) {
            $rows[] = $this->exportRow($archive);
        }
        $entries = [
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->rootRelationshipsXml(),
            'xl/workbook.xml' => $this->workbookXml(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationshipsXml(),
            'xl/worksheets/sheet1.xml' => $this->worksheetXml($rows),
        ];
        foreach ($entries as $name => $contents) {
            if (! $zip->addFromString($name, $contents)) {
                $zip->close();
                unlink($tempPath);
                throw new RuntimeException('Gagal menulis bagian file Excel: '.$name);
            }
        }
        if (! $zip->close()) {
            unlink($tempPath);
            throw new RuntimeException('Gagal menyelesaikan file Excel.');
        }

        return response()->download(
            $tempPath,
            'laporan-arsip.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    private function authorizeReports(Request $request): void
    {
        abort_unless(RolePermissions::allows($request->user(), RolePermissions::REPORTS_VIEW), 403);
    }

    private function filters(Request $request): array
    {
        $categoryRules = ['nullable', 'integer', 'min:0'];
        if ($request->filled('kategori') && (int) $request->input('kategori') > 0) {
            $categoryRules[] = 'exists:kategori_arsip,id';
        }

        $locationRules = ['nullable', 'integer', 'min:0'];
        if ($request->filled('lokasi') && (int) $request->input('lokasi') > 0) {
            $locationRules[] = 'exists:lokasi,id';
        }

        $validated = $request->validate([
            'tahun' => ['nullable', 'integer', 'between:1901,2155'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'status' => ['nullable', 'string', Rule::in(self::STATUSES)],
            'kategori' => $categoryRules,
            'lokasi' => $locationRules,
        ]);

        return [
            'tahun' => $validated['tahun'] ?? null,
            'bulan' => $validated['bulan'] ?? null,
            'status' => $validated['status'] ?? null,
            'kategori' => ($validated['kategori'] ?? 0) > 0 ? (int) $validated['kategori'] : null,
            'lokasi' => ($validated['lokasi'] ?? 0) > 0 ? (int) $validated['lokasi'] : null,
        ];
    }

    private function reportQuery(array $filters, User $user): Builder
    {
        $query = DB::table('arsip as a')
            ->leftJoin('kategori_arsip as k', 'k.id', '=', 'a.kategori_id')
            ->leftJoin('lokasi as l', 'l.id', '=', 'a.lokasi_id')
            ->select([
                'a.qr_code',
                'a.nomor_berkas',
                'a.nama_berkas',
                'a.kode_arsip',
                'a.tahun',
                'a.bulan',
                'a.tanggal_masuk',
                'a.status',
                'k.nama_kategori',
                'l.gudang',
                'l.rak',
                'l.lemari',
                'l.box',
            ]);
        ArchiveAccess::scope($query, $user, 'a');

        if ($filters['tahun'] !== null) {
            $query->whereYear('a.tanggal_masuk', $filters['tahun']);
        }
        if ($filters['bulan'] !== null) {
            $query->whereMonth('a.tanggal_masuk', $filters['bulan']);
        }
        if ($filters['status'] !== null) {
            $query->where('a.status', $filters['status']);
        }
        if ($filters['kategori'] !== null) {
            $query->where('a.kategori_id', $filters['kategori']);
        }
        if ($filters['lokasi'] !== null) {
            $query->where('a.lokasi_id', $filters['lokasi']);
        }

        return $query->orderByDesc('a.tanggal_masuk')->orderByDesc('a.id');
    }

    private function exportRow(object $archive): array
    {
        return [
            $archive->qr_code,
            $archive->nomor_berkas,
            $archive->nama_berkas,
            $archive->kode_arsip,
            $archive->nama_kategori ?? '',
            $archive->tahun,
            $archive->bulan,
            $archive->tanggal_masuk,
            implode(' / ', array_filter([$archive->gudang, $archive->rak, $archive->lemari, $archive->box])),
            $archive->status,
        ];
    }

    private function monthNames(): array
    {
        return [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Laporan Arsip" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';
    }

    private function worksheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rowIndex => $values) {
            $excelRow = $rowIndex + 1;
            $xml .= '<row r="'.$excelRow.'">';
            foreach (array_values($values) as $columnIndex => $value) {
                $column = '';
                for ($number = $columnIndex + 1; $number > 0; $number = intdiv($number - 1, 26)) {
                    $column = chr(65 + (($number - 1) % 26)).$column;
                }
                $cell = $column.$excelRow;
                $text = htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                $xml .= '<c r="'.$cell.'" t="inlineStr"><is><t>'.$text.'</t></is></c>';
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }
}
