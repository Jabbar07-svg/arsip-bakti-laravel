@extends('layouts.app')

@section('title', 'Katalog Arsip Publik')

@section('content')
<main class="page customer-page">
    <div class="customer-page-heading"><div><span class="customer-kicker">Koleksi terbuka</span><h1>Katalog arsip publik</h1><p>Telusuri arsip yang memang tersedia untuk akses publik.</p></div><span class="customer-result-count">{{ number_format($archiveCount, 0, ',', '.') }} hasil</span></div>
    @if ($errors->any())<div class="customer-alert customer-alert-error" role="alert">{{ $errors->first() }}</div>@endif
    <section class="panel customer-catalog-panel">
        <form class="customer-filter-form" method="get" action="{{ route('customer.archives.index') }}">
            <label class="customer-search-field"><span>Cari arsip</span><input type="search" name="q" value="{{ $filters['search'] }}" placeholder="Nomor, judul, kategori, atau QR"></label>
            <label><span>Kategori</span><select name="kategori"><option value="">Semua kategori</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected($filters['categoryId'] === (int) $category->id)>{{ $category->nama_kategori }}</option>@endforeach</select></label>
            <label><span>Status</span><select name="status"><option value="">Semua status</option>@foreach (['Tersimpan', 'Dipinjam', 'Diproses', 'Hilang', 'Nonaktif'] as $option)<option value="{{ $option }}" @selected($filters['status'] === $option)>{{ $option }}</option>@endforeach</select></label>
            <label><span>Dari tanggal</span><input type="date" name="dari" value="{{ $filters['from'] }}"></label>
            <label><span>Sampai tanggal</span><input type="date" name="sampai" value="{{ $filters['to'] }}"></label>
            <div class="customer-filter-actions"><button class="button button-primary" type="submit">Terapkan filter</button><a class="button" href="{{ route('customer.archives.index') }}">Reset</a></div>
        </form>
        <div class="customer-catalog-list">
            @forelse ($archives as $archive)
                <article class="customer-catalog-item">
                    <span class="customer-document-icon" aria-hidden="true">▤</span>
                    <div class="customer-catalog-copy"><div class="customer-catalog-top"><span class="status status-{{ \Illuminate\Support\Str::slug($archive->status) }}">{{ $archive->status }}</span><span class="customer-catalog-date">{{ \Illuminate\Support\Carbon::parse($archive->tanggal_masuk)->format('d/m/Y') }}</span></div><h2><a href="{{ route('archives.show', $archive->id) }}">{{ $archive->nama_berkas }}</a></h2><p>{{ $archive->nomor_berkas }} · {{ $archive->kode_arsip }}</p><small>{{ $archive->nama_kategori ?: 'Tanpa kategori' }} · QR {{ $archive->qr_code }}</small></div>
                    <a class="button" href="{{ route('archives.show', $archive->id) }}">Lihat detail</a>
                </article>
            @empty
                <div class="customer-empty customer-catalog-empty"><strong>Tidak ada arsip yang cocok</strong><span>Coba ubah kata pencarian atau hapus filter untuk melihat koleksi lain.</span><a href="{{ route('customer.archives.index') }}">Hapus semua filter</a></div>
            @endforelse
        </div>
        <div class="customer-pagination">{{ $archives->links() }}</div>
    </section>
</main>
<style>
.customer-page{max-width:1240px}.customer-page-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:15px;margin-bottom:18px}.customer-page-heading h1{margin:7px 0 5px}.customer-page-heading p{margin:0;color:#748a9a;font-size:12px}.customer-result-count{padding:8px 11px;border-radius:20px;background:#eaf5fa;color:#286183;font-size:10px;font-weight:800;white-space:nowrap}.customer-catalog-panel{margin-top:0;padding:0 18px 14px}.customer-filter-form{display:grid;grid-template-columns:minmax(180px,2fr) repeat(4,minmax(120px,1fr)) auto;align-items:end;gap:10px;padding:16px 0;border-bottom:1px solid #edf1f4}.customer-filter-form label{display:grid;gap:6px;color:#526d7f;font-size:10px;font-weight:700}.customer-filter-form input,.customer-filter-form select{width:100%;min-height:40px;padding:7px 9px;border:1px solid #dce5ea;border-radius:8px;background:#fff;color:#29485e;font:inherit}.customer-filter-actions{display:flex;gap:7px}.customer-filter-actions .button{min-height:40px;padding:0 11px;font-size:10px}.customer-catalog-list{display:grid}.customer-catalog-item{display:flex;align-items:center;gap:12px;padding:15px 4px;border-bottom:1px solid #edf1f4}.customer-catalog-copy{min-width:0;flex:1}.customer-catalog-top{display:flex;align-items:center;justify-content:space-between;gap:8px}.customer-catalog-date{color:#8295a3;font-size:9px}.customer-catalog-copy h2{margin:6px 0 3px;font-size:13px}.customer-catalog-copy h2 a{color:#1c405b}.customer-catalog-copy p,.customer-catalog-copy>small{display:block;margin:0;color:#7d909e;font-size:10px}.customer-catalog-copy>small{margin-top:5px;color:#8b9aa5}.customer-catalog-item>.button{flex:0 0 auto;min-height:37px;font-size:10px}.customer-catalog-empty{justify-items:center;padding:55px 18px;text-align:center}.customer-catalog-empty a{margin-top:6px;font-weight:700}.customer-pagination{padding-top:13px}
@media(max-width:1000px){.customer-filter-form{grid-template-columns:repeat(3,minmax(0,1fr))}.customer-search-field{grid-column:span 2}}
@media(max-width:650px){.customer-page-heading{align-items:flex-start;flex-direction:column}.customer-filter-form{grid-template-columns:repeat(2,minmax(0,1fr))}.customer-search-field{grid-column:1/-1}.customer-filter-actions{grid-column:1/-1}.customer-filter-actions .button{flex:1}.customer-catalog-panel{padding:0 12px 12px}.customer-catalog-item{align-items:flex-start;gap:9px;padding:13px 0}.customer-catalog-item>.button{align-self:center;min-height:35px;padding:0 9px}.customer-catalog-copy h2{font-size:12px}.customer-catalog-date{font-size:8px}}
</style>
@endsection
