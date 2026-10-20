<?php

namespace App\Http\Middleware;

use App\Support\RolePermissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null && $request->user()->status !== 'Aktif') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'username' => 'Akun Anda tidak aktif. Hubungi Admin.',
            ]);
        }

        if ($request->user() !== null && ! RolePermissions::hasSupportedRole($request->user())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'username' => 'Role akun belum dipetakan ke sistem terbaru. Hubungi Admin.',
            ]);
        }

        return $next($request);
    }
}
