@extends('layouts.app')

@section('title', 'Manajemen Pengguna')

@section('content')
<main class="page users-page">
    <div class="page-heading-row"><div><div class="eyebrow">Manajemen akses</div><h1>Pengguna</h1><p class="page-description">Kelola akun, status, divisi, dan tautan reset password.</p></div></div>
    @if (session('success'))<div class="notice-success" role="status">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="notice-error" role="alert">{{ $errors->first() }}</div>@endif
    @if (session('oneTimeResetLink'))
        <section class="panel one-time-reset" role="status">
            <strong>Tautan reset sekali pakai · berlaku 30 menit</strong>
            <p>Salin dan sampaikan kepada pengguna melalui jalur yang aman. Tautan ini tidak akan ditampilkan lagi setelah meninggalkan halaman.</p>
            <a href="{{ session('oneTimeResetLink') }}">{{ session('oneTimeResetLink') }}</a>
        </section>
    @endif

    <div class="user-metrics">
        <section class="panel user-metric"><span>Total akun</span><strong>{{ number_format($users->total()) }}</strong></section>
        <section class="panel user-metric"><span>Akun aktif</span><strong>{{ number_format($activeCount) }}</strong></section>
    </div>

    <section class="panel user-section">
        <div class="panel-heading"><div><h2>Permintaan reset password</h2><p>Tautan di-hash di database, berlaku 30 menit, dan dapat digunakan sekali.</p></div><span>{{ $resetRequests->count() }} permintaan</span></div>
        <div class="user-card-list">
            @forelse ($resetRequests as $reset)
                <article class="user-card reset-card">
                    <div><strong>{{ $reset->nama }}</strong><p>@{{ $reset->username }} · {{ $reset->role }}</p><span class="status status-{{ \Illuminate\Support\Str::slug($reset->status) }}">{{ $reset->status }}</span>
                        @if ($reset->expires_at)<small class="user-muted">Kadaluarsa: {{ \Illuminate\Support\Carbon::parse($reset->expires_at)->format('d/m/Y H:i') }}</small>@endif
                    </div>
                    @if ($reset->status === 'Menunggu Verifikasi' || ($reset->expires_at && \Illuminate\Support\Carbon::parse($reset->expires_at)->isPast()))
                        <form method="post" action="{{ route('users.reset-link') }}">@csrf<input type="hidden" name="request_id" value="{{ $reset->id }}"><button class="button button-primary" type="submit">Buat tautan</button></form>
                    @else
                        <span class="user-muted">Tautan aktif. Kirimkan dengan aman.</span>
                    @endif
                </article>
            @empty
                <div class="empty-inline">Belum ada permintaan reset password.</div>
            @endforelse
        </div>
    </section>

    <div class="user-management-grid">
        <section class="panel user-section">
            <div class="panel-heading"><div><h2>Tambah pengguna</h2><p>Buat akun dengan role dan divisi yang sesuai.</p></div></div>
            <form class="user-form" method="post" action="{{ route('users.store') }}">
                @csrf
                <label>Nama lengkap<input name="nama" maxlength="150" autocomplete="name" value="{{ old('nama') }}" required></label>
                <label>Username<input name="username" maxlength="80" autocomplete="off" value="{{ old('username') }}" required></label>
                <label>Password<input type="password" name="password" minlength="8" maxlength="255" autocomplete="new-password" required><small>Minimal 8 karakter.</small></label>
                <label>Hak akses<select name="role" required>@foreach ($roles as $role)<option value="{{ $role }}" @selected(old('role', 'Customer') === $role)>{{ $role }}</option>@endforeach</select></label>
                <label class="user-wide">Divisi (opsional)<input name="division" maxlength="150" value="{{ old('division') }}" placeholder="Contoh: Keuangan"><small>Dipakai untuk membatasi arsip Internal Divisi.</small></label>
                <button class="button button-primary user-wide" type="submit">Tambah pengguna</button>
            </form>
        </section>

        <section class="panel user-section">
            <div class="panel-heading user-list-heading">
                <div><h2>Daftar pengguna</h2><p>{{ number_format($users->total()) }} akun terdaftar</p></div>
                <form class="user-search" method="get" action="{{ route('users.index') }}"><input type="search" name="q" value="{{ $search }}" placeholder="Cari nama, username, role" aria-label="Cari pengguna"><button class="button button-small" type="submit">Cari</button></form>
            </div>
            <div class="user-card-list">
                @forelse ($users as $user)
                    <article class="user-card">
                        <div class="user-details">
                            <div class="user-identity"><span class="user-avatar">{{ mb_strtoupper(mb_substr($user->nama, 0, 1)) }}</span><div><strong>{{ $user->nama }}</strong><p>@{{ $user->username }} · {{ $user->role }}</p><span class="status status-{{ \Illuminate\Support\Str::slug($user->status) }}">{{ $user->status }}</span></div></div>
                            @if ($user->id !== auth()->id())
                                <form class="role-form" method="post" action="{{ route('users.role', $user->id) }}">@csrf @method('PATCH')<label>Role<select name="role" aria-label="Role {{ $user->nama }}">@foreach ($roles as $role)<option value="{{ $role }}" @selected($user->role === $role)>{{ $role }}</option>@endforeach</select></label><button class="button button-small" type="submit">Simpan role</button></form>
                            @else
                                <span class="user-muted">Role akun sendiri tidak dapat diubah.</span>
                            @endif
                            <form class="division-form" method="post" action="{{ route('users.division', $user->id) }}">@csrf @method('PATCH')<label>Divisi<input name="division" maxlength="150" value="{{ $user->division }}" placeholder="Belum ditetapkan" aria-label="Divisi {{ $user->nama }}"></label><button class="button button-small" type="submit">Simpan divisi</button></form>
                        </div>
                        <div class="user-actions">
                            @if ($user->id !== auth()->id())
                                <form method="post" action="{{ route('users.status', $user->id) }}">@csrf @method('PATCH')<button class="button button-small" type="submit">{{ $user->status === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                                <form method="post" action="{{ route('users.reset-link') }}">@csrf<input type="hidden" name="user_id" value="{{ $user->id }}"><button class="button button-small" type="submit">Buat reset password</button></form>
                            @else
                                <span class="user-muted">Akun yang sedang digunakan</span>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="empty-inline">Tidak ada pengguna yang cocok.</div>
                @endforelse
            </div>
            {{ $users->links() }}
        </section>
    </div>
</main>
<style>
.users-page{max-width:1280px}.user-metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:18px}.user-metric{display:grid;gap:5px;padding:15px 18px}.user-metric span,.user-muted{color:#8294a0;font-size:10px}.user-metric strong{color:#1c405b;font-size:23px}.user-section{margin-top:15px;padding:0 18px 15px}.user-section .panel-heading>span{color:#8294a0;font-size:10px}.one-time-reset{display:grid;gap:8px;margin-top:14px;padding:16px;border:1px solid #f3d39b;background:#fff9ec}.one-time-reset strong{color:#725012}.one-time-reset p{margin:0;color:#826f4c;font-size:11px}.one-time-reset a{overflow-wrap:anywhere;color:#0876b3;font-size:11px}.user-management-grid{display:grid;grid-template-columns:minmax(270px,.8fr) minmax(0,1.5fr);gap:15px}.user-form{display:grid;gap:11px;padding:15px}.user-form label,.division-form label{display:grid;gap:5px;color:#526d7f;font-size:10px;font-weight:650}.user-form input,.user-form select,.division-form input,.user-search input{width:100%;min-height:38px;padding:8px 10px;border:1px solid #dce5ea;border-radius:8px;background:#fff;color:#29485e;font:inherit}.user-form small{color:#8294a0;font-weight:400}.user-wide{grid-column:1/-1}.user-list-heading{align-items:center}.user-search{display:flex;gap:6px}.user-search input{min-width:200px}.user-card-list{display:grid;gap:8px;padding-top:12px}.user-card{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:13px;border:1px solid #e7edf1;border-radius:10px}.user-details{display:grid;gap:11px;flex:1;min-width:0}.user-identity{display:flex;align-items:center;gap:10px}.user-avatar{display:grid;place-items:center;width:37px;height:37px;flex:0 0 auto;border-radius:50%;background:#edf6fa;color:#0876b3;font-size:14px;font-weight:700}.user-identity strong,.reset-card strong{color:#1c405b;font-size:12px}.user-identity p,.reset-card p{margin:4px 0;color:#8294a0;font-size:10px}.user-identity .status{margin-top:6px}.division-form{display:flex;align-items:end;gap:7px;max-width:450px}.division-form label{flex:1}.user-actions{display:flex;flex-wrap:wrap;gap:7px;justify-content:flex-end}.reset-card .user-muted{display:block;margin-top:7px}.user-card-list .pagination{padding:10px 0}.reset-card{align-items:center}
.role-form{display:flex;align-items:end;gap:7px}.role-form label{display:grid;gap:5px;color:#526d7f;font-size:10px;font-weight:650}.role-form select{min-height:32px;max-width:220px;padding:5px;border:1px solid #dce5ea;border-radius:7px;background:#fff;color:#29485e;font:inherit}.user-form input,.user-form select,.division-form input,.user-search input{width:100%;min-height:38px;padding:8px 10px;border:1px solid #dce5ea;border-radius:8px;background:#fff;color:#29485e;font:inherit}.user-form small{color:#8294a0;font-weight:400}.user-wide{grid-column:1/-1}.user-list-heading{align-items:center}.user-search{display:flex;gap:6px}.user-search input{min-width:200px}.user-card-list{display:grid;gap:8px;padding-top:12px}.user-card{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:13px;border:1px solid #e7edf1;border-radius:10px}.user-details{display:grid;gap:11px;flex:1;min-width:0}.user-identity{display:flex;align-items:center;gap:10px}.user-avatar{display:grid;place-items:center;width:37px;height:37px;flex:0 0 auto;border-radius:50%;background:#edf6fa;color:#0876b3;font-size:14px;font-weight:700}.user-identity strong,.reset-card strong{color:#1c405b;font-size:12px}.user-identity p,.reset-card p{margin:4px 0;color:#8294a0;font-size:10px}.user-identity .status{margin-top:6px}.division-form{display:flex;align-items:end;gap:7px;max-width:450px}.division-form label{flex:1}.user-actions{display:flex;flex-wrap:wrap;gap:7px;justify-content:flex-end}.reset-card .user-muted{display:block;margin-top:7px}.user-card-list .pagination{padding:10px 0}.reset-card{align-items:center}
@media(max-width:950px){.user-management-grid{grid-template-columns:1fr}}
@media(max-width:600px){.user-section{padding:0 12px 12px}.user-card{align-items:flex-start;flex-direction:column}.user-actions{justify-content:flex-start;width:100%}.division-form{max-width:none}.user-list-heading{align-items:flex-start;flex-direction:column}.user-search{width:100%}.user-search input{min-width:0}.user-metrics{gap:8px}.reset-card{align-items:flex-start}.one-time-reset{overflow:hidden}}
</style>
@endsection
