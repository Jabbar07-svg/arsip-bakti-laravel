@extends('layouts.app')

@section('title', 'Label QR Arsip')

@section('content')
<main class="page qr-print-page">
    <div class="page-heading-row no-print"><div><div class="eyebrow">Label identifikasi</div><h1>QR & Barcode Arsip</h1></div><button class="button button-primary" type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>
    <article class="panel archive-label">
        <header>ARSIP BAKTI · KOMDIGI</header>
        <h2>{{ $archive->nama_berkas }}</h2>
        <p>{{ $archive->nomor_berkas }} · {{ $archive->kode_arsip }}</p>
        <div class="qr-large"><div id="archive-label-qr" data-qr-code="{{ $archive->qr_code }}" data-qr-size="400" aria-label="QR {{ $archive->qr_code }}"></div><p data-qr-error hidden role="alert">QR tidak dapat dibuat.</p></div>
        <strong class="label-code">{{ $archive->qr_code }}</strong>
        <svg class="label-barcode" data-barcode="{{ $archive->qr_code }}" role="img" aria-label="Barcode {{ $archive->qr_code }}"></svg>
        <p data-barcode-error hidden role="alert">Barcode tidak dapat dibuat.</p>
        <div class="label-location">{{ implode(' / ', array_filter([$archive->gudang, $archive->rak, $archive->lemari, $archive->box])) }}</div>
        <div class="label-actions no-print"><button class="button button-primary" type="button" data-download-qr="archive-label-qr" data-filename="{{ $archive->qr_code }}.png">Unduh QR PNG</button><a class="button" href="{{ route('archives.show', $archive->id) }}">Kembali ke detail</a></div>
    </article>
</main>
<style>
.qr-print-page{max-width:800px}.archive-label{display:grid;justify-items:center;gap:9px;margin:15px auto;padding:25px;text-align:center}.archive-label header{width:100%;padding-bottom:12px;border-bottom:2px solid #0876b3;color:#0876b3;font-size:11px;font-weight:800;letter-spacing:1px}.archive-label h2{margin:7px 0 0;color:#1c405b;font-size:16px}.archive-label>p{margin:0;color:#718696;font-size:10px}.qr-large{display:grid;place-items:center;width:min(100%,440px);aspect-ratio:1;padding:15px;border:1px solid #edf1f4;border-radius:10px;background:white}.qr-large canvas{display:block;max-width:100%;height:auto!important;image-rendering:pixelated}.label-code{color:#123957;font-size:16px;overflow-wrap:anywhere}.label-barcode{display:block;width:min(100%,400px);height:80px}.label-location{color:#718696;font-size:10px}.label-actions{display:flex;gap:8px;margin-top:5px}
@media(max-width:500px){.archive-label{margin:10px;padding:15px}.label-actions{width:100%;flex-direction:column}.label-actions>*{width:100%}}
@media print{.qr-print-page{padding:0}.archive-label{max-width:110mm;margin:0 auto;border:0;box-shadow:none}.qr-large{width:85mm;padding:5mm;border:0}.qr-large canvas{width:75mm!important;height:75mm!important}.label-barcode{height:20mm}}
</style>
@endsection
