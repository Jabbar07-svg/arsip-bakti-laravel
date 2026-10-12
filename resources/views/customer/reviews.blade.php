@extends('layouts.app')

@section('title', 'Verifikasi Customer')

@section('content')
<main class="page customer-review-page">
    <div class="page-heading-row">
        <div><div class="eyebrow">Akses Customer</div><h1>Verifikasi Customer</h1><p class="page-description">Periksa dokumen dan permintaan arsip. Berkas baru menjadi arsip resmi setelah disetujui.</p></div>
    </div>
    @if (session('success'))<div class="notice-success" role="status">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="notice-error" role="alert">{{ $errors->first() }}</div>@endif

    <section class="panel review-section" id="submissions">
        <div class="panel-heading"><div><h2>Pengajuan dokumen</h2><p>Persetujuan membuat arsip resmi beserta QR dan menghubungkan berkas privat.</p></div><span>{{ $submissions->where('status', 'Menunggu Verifikasi')->count() }} menunggu</span></div>
        <div class="review-list">
            @forelse ($submissions as $submission)
                <article class="review-card">
                    <div class="review-card-summary">
                        <div><span class="eyebrow">{{ $submission->customer_name }} · @{{ $submission->username }}</span><h3>{{ $submission->nama_dokumen }}</h3><p>{{ $submission->nomor_dokumen }} · {{ $submission->nama_kategori ?: 'Tanpa kategori' }} · {{ \Illuminate\Support\Carbon::parse($submission->tanggal_dokumen)->format('d/m/Y') }}</p></div>
                        <span class="status status-{{ \Illuminate\Support\Str::slug($submission->status) }}">{{ $submission->status }}</span>
                    </div>
                    @if ($submission->keterangan)<p class="review-description">{{ $submission->keterangan }}</p>@endif
                    @if ($submission->reviewer_note)<p class="review-note">Catatan sebelumnya: {{ $submission->reviewer_note }}</p>@endif
                    <div class="review-file-links"><a href="{{ route('customer.submissions.file', $submission->id) }}" target="_blank" rel="noopener">Pratinjau berkas</a><a href="{{ route('customer.submissions.file', ['submission' => $submission->id, 'download' => 1]) }}">Unduh berkas</a>
                        @if ($submission->arsip_id)<a href="{{ route('archives.show', $submission->arsip_id) }}">Buka arsip resmi</a>@endif
                    </div>
                    @if ($submission->status === 'Menunggu Verifikasi')
                        <form class="review-form" method="post" action="{{ route('customer-reviews.submissions.review', $submission->id) }}">
                            @csrf @method('PATCH')
                            <label>Keputusan<select name="status" required>@foreach ($reviewStatuses as $status)<option value="{{ $status }}" @selected($status === 'Disetujui')>{{ $status }}</option>@endforeach</select></label>
                            <label>Lokasi arsip saat disetujui<select name="lokasi_id"><option value="">Pilih lokasi</option>@foreach ($locations as $location)<option value="{{ $location->id }}">{{ implode(' / ', array_filter([$location->gudang, $location->rak, $location->lemari, $location->box])) }}</option>@endforeach</select></label>
                            <label>Klasifikasi arsip<select name="classification">@foreach ($classifications as $classification)<option value="{{ $classification }}" @selected($classification === 'Internal')>{{ $classification }}</option>@endforeach</select></label>
                            <label>Divisi (untuk Internal Divisi)<select name="division"><option value="">Pilih divisi</option>@foreach ($divisions as $division)<option value="{{ $division }}">{{ $division }}</option>@endforeach</select></label>
                            <label class="review-wide">Catatan untuk Customer<textarea name="reviewer_note" rows="2" maxlength="5000" placeholder="Wajib jika ditolak atau perlu perbaikan"></textarea></label>
                            <button class="button button-primary" type="submit">Simpan keputusan</button>
                        </form>
                    @endif
                </article>
            @empty
                <div class="empty-inline">Belum ada dokumen yang diajukan.</div>
            @endforelse
        </div>
    </section>

    <section class="panel review-section" id="requests">
        <div class="panel-heading"><div><h2>Permintaan pemeriksaan arsip</h2><p>Persetujuan hanya mengonfirmasi bahwa kode tersebut cocok dengan arsip Publik.</p></div><span>{{ $accessRequests->where('status', 'Menunggu Verifikasi')->count() }} menunggu</span></div>
        <div class="review-list">
            @forelse ($accessRequests as $accessRequest)
                <article class="review-card">
                    <div class="review-card-summary">
                        <div><span class="eyebrow">{{ $accessRequest->customer_name }} · @{{ $accessRequest->username }}</span><h3>{{ $accessRequest->requested_code }}</h3><p>{{ $accessRequest->purpose }}</p></div>
                        <span class="status status-{{ \Illuminate\Support\Str::slug($accessRequest->status) }}">{{ $accessRequest->status }}</span>
                    </div>
                    @if ($accessRequest->reviewer_note)<p class="review-note">Catatan sebelumnya: {{ $accessRequest->reviewer_note }}</p>@endif
                    @if ($accessRequest->status === 'Menunggu Verifikasi')
                        <form class="review-form access-review-form" method="post" action="{{ route('customer-reviews.access-requests.review', $accessRequest->id) }}">
                            @csrf @method('PATCH')
                            <label>Keputusan<select name="status" required>@foreach ($reviewStatuses as $status)<option value="{{ $status }}" @selected($status === 'Disetujui')>{{ $status }}</option>@endforeach</select></label>
                            <label class="review-wide">Catatan<textarea name="reviewer_note" rows="2" maxlength="5000" placeholder="Wajib jika ditolak atau perlu perbaikan"></textarea></label>
                            <button class="button button-primary" type="submit">Simpan keputusan</button>
                        </form>
                    @endif
                </article>
            @empty
                <div class="empty-inline">Belum ada permintaan pemeriksaan arsip.</div>
            @endforelse
        </div>
    </section>
</main>
<style>
.review-section{margin-top:16px;padding:0 18px 16px}.review-list{display:grid;gap:11px;padding-top:14px}.review-card{min-width:0;padding:15px;border:1px solid #e7edf1;border-radius:11px}.review-card-summary{display:flex;justify-content:space-between;align-items:flex-start;gap:15px}.review-card h3{margin:5px 0;color:#1c405b;font-size:14px;overflow-wrap:anywhere}.review-card p{margin:0;color:#758b99;font-size:11px;line-height:1.6;overflow-wrap:anywhere}.review-card .review-description,.review-card .review-note{margin-top:10px}.review-card .review-note{color:#9b5b19}.review-file-links{display:flex;flex-wrap:wrap;gap:14px;margin-top:11px;font-size:11px}.review-file-links a{color:#0876b3;text-decoration:none}.review-form{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;align-items:end;margin-top:14px;padding-top:13px;border-top:1px solid #edf1f4}.review-form label{display:grid;gap:5px;color:#526d7f;font-size:10px;font-weight:650}.review-form select,.review-form textarea{width:100%;min-height:37px;padding:8px;border:1px solid #dce5ea;border-radius:8px;background:#fff;color:#29485e;font:inherit}.review-form button{justify-self:start}.review-wide{grid-column:1/-1}.panel-heading>span{color:#7d909e;font-size:11px}
@media(max-width:800px){.review-form{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){.review-section{padding:0 12px 12px}.review-card-summary{flex-direction:column}.review-form{grid-template-columns:1fr}.review-form>*{grid-column:1/-1}.review-form button{width:100%}}
</style>
@endsection
