@extends('layouts.app')

@section('title', 'Peminjaman Saya')

@section('content')
<main class="page employee-loans-page">
    <div class="page-heading-row">
        <div><div class="eyebrow">Ruang kerja Pegawai</div><h1>Peminjaman Saya</h1><p class="page-description">Pantau transaksi tanpa QR dan peminjaman dari permintaan yang telah disetujui.</p></div>
        <a class="button button-primary" href="{{ route('archives.index') }}">Cari arsip</a>
    </div>
    @if (session('success'))<div class="notice-success" role="status">{{ session('success') }}</div>@endif
    <section class="loan-metrics" aria-label="Ringkasan peminjaman">
        <article class="panel"><span>Total transaksi</span><strong>{{ number_format($total, 0, ',', '.') }}</strong></article>
        <article class="panel"><span>Sedang dipinjam</span><strong>{{ number_format($active, 0, ',', '.') }}</strong></article>
        <article class="panel"><span>Dikembalikan</span><strong>{{ number_format($returned, 0, ',', '.') }}</strong></article>
    </section>
    <section class="panel employee-loan-list">
        <div class="panel-heading"><div><h2>Riwayat transaksi</h2><p>Transaksi langsung Anda dan permintaan yang disetujui.</p></div></div>
        @forelse ($loans as $loan)
            <article class="employee-loan-item">
                <div class="employee-loan-archive"><a href="{{ route('archives.show', $loan->arsip_id) }}"><strong>{{ $loan->nama_berkas }}</strong></a><small>{{ $loan->nomor_berkas }}</small></div>
                <div><span class="employee-loan-label">Tanggal pinjam</span><strong>{{ \Illuminate\Support\Carbon::parse($loan->tanggal_pinjam)->format('d/m/Y') }}</strong></div>
                <div><span class="employee-loan-label">{{ $loan->tanggal_dikembalikan ? 'Tanggal dikembalikan' : 'Rencana kembali' }}</span><strong>{{ $loan->tanggal_dikembalikan ? \Illuminate\Support\Carbon::parse($loan->tanggal_dikembalikan)->format('d/m/Y') : ($loan->tanggal_rencana_kembali ? \Illuminate\Support\Carbon::parse($loan->tanggal_rencana_kembali)->format('d/m/Y') : '—') }}</strong></div>
                <span class="status status-{{ \Illuminate\Support\Str::slug($loan->status) }}">{{ $loan->status }}</span>
                @if ($loan->keperluan)<p class="employee-loan-purpose">{{ $loan->keperluan }}</p>@endif
                @if ($loan->condition_return)<p class="employee-loan-purpose">Kondisi saat dikembalikan: {{ $loan->condition_return }}</p>@endif
            </article>
        @empty
            <div class="empty-state">Belum ada transaksi. Buka arsip yang dapat Anda akses lalu pilih <strong>Pinjam tanpa QR</strong>.</div>
        @endforelse
        @if ($loans->hasPages())<div class="pagination">{{ $loans->links() }}</div>@endif
    </section>
</main>
<style>
.loan-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:11px;margin-top:18px}.loan-metrics .panel{margin:0;padding:15px}.loan-metrics span{display:block;color:#7c8f9d;font-size:10px}.loan-metrics strong{display:block;margin-top:6px;color:#173b58;font-size:24px}
.employee-loan-list{overflow:hidden}.employee-loan-item{display:grid;grid-template-columns:minmax(180px,2fr) repeat(2,minmax(100px,1fr)) auto;align-items:center;gap:12px;padding:16px 19px;border-bottom:1px solid #edf1f4}.employee-loan-item:last-of-type{border-bottom:0}.employee-loan-item strong{color:#29485e;font-size:11px}.employee-loan-archive small{display:block;margin-top:5px;color:#8295a3;font-size:10px}.employee-loan-label{display:block;margin-bottom:5px;color:#8295a3;font-size:10px}.employee-loan-purpose{grid-column:1/-1;margin:0;color:#637b8b;font-size:11px;line-height:1.5}
@media(max-width:720px){.employee-loan-item{grid-template-columns:1fr 1fr}.employee-loan-archive,.employee-loan-purpose{grid-column:1/-1}.employee-loan-item>.status{justify-self:start}}
@media(max-width:450px){.loan-metrics .panel{padding:12px}.loan-metrics strong{font-size:20px}.employee-loan-item{padding:14px}}
</style>
@endsection
