@extends('layouts.app')

@section('title', 'Berita Acara Penyusutan')

@section('content')
<main class="page minutes-page">
    <div class="page-heading-row no-print"><div><div class="eyebrow">Dokumen pelaksanaan</div><h1>Berita Acara Penyusutan</h1></div><button class="button button-primary" type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>
    @if (session('success'))<div class="notice-success no-print" role="status">{{ session('success') }}</div>@endif
    <article class="panel minutes-document">
        <header><strong>SISTEM ARSIP BAKTI</strong><span>BERITA ACARA PENYUSUTAN ARSIP</span></header>
        <h2>Nomor {{ $record->minutes_number }}</h2>
        <p>Pada tanggal {{ \Illuminate\Support\Carbon::parse($record->completed_at)->format('d/m/Y') }}, telah dicatat pelaksanaan penyusutan terhadap arsip berikut sesuai tindakan yang telah disetujui.</p>
        <dl><div><dt>Nama arsip</dt><dd>{{ $record->nama_berkas }}</dd></div><div><dt>Nomor berkas</dt><dd>{{ $record->nomor_berkas }}</dd></div><div><dt>Tindakan</dt><dd>{{ $record->action }}</dd></div><div><dt>Alasan usulan</dt><dd>{{ $record->reason }}</dd></div><div><dt>Keterangan pelaksanaan</dt><dd>{{ $record->minutes_note }}</dd></div><div><dt>Pengusul</dt><dd>{{ $record->requester_name }}</dd></div><div><dt>Pemeriksa</dt><dd>{{ $record->reviewer_name ?: '—' }}</dd></div></dl>
        <div class="minutes-signatures"><div>Pelaksana / Approver</div><div>Penanggung jawab arsip</div></div>
    </article>
</main>
<style>
.minutes-document{max-width:850px;margin:24px auto;padding:42px 50px;color:#263b4a}.minutes-document header{display:grid;gap:5px;padding-bottom:18px;border-bottom:2px solid #263b4a;text-align:center}.minutes-document header strong{font-size:15px;letter-spacing:.08em}.minutes-document header span{font-size:12px;font-weight:700}.minutes-document h2{margin:22px 0;text-align:center;font-size:15px}.minutes-document p{font-size:13px;line-height:1.8}.minutes-document dl{display:grid;gap:12px;margin:22px 0}.minutes-document dl>div{display:grid;grid-template-columns:190px 1fr;gap:14px}.minutes-document dt{font-weight:700}.minutes-document dd{margin:0;white-space:pre-line}.minutes-signatures{display:grid;grid-template-columns:1fr 1fr;gap:80px;margin-top:70px;text-align:center}.minutes-signatures div{padding-top:55px;border-top:1px solid #73808a}
@media(max-width:600px){.minutes-document{margin:12px;padding:22px}.minutes-document dl>div{grid-template-columns:1fr;gap:3px}.minutes-signatures{gap:25px}}
@media print{.no-print,.topbar{display:none!important}.page{padding:0!important}.minutes-document{max-width:none;margin:0;border:0;box-shadow:none;padding:20mm}.minutes-signatures{page-break-inside:avoid}}
</style>
@endsection
