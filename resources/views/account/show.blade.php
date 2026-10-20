@extends('layouts.app')

@section('title', 'Profil & Pengaturan')

@section('content')
<main class="page account-page">
    <div class="account-heading"><span class="account-kicker">Pengaturan akun</span><h1>Profil &amp; pengaturan</h1><p>Kelola informasi profil dan keamanan akun Anda.</p></div>
    @if (session('success'))<div class="account-alert account-alert-success" role="status">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="account-alert account-alert-error" role="alert">{{ $errors->first() }}</div>@endif

    <div class="account-layout">
        <section class="panel account-profile-panel">
            <div class="account-panel-heading"><div><h2>Profil Anda</h2><p>Informasi yang terlihat saat menggunakan portal.</p></div></div>
            <form method="post" action="{{ route('account.profile') }}" enctype="multipart/form-data" class="account-form" data-profile-form data-submit-form>
                @csrf @method('put')
                <div class="account-avatar-editor">
                    <span class="account-avatar-preview">
                        <span data-avatar-fallback>{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->nama, 0, 1)) }}</span>
                        @if (auth()->user()->profile_image)
                            <img src="{{ route('account.photo', ['filename' => basename(auth()->user()->profile_image)]) }}" alt="Foto profil Anda" data-profile-preview>
                        @else
                            <img src="" alt="Pratinjau foto profil" data-profile-preview hidden>
                        @endif
                    </span>
                    <div><strong>Foto profil</strong><p>Pilih foto JPG, PNG, atau WebP hingga 5 MB.</p><label class="account-photo-button" for="profile-image-input">Pilih foto</label></div>
                </div>
                <label class="account-field">Nama tampilan<input name="nama" maxlength="150" value="{{ old('nama', auth()->user()->nama) }}" required>@error('nama')<small class="form-error">{{ $message }}</small>@enderror</label>
                <div class="account-identity">
                    <span><strong>Username</strong>{{ auth()->user()->username }}</span>
                    <span><strong>Email</strong>{{ $accountEmail ?: 'Email belum terdaftar' }}</span>
                    <span><strong>Metode autentikasi</strong>{{ $authMethod === 'ldap' ? 'LDAP' : 'Lokal' }}</span>
                    @if ($authMethod === 'ldap' && !empty($ldapProfile['name']))
                        <span><strong>Nama dari direktori</strong>{{ $ldapProfile['name'] }}</span>
                    @endif
                </div>
                <label class="account-field">Foto profil<input class="account-file-input" id="profile-image-input" type="file" name="profile_image" accept="image/jpeg,image/png,image/webp" data-profile-image-input><span class="account-file-hint" data-profile-file-name>Belum ada foto baru dipilih.</span>@error('profile_image')<small class="form-error">{{ $message }}</small>@enderror</label>
                <div class="account-form-actions"><span>Nama akun: {{ auth()->user()->username }}</span><button class="button button-primary" type="submit" data-submit-button>Simpan perubahan</button></div>
            </form>
        </section>

        @if ($authMethod === 'local')
        <section class="panel account-password-panel">
            <div class="account-panel-heading"><div><h2>Keamanan</h2><p>Gunakan kata sandi yang kuat dan tidak digunakan di tempat lain.</p></div><span class="account-lock" aria-hidden="true">⌑</span></div>
            <form method="post" action="{{ route('account.password') }}" class="account-form" data-submit-form>
                @csrf @method('put')
                <label class="account-field">Password saat ini<input type="password" name="current_password" autocomplete="current-password" required>@error('current_password')<small class="form-error">{{ $message }}</small>@enderror</label>
                <label class="account-field">Password baru<input type="password" name="password" autocomplete="new-password" minlength="8" required>@error('password')<small class="form-error">{{ $message }}</small>@enderror</label>
                <label class="account-field">Ulangi password baru<input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label>
                <div class="account-form-actions"><span>Minimal 8 karakter.</span><button class="button button-primary" type="submit" data-submit-button>Perbarui password</button></div>
            </form>
        </section>
        @else
        <section class="panel account-password-panel">
            <div class="account-panel-heading"><div><h2>Pemulihan password</h2><p>Password akun direktori tidak dikelola oleh aplikasi arsip.</p></div></div>
            <div class="account-form"><p>Jika lupa password, hubungi administrator atau ikuti prosedur pemulihan akun resmi BAKTI/Komdigi.</p></div>
        </section>
        @endif
    </div>

    <section class="panel account-notification-panel">
        <div class="account-panel-heading"><div><h2>Notifikasi terbaru</h2><p>Pemberitahuan terkini untuk akun Anda.</p></div><a href="{{ route('notifications.index') }}">Lihat semua →</a></div>
        @forelse ($notifications as $notification)
            <article class="account-notification-row">
                <span class="account-notification-mark" aria-hidden="true"></span>
                <div class="account-notification-copy"><strong>{{ $notification->title }}</strong><p>{{ $notification->body }}</p><small>{{ \Illuminate\Support\Carbon::parse($notification->created_at)->format('d/m/Y H:i') }}</small></div>
                @if (!$notification->read_at)<span class="account-unread">Baru</span>@endif
            </article>
        @empty
            <div class="account-empty"><strong>Belum ada notifikasi</strong><span>Pemberitahuan penting akan muncul di sini.</span></div>
        @endforelse
    </section>
</main>
<style>
.account-notification-copy small{color:#91a0aa;font-size:9px}.account-unread{padding:4px 7px;border-radius:12px;background:#e8f4fa;color:#087fc3;font-size:8px;font-weight:800}.account-empty{display:grid;gap:4px;padding:24px;text-align:center;color:#8295a3;font-size:10px}.account-empty strong{color:#456478;font-size:11px}
@media(max-width:780px){.account-layout{grid-template-columns:1fr}}
@media(max-width:520px){.account-form{padding:15px}.account-panel-heading{padding:14px}.account-form-actions{align-items:stretch;flex-direction:column}.account-form-actions .button{width:100%}}
</style>
<style>
.account-identity{display:grid;gap:8px;padding:12px;border:1px solid #edf1f4;border-radius:8px;background:#fbfdfe}
.account-identity span{display:grid;gap:3px;color:#49677c;font-size:10px;overflow-wrap:anywhere}
.account-identity strong{color:#8295a3;font-size:9px}
</style>
@endsection
