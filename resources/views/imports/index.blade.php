@extends('layouts.app')

@section('title', 'Impor / Ekspor Data')

@section('content')
<main class="page imports-page">
    <div class="page-heading-row">
        <div>
            <div class="eyebrow">Migrasi data</div>
            <h1>Impor / Ekspor CSV</h1>
            <p class="page-description">Unggah CSV, periksa hasil validasi lebih dulu, lalu konfirmasi untuk memasukkan baris yang lolos.</p>
        </div>
    </div>

    @if ($errors->any())<div class="notice-error" role="alert">{{ $errors->first() }}</div>@endif

    @if (session('csvImportResult'))
        @php($result = session('csvImportResult'))
        <section class="panel import-result" role="status">
            <strong>Proses selesai</strong>
            <p>{{ $result['imported'] }} baris baru diimpor · {{ $result['updated'] }} baris diperbarui · {{ $result['skipped'] }} baris dilewati.</p>
            @if ($result['errors'])
                <details><summary>Rincian kesalahan ({{ count($result['errors']) }})</summary><ul>@foreach (array_slice($result['errors'], 0, 30) as $error)<li>{{ $error }}</li>@endforeach</ul></details>
            @endif
        </section>
    @endif

    @if (session('csvImportPreview'))
        @php($preview = session('csvImportPreview'))
        <section class="panel import-preview" aria-labelledby="preview-title">
            <div>
                <div class="eyebrow">Pratinjau · belum ada perubahan data</div>
                <h2 id="preview-title">{{ $preview['name'] }}</h2>
                <p>{{ ucfirst($preview['type']) }} · {{ $preview['rowCount'] }} baris data · {{ $preview['valid'] }} baris lolos validasi · {{ count($preview['errors']) }} baris bermasalah.</p>
            </div>
            @if ($preview['errors'])
                <details><summary>Lihat kesalahan baris ({{ count($preview['errors']) }})</summary><ul>@foreach (array_slice($preview['errors'], 0, 30) as $error)<li>{{ $error }}</li>@endforeach</ul></details>
            @endif
            <div class="preview-actions">
                @if ($preview['valid'] > 0)
                    <form method="post" action="{{ route('imports.commit') }}" onsubmit="return confirm('Konfirmasi impor? Baris yang valid akan ditulis ke database arsip.');">
                        @csrf
                        <button class="button button-primary" type="submit">Konfirmasi impor {{ $preview['valid'] }} baris</button>
                    </form>
                @endif
                <form method="post" action="{{ route('imports.discard') }}" onsubmit="return confirm('Batalkan pratinjau ini?');">
                    @csrf @method('DELETE')
                    <button class="button" type="submit">Batalkan pratinjau</button>
                </form>
                <form method="post" action="{{ route('imports.preview') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="type" value="{{ $preview['type'] }}">
                    <input type="file" name="csv_file" accept=".csv,text/csv" required>
                    <button class="button" type="submit">Ganti file</button>
                </form>
            </div>
        </section>
    @endif

    <section class="panel import-card">
        <div class="panel-heading"><div><h2>Unggah CSV untuk pratinjau</h2><p>Maksimal 10 MB. Pratinjau tidak menulis ke database; impor hanya berjalan setelah konfirmasi.</p></div></div>
        <form class="import-form" method="post" action="{{ route('imports.preview') }}" enctype="multipart/form-data">
            @csrf
            <label>Jenis data
                <select name="type" required>
                    <option value="kategori">Kategori</option>
                    <option value="lokasi">Lokasi gudang</option>
                    <option value="arsip">Arsip / inventaris</option>
                </select>
            </label>
            <label>File CSV<input type="file" name="csv_file" accept=".csv,text/csv" required></label>
            <button class="button button-primary" type="submit">Validasi & pratinjau</button>
        </form>
    </section>

    <section class="panel import-card">
        <div class="panel-heading"><div><h2>Unduh data CSV</h2><p>Header ekspor sama dengan template yang digunakan impor PHP Native.</p></div></div>
        <div class="export-links">
            <a class="button" href="{{ route('imports.export', 'kategori') }}">Unduh kategori</a>
            <a class="button" href="{{ route('imports.export', 'lokasi') }}">Unduh lokasi</a>
            <a class="button" href="{{ route('imports.export', 'arsip') }}">Unduh arsip</a>
        </div>
    </section>

    <section class="panel import-card">
        <div class="panel-heading"><div><h2>Format kolom CSV</h2><p>Header wajib; kolom tambahan yang dikenali tetap boleh disertakan.</p></div></div>
        <dl class="import-columns">
            <dt>Kategori</dt><dd><code>nama_kategori</code>, <code>keterangan</code>, <code>status</code></dd>
            <dt>Lokasi</dt><dd><code>gudang</code>, <code>rak</code>, <code>lemari</code>, <code>box</code>, <code>capacity_boxes</code>, <code>keterangan</code>, <code>status</code></dd>
            <dt>Arsip</dt><dd><code>nomor_berkas</code>, <code>nama_berkas</code>, <code>kode_arsip</code>, <code>nama_kategori</code>, <code>tanggal_masuk</code>, <code>gudang</code>, <code>rak</code>, <code>lemari</code>, <code>box</code>; opsional: <code>classification</code>, <code>division</code>, <code>physical_condition</code>, <code>keterangan</code>.</dd>
        </dl>
        <p class="import-note">Tanggal harus YYYY-MM-DD. Kategori dan lokasi wajib diimpor sebelum arsip. Nomor berkas duplikat dilewati. QR dibuat otomatis; lampiran digital tidak termasuk impor.</p>
    </section>
