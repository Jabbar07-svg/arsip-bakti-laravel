@extends('layouts.app')

@section('title', 'Lokasi Gudang')

@section('content')
<main class="page">
    <div class="page-heading-row">
        <div>
            <div class="eyebrow">Master Data Gudang</div>
            <h1>Lokasi Gudang</h1>
            <p class="page-description">Atur penempatan arsip dari gudang, rak, lemari, hingga box.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="notice-success" role="status">{{ session('success') }}</div>
    @endif

    <section class="location-metrics" aria-label="Ringkasan lokasi">
        <article class="panel location-metric"><span>Lokasi terdaftar</span><strong>{{ number_format($locations->count(), 0, ',', '.') }}</strong></article>
        <article class="panel location-metric"><span>Lokasi aktif</span><strong>{{ number_format($activeLocations, 0, ',', '.') }}</strong></article>
        <article class="panel location-metric"><span>Arsip di lokasi</span><strong>{{ number_format($totalArchives, 0, ',', '.') }}</strong></article>
    </section>

    <div class="location-layout">
        <section class="panel location-form-card" id="location-form">
            <div class="panel-heading">
                <div><h2>{{ $editing ? 'Edit lokasi' : 'Tambah lokasi' }}</h2><p>Semua tingkatan lokasi wajib diisi.</p></div>
            </div>
            <form method="post" action="{{ $editing ? route('locations.update', $editing->id) : route('locations.store') }}" class="location-form">
                @csrf
                @if ($editing) @method('put') @endif
                <label>Gudang<input name="gudang" maxlength="150" value="{{ old('gudang', $editing->gudang ?? 'Gudang Utama') }}" required></label>
                <label>Rak<input name="rak" maxlength="100" value="{{ old('rak', $editing->rak ?? '') }}" placeholder="Contoh: Rak A" required></label>
                <div class="location-form-pair">
                    <label>Lemari<input name="lemari" maxlength="100" value="{{ old('lemari', $editing->lemari ?? '') }}" required></label>
                    <label>Box<input name="box" maxlength="100" value="{{ old('box', $editing->box ?? '') }}" required></label>
                </div>
                <label>Kapasitas berkas (opsional)<input type="number" name="capacity_boxes" min="1" value="{{ old('capacity_boxes', $editing->capacity_boxes ?? '') }}" placeholder="Jumlah berkas"></label>
                <label>Keterangan<textarea name="keterangan" rows="3" maxlength="2000">{{ old('keterangan', $editing->keterangan ?? '') }}</textarea></label>
                @foreach (['gudang', 'rak', 'lemari', 'box', 'capacity_boxes', 'keterangan'] as $field)
                    @error($field)<span class="form-error">{{ $message }}</span>@enderror
                @endforeach
                <div class="location-form-actions">
                    <button class="button button-primary" type="submit">{{ $editing ? 'Simpan perubahan' : 'Tambah lokasi' }}</button>
                    @if ($editing)<a class="button" href="{{ route('locations.index') }}">Batal</a>@endif
                </div>
            </form>
        </section>

        <section class="panel location-list-card">
            <div class="panel-heading location-list-head">
                <div><h2>Daftar lokasi</h2><p>{{ number_format($locations->count(), 0, ',', '.') }} lokasi ditemukan</p></div>
                <form method="get" action="{{ route('locations.index') }}" class="location-search">
                    <label class="visually-hidden" for="location-search">Cari lokasi</label>
                    <input id="location-search" name="q" value="{{ $search }}" placeholder="Cari gudang, rak, lemari, box">
                    <button class="button" type="submit">Cari</button>
                </form>
            </div>
            @if ($locations->isEmpty())
                <div class="empty-state">Belum ada lokasi yang sesuai.</div>
            @else
                <div class="table-scroll">
                    <table class="report-table location-table">
                        <thead><tr><th>Lokasi</th><th>Arsip / kapasitas</th><th>Status</th><th>Aksi</th></tr></thead>
                        <tbody>
                        @foreach ($locations as $location)
                            @php($locationLabel = implode(' / ', array_filter([$location->gudang, $location->rak, $location->lemari, $location->box])))
                            @php($capacity = (int) ($location->capacity_boxes ?? 0))
                            <tr>
                                <td><strong>{{ $location->gudang }}</strong><small class="location-path">{{ $locationLabel }}</small></td>
                                <td>
                                    <a href="{{ route('archives.index', ['lokasi' => $location->id]) }}">{{ number_format((int) $location->total_arsip, 0, ',', '.') }} arsip{{ $capacity > 0 ? ' / '.number_format($capacity, 0, ',', '.') : '' }}</a>
                                    @if ($capacity > 0)<small class="location-path">{{ min(100, (int) round(((int) $location->total_arsip / $capacity) * 100)) }}% terisi</small>@endif
                                </td>
                                <td><span class="status status-{{ \Illuminate\Support\Str::slug($location->status) }}">{{ $location->status }}</span></td>
                                <td>
                                    <div class="location-actions">
                                        <a class="button location-action" href="{{ route('locations.index', ['edit' => $location->id, 'q' => $search]) }}#location-form">Edit</a>
                                        <form method="post" action="{{ route('locations.status', $location->id) }}">
                                            @csrf @method('patch')
                                            <button class="button location-action" type="submit">{{ $location->status === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</main>
<style>
.location-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:18px}.location-metric{margin:0;padding:16px 18px}.location-metric span{display:block;color:#74879a;font-size:11px}.location-metric strong{display:block;margin-top:6px;color:#153b59;font-size:25px}.location-layout{display:grid;grid-template-columns:minmax(260px,.72fr) minmax(0,1.7fr);gap:16px;align-items:start}.location-form-card,.location-list-card{padding:0 18px 18px}.location-form{display:grid;gap:12px}.location-form label{display:grid;gap:6px;color:#52697c;font-size:11px;font-weight:700}.location-form input,.location-form textarea{width:100%;min-height:41px;padding:9px 11px;border:1px solid #dce6ec;border-radius:8px;background:#fff;color:#29485e;font-size:13px}.location-form textarea{resize:vertical}.location-form-pair{display:grid;grid-template-columns:1fr 1fr;gap:10px}.location-form-actions,.location-actions{display:flex;flex-wrap:wrap;gap:7px}.location-list-head{align-items:center}.location-search{display:flex;gap:7px}.location-search input{min-width:0;width:min(250px,42vw);min-height:38px;padding:0 10px;border:1px solid #dce6ec;border-radius:8px}.location-table{min-width:720px}.location-path{display:block;margin-top:4px;color:#8193a0;font-size:10px}.location-action{min-height:32px;padding:0 9px;font-size:10px}.location-actions form{margin:0}
@media(max-width:980px){.location-layout{grid-template-columns:1fr}.location-list-card{margin-top:0}}
@media(max-width:620px){.location-metrics{gap:7px}.location-metric{padding:12px 9px}.location-metric span{font-size:9px}.location-metric strong{font-size:19px}.location-form-card,.location-list-card{padding:0 12px 13px}.location-list-head{align-items:flex-start;flex-direction:column}.location-search,.location-search input{width:100%}.location-form-pair{gap:7px}.location-actions{min-width:150px}}
</style>
@endsection
