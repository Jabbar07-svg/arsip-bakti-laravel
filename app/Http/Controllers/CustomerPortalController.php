<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class CustomerPortalController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->isCustomer($request->user()), 403);
        $userId = $request->user()->id;
        $submissionStatuses = DB::table('customer_submissions')
            ->where('user_id', $userId)
            ->select('status')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total): int => (int) $total)
            ->all();
        $requestStatuses = DB::table('customer_requests')
            ->where('user_id', $userId)
            ->select('status')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total): int => (int) $total)
            ->all();
        $statuses = ['Menunggu Verifikasi', 'Disetujui', 'Ditolak', 'Perlu Perbaikan'];
        $statusCounts = [];
        foreach ($statuses as $status) {
            $statusCounts[$status] = ($submissionStatuses[$status] ?? 0) + ($requestStatuses[$status] ?? 0);
        }
        $activity = DB::table('customer_submissions')
            ->where('user_id', $userId)
            ->select(['id', 'nama_dokumen as title', 'status', 'updated_at', DB::raw("'Dokumen dikirim' as kind")])
            ->unionAll(
                DB::table('customer_requests')
                    ->where('user_id', $userId)
                    ->select(['id', 'requested_code as title', 'status', 'updated_at', DB::raw("'Permintaan pemeriksaan' as kind")])
            )
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        return view('customer.portal', [
            'publicArchiveCount' => DB::table('arsip')->where('classification', 'Publik')->count(),
            'statusCounts' => $statusCounts,
            'recentArchives' => DB::table('arsip as a')
                ->leftJoin('kategori_arsip as k', 'k.id', '=', 'a.kategori_id')
                ->where('a.classification', 'Publik')
                ->select(['a.id', 'a.qr_code', 'a.nomor_berkas', 'a.nama_berkas', 'a.kode_arsip', 'a.tanggal_masuk', 'a.status', 'k.nama_kategori'])
                ->orderByDesc('a.tanggal_masuk')
                ->orderByDesc('a.id')
                ->limit(4)
                ->get(),
            'recentActivity' => $activity,
        ]);
    }

    public function archives(Request $request): View
    {
        abort_unless($this->isCustomer($request->user()), 403);

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $categoryId = $request->query('kategori');
        $from = $request->query('dari');
        $to = $request->query('sampai');
        $query = DB::table('arsip as a')
            ->leftJoin('kategori_arsip as k', 'k.id', '=', 'a.kategori_id')
            ->where('a.classification', 'Publik');

        if ($search !== '') {
            $query->where(function (Builder $matching) use ($search): void {
                $matching->where('a.nomor_berkas', 'like', '%'.$search.'%')
                    ->orWhere('a.nama_berkas', 'like', '%'.$search.'%')
                    ->orWhere('a.kode_arsip', 'like', '%'.$search.'%')
                    ->orWhere('a.qr_code', 'like', '%'.$search.'%')
                    ->orWhere('k.nama_kategori', 'like', '%'.$search.'%');
            });
        }
        if (in_array($status, ['Tersimpan', 'Dipinjam', 'Diproses', 'Hilang', 'Nonaktif'], true)) {
            $query->where('a.status', $status);
        } else {
            $status = '';
        }
        if ($categoryId !== null && $categoryId !== '') {
            $validated = $request->validate(['kategori' => ['integer', 'exists:kategori_arsip,id']]);
            $categoryId = (int) $validated['kategori'];
            $query->where('a.kategori_id', $categoryId);
        } else {
            $categoryId = null;
        }
        if ($from !== null && $from !== '') {
            $validated = $request->validate(['dari' => ['date_format:Y-m-d']]);
            $from = $validated['dari'];
            $query->whereDate('a.tanggal_masuk', '>=', $from);
        }
        if ($to !== null && $to !== '') {
            $validated = $request->validate(['sampai' => ['date_format:Y-m-d']]);
            $to = $validated['sampai'];
            $query->whereDate('a.tanggal_masuk', '<=', $to);
        }

        $archives = $query->select([
                'a.id', 'a.qr_code', 'a.nomor_berkas', 'a.nama_berkas', 'a.kode_arsip',
                'a.tanggal_masuk', 'a.status', 'k.nama_kategori',
            ])->orderByDesc('a.tanggal_masuk')->orderByDesc('a.id');
        $archiveCount = (clone $archives)->count();

        return view('customer.archives', [
            'archives' => $archives->paginate(12)->withQueryString(),
            'archiveCount' => $archiveCount,
            'categories' => DB::table('kategori_arsip')->where('status', 'Aktif')->orderBy('nama_kategori')->get(),
            'filters' => compact('search', 'status', 'categoryId', 'from', 'to'),
        ]);
    }

    public function submissionForm(Request $request): View
    {
        abort_unless($this->isCustomer($request->user()), 403);

        return view('customer.submit', [
            'categories' => DB::table('kategori_arsip')->where('status', 'Aktif')->orderBy('nama_kategori')->get(),
        ]);
    }

    public function accessRequestForm(Request $request): View
    {
        abort_unless($this->isCustomer($request->user()), 403);

        return view('customer.access-request');
    }

    public function history(Request $request): View
    {
        abort_unless($this->isCustomer($request->user()), 403);
        $userId = $request->user()->id;

        return view('customer.history', [
            'submissions' => DB::table('customer_submissions as s')
                ->leftJoin('kategori_arsip as k', 'k.id', '=', 's.kategori_id')
                ->where('s.user_id', $userId)
                ->select(['s.*', 'k.nama_kategori'])
                ->orderByDesc('s.updated_at')->limit(50)->get(),
            'accessRequests' => DB::table('customer_requests')
                ->where('user_id', $userId)
                ->orderByDesc('updated_at')->limit(50)->get(),
        ]);
    }

    public function requestAccess(Request $request): RedirectResponse
    {
        abort_unless($this->isCustomer($request->user()), 403);
        $data = $request->validate([
            'requested_code' => ['required', 'string', 'max:180'],
            'purpose' => ['required', 'string', 'max:5000'],
        ]);
        $requestedCode = trim($data['requested_code']);
        $user = $request->user();

        $requestId = DB::transaction(function () use ($user, $requestedCode, $data): int {
            $duplicate = DB::table('customer_requests')
                ->where('user_id', $user->id)
                ->where('requested_code', $requestedCode)
                ->where('status', 'Menunggu Verifikasi')
                ->exists();
            abort_if($duplicate, 409, 'Permintaan untuk kode tersebut masih menunggu verifikasi.');

            $id = DB::table('customer_requests')->insertGetId([
                'user_id' => $user->id,
                'requested_code' => $requestedCode,
                'purpose' => trim($data['purpose']),
                'status' => 'Menunggu Verifikasi',
                'reviewer_note' => null,
                'reviewed_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->notifyAdmins(
                'Permintaan akses arsip',
                $user->nama.' meminta pemeriksaan kode '.$requestedCode.'.',
                route('customer-reviews.index'),
                ['request_id' => $id]
            );

            return $id;
        });

        return redirect()->route('customer.history')->with('success', 'Permintaan akses terkirim dan menunggu verifikasi.');
    }

    public function submitDocument(Request $request): RedirectResponse
    {
        abort_unless($this->isCustomer($request->user()), 403);
        $data = $request->validate([
            'nomor_dokumen' => ['required', 'string', 'max:180'],
            'nama_dokumen' => ['required', 'string', 'max:255'],
            'kategori_id' => ['required', 'integer', Rule::exists('kategori_arsip', 'id')->where('status', 'Aktif')],
            'tanggal_dokumen' => ['required', 'date_format:Y-m-d'],
            'keterangan' => ['nullable', 'string', 'max:10000'],
            'document_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:15360'],
        ]);

        $this->storeSubmission($request, null, $data);

        return redirect()->route('customer.history')->with('success', 'Dokumen terkirim. Admin akan memeriksa pengajuan Anda.');
    }

    public function resubmitDocument(Request $request, int $submission): RedirectResponse
    {
        abort_unless($this->isCustomer($request->user()), 403);
        $data = $request->validate([
            'nomor_dokumen' => ['required', 'string', 'max:180'],
            'nama_dokumen' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:10000'],
            'document_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:15360'],
        ]);

        $this->storeSubmission($request, $submission, $data);

        return redirect()->route('customer.history')->with('success', 'Revisi dokumen terkirim dan menunggu verifikasi ulang.');
    }

    public function submissionFile(Request $request, int $submission): mixed
    {
        $record = DB::table('customer_submissions')->where('id', $submission)->first();
        abort_if($record === null, 404);
        abort_unless(
            $record->user_id === $request->user()->id
                || RolePermissions::allows($request->user(), RolePermissions::USERS_MANAGE),
            403
        );

        $path = $this->privateSubmissionPath($record->file_path);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);
        $stream = $disk->readStream($path);
        abort_unless(is_resource($stream), 404);
        $filename = str_replace(["\r", "\n", '"'], ['', '', '_'], basename($record->nama_file));

        return response()->stream(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Cache-Control' => 'no-store, private',
            'Content-Type' => $record->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.addslashes($filename).'"',
        ]);
    }

    private function storeSubmission(Request $request, ?int $submission, array $data): void
    {
        $user = $request->user();
        $file = $request->file('document_file');
        $extension = strtolower((string) $file->guessExtension());
        if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true)) {
            throw ValidationException::withMessages(['document_file' => 'Format yang didukung: PDF, JPG, PNG, dan WebP.']);
        }

        $storedName = bin2hex(random_bytes(18)).'.'.$extension;
        $storedPath = $file->storeAs('customer-submissions', $storedName, 'local');
        if ($storedPath === false) {
            throw new RuntimeException('File tidak dapat disimpan di penyimpanan privat.');
        }

        $oldPath = null;
        try {
            DB::transaction(function () use ($request, $submission, $data, $user, $file, $storedPath, &$oldPath): void {
                $existing = null;
                if ($submission !== null) {
                    $existing = DB::table('customer_submissions')
                        ->where('id', $submission)
                        ->where('user_id', $user->id)
                        ->lockForUpdate()
                        ->first();
                    abort_if($existing === null, 404);
                    abort_unless($existing->status === 'Perlu Perbaikan' && $existing->arsip_id === null, 409, 'Pengajuan ini tidak lagi dapat direvisi.');
                    $oldPath = $existing->file_path;
                }

                $values = [
                    'nomor_dokumen' => trim($data['nomor_dokumen']),
                    'nama_dokumen' => trim($data['nama_dokumen']),
                    'keterangan' => isset($data['keterangan']) ? trim($data['keterangan']) : null,
                    'nama_file' => basename(str_replace('\\', '/', $file->getClientOriginalName())),
                    'file_path' => 'laravel/'.$storedPath,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'file_size' => $file->getSize(),
                    'status' => 'Menunggu Verifikasi',
                    'reviewer_note' => null,
                    'reviewed_by' => null,
                    'updated_at' => now(),
                ];
                if ($existing === null) {
                    DB::table('customer_submissions')->insert($values + [
                        'user_id' => $user->id,
                        'arsip_id' => null,
                        'kategori_id' => $data['kategori_id'],
                        'tanggal_dokumen' => $data['tanggal_dokumen'],
                        'created_at' => now(),
                    ]);
                } else {
                    DB::table('customer_submissions')->where('id', $submission)->update($values);
                }

                $this->notifyAdmins(
                    $existing === null ? 'Dokumen baru menunggu verifikasi' : 'Revisi dokumen menunggu verifikasi',
                    $user->nama.' mengajukan '.$data['nama_dokumen'].'.',
                    route('customer-reviews.index'),
                    ['submission_id' => $submission]
                );
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPath);
            throw $exception;
        }

        if ($oldPath !== null) {
            $oldStoredPath = $this->privateSubmissionPath($oldPath);
            Storage::disk('local')->delete($oldStoredPath);
        }
    }

    private function privateSubmissionPath(string $databasePath): string
    {
        abort_unless(preg_match('#\Alaravel/customer-submissions/[a-f0-9]{36}\.(pdf|jpg|jpeg|png|webp)\z#i', $databasePath) === 1, 404);

        return substr($databasePath, strlen('laravel/'));
    }

    private function notifyAdmins(string $title, string $body, string $url, array $data): void
    {
        $adminIds = DB::table('users')->where('role', 'Admin')->where('status', 'Aktif')->pluck('id');
        foreach ($adminIds as $adminId) {
            DB::table('notifications')->insert([
                'user_id' => $adminId,
                'title' => $title,
                'body' => $body,
                'type' => 'warning',
                'url' => $url,
                'data' => json_encode($data, JSON_THROW_ON_ERROR),
                'is_sent' => 1,
                'created_at' => now(),
            ]);
        }
    }

    private function isCustomer(User $user): bool
    {
        return RolePermissions::allows($user, RolePermissions::CUSTOMER_PORTAL);
    }
}