</main>
<style>
.imports-page{max-width:1080px}.import-card{padding:0 18px 18px}.import-form{display:grid;grid-template-columns:minmax(150px,.7fr) minmax(240px,1.5fr) auto;align-items:end;gap:12px;padding:5px 2px 2px}.import-form label{display:grid;gap:6px;color:#49677c;font-size:11px;font-weight:700}.import-form select,.import-form input[type=file],.preview-actions input[type=file]{width:100%;min-height:42px;padding:8px;border:1px solid #dce6ec;border-radius:8px;background:#fff;color:#29485e;font:inherit}.export-links,.preview-actions{display:flex;align-items:center;flex-wrap:wrap;gap:8px;padding:4px 2px}.import-columns{display:grid;grid-template-columns:110px minmax(0,1fr);gap:10px 14px;padding:4px 2px}.import-columns dt{color:#405d71;font-size:11px;font-weight:700}.import-columns dd{margin:0;color:#718696;font-size:11px;line-height:1.6;overflow-wrap:anywhere}.import-columns code{padding:2px 4px;border-radius:5px;background:#f1f6f8;color:#286183}.import-note{padding:11px;border-radius:8px;background:#f2f7f9;color:#617b8e;font-size:11px;line-height:1.6}.import-preview,.import-result{display:grid;gap:12px;padding:17px 19px}.import-preview h2,.import-result strong{margin:7px 0;color:#1c405b;font-size:16px}.import-preview p,.import-result p{margin:0;color:#718696;font-size:11px;line-height:1.6}.import-preview details,.import-result details{color:#526d7f;font-size:11px}.import-preview ul,.import-result ul{display:grid;gap:4px;margin:8px 0;padding-left:20px;color:#8d4b47;line-height:1.5}.preview-actions form{display:flex;align-items:center;flex-wrap:wrap;gap:7px}.preview-actions input[type=file]{width:min(100%,300px);font-size:10px}
@media(max-width:700px){.imports-page{padding:15px}.import-form{grid-template-columns:1fr}.import-form button{width:100%}.import-card{padding:0 13px 14px}.import-columns{grid-template-columns:1fr;gap:4px}.import-columns dd{margin-bottom:9px}.export-links{display:grid;grid-template-columns:1fr}.export-links .button{width:100%}.preview-actions{align-items:stretch;flex-direction:column}.preview-actions form,.preview-actions form>*{width:100%}}
</style>
@endsection
