@extends('layouts.app')

@section('title', 'Ajukan Distribusi Arsip')

@section('content')
<main class="page">
    <div class="eyebrow">Distribusi Antarunit</div>
    <h1>Ajukan distribusi arsip</h1>
    <p class="page-description">Pilih arsip yang dapat Anda akses dan petugas aktif di unit tujuan. Arsip dikunci dari transaksi lain sambil menunggu keputusan.</p>

    @if ($errors->any())
        <div class="form-alert" role="alert">
            <strong>Permintaan belum dapat disimpan.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="panel">
        <div class="panel-heading"><div><h2>Detail permintaan</h2><p>Status awal: Menunggu Persetujuan</p></div></div>
        <form method="post" action="{{ route('distributions.store') }}" class="form-panel">
            @csrf
            <div class="field">
                <label for="archive_id">Arsip</label>
                <select id="archive_id" name="archive_id" required>
                    <option value="">Pilih arsip</option>
                    @foreach ($archives as $archive)
                        <option value="{{ $archive->id }}" @selected(old('archive_id') == $archive->id)>
                            {{ $archive->nomor_berkas }} · {{ $archive->nama_berkas }} ({{ $archive->division ?: 'Tanpa divisi' }})
                        </option>
                    @endforeach
                </select>
                @error('archive_id')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="recipient_id">Petugas penerima di unit tujuan</label>
                <select id="recipient_id" name="recipient_id" required>
                    <option value="">Pilih petugas penerima</option>
                    @foreach ($recipients as $recipient)
                        <option value="{{ $recipient->id }}" @selected(old('recipient_id') == $recipient->id)>
                            {{ $recipient->nama }} · {{ $recipient->role }} — {{ $recipient->division }}
                        </option>
                    @endforeach
                </select>
                @error('recipient_id')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="notes">Keterangan distribusi</label>
                <textarea id="notes" name="notes" rows="4" maxlength="5000" placeholder="Tujuan atau alasan perpindahan arsip">{{ old('notes') }}</textarea>
                @error('notes')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            @if ($archives->isEmpty() || $recipients->isEmpty())
                <p class="form-hint">Permintaan belum dapat dibuat: pastikan tersedia arsip tersimpan yang boleh diakses dan petugas aktif pada divisi tujuan.</p>
            @endif
            <div class="form-actions">
                <a class="button" href="{{ route('distributions.index') }}">Kembali</a>
                <button class="button button-primary" type="submit" @disabled($archives->isEmpty() || $recipients->isEmpty())>Kirim untuk persetujuan</button>
            </div>
        </form>
    </section>
</main>
<style>
    .form-panel{padding:20px}
    .form-panel select,.form-panel textarea{width:100%;min-height:46px;padding:10px 12px;border:1px solid #dce6ec;border-radius:9px;background:#fff;color:#173b58;font:inherit;font-size:13px}
    .form-panel textarea{min-height:110px;resize:vertical}
    .form-panel select:focus,.form-panel textarea:focus{border-color:#55a5cc;outline:3px solid rgba(8,127,195,.12)}
    .form-actions{display:flex;justify-content:flex-end;gap:9px;margin-top:22px}
    .form-hint{padding:12px;border-radius:9px;background:#fff8e8;color:#805f1c;font-size:12px;line-height:1.55}
    .form-alert ul{margin:7px 0 0;padding-left:20px}
    @media(max-width:560px){.form-panel{padding:15px}.form-actions{flex-direction:column-reverse}.form-actions .button{width:100%}}
</style>
@endsection
