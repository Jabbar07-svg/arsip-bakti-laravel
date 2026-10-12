<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function create(string $token): View
    {
        $record = $this->findValidReset($token);
        abort_if($record === null, 404, 'Tautan reset tidak valid atau sudah kedaluwarsa.');

        return view('auth.reset-password', ['token' => $token, 'username' => $record->username]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);
        $tokenHash = hash('sha256', $token);
        $resetUserId = null;

        DB::transaction(function () use ($tokenHash, $validated, &$resetUserId): void {
            $reset = DB::table('password_reset_requests')
                ->where('token_hash', $tokenHash)
                ->where('status', 'Link Diterbitkan')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();
            abort_if($reset === null, 404, 'Tautan reset tidak valid atau sudah kedaluwarsa.');
            $activeUser = DB::table('users')->where('id', $reset->user_id)->where('status', 'Aktif')->exists();
            abort_unless($activeUser, 404, 'Akun tidak tersedia.');
            $resetUserId = (int) $reset->user_id;
            DB::table('users')->where('id', $reset->user_id)->update([
                'password' => Hash::make($validated['password']),
                'updated_at' => now(),
            ]);
            DB::table('remember_tokens')->where('user_id', $reset->user_id)->delete();
            DB::table('password_reset_requests')->where('id', $reset->id)->update([
                'status' => 'Selesai',
                'token_hash' => null,
                'completed_at' => now(),
            ]);
        });
        if (Auth::id() === $resetUserId) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')->with('success', 'Password berhasil diatur ulang. Silakan masuk dengan password baru.');
    }

    private function findValidReset(string $token): ?object
    {
        return DB::table('password_reset_requests as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.token_hash', hash('sha256', $token))
            ->where('r.status', 'Link Diterbitkan')
            ->where('r.expires_at', '>', now())
            ->where('u.status', 'Aktif')
            ->select(['r.id', 'u.username'])
            ->first();
    }
}
