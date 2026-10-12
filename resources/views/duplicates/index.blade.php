@extends('layouts.app')

@section('title', 'Verifikasi Duplikasi')

@section('content')
<main class="page duplicate-page">
    <div class="page-heading-row"><div><div class="eyebrow">Inventarisasi arsip</div><h1>Verifikasi Duplikasi</h1><p class="page-description">Kandidat ditandai berdasarkan nomor berkas yang sama atau nama, kategori, dan tahun yang sama. Petugas memutuskan hasil akhirnya.</p></div><span class="duplicate-count">{{ number_format($pendingCount) }} menunggu</span></div>
    @if (session('success'))<div class="notice-success" role="status">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="notice-error" role="alert">{{ $errors->first() }}</div>@endif

    <section class="panel duplicate-discovery">
        <div><strong>Perbarui kandidat</strong><p>Jalankan pencarian kandidat baru secara bertahap. Setiap proses memeriksa maksimal 500 pasangan yang belum tercatat.</p></div>
        <form method="post" action="{{ route('duplicates.discover') }}" onsubmit="return confirm('Jalankan pencarian kandidat baru sekarang?')">
            @csrf
            <button class="button button-primary" type="submit">Jalankan discovery</button>
        </form>
    </section>

    <section class="panel duplicate-list">
        @forelse ($reviews as $review)
            <article class="duplicate-card">
                <div class="duplicate-pair">
                    <div><span class="eyebrow">Arsip pertama</span><h2>{{ $review->archive_name }}</h2><p>{{ $review->archive_number }} · {{ $review->archive_qr }}</p><a href="{{ route('archives.show', $review->archive_id) }}">Lihat arsip</a></div>
                    <span class="duplicate-versus">↔</span>
                    <div><span class="eyebrow">Kandidat</span><h2>{{ $review->candidate_name }}</h2><p>{{ $review->candidate_number }} · {{ $review->candidate_qr }}</p><a href="{{ route('archives.show', $review->candidate_id) }}">Lihat kandidat</a></div>
                </div>
                <div class="duplicate-meta"><span>Alasan cocok: <strong>{{ $review->match_reason }}</strong></span><span class="status status-{{ \Illuminate\Support\Str::slug($review->status) }}">{{ $review->status }}</span></div>
                @if ($review->reviewer_note)<p class="duplicate-note">Catatan: {{ $review->reviewer_note }}</p>@endif
                @if ($review->status === 'Menunggu Verifikasi')
                    <form class="duplicate-review-form" method="post" action="{{ route('duplicates.decide', $review->id) }}">@csrf @method('PATCH')<label>Catatan (wajib jika duplikat)<input name="reviewer_note" maxlength="5000"></label><button class="button button-small button-primary" name="status" value="Duplikat">Duplikat</button><button class="button button-small" name="status" value="Bukan Duplikat">Bukan duplikat</button></form>
                @else
                    <small class="duplicate-reviewer">Diperiksa oleh {{ $review->reviewer_name ?: '—' }}</small>
                @endif
            </article>
        @empty
            <div class="empty-state">Belum ditemukan pasangan arsip yang berpotensi duplikat.</div>
        @endforelse
    </section>
</main>
<style>
.duplicate-page{max-width:1150px}.duplicate-count{padding:8px 12px;border-radius:8px;background:#eaf5fb;color:#17678f;font-size:10px;font-weight:800}.duplicate-list{display:grid;gap:10px;margin-top:16px;padding:15px}.duplicate-card{padding:15px;border:1px solid #e7edf1;border-radius:11px}.duplicate-pair{display:grid;grid-template-columns:1fr 30px 1fr;gap:12px;align-items:center}.duplicate-pair h2{margin:5px 0;color:#1c405b;font-size:13px;overflow-wrap:anywhere}.duplicate-pair p,.duplicate-reviewer{display:block;margin:4px 0;color:#8294a0;font-size:10px;overflow-wrap:anywhere}.duplicate-pair a{color:#0876b3;font-size:10px;text-decoration:none}.duplicate-versus{text-align:center;color:#9aabb5;font-size:17px}.duplicate-meta{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:13px;padding-top:10px;border-top:1px solid #edf1f4;color:#8294a0;font-size:10px}.duplicate-meta strong{color:#526d7f}.duplicate-note{margin:8px 0;color:#9b5b19;font-size:10px}.duplicate-review-form{display:flex;align-items:end;gap:7px;margin-top:10px}.duplicate-review-form label{display:grid;flex:1;gap:4px;color:#526d7f;font-size:9px}.duplicate-review-form input{min-height:36px;padding:7px;border:1px solid #dce5ea;border-radius:8px;font:inherit}.duplicate-review-form button{white-space:nowrap}
.duplicate-discovery{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:16px;padding:15px}.duplicate-discovery p{margin:4px 0 0;color:#8294a0;font-size:11px}.duplicate-discovery form{flex:0 0 auto}
@media(max-width:600px){.duplicate-list{padding:11px}.duplicate-pair{grid-template-columns:1fr}.duplicate-versus{text-align:left}.duplicate-review-form{align-items:stretch;flex-direction:column}.duplicate-review-form>*{width:100%}}
@media(max-width:600px){.duplicate-discovery{align-items:stretch;flex-direction:column}.duplicate-discovery form,.duplicate-discovery button{width:100%}}
</style>
@endsection
