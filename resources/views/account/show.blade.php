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
                <label class="account-field">Foto profil<input class="account-file-input" id="profile-image-input" type="file" name="profile_image" accept="image/jpeg,image/png,image/webp" data-profile-image-input><span class="account-file-hint" data-profile-file-name>Belum ada foto baru dipilih.</span>@error('profile_image')<small class="form-error">{{ $message }}</small>@enderror</label>
                <div class="account-form-actions"><span>Nama akun: {{ auth()->user()->username }}</span><button class="button button-primary" type="submit" data-submit-button>Simpan perubahan</button></div>
            </form>
        </section>

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
.account-page{max-width:1180px}.account-heading{margin-bottom:18px}.account-kicker{color:#087fc3;font-size:10px;font-weight:800;letter-spacing:1px;text-transform:uppercase}.account-heading h1{margin:7px 0 5px}.account-heading p{margin:0;color:#748a9a;font-size:12px}.account-alert{margin:0 0 14px;padding:12px 14px;border-radius:10px;font-size:11px}.account-alert-success{background:#eaf7f0;color:#1a7652}.account-alert-error{background:#fff0ee;color:#a43b35}.account-layout{display:grid;grid-template-columns:1.1fr .9fr;gap:15px}.account-profile-panel,.account-password-panel{margin:0;overflow:hidden}.account-panel-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:17px 19px;border-bottom:1px solid #edf1f4}.account-panel-heading h2{margin:0;color:#1c405b;font-size:14px}.account-panel-heading p{margin:4px 0 0;color:#8295a3;font-size:10px}.account-panel-heading>a{font-size:10px;font-weight:700}.account-form{display:grid;gap:14px;padding:18px 19px}.account-avatar-editor{display:flex;align-items:center;gap:13px;padding-bottom:15px;border-bottom:1px solid #edf1f4}.account-avatar-preview{position:relative;display:grid;width:70px;height:70px;flex:0 0 70px;place-items:center;overflow:hidden;border:1px solid #dfe9ef;border-radius:50%;background:#e8f4fa;color:#087fc3;font-size:23px;font-weight:800}.account-avatar-preview img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}.account-avatar-editor strong{color:#29485e;font-size:11px}.account-avatar-editor p{margin:4px 0 7px;color:#8295a3;font-size:9px}.account-photo-button{display:inline-flex;align-items:center;min-height:30px;padding:0 10px;border:1px solid #dce6ec;border-radius:7px;color:#0876b3;font-size:9px;font-weight:700;cursor:pointer}.account-file-input{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap}.account-file-input:focus-visible+.account-file-hint{outline:2px solid #087fc3}.account-file-hint{color:#8295a3;font-size:9px}.account-field{display:grid;gap:6px;color:#49677c;font-size:10px;font-weight:700}.account-field input{width:100%;min-height:42px;padding:9px 11px;border:1px solid #dce6ec;border-radius:8px;background:#fff;color:#29485e;font:inherit;font-size:12px}.account-field input:focus{border-color:#55a5cc;outline:3px solid rgba(8,127,195,.1)}.account-lock{color:#087fc3;font-size:22px}.account-form-actions{display:flex;align-items:center;justify-content:space-between;gap:10px;padding-top:5px}.account-form-actions>span{color:#8295a3;font-size:9px}.account-form-actions .button{min-height:38px;font-size:10px}.account-notification-panel{margin-top:15px;overflow:hidden}.account-notification-row{display:flex;align-items:flex-start;gap:11px;padding:13px 18px;border-bottom:1px solid #edf1f4}.account-notification-mark{width:8px;height:8px;flex:0 0 8px;margin-top:4px;border-radius:50%;background:#1788bf}.account-notification-copy{min-width:0;flex:1}.account-notification-copy strong{color:#29485e;font-size:10px}.account-notification-copy p{margin:4px 0;color:#718696;font-size:10px;line-height:1.45}.account-notification-copy small{color:#91a0aa;font-size:9px}.account-unread{padding:4px 7px;border-radius:12px;background:#e8f4fa;color:#087fc3;font-size:8px;font-weight:800}.account-empty{display:grid;gap:4px;padding:24px;text-align:center;color:#8295a3;font-size:10px}.account-empty strong{color:#456478;font-size:11px}
@media(max-width:780px){.account-layout{grid-template-columns:1fr}}
@media(max-width:520px){.account-form{padding:15px}.account-panel-heading{padding:14px}.account-form-actions{align-items:stretch;flex-direction:column}.account-form-actions .button{width:100%}}
</style>
@endsection
