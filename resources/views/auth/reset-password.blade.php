@extends('layouts.app')

@section('title', 'Atur Ulang Password')

@section('content')
<main class="auth-page">
    <section class="auth-card">
        <div class="eyebrow">Pemulihan akun</div>
        <h1>Atur ulang password</h1>
        <p class="page-description">Tautan ini hanya dapat digunakan sekali dan berlaku selama 30 menit.</p>
        <p class="reset-username">Akun: <strong>{{ $username }}</strong></p>
        @if ($errors->any())<div class="notice-error" role="alert">{{ $errors->first() }}</div>@endif
        <form method="post" action="{{ route('password.reset.store', $token) }}" class="reset-form">
            @csrf
            <label>Password baru<input type="password" name="password" minlength="8" maxlength="255" autocomplete="new-password" required></label>
            <label>Ulangi password baru<input type="password" name="password_confirmation" autocomplete="new-password" required></label>
            <button class="button button-primary" type="submit">Simpan password baru</button>
        </form>
    </section>
</main>
<style>
.reset-username{margin:16px 0;color:#61798b;font-size:12px}.reset-form{display:grid;gap:14px}.reset-form label{display:grid;gap:6px;color:#49677c;font-size:11px;font-weight:700}.reset-form input{width:100%;min-height:42px;padding:8px 10px;border:1px solid #dce6ec;border-radius:8px;background:#fff;color:#29485e;font-size:13px}
</style>
@endsection
