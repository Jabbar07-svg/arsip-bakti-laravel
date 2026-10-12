@extends('layouts.app')

@section('title', 'Buat Peminjaman')

@section('content')
<main class="page">
    <div class="page-heading-row">
        <div><div class="eyebrow">Manajemen Sirkulasi</div><h1>Buat Peminjaman</h1><p class="page-description">Catat arsip keluar dan jadwal pengembaliannya.</p></div>
        <a class="button" href="{{ route('loans.index') }}">Kembali</a>
    </div>

    <section class="panel loan-form-panel">
        @if ($archives->isEmpty())
            <div class="empty-state">Tidak ada arsip berstatus Tersimpan yang dapat Anda pinjamkan.</div>
        @else
            <form method="post" action="{{ route('loans.store') }}" class="loan-form">
                @csrf
                <label>Arsip<select name="arsip_id" required><option value="">Pilih arsip tersedia</option>@foreach ($archives as $archive)<option value="{{ $archive->id }}" @selected((string) old('arsip_id') === (string) $archive->id)>{{ $archive->qr_code }} · {{ $archive->nomor_berkas }} · {{ $archive->nama_berkas }} · {{ implode(' / ', array_filter([$archive->gudang, $archive->rak, $archive->lemari, $archive->box])) }}</option>@endforeach</select></label>
                @error('arsip_id')<span class="form-error">{{ $message }}</span>@enderror
                <label>Nama peminjam<input name="nama_peminjam" maxlength="150" value="{{ old('nama_peminjam') }}" required></label>
                @error('nama_peminjam')<span class="form-error">{{ $message }}</span>@enderror
                <label>Unit / divisi<input name="unit" maxlength="150" value="{{ old('unit', auth()->user()->division) }}"></label>
                @error('unit')<span class="form-error">{{ $message }}</span>@enderror
                <div class="loan-date-fields">
                    <label>Tanggal pinjam<input type="date" name="tanggal_pinjam" value="{{ old('tanggal_pinjam', now()->toDateString()) }}" required></label>
                    <label>Rencana kembali<input type="date" name="tanggal_rencana_kembali" value="{{ old('tanggal_rencana_kembali') }}"></label>
                </div>
                @error('tanggal_pinjam')<span class="form-error">{{ $message }}</span>@enderror
                @error('tanggal_rencana_kembali')<span class="form-error">{{ $message }}</span>@enderror
                <label>Keperluan<textarea name="keperluan" rows="3" maxlength="5000">{{ old('keperluan') }}</textarea></label>
                @error('keperluan')<span class="form-error">{{ $message }}</span>@enderror
                <button class="button button-primary" type="submit">Simpan peminjaman</button>
            </form>
        @endif
    </section>
</main>
<style>
.loan-form-panel{max-width:760px;padding:20px}.loan-form{display:grid;gap:12px}.loan-form label{display:grid;gap:6px;color:#49677c;font-size:11px;font-weight:700}.loan-form input,.loan-form select,.loan-form textarea{width:100%;min-height:42px;padding:9px 11px;border:1px solid #dce6ec;border-radius:8px;background:white;color:#29485e;font-size:13px}.loan-form textarea{resize:vertical}.loan-date-fields{display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media(max-width:560px){.loan-form-panel{padding:14px}.loan-date-fields{grid-template-columns:1fr}}
</style>
@endsection
