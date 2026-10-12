@extends('layouts.app')

@section('title', 'Label QR Box')

@section('content')
<main class="page box-label-page">
    <div class="page-heading-row no-print"><div><div class="eyebrow">Lokasi penyimpanan</div><h1>Label QR Box</h1></div><button class="button button-primary" type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>
    <article class="panel box-label-card">
        <header>ARSIP BAKTI · KOMDIGI</header><h2>LABEL LOKASI BOX</h2>
        <p>{{ implode(' / ', array_filter([$location->gudang, $location->rak, $location->lemari, $location->box])) }}</p>
        <div id="box-label-qr" data-qr-code="{{ $boxCode }}" data-qr-size="320" aria-label="QR {{ $boxCode }}"></div>
        <strong>{{ $boxCode }}</strong>
        <svg data-barcode="{{ $boxCode }}" role="img" aria-label="Barcode {{ $boxCode }}"></svg>
        <span>{{ number_format($archiveCount) }} arsip tersimpan di lokasi ini</span>
        <p data-qr-error hidden role="alert">QR tidak dapat dibuat.</p><p data-barcode-error hidden role="alert">Barcode tidak dapat dibuat.</p>
        <button class="button button-primary no-print" type="button" data-download-qr="box-label-qr" data-filename="{{ $boxCode }}.png">Unduh QR PNG</button>
    </article>
</main>
<style>
.box-label-page{max-width:650px}.box-label-card{display:grid;justify-items:center;gap:10px;margin:16px auto;padding:25px;text-align:center}.box-label-card header{width:100%;padding-bottom:12px;border-bottom:2px solid #0876b3;color:#0876b3;font-size:10px;font-weight:800;letter-spacing:1px}.box-label-card h2{margin:6px 0 0;color:#1c405b;font-size:15px}.box-label-card p{margin:0;color:#718696;font-size:10px}.box-label-card>div{display:grid;place-items:center;width:min(100%,360px);aspect-ratio:1}.box-label-card canvas{max-width:100%;height:auto!important}.box-label-card>strong{color:#123957;font-size:15px}.box-label-card svg{width:min(100%,300px);height:65px}.box-label-card>span{color:#8294a0;font-size:10px}
@media(max-width:500px){.box-label-page{padding:12px}.box-label-card{margin:10px;padding:16px}}
@media print{.box-label-page{padding:0}.box-label-card{max-width:100mm;margin:0 auto;border:0;box-shadow:none}.box-label-card>div{width:75mm}.box-label-card canvas{width:68mm!important;height:68mm!important}.box-label-card svg{height:20mm}}
</style>
@endsection
