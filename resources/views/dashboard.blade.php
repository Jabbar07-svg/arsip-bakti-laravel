@extends('layouts.app')

@section('title', 'Data Arsip')

@section('content')
<main class="page">
    <div class="page-heading-row">
        <div>
            <div class="eyebrow">Inventaris Arsip</div>
            <h1>Data Arsip</h1>
            <p class="page-description">Cari dan kelola arsip sesuai hak akses akun Anda.</p>
        </div>
        @if ($canManageArchives)
            <a class="button button-primary" href="{{ route('archives.create') }}">+ Tambah Arsip</a>
        @endif
    </div>

    @if (session('success'))
        <div class="notice-success" role="status">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="notice-error" role="alert">{{ session('error') }}</div>
    @endif

    <form class="archive-filters" method="get" action="{{ route('archives.index') }}">
        <label class="search-field">
            <span class="visually-hidden">Cari arsip</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Nomor, nama, kode, QR, divisi, gudang, box, atau kategori...">
        </label>
        <label>
            <span class="visually-hidden">Filter status</span>
            <select name="status">
                <option value="">Semua status</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="visually-hidden">Filter lokasi</span>
            <select name="lokasi">
                <option value="">Semua lokasi</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}" @selected((string) $filters['lokasi'] === (string) $location->id)>{{ implode(' / ', array_filter([$location->gudang, $location->rak, $location->lemari, $location->box])) }}</option>
                @endforeach
            </select>
        </label>
        <button class="button button-primary" type="submit">Cari arsip</button>
        @if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['lokasi'] !== null)
            <a class="button" href="{{ route('archives.index') }}">Hapus filter</a>
        @endif
    </form>

    <section class="panel" aria-labelledby="archive-list-title">
        <div class="panel-heading">
            <div>
                <h2 id="archive-list-title">Daftar arsip</h2>
                <p>Data dari database arsip yang sama dengan aplikasi sebelumnya.</p>
            </div>
            <span class="count-badge">{{ number_format($archiveCount, 0, ',', '.') }} arsip</span>
        </div>

        @if ($archives->isEmpty())
            <div class="empty-state">Arsip tidak ditemukan untuk pencarian ini.</div>
        @else
            <div class="archive-table-wrap">
                <table class="archive-table">
                    <thead>
                        <tr><th>Nomor / Nama Berkas</th><th>Kategori</th><th>Tanggal</th><th>Lokasi</th><th>QR ID</th><th>Status</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($archives as $archive)
                            <tr>
                                <td><a class="archive-title" href="{{ route('archives.show', $archive->id) }}">{{ $archive->nama_berkas }}</a><div class="archive-detail">{{ $archive->nomor_berkas }} · {{ $archive->kode_arsip }}</div></td>
                                <td>{{ $archive->nama_kategori ?? '—' }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($archive->tanggal_masuk)->format('d/m/Y') }}</td>
                                <td>{{ implode(' / ', array_filter([$archive->gudang, $archive->rak, $archive->lemari, $archive->box])) }}</td>
                                <td><span class="qr-id">{{ $archive->qr_code }}</span></td>
                                <td><span class="status status-{{ \Illuminate\Support\Str::slug($archive->status) }}">{{ $archive->status }}</span></td>
                                <td><a class="button button-small" href="{{ route('archives.show', $archive->id) }}">Detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="archive-cards">
                @foreach ($archives as $archive)
                    <article class="archive-card">
                        <div class="archive-card-head">
                            <div>
                                <a class="archive-card-title" href="{{ route('archives.show', $archive->id) }}">{{ $archive->nama_berkas }}</a>
                                <div class="archive-card-code">{{ $archive->nomor_berkas }} · {{ $archive->kode_arsip }}</div>
                            </div>
                            <span class="status status-{{ \Illuminate\Support\Str::slug($archive->status) }}">{{ $archive->status }}</span>
                        </div>
                        <div class="archive-card-meta">
                            <div><span>QR ID</span><strong>{{ $archive->qr_code }}</strong></div>
                            <div><span>Tanggal masuk</span><strong>{{ \Illuminate\Support\Carbon::parse($archive->tanggal_masuk)->format('d/m/Y') }}</strong></div>
                            <div><span>Kategori</span><strong>{{ $archive->nama_kategori ?? '—' }}</strong></div>
                            <div><span>Lokasi</span><strong>{{ implode(' / ', array_filter([$archive->gudang, $archive->rak, $archive->lemari, $archive->box])) ?: '—' }}</strong></div>
                        </div>
                        <a class="button button-small card-action" href="{{ route('archives.show', $archive->id) }}">Buka detail</a>
                    </article>
                @endforeach
            </div>
            @if ($archives->hasPages())
                <nav class="pagination" aria-label="Navigasi halaman arsip">
                    <span>Halaman {{ $archives->currentPage() }} dari {{ $archives->lastPage() }}</span>
                    <span class="pagination-buttons">
                        @if ($archives->previousPageUrl())<a class="button" href="{{ $archives->previousPageUrl() }}">Sebelumnya</a>@endif
                        @if ($archives->nextPageUrl())<a class="button" href="{{ $archives->nextPageUrl() }}">Berikutnya</a>@endif
                    </span>
                </nav>
            @endif
        @endif
    </section>
    <p class="footer-note">Data rahasia dan data di luar hak akses tidak akan ditampilkan.</p>
</main>
<style>
.page-heading-row{display:flex;align-items:flex-end;justify-content:space-between;gap:14px}
.archive-filters{display:grid;grid-template-columns:minmax(220px,1fr) minmax(150px,190px) minmax(180px,240px) auto auto;gap:9px;margin-top:21px;padding:13px;border:1px solid #e0e9ef;border-radius:12px;background:#fff}
.archive-filters input,.archive-filters select{width:100%;min-height:42px;padding:0 11px;border:1px solid #dce6ec;border-radius:8px;background:#fff;color:#29485e;font-size:13px}
.archive-filters input:focus,.archive-filters select:focus{border-color:#55a5cc;outline:3px solid rgba(8,127,195,.12)}
.visually-hidden{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.notice-success,.notice-error{margin-top:16px;padding:12px 14px;border-radius:9px;font-size:12px}
.notice-success{background:#edf8f2;color:#187653}
.notice-error{background:#fff1ef;color:#a43b35}
.archive-title{color:#1c405b;font-size:12px;font-weight:700}
.archive-title:hover,.archive-card-title:hover{text-decoration:underline}
.status-hilang,.status-nonaktif{background:#fff0ee;color:#a43b35}
.status-dipinjam,.status-diproses{background:#fff7e6;color:#946516}
.button-small{min-height:32px;padding:0 10px;font-size:10px}
.card-action{display:none}
.pagination-buttons{display:flex;gap:8px}
@media(max-width:680px){
    .page-heading-row{align-items:flex-start;flex-direction:column}
    .page-heading-row>.button{width:100%}
    .archive-filters{grid-template-columns:1fr 1fr;padding:10px}
    .archive-filters .search-field{grid-column:1/-1}
    .archive-filters .button{width:100%}
    .card-action{display:inline-flex;margin-top:12px}
}
@media(max-width:380px){.archive-filters{grid-template-columns:1fr}.archive-filters .search-field{grid-column:auto}}
</style>
@endsection
