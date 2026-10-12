<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

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
        $requestedRole = $request->input('role');
        if (in_array($requestedRole, RolePermissions::ASSIGNABLE_ROLES, true)
            && ! in_array($requestedRole, $roles, true)) {
            return back()
                ->withInput()
                ->withErrors(['role' => 'Skema database belum mendukung role tersebut. Role akun tidak diubah.']);
        }
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'role' => ['required', Rule::in($roles)],
            'division' => ['nullable', 'string', 'max:150'],
        ]);
        User::query()->create([
            'nama' => trim($validated['nama']),
            'username' => trim($validated['username']),
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'division' => trim((string) ($validated['division'] ?? '')) ?: null,
            'status' => 'Aktif',
        ]);

        return redirect()->route('users.index')->with('success', 'Akun pengguna berhasil ditambahkan.');
    }

    public function toggleStatus(Request $request, int $user): RedirectResponse
    {
        $this->authorizeAdmin($request->user());
        DB::transaction(function () use ($request, $user): void {
            $target = DB::table('users')->where('id', $user)->lockForUpdate()->first();
            abort_if($target === null, 404);
            abort_if((int) $target->id === (int) $request->user()->id, 409, 'Akun yang sedang digunakan tidak dapat dinonaktifkan.');
            if ($target->role === 'Super Admin' && $target->status === 'Aktif') {
                $activeAdmins = DB::table('users')->where('role', 'Super Admin')->where('status', 'Aktif')->count();
                abort_if($activeAdmins <= 1, 409, 'Super Admin aktif terakhir tidak dapat dinonaktifkan.');
            }
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
        $requestedRole = $request->input('role');
        if (in_array($requestedRole, RolePermissions::ASSIGNABLE_ROLES, true)
            && ! in_array($requestedRole, $roles, true)) {
            return back()
                ->withInput()
                ->withErrors(['role' => 'Skema database belum mendukung role tersebut. Role akun tidak diubah.']);
        }
        $validated = $request->validate(['role' => ['required', Rule::in($roles)]]);

        DB::transaction(function () use ($request, $user, $validated): void {
            abort_if($user === (int) $request->user()->id, 409, 'Role akun yang sedang digunakan tidak dapat diubah.');
            if ($validated['role'] !== 'Super Admin') {
                DB::table('users')
                    ->where('role', 'Super Admin')
                    ->where('status', 'Aktif')
                    ->lockForUpdate()
                    ->get(['id']);
            }

            $target = DB::table('users')->where('id', $user)->lockForUpdate()->first();
            abort_if($target === null, 404);
            if ($target->role === $validated['role']) {
                return;
            }
            if ($target->role === 'Super Admin' && $target->status === 'Aktif' && $validated['role'] !== 'Super Admin') {
                $activeAdmins = DB::table('users')->where('role', 'Super Admin')->where('status', 'Aktif')->count();
                abort_if($activeAdmins <= 1, 409, 'Super Admin aktif terakhir tidak dapat diturunkan role-nya.');
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
                'body' => 'Super Admin telah menyiapkan tautan sekali pakai yang berlaku selama 30 menit.',
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

    private function assignableRoles(): array
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return RolePermissions::ASSIGNABLE_ROLES;
        }

        $column = DB::selectOne(
            'SELECT COLUMN_TYPE AS column_type
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?',
            ['users', 'role'],
        );
        if (! is_string($column->column_type ?? null)) {
            throw new RuntimeException('The users.role column could not be inspected; role assignment is blocked.');
        }

        $columnType = (string) $column->column_type;
        if (! str_starts_with(strtolower($columnType), 'enum(') || ! str_ends_with($columnType, ')')) {
            return RolePermissions::ASSIGNABLE_ROLES;
        }

        $databaseRoles = str_getcsv(substr($columnType, 5, -1), ',', "'", '\\');

        return array_values(array_intersect(RolePermissions::ASSIGNABLE_ROLES, $databaseRoles));
    }
}
