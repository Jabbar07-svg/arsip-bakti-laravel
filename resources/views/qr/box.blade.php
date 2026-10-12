@extends('layouts.app')

@section('title', 'Detail Box')

@section('content')
<main class="page box-page">
    <div class="page-heading-row"><div><div class="eyebrow">Lokasi penyimpanan</div><h1>Detail Box</h1><p class="page-description">{{ implode(' / ', array_filter([$location->gudang, $location->rak, $location->lemari, $location->box])) }}</p></div><a class="button" href="{{ route('qr.box-label', $location->id) }}">Cetak label box</a></div>
    <div class="box-layout">
        <section class="panel box-summary"><span class="eyebrow">Kode QR box</span><strong class="box-code">{{ $boxCode }}</strong><div id="box-qr" data-qr-code="{{ $boxCode }}" data-qr-size="190" aria-label="QR {{ $boxCode }}"></div><svg data-barcode="{{ $boxCode }}" role="img" aria-label="Barcode {{ $boxCode }}"></svg><p data-qr-error hidden role="alert">QR gagal dibuat.</p><p data-barcode-error hidden role="alert">Barcode gagal dibuat.</p><a class="button button-primary" href="{{ route('qr.box-label', $location->id) }}">Cetak / unduh label</a></section>
        <section class="panel box-archives"><div class="panel-heading"><div><h2>Arsip dalam box</h2><p>{{ number_format($archives->total()) }} arsip dapat diakses akun ini</p></div></div>
            <div class="box-list">@forelse ($archives as $archive)<article><div><strong>{{ $archive->nama_berkas }}</strong><span>{{ $archive->qr_code }} · {{ $archive->nama_kategori ?: 'Tanpa kategori' }} · {{ $archive->status }}</span></div><a class="button button-small" href="{{ route('archives.show', $archive->id) }}">Detail</a></article>@empty<div class="empty-inline">Belum ada arsip yang dapat diakses di box ini.</div>@endforelse</div>{{ $archives->links() }}
        </section>
    </div>
</main>
<style>
.box-page{max-width:1200px}.box-layout{display:grid;grid-template-columns:minmax(240px,.6fr) minmax(0,1.4fr);gap:14px;margin-top:16px;align-items:start}.box-summary{display:grid;justify-items:center;gap:9px;padding:18px;text-align:center}.box-code{color:#123957;font-size:15px}#box-qr{display:grid;place-items:center;min-height:190px}#box-qr canvas{max-width:100%;height:auto!important}.box-summary svg{width:min(100%,250px);height:60px}.box-summary .button{width:100%}.box-archives{overflow:hidden}.box-list{display:grid;gap:8px;padding:12px}.box-list article{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:11px;border:1px solid #e7edf1;border-radius:9px}.box-list article div{display:grid;gap:4px;min-width:0}.box-list strong{color:#1c405b;font-size:11px}.box-list span{color:#8294a0;font-size:9px;overflow-wrap:anywhere}.box-archives .pagination{padding:0 12px 12px}
@media(max-width:800px){.box-layout{grid-template-columns:1fr}}
@media(max-width:560px){.box-page{padding:12px}.box-list article{align-items:flex-start;flex-direction:column}.box-list .button{width:100%}}
</style>
@endsection
