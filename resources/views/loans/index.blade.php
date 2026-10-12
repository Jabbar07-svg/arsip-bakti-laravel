@extends('layouts.app')

@section('title', 'Peminjaman Arsip')

@section('content')
<main class="page">
    <div class="page-heading-row">
        <div><div class="eyebrow">Manajemen Sirkulasi</div><h1>Peminjaman Arsip</h1><p class="page-description">Kelola arsip yang dipinjam, jatuh tempo, dan telah dikembalikan.</p></div>
        <a class="button button-primary" href="{{ route('loans.create') }}">+ Buat peminjaman</a>
    </div>

    @if (session('success'))<div class="notice-success" role="status">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="notice-error" role="alert">{{ session('error') }}</div>@endif

    <section class="loan-metrics" aria-label="Ringkasan transaksi">
        <article class="panel"><span>Total transaksi</span><strong>{{ number_format($total, 0, ',', '.') }}</strong></article>
        <article class="panel"><span>Sedang dipinjam</span><strong>{{ number_format($active, 0, ',', '.') }}</strong></article>
        <article class="panel"><span>Dikembalikan</span><strong>{{ number_format($returned, 0, ',', '.') }}</strong></article>
        <article class="panel"><span>Jatuh tempo</span><strong>{{ number_format($due, 0, ',', '.') }}</strong></article>
    </section>

    <form class="loan-filters panel" method="get" action="{{ route('loans.index') }}">
        <label class="visually-hidden" for="loan-search">Cari transaksi</label><input id="loan-search" type="search" name="q" value="{{ $search }}" placeholder="Cari peminjam, unit, berkas, atau QR">
        <label class="visually-hidden" for="loan-status">Filter status</label><select id="loan-status" name="status"><option value="">Semua status</option><option value="Dipinjam" @selected($status === 'Dipinjam')>Dipinjam</option><option value="Dikembalikan" @selected($status === 'Dikembalikan')>Dikembalikan</option></select>
        <button class="button button-primary" type="submit">Terapkan</button>
    </form>

    <section class="panel loan-list">
        <div class="panel-heading"><div><h2>Daftar transaksi</h2><p>Data mengikuti hak akses arsip akun Anda.</p></div></div>
        @if ($loans->isEmpty())
            <div class="empty-state">Belum ada transaksi yang sesuai.</div>
        @else
            <div class="table-scroll">
                <table class="report-table loan-table">
                    <thead><tr><th>Arsip</th><th>Peminjam</th><th>Tanggal</th><th>Status</th><th>Pengembalian</th></tr></thead>
                    <tbody>
                    @foreach ($loans as $loan)
                        <tr>
                            <td><a href="{{ route('archives.show', $loan->arsip_id) }}"><strong>{{ $loan->nama_berkas }}</strong></a><small class="loan-meta">{{ $loan->nomor_berkas }} · {{ $loan->qr_code }}</small></td>
                            <td>{{ $loan->nama_peminjam }}<small class="loan-meta">{{ $loan->unit ?: 'Tanpa unit' }}</small></td>
                            <td>{{ \Illuminate\Support\Carbon::parse($loan->tanggal_pinjam)->format('d/m/Y') }}<small class="loan-meta">Rencana kembali: {{ $loan->tanggal_rencana_kembali ? \Illuminate\Support\Carbon::parse($loan->tanggal_rencana_kembali)->format('d/m/Y') : '—' }}</small></td>
                            <td><span class="status status-{{ \Illuminate\Support\Str::slug($loan->status) }}">{{ $loan->status }}</span>@if ($loan->status === 'Dipinjam' && $loan->tanggal_rencana_kembali && $loan->tanggal_rencana_kembali < today()->toDateString())<small class="loan-overdue">Terlambat</small>@endif</td>
                            <td>
                                @if ($loan->status === 'Dipinjam')
                                    <form class="return-form" method="post" action="{{ route('loans.return', $loan->id) }}" onsubmit="return confirm('Catat pengembalian arsip ini?')">
                                        @csrf @method('patch')
                                        <label class="visually-hidden" for="condition-{{ $loan->id }}">Kondisi saat kembali</label>
                                        <select id="condition-{{ $loan->id }}" name="condition_return" required><option value="">Pilih kondisi</option>@foreach (['Baik', 'Rusak Ringan', 'Rusak Berat', 'Tidak Lengkap'] as $condition)<option value="{{ $condition }}">{{ $condition }}</option>@endforeach</select>
                                        <button class="button button-small" type="submit">Kembalikan</button>
                                    </form>
                                @else
                                    <span>{{ $loan->tanggal_dikembalikan ? \Illuminate\Support\Carbon::parse($loan->tanggal_dikembalikan)->format('d/m/Y') : '—' }}</span>
                                    <small class="loan-meta">{{ $loan->condition_return ?: '' }}</small>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($loans->hasPages())<div class="pagination">{{ $loans->links() }}</div>@endif
        @endif
    </section>
</main>
<style>
.loan-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:11px;margin-top:18px}.loan-metrics .panel{margin:0;padding:15px}.loan-metrics span{display:block;color:#7c8f9d;font-size:10px}.loan-metrics strong{display:block;margin-top:6px;color:#173b58;font-size:24px}.loan-filters{display:grid;grid-template-columns:minmax(200px,1fr) 190px auto;gap:8px;padding:12px}.loan-filters input,.loan-filters select,.return-form select{min-width:0;min-height:39px;padding:0 9px;border:1px solid #dce6ec;border-radius:8px;background:#fff;color:#29485e;font-size:11px}.loan-list{overflow:hidden}.loan-table{min-width:890px}.loan-table td{vertical-align:top}.loan-meta{display:block;margin-top:4px;color:#8295a3;font-size:10px}.loan-overdue{display:block;margin-top:4px;color:#aa413a;font-size:10px;font-weight:700}.return-form{display:flex;gap:5px;min-width:210px}.button-small{min-height:34px;padding:0 10px;font-size:10px}
@media(max-width:760px){.loan-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.loan-filters{grid-template-columns:1fr 1fr}.loan-filters input{grid-column:1/-1}}
@media(max-width:420px){.loan-metrics .panel{padding:12px}.loan-metrics strong{font-size:20px}.loan-filters{grid-template-columns:1fr}.loan-filters input{grid-column:auto}.loan-filters .button{width:100%}}
</style>
@endsection
