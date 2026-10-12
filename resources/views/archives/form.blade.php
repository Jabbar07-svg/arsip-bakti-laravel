@extends('layouts.app')

@php($editing = $archive !== null)

@section('title', $editing ? 'Edit Arsip' : 'Tambah Arsip')

@section('content')
<main class="page archive-form-page">
    <div class="eyebrow">Inventaris Arsip</div>
    <h1>{{ $editing ? 'Edit Arsip' : 'Tambah Arsip' }}</h1>
    <p class="page-description">{{ $editing ? 'Perbarui informasi arsip. QR ID yang sudah dibuat akan tetap sama.' : 'Isi informasi arsip. QR ID unik akan dibuat otomatis saat disimpan.' }}</p>

    <div class="qr-note">
        <span class="qr-note-icon" aria-hidden="true">QR</span>
        <div><strong>QR dibuat otomatis</strong><p>Setelah arsip disimpan, QR ID akan terhubung ke data arsip. Modul cetak QR masih tersedia di aplikasi sebelumnya.</p></div>
    </div>

    @if ($errors->any())
        <div class="form-errors" role="alert">
            <strong>Periksa kembali data yang dimasukkan.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($categories->isEmpty() || $locations->isEmpty())
        <div class="notice-error" role="alert">Tambahkan kategori dan lokasi yang aktif sebelum membuat arsip.</div>
    @endif

    <form class="archive-form panel" method="post" enctype="multipart/form-data" action="{{ $editing ? route('archives.update', $archive->id) : route('archives.store') }}" data-submit-form>
        @csrf
        @if ($editing)@method('PUT')@endif
        <div class="form-grid">
            <div class="field-group-heading field-full">
                <h2>Identitas arsip</h2>
                <p>Nomor referensi dan judul yang digunakan untuk mengenali berkas.</p>
            </div>
            <div class="field">
                <label for="nomor_berkas">Nomor Berkas <span>*</span></label>
                <input id="nomor_berkas" name="nomor_berkas" maxlength="150" value="{{ old('nomor_berkas', $archive->nomor_berkas ?? '') }}" required>
                @error('nomor_berkas')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="kode_arsip">Kode Arsip <span>*</span></label>
                <input id="kode_arsip" name="kode_arsip" maxlength="150" value="{{ old('kode_arsip', $archive->kode_arsip ?? '') }}" required>
                @error('kode_arsip')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="field field-full">
                <label for="nama_berkas">Nama / Judul Berkas <span>*</span></label>
                <input id="nama_berkas" name="nama_berkas" maxlength="255" value="{{ old('nama_berkas', $archive->nama_berkas ?? '') }}" required>
                @error('nama_berkas')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="field-group-heading field-full">
                <h2>Informasi dan klasifikasi</h2>
                <p>Kategori, status, tanggal, kondisi, serta tingkat akses arsip.</p>
            </div>
            <div class="field">
                <label for="kategori_id">Kategori <span>*</span></label>
                <select id="kategori_id" name="kategori_id" required>
                    <option value="">Pilih kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('kategori_id', $archive->kategori_id ?? '') === (string) $category->id)>{{ $category->nama_kategori }}{{ $category->status !== 'Aktif' ? ' (Nonaktif)' : '' }}</option>
                    @endforeach
                </select>
                @error('kategori_id')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="tanggal_masuk">Tanggal Masuk <span>*</span></label>
                <input id="tanggal_masuk" type="date" name="tanggal_masuk" value="{{ old('tanggal_masuk', $archive->tanggal_masuk ?? now()->format('Y-m-d')) }}" required>
                @error('tanggal_masuk')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="status">Status <span>*</span></label>
                <select id="status" name="status" required>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(old('status', $archive->status ?? 'Tersimpan') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                @error('status')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="physical_condition">Kondisi Fisik <span>*</span></label>
                <select id="physical_condition" name="physical_condition" required>
                    @foreach ($conditions as $condition)
                        <option value="{{ $condition }}" @selected(old('physical_condition', $archive->physical_condition ?? 'Baik') === $condition)>{{ $condition }}</option>
                    @endforeach
                </select>
                @error('physical_condition')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="classification">Klasifikasi Akses <span>*</span></label>
                <select id="classification" name="classification" required>
                    @foreach ($classifications as $classification)
                        <option value="{{ $classification }}" @selected(old('classification', $archive->classification ?? 'Internal') === $classification)>{{ $classification }}</option>
                    @endforeach
                </select>
                <small>Dokumen Rahasia hanya dapat diatur oleh Super Admin.</small>
                @error('classification')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="field" id="division-field">
                <label for="division">Divisi Arsip <span>*</span></label>
                @if (\App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::ARCHIVES_MANAGE_CONFIDENTIAL))
                    <select id="division" name="division">
                        <option value="">Pilih divisi</option>
                        @foreach ($divisions as $division)
                            <option value="{{ $division }}" @selected(old('division', $archive->division ?? $currentDivision) === $division)>{{ $division }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="hidden" name="division" value="{{ $currentDivision }}">
                    <input id="division" value="{{ $currentDivision ?: 'Divisi akun belum diatur' }}" disabled>
                @endif
                <small>Hanya pegawai dari divisi yang ditentukan yang dapat membuka arsip ini.</small>
                @error('division')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="field-group-heading field-full">
                <h2>Penyimpanan fisik</h2>
                <p>Pilih lokasi gudang dan susunan penyimpanan arsip.</p>
            </div>
            <div class="field field-full">
                <label for="lokasi_id">Lokasi Gudang / Rak / Lemari / Box <span>*</span></label>
                <select id="lokasi_id" name="lokasi_id" required>
                    <option value="">Pilih lokasi</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected((string) old('lokasi_id', $archive->lokasi_id ?? '') === (string) $location->id)>{{ $location->gudang }} / {{ $location->rak }} / {{ $location->lemari }} / {{ $location->box }}{{ $location->status !== 'Aktif' ? ' (Nonaktif)' : '' }}</option>
                    @endforeach
                </select>
                @error('lokasi_id')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="field-group-heading field-full">
                <h2>Catatan dan lampiran</h2>
                <p>Tambahkan keterangan atau dokumen pendukung bila tersedia.</p>
            </div>
            <div class="field field-full">
                <label for="keterangan">Keterangan</label>
                <textarea id="keterangan" name="keterangan" rows="4">{{ old('keterangan', $archive->keterangan ?? '') }}</textarea>
                @error('keterangan')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="field field-full">
                <label for="file">Lampiran Digital <span class="optional">Opsional</span></label>
                <input id="file" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" data-file-preview-input>
                <small>PDF, JPG, PNG, DOC, DOCX, XLS, atau XLSX · Maks. 10 MB. Lampiran disimpan terpisah dari QR.</small>
                <div class="file-preview" data-file-preview hidden>
                    <span><strong data-file-name></strong><br><span data-file-size></span></span>
                    <button type="button" data-file-clear>Hapus pilihan</button>
                </div>
                @error('file')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="form-actions">
            <a class="button" href="{{ $editing ? route('archives.show', $archive->id) : route('archives.index') }}">Batal</a>
            <button class="button button-primary" type="submit" data-submit-button><span>{{ $editing ? 'Simpan perubahan' : 'Simpan arsip' }}</span></button>
        </div>
    </form>
</main>
<style>
.qr-note{display:flex;gap:12px;margin-top:18px;padding:14px;border:1px solid #d8eaf2;border-radius:10px;background:#f4fafc}
.qr-note-icon{display:grid;place-items:center;width:38px;height:38px;flex:0 0 38px;border-radius:10px;background:#e0f1f8;color:#087fc3;font-size:11px;font-weight:900}
.qr-note strong{color:#244b65;font-size:12px}.qr-note p{margin:4px 0 0;color:#718798;font-size:11px;line-height:1.5}
.archive-form{margin-top:16px;padding:22px}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}
.field{min-width:0}.field-full{grid-column:1/-1}
.field label{display:block;margin-bottom:7px;color:#405d71;font-size:12px;font-weight:700}
.field label>span{color:#087fc3}.field label>.optional{color:#8496a2;font-size:10px;font-weight:500}
.field input,.field select,.field textarea{width:100%;min-height:44px;padding:10px 12px;border:1px solid #dce6ec;border-radius:8px;background:#fff;color:#29485e;font:inherit;font-size:13px}
.field input[type=file]{padding:6px}.field textarea{min-height:100px;resize:vertical}
.field input:focus,.field select:focus,.field textarea:focus{border-color:#55a5cc;outline:3px solid rgba(8,127,195,.12)}
.field input:disabled{background:#f5f8fa;color:#748a9a}
.field small{display:block;margin-top:5px;color:#7e919f;font-size:10px;line-height:1.5}
.form-error{margin:5px 0 0;color:#a43b35;font-size:11px}
.form-errors{margin-top:15px;padding:13px 16px;border:1px solid #f0d3d0;border-radius:9px;background:#fff5f3;color:#a43b35;font-size:12px}
.form-errors ul{margin:6px 0 0;padding-left:18px;line-height:1.6}
.form-actions{display:flex;justify-content:flex-end;gap:9px;margin-top:21px;padding-top:16px;border-top:1px solid #edf1f4}
@media(max-width:600px){.archive-form{padding:16px}.form-grid{grid-template-columns:1fr;gap:12px}.field-full{grid-column:auto}.form-actions{display:grid;grid-template-columns:1fr 1fr}.qr-note{align-items:flex-start}}
</style>
<script>
const classificationField = document.getElementById('classification');
const divisionField = document.getElementById('division-field');
const updateDivisionVisibility = () => {
    divisionField.hidden = classificationField.value !== 'Internal Divisi';
};
classificationField.addEventListener('change', updateDivisionVisibility);
updateDivisionVisibility();
</script>
@endsection
