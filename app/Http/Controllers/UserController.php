<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request->user());
        $search = trim((string) $request->query('q', ''));
        $users = DB::table('users')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($matching) use ($search): void {
                    $matching->where('nama', 'like', '%'.$search.'%')
                        ->orWhere('username', 'like', '%'.$search.'%')
                        ->orWhere('role', 'like', '%'.$search.'%');
                });
            })
            ->select(['id', 'nama', 'username', 'role', 'division', 'status', 'created_at'])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();
        $resetRequests = DB::table('password_reset_requests as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->whereIn('r.status', ['Menunggu Verifikasi', 'Link Diterbitkan'])
            ->select(['r.id', 'r.status', 'r.requested_at', 'r.expires_at', 'u.nama', 'u.username', 'u.role'])
            ->orderByDesc('r.requested_at')
            ->limit(100)
            ->get();

        return view('users.index', [
            'users' => $users,
            'search' => $search,
            'roles' => $this->assignableRoles(),
            'activeCount' => DB::table('users')->where('status', 'Aktif')->count(),
            'resetRequests' => $resetRequests,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request->user());
        $request->merge([
            'nama' => trim((string) $request->input('nama')),
            'username' => trim((string) $request->input('username')),
        ]);
        $roles = $this->assignableRoles();
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', 'string', 'min:12', 'max:255'],
            'role' => ['required', Rule::in($roles)],
            'division' => ['nullable', 'string', 'max:150'],
            'status' => ['sometimes', Rule::in(['Aktif', 'Nonaktif'])],
        ], [
            'nama.required' => 'Nama lengkap wajib diisi.',
            'nama.max' => 'Nama lengkap maksimal 150 karakter.',
            'username.required' => 'Username wajib diisi.',
            'username.max' => 'Username maksimal 80 karakter.',
            'username.alpha_dash' => 'Username hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
            'username.unique' => 'Username sudah digunakan. Pilih username lain.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 12 karakter.',
            'password.max' => 'Password maksimal 255 karakter.',
            'role.required' => 'Pilih hak akses untuk akun ini.',
            'role.in' => 'Hak akses yang dipilih tidak valid.',
            'division.max' => 'Nama divisi maksimal 150 karakter.',
            'status.in' => 'Status akun yang dipilih tidak valid.',
        ]);
        try {
            User::query()->create([
                'nama' => trim($validated['nama']),
                'username' => trim($validated['username']),
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'division' => trim((string) ($validated['division'] ?? '')) ?: null,
                'status' => $validated['status'] ?? 'Aktif',
            ]);
        } catch (QueryException $exception) {
            if (! $this->isUsernameUniqueViolation($exception)) {
                throw $exception;
            }

            return back()
                ->withInput($request->except(['password', 'password_confirmation', '_token']))
                ->withErrors(['username' => 'Username sudah digunakan. Pilih username lain.']);
        }

        return redirect()->route('users.index')->with('success', 'Akun pengguna berhasil ditambahkan.');
    }

    public function toggleStatus(Request $request, int $user): RedirectResponse
    {
        $this->authorizeAdmin($request->user());
        DB::transaction(function () use ($request, $user): void {
            $target = DB::table('users')->where('id', $user)->lockForUpdate()->first();
            abort_if($target === null, 404);
            abort_if((int) $target->id === (int) $request->user()->id, 409, 'Akun yang sedang digunakan tidak dapat dinonaktifkan.');
            abort_if($target->role === 'Admin', 409, 'Status Admin utama hanya dapat diubah melalui prosedur pemulihan terkendali.');
            $nextStatus = $target->status === 'Aktif' ? 'Nonaktif' : 'Aktif';
            DB::table('users')->where('id', $user)->update(['status' => $nextStatus, 'updated_at' => now()]);
            if ($nextStatus === 'Nonaktif') {
                DB::table('remember_tokens')->where('user_id', $user)->delete();
            }
        });

        return redirect()->route('users.index')->with('success', 'Status akun berhasil diperbarui.');
    }

    public function setDivision(Request $request, int $user): RedirectResponse
    {
        $this->authorizeAdmin($request->user());
        $validated = $request->validate(['division' => ['nullable', 'string', 'max:150']]);
        $affected = DB::table('users')->where('id', $user)->update([
            'division' => trim((string) ($validated['division'] ?? '')) ?: null,
            'updated_at' => now(),
        ]);
        abort_if($affected === 0 && ! DB::table('users')->where('id', $user)->exists(), 404);

        return redirect()->route('users.index')->with('success', 'Divisi pengguna berhasil diperbarui.');
    }

    public function setRole(Request $request, int $user): RedirectResponse
    {
        $this->authorizeAdmin($request->user());
        $roles = $this->assignableRoles();
        $validated = $request->validate(['role' => ['required', Rule::in($roles)]]);

        DB::transaction(function () use ($request, $user, $validated): void {
            abort_if($user === (int) $request->user()->id, 409, 'Role akun yang sedang digunakan tidak dapat diubah.');
            $target = DB::table('users')->where('id', $user)->lockForUpdate()->first();
            abort_if($target === null, 404);
            abort_if($target->role === 'Admin', 409, 'Role Admin utama hanya dapat diubah melalui prosedur pemulihan terkendali.');
            if ($target->role === $validated['role']) {
                return;
            }

            DB::table('users')->where('id', $user)->update([
                'role' => $validated['role'],
                'updated_at' => now(),
            ]);
            DB::table('remember_tokens')->where('user_id', $user)->delete();
        });

        return redirect()->route('users.index')->with('success', 'Role pengguna berhasil diperbarui. Sesi ingat-akun lama telah dicabut.');
    }

    public function issueResetLink(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request->user());
        if (config('ldap.auth_mode') !== 'local') {
            return back()->withErrors(['user_id' => 'Reset password lokal tidak berlaku untuk akun direktori. Hubungi administrator BAKTI/Komdigi.']);
        }

        $validated = $request->validate([
            'request_id' => ['nullable', 'integer', 'exists:password_reset_requests,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        if (empty($validated['request_id']) && empty($validated['user_id'])) {
            return back()->withErrors(['user_id' => 'Pilih permintaan reset atau pengguna.']);
        }

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        DB::transaction(function () use ($request, $validated, $tokenHash): void {
            if (! empty($validated['request_id'])) {
                $reset = DB::table('password_reset_requests')
                    ->where('id', $validated['request_id'])
                    ->lockForUpdate()
                    ->first();
                abort_if($reset === null, 404);
                abort_if((int) $reset->user_id === (int) $request->user()->id, 403, 'Pemilik permintaan reset tidak dapat memproses permintaannya sendiri.');
                $issuable = $reset->status === 'Menunggu Verifikasi'
                    || ($reset->status === 'Link Diterbitkan' && $reset->expires_at !== null && $reset->expires_at < now()->toDateTimeString());
                abort_unless($issuable, 409, 'Permintaan reset tidak lagi menunggu verifikasi.');
                $targetUserId = (int) $reset->user_id;
                abort_unless(DB::table('users')->where('id', $targetUserId)->where('status', 'Aktif')->exists(), 404);
            } else {
                $targetUserId = (int) $validated['user_id'];
                abort_if($targetUserId === (int) $request->user()->id, 422, 'Pilih pengguna lain untuk dibuatkan tautan reset.');
                abort_unless(DB::table('users')->where('id', $targetUserId)->where('status', 'Aktif')->exists(), 404);
                $resetId = DB::table('password_reset_requests')->insertGetId([
                    'user_id' => $targetUserId,
                    'status' => 'Menunggu Verifikasi',
                    'requested_at' => now(),
                    'reviewed_by' => $request->user()->id,
                    'token_hash' => null,
                    'expires_at' => null,
                    'completed_at' => null,
                ]);
                $reset = (object) ['id' => $resetId, 'user_id' => $targetUserId];
            }

            DB::table('password_reset_requests')->where('id', $reset->id)->update([
                'status' => 'Link Diterbitkan',
                'reviewed_by' => $request->user()->id,
                'token_hash' => $tokenHash,
                'expires_at' => now()->addMinutes(30),
                'completed_at' => null,
            ]);
            DB::table('notifications')->insert([
                'user_id' => $targetUserId,
                'title' => 'Tautan reset password siap',
                'body' => 'Admin telah menyiapkan tautan sekali pakai yang berlaku selama 30 menit.',
                'type' => 'info',
                'url' => route('account.show'),
                'data' => json_encode(['password_reset_request_id' => $reset->id], JSON_THROW_ON_ERROR),
                'is_sent' => 1,
                'created_at' => now(),
            ]);
        });

        return redirect()->route('users.index')->with('oneTimeResetLink', route('password.reset', ['token' => $rawToken]));
    }

    private function authorizeAdmin(User $user): void
    {
        abort_unless(RolePermissions::allows($user, RolePermissions::USERS_MANAGE), 403);
    }

    private function isUsernameUniqueViolation(QueryException $exception): bool
    {
        if ((string) $exception->getCode() !== '23000') {
            return false;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'uq_users_username')
            || str_contains($message, 'users.username');
    }

    private function assignableRoles(): array
    {
        return RolePermissions::STAFF_ASSIGNABLE_ROLES;
    }
}
