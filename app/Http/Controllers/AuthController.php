<?php

namespace App\Http\Controllers;

use App\Exceptions\LdapUnavailableException;
use App\Models\User;
use App\Services\LdapAuthenticator;
use App\Support\RolePermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(RolePermissions::landingRouteName(Auth::user()));
        }

        return view('auth.login');
    }

    public function store(Request $request, LdapAuthenticator $ldap): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:80'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        $credentials['username'] = trim($credentials['username']);

        $authMode = config('ldap.auth_mode');
        $ldapIdentity = null;

        if ($authMode === 'local') {
            $user = User::query()
                ->where('username', $credentials['username'])
                ->where('status', 'Aktif')
                ->first();

            if ($user === null || ! Hash::check($credentials['password'], $user->getAuthPassword())) {
                return back()
                    ->withErrors(['username' => 'Username atau password tidak sesuai.'])
                    ->onlyInput('username');
            }

        } elseif ($authMode === 'ldap') {
            try {
                $ldapIdentity = $ldap->authenticate($credentials['username'], $credentials['password']);
            } catch (LdapUnavailableException) {
                return back()
                    ->withErrors(['username' => 'Autentikasi direktori sedang tidak tersedia. Coba lagi atau hubungi administrator.'])
                    ->onlyInput('username');
            }

            if ($ldapIdentity === null) {
                return back()
                    ->withErrors(['username' => 'Username atau password tidak sesuai.'])
                    ->onlyInput('username');
            }

            $user = User::query()->where('username', $ldapIdentity['username'])->first();
            if ($user === null || $user->status !== 'Aktif') {
                return back()
                    ->withErrors(['username' => 'Akun direktori belum diberi akses ke aplikasi. Hubungi administrator.'])
                    ->onlyInput('username');
            }

        } else {
            return back()
                ->withErrors(['username' => 'Metode autentikasi belum dikonfigurasi. Hubungi administrator.'])
                ->onlyInput('username');
        }

        if (! RolePermissions::hasSupportedRole($user)) {
            return back()
                ->withErrors(['username' => 'Role akun belum dipetakan ke sistem terbaru. Hubungi Admin.'])
                ->onlyInput('username');
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_method', $authMode);
        $request->session()->forget('ldap_profile');
        if ($ldapIdentity !== null) {
            $request->session()->put('ldap_profile', $ldapIdentity);
        }

        return redirect()->route(RolePermissions::landingRouteName($user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
