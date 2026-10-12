@extends('layouts.app')

@section('title', 'Cetak QR Massal')

@section('content')
<main class="page qr-page">
    <div class="page-heading-row"><div><div class="eyebrow">Identifikasi arsip</div><h1>Cetak QR Massal</h1><p class="page-description">Pilih periode dan kategori. QR dan barcode dibuat di browser menggunakan pustaka lokal.</p></div><div class="qr-page-actions">@if (!$printAll)<a class="button" href="{{ route('qr.index', ['tahun' => $filters['year'], 'bulan' => $filters['month'], 'kategori' => $filters['categoryId'], 'cetak' => 1]) }}">Cetak semua hasil filter ({{ number_format($archiveCount) }})</a>@endif<button class="button button-primary" type="button" onclick="window.print()">{{ $printAll ? 'Cetak semua label' : 'Cetak halaman ini' }}</button></div></div>
    @if ($errors->any())<div class="notice-error" role="alert">{{ $errors->first() }}</div>@endif
    <section class="panel qr-filters">
        <form method="get" action="{{ route('qr.index') }}">
            <label>Tahun<input type="number" name="tahun" min="1900" max="2100" value="{{ $filters['year'] }}" required></label>
            <label>Bulan<select name="bulan"><option value="0">Semua bulan</option>@for ($month = 1; $month <= 12; $month++)<option value="{{ $month }}" @selected($filters['month'] === $month)>Bulan {{ $month }}</option>@endfor</select></label>
            <label>Kategori<select name="kategori"><option value="0">Semua kategori</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected($filters['categoryId'] === (int) $category->id)>{{ $category->nama_kategori }}</option>@endforeach</select></label>
            <button class="button button-primary" type="submit">Terapkan filter</button>
        </form>
        <p class="qr-result-count">{{ number_format($archiveCount) }} label cocok · menampilkan {{ number_format($archives->count()) }}{{ $printAll ? ' seluruh hasil filter' : ' pada halaman ini' }}.</p>
    </section>
    <section class="qr-label-grid">
        @forelse ($archives as $archive)
            <article class="panel qr-label-card">
                <div class="qr-label-heading"><strong>{{ $archive->qr_code }}</strong><span>{{ $archive->status }}</span></div>
                <div class="qr-canvas-frame"><div id="qr-{{ $archive->id }}" data-qr-code="{{ $archive->qr_code }}" data-qr-size="190" aria-label="QR {{ $archive->qr_code }}"></div><p data-qr-error hidden role="alert">QR gagal dibuat. Muat ulang halaman.</p></div>
                <svg class="barcode-svg" data-barcode="{{ $archive->qr_code }}" role="img" aria-label="Barcode {{ $archive->qr_code }}"></svg>
                <p data-barcode-error hidden role="alert">Barcode gagal dibuat.</p>
                <strong class="qr-archive-name">{{ $archive->nama_berkas }}</strong>
                <span class="qr-archive-location">{{ implode(' / ', array_filter([$archive->gudang, $archive->rak, $archive->lemari, $archive->box])) }}</span>
                @unless ($printAll)<a class="button button-small no-print" href="{{ route('qr.archive-label', $archive->id) }}">Label / unduh</a>@endunless
            </article>
        @empty
            <div class="panel empty-state">Tidak ada arsip yang cocok dengan filter ini.</div>
        @endforelse
    </section>
    @if (!$printAll)<div class="no-print">{{ $archives->links() }}</div>@endif
</main>
<style>
.qr-page{max-width:1300px}.qr-page-actions{display:flex;gap:8px;flex-wrap:wrap}.qr-filters{padding:15px 18px}.qr-filters form{display:flex;align-items:end;flex-wrap:wrap;gap:10px}.qr-filters label{display:grid;min-width:150px;gap:5px;color:#526d7f;font-size:10px;font-weight:650}.qr-filters input,.qr-filters select{min-height:38px;padding:8px;border:1px solid #dce5ea;border-radius:8px;background:#fff;color:#29485e}.qr-result-count{margin:12px 0 0;color:#8294a0;font-size:10px}.qr-label-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:14px 0}.qr-label-card{display:grid;justify-items:center;gap:8px;padding:15px;text-align:center;break-inside:avoid}.qr-label-heading{display:flex;justify-content:space-between;align-items:center;gap:8px;width:100%;color:#123957;font-size:10px}.qr-label-heading strong{overflow-wrap:anywhere}.qr-label-heading span{color:#8294a0;font-size:9px}.qr-canvas-frame{display:grid;place-items:center;min-height:195px;width:100%;background:#fff}.qr-canvas-frame canvas{display:block;max-width:100%;height:auto!important;image-rendering:pixelated}.qr-canvas-frame p,[data-barcode-error]{color:#a43b35;font-size:10px}.barcode-svg{display:block;width:min(100%,270px);height:64px}.qr-archive-name{color:#29485e;font-size:11px;line-height:1.4}.qr-archive-location{color:#8294a0;font-size:9px}.qr-label-card .button{margin-top:4px}
@media(max-width:900px){.qr-label-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){.qr-page{padding:12px}.qr-page-actions{width:100%;display:grid}.qr-page-actions .button{width:100%}.qr-label-grid{grid-template-columns:1fr}.qr-filters{padding:12px}.qr-filters form{display:grid;grid-template-columns:1fr 1fr}.qr-filters label{min-width:0}.qr-filters button{grid-column:1/-1}}
@media print{.qr-page{max-width:none;padding:0}.qr-label-grid{grid-template-columns:repeat(3,1fr);gap:7mm}.qr-label-card{border:1px solid #d3dce1;box-shadow:none;padding:8mm}.qr-canvas-frame{min-height:48mm}.qr-canvas-frame canvas{width:45mm!important;height:45mm!important}.barcode-svg{height:16mm}.qr-filters{display:none}}
</style>
@endsection
