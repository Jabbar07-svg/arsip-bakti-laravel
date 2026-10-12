@extends('layouts.app')

@section('title', 'Minta Pemeriksaan')

@section('content')
<main class="page customer-form-page">
    <a class="customer-back-link" href="{{ route('customer.portal') }}">← Kembali ke dashboard</a>
    <div class="customer-page-heading"><div><span class="customer-kicker">Bantuan menemukan dokumen</span><h1>Minta pemeriksaan arsip</h1><p>Gunakan layanan ini jika arsip yang Anda perlukan belum ditemukan di katalog publik.</p></div></div>
    @if ($errors->any())<div class="customer-alert customer-alert-error" role="alert">{{ $errors->first() }}</div>@endif
    <section class="panel customer-form-panel">
        <div class="customer-form-intro"><span class="customer-form-icon customer-request-icon" aria-hidden="true">⌕</span><div><strong>Permintaan pemeriksaan</strong><p>Petugas akan memeriksa kode yang Anda masukkan. Permintaan ini tidak otomatis membuka arsip privat.</p></div></div>
        <form class="customer-service-form" method="post" action="{{ route('customer.access-requests.store') }}" data-submit-form>
            @csrf
            <div class="customer-form-fields customer-single-column">
                <label>Nomor atau kode QR<input name="requested_code" maxlength="180" value="{{ old('requested_code') }}" required placeholder="Masukkan nomor dokumen atau kode QR">@error('requested_code')<small class="form-error">{{ $message }}</small>@enderror</label>
                <label>Alasan permintaan<textarea name="purpose" rows="5" maxlength="5000" required placeholder="Jelaskan dokumen yang dicari dan keperluannya">{{ old('purpose') }}</textarea>@error('purpose')<small class="form-error">{{ $message }}</small>@enderror</label>
            </div>
            <div class="customer-form-footer"><p>Anda dapat melacak status dan catatan pemeriksa melalui halaman Riwayat Permintaan.</p><button class="button button-primary" type="submit" data-submit-button><span>Kirim permintaan</span><span aria-hidden="true">→</span></button></div>
        </form>
    </section>
</main>
<style>
.customer-form-page{max-width:820px}.customer-back-link{display:inline-block;margin-bottom:17px;color:#567286;font-size:11px;font-weight:700}.customer-page-heading{margin-bottom:18px}.customer-page-heading h1{margin:7px 0 5px}.customer-page-heading p{margin:0;color:#748a9a;font-size:12px;line-height:1.6}.customer-form-panel{margin:0;padding:0 22px 20px}.customer-form-intro{display:flex;align-items:center;gap:12px;padding:19px 0;border-bottom:1px solid #edf1f4}.customer-form-icon{display:grid;width:40px;height:40px;place-items:center;border-radius:11px;background:#e8f4fa;color:#087fc3;font-size:21px;font-weight:800}.customer-request-icon{background:#edf3fb;color:#527db3}.customer-form-intro strong{color:#29485e;font-size:13px}.customer-form-intro p{margin:4px 0 0;color:#8295a3;font-size:10px;line-height:1.5}.customer-form-fields{display:grid;grid-template-columns:1fr 1fr;gap:15px 14px;padding:19px 0}.customer-single-column{grid-template-columns:1fr}.customer-form-fields label{display:grid;align-content:start;gap:7px;color:#49677c;font-size:10px;font-weight:750}.customer-form-fields input,.customer-form-fields textarea{width:100%;min-height:43px;padding:10px 11px;border:1px solid #dce6ec;border-radius:9px;background:#fff;color:#29485e;font:inherit;font-size:12px}.customer-form-fields textarea{resize:vertical}.customer-form-fields input:focus,.customer-form-fields textarea:focus{border-color:#55a5cc;outline:3px solid rgba(8,127,195,.1)}.customer-form-footer{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:7px;padding-top:15px;border-top:1px solid #edf1f4}.customer-form-footer p{max-width:450px;margin:0;color:#8295a3;font-size:9px;line-height:1.6}.customer-form-footer .button{gap:14px}
@media(max-width:600px){.customer-form-panel{padding:0 14px 15px}.customer-form-footer{align-items:stretch;flex-direction:column}.customer-form-footer .button{width:100%}}
</style>
@endsection
