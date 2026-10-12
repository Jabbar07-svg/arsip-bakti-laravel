@extends('layouts.app')

@section('title', 'Laporan Arsip')

@section('content')
<main class="page report-page">
    <div class="page-heading-row">
        <div>
            <div class="eyebrow">Pelaporan</div>
            <h1>Laporan Arsip</h1>
            <p class="page-description">Susun dan cetak laporan berdasarkan periode, kategori, status, dan lokasi.</p>
        </div>
        <div class="report-actions">
            <a class="button" href="{{ route('reports.export', array_filter($filters, fn ($value) => $value !== null) + ['type' => 'csv']) }}">Unduh CSV</a>
            <a class="button button-primary" href="{{ route('reports.export', array_filter($filters, fn ($value) => $value !== null) + ['type' => 'xlsx']) }}">Unduh Excel</a>
            <a class="button" href="{{ route('reports.export', array_filter($filters, fn ($value) => $value !== null) + ['type' => 'pdf']) }}">Unduh PDF</a>
            <a class="button" target="_blank" rel="noopener" href="{{ route('reports.print', array_filter($filters, fn ($value) => $value !== null)) }}">Versi cetak</a>
        </div>
    </div>

    <section class="panel filter-panel">
        <form method="get" action="{{ route('reports.index') }}" class="report-filters">
            <label>Tahun<input type="number" name="tahun" min="1901" max="2155" value="{{ $filters['tahun'] }}" placeholder="{{ now()->year }}"></label>
            <label>Bulan<select name="bulan"><option value="">Semua bulan</option>@foreach ($monthNames as $number => $name)<option value="{{ $number }}" @selected((string) $filters['bulan'] === (string) $number)>{{ $name }}</option>@endforeach</select></label>
            <label>Status<select name="status"><option value="">Semua status</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>@endforeach</select></label>
            <label>Kategori<select name="kategori"><option value="0">Semua kategori</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((int) $filters['kategori'] === $category->id)>{{ $category->nama_kategori }}</option>@endforeach</select></label>
            <label>Lokasi<select name="lokasi"><option value="0">Semua lokasi</option>@foreach ($locations as $location)<option value="{{ $location->id }}" @selected((int) $filters['lokasi'] === $location->id)>{{ implode(' / ', array_filter([$location->gudang, $location->rak, $location->lemari, $location->box])) }}</option>@endforeach</select></label>
            <div class="filter-actions"><button class="button button-primary" type="submit">Terapkan filter</button><a class="button" href="{{ route('reports.index') }}">Reset</a></div>
        </form>
    </section>

    <section class="panel report-paper">
        <header class="report-masthead">
            <img src="{{ asset('images/bakti-logo.png') }}" alt="Logo BAKTI Komdigi">
            <div><span>Arsip BAKTI · Komdigi</span><h2>Laporan Data Arsip</h2><p>Gudang Utama · Dokumen resmi sistem pengelolaan arsip</p></div>
        </header>
        <div class="report-summary"><span><strong>{{ number_format($archives->count(), 0, ',', '.') }}</strong> arsip ditemukan</span><span>Dicetak {{ now()->format('d/m/Y H:i') }}</span></div>
        @if ($archives->isEmpty())
            <div class="empty-state">Tidak ada data sesuai filter.</div>
        @else
            <div class="table-scroll">
                <table class="report-table">
                    <thead><tr><th>No.</th><th>QR ID</th><th>Nomor berkas</th><th>Nama / Judul</th><th>Kategori</th><th>Tanggal masuk</th><th>Lokasi</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach ($archives as $index => $archive)
                        <tr>
                            <td>{{ $index + 1 }}</td><td><span class="qr-id">{{ $archive->qr_code }}</span></td>
                            <td>{{ $archive->nomor_berkas }}</td><td>{{ $archive->nama_berkas }}<small class="report-code">{{ $archive->kode_arsip }}</small></td>
                            <td>{{ $archive->nama_kategori ?? '—' }}</td><td>{{ \Illuminate\Support\Carbon::parse($archive->tanggal_masuk)->format('d/m/Y') }}</td>
                            <td>{{ implode(' / ', array_filter([$archive->gudang, $archive->rak, $archive->lemari, $archive->box])) ?: '—' }}</td>
                            <td>{{ $archive->status }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</main>
<style>
.page-heading-row{display:flex;align-items:flex-end;justify-content:space-between;gap:16px}.report-actions{display:flex;gap:7px;flex-wrap:wrap}.filter-panel{padding:17px}.report-filters{display:grid;grid-template-columns:repeat(5,minmax(130px,1fr));gap:11px}.report-filters label{display:grid;gap:6px;color:#49677c;font-size:10px;font-weight:700}.report-filters input,.report-filters select{width:100%;min-height:41px;padding:0 10px;border:1px solid #dce6ec;border-radius:8px;background:white;color:#29485e;font-size:12px}.filter-actions{grid-column:1/-1;display:flex;gap:8px}.report-paper{padding:23px;overflow:hidden}.report-masthead{display:flex;align-items:center;gap:19px;padding:0 0 16px;border-bottom:2px solid #087fc3}.report-masthead img{width:96px;height:60px;object-fit:contain}.report-masthead span{color:#087fc3;font-size:10px;font-weight:800;letter-spacing:.7px;text-transform:uppercase}.report-masthead h2{margin:5px 0;color:#173b58;font-size:20px}.report-masthead p{margin:0;color:#8295a3;font-size:10px}.report-summary{display:flex;justify-content:space-between;gap:10px;margin:15px 0;color:#718696;font-size:10px}.report-summary strong{color:#173b58;font-size:14px}.report-code{display:block;margin-top:3px;color:#8295a3;font-size:9px}.table-scroll{overflow-x:auto}.report-table{min-width:900px}.empty-state{padding:28px;text-align:center;color:#748a9a}
@media(max-width:850px){.page-heading-row{align-items:flex-start;flex-direction:column}.report-actions{width:100%}.report-actions .button{flex:1}.report-filters{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:480px){.report-filters{grid-template-columns:1fr}.filter-actions{grid-column:auto}.report-actions{display:grid;grid-template-columns:1fr 1fr}.report-actions .button:last-child{grid-column:1/-1}.report-paper{padding:15px}.report-masthead img{width:70px}.report-summary{flex-direction:column}}
</style>
@endsection
