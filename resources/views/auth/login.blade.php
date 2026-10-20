@extends('layouts.app')

@section('title', 'Masuk')

@section('content')
<main class="auth-page">
    <div class="auth-layout">
        <section class="auth-brand-panel" aria-label="Sistem Arsip BAKTI Komdigi">
            <div class="auth-brand">
                <img src="{{ asset('images/bakti-logo.png') }}" alt="Logo BAKTI Komdigi">
                <span><strong>Sistem Arsip</strong><small>BAKTI Komdigi · Gudang Utama</small></span>
            </div>
            <div class="auth-brand-copy">
                <span class="auth-brand-kicker">LAYANAN INTERNAL BAKTI</span>
                <h1>Arsip tertata.<br>Informasi terjaga.</h1>
                <p>Kelola dokumen dan informasi kelembagaan secara aman, terstruktur, dan mudah ditelusuri.</p>
            </div>
            <div class="auth-brand-footer"><span class="auth-brand-mark" aria-hidden="true">BAKTI</span><span>Pengelolaan arsip digital</span></div>
            <svg class="auth-brand-art" viewBox="0 0 560 430" aria-hidden="true">
                <path d="M62 430V118l153-68v380M225 430V90l150-68v408M386 430V155l117-53v328" />
                <path d="M85 166h103M85 213h103M85 260h103M85 307h103M247 138h105M247 185h105M247 232h105M247 279h105M408 195h72M408 242h72M408 289h72" />
            </svg>
        </section>

        <section class="auth-card" aria-labelledby="login-title">
            <div class="auth-card-heading">
                <div class="eyebrow">Akses akun</div>
                <h2 id="login-title">Selamat datang kembali</h2>
                <p>Masuk dengan akun Sistem Arsip BAKTI yang telah terdaftar.</p>
            </div>

            @if (session('success'))
                <div class="notice-success" role="status">{{ session('success') }}</div>
            @endif
            @if (session('csrf_expired'))
                <div class="form-alert" role="alert">{{ session('csrf_expired') }}</div>
            @endif
            @if ($errors->any())
                <div class="form-alert" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="post" action="{{ route('login.store', [], false) }}">
                @csrf
                <div class="field">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" value="{{ old('username') }}" autocomplete="username" maxlength="80" placeholder="Masukkan username" required>
                    @error('username')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Masukkan password" required>
                    @error('password')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <button class="button button-primary auth-submit" type="submit">
                    <span>Masuk ke sistem</span><span aria-hidden="true">→</span>
                </button>
            </form>
            @if (config('ldap.auth_mode') === 'ldap')
                <p class="auth-help">Gunakan akun internal BAKTI/Komdigi. Jika lupa password, hubungi administrator atau ikuti prosedur pemulihan akun resmi.</p>
            @elseif (config('ldap.auth_mode') === 'local')
                <p class="auth-help">Akun aktif yang sama digunakan pada aplikasi sebelumnya. Modul yang belum dipindahkan tetap dapat diakses melalui aplikasi lama.</p>
            @else
                <p class="auth-help">Metode autentikasi belum dikonfigurasi. Hubungi administrator.</p>
            @endif
            <div class="auth-security-note"><span aria-hidden="true">▣</span><span>Akses terlindungi · Gunakan akun pribadi Anda</span></div>
        </section>
    </div>
</main>
@endsection
