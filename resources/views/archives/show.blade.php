@extends('layouts.app')

@section('title', 'Detail Arsip')

@section('content')
<main class="page archive-detail-page">
    <div class="page-heading-row detail-heading">
        <div>
            <div class="eyebrow">Inventaris Arsip</div>
            <h1>Detail Arsip</h1>
            <p class="page-description">{{ $archive->nama_berkas }}</p>
        </div>
        <div class="detail-actions">
            <a class="button" href="{{ route('archives.index') }}">Kembali</a>
            @if ($canManageArchives)
                <a class="button button-primary" href="{{ route('archives.edit', $archive->id) }}">Edit Arsip</a>
            @endif
        </div>
    </div>

    @if (session('success'))<div class="notice-success" role="status">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="notice-error" role="alert">{{ $errors->first() }}</div>@endif

    <div class="detail-layout">
        <div class="detail-main">
            <section class="panel detail-panel">
                <div class="record-heading">
                    <div><span class="status status-{{ \Illuminate\Support\Str::slug($archive->status) }}">{{ $archive->status }}</span><h2>{{ $archive->nama_berkas }}</h2><p>{{ $archive->nomor_berkas }} · {{ $archive->kode_arsip }}</p></div>
                    <div class="record-qr"><span>QR ID</span><strong>{{ $archive->qr_code }}</strong></div>
                </div>
                @if ($canRequestLoan)
                    <form class="loan-request-form" method="post" action="{{ route('loan-requests.store', $archive->id) }}">
                        @csrf
                        <div class="no-qr-notice"><strong>Ajukan peminjaman · perlu persetujuan</strong><span>Permintaan arsip publik akan ditinjau approver. Statusnya dapat dipantau pada menu Permintaan Peminjaman.</span></div>
                        <label>Keperluan peminjaman<textarea name="purpose" rows="2" maxlength="5000" required>{{ old('purpose') }}</textarea></label>
                        <label>Rencana tanggal kembali (opsional)<input type="date" name="requested_due_date" min="{{ now()->toDateString() }}" value="{{ old('requested_due_date') }}"></label>
                        @error('purpose')<span class="form-error">{{ $message }}</span>@enderror
                        @error('requested_due_date')<span class="form-error">{{ $message }}</span>@enderror
                        <button class="button button-primary" type="submit">Ajukan peminjaman</button>
                    </form>
                @endif
                @if ($canBorrowWithoutQr)
                    <form class="loan-request-form" method="post" action="{{ route('employee-loans.store', $archive->id) }}" data-submit-form>
                        @csrf
                        <div class="no-qr-notice"><strong>Peminjaman tanpa QR</strong><span>Arsip dipilih dari halaman ini. Tidak perlu scan atau memasukkan kode QR.</span></div>
                        <label>Keperluan (opsional)<textarea name="keperluan" rows="2" maxlength="5000">{{ old('keperluan') }}</textarea></label>
                        <label>Rencana tanggal kembali (opsional)<input type="date" name="tanggal_rencana_kembali" min="{{ now()->toDateString() }}" value="{{ old('tanggal_rencana_kembali') }}"></label>
                        @error('keperluan')<span class="form-error">{{ $message }}</span>@enderror
                        @error('tanggal_rencana_kembali')<span class="form-error">{{ $message }}</span>@enderror
                        <button class="button button-primary" type="submit" data-submit-button onclick="return confirm('Catat peminjaman arsip ini tanpa memindai QR?')">Pinjam tanpa QR</button>
                        <a class="button button-small" href="{{ route('employee-loans.index') }}">Lihat peminjaman saya</a>
                    </form>
                @endif
                <dl class="archive-details">
                    <div><dt>Klasifikasi</dt><dd>{{ $archive->classification }}</dd></div>
                    <div><dt>Divisi</dt><dd>{{ $archive->division ?: '—' }}</dd></div>
                    <div><dt>Kondisi Fisik</dt><dd>{{ $archive->physical_condition }}</dd></div>
                    <div><dt>Kategori</dt><dd>{{ $archive->nama_kategori }}</dd></div>
                    <div><dt>Tahun / Bulan</dt><dd>{{ $archive->tahun }} / {{ str_pad((string) $archive->bulan, 2, '0', STR_PAD_LEFT) }}</dd></div>
                    <div><dt>Tanggal Masuk</dt><dd>{{ \Illuminate\Support\Carbon::parse($archive->tanggal_masuk)->format('d/m/Y') }}</dd></div>
                    <div><dt>Lokasi</dt><dd>{{ implode(' / ', array_filter([$archive->gudang, $archive->rak, $archive->lemari, $archive->box])) }}</dd></div>
                    <div><dt>Dibuat Oleh</dt><dd>{{ $archive->pembuat }}</dd></div>
                    <div class="detail-full"><dt>Keterangan</dt><dd>{!! nl2br(e($archive->keterangan ?: '—')) !!}</dd></div>
                </dl>
            </section>

            <section class="panel secondary-panel">
                <div class="panel-heading"><div><h2>Dokumen Pendukung</h2><p>Lampiran digital yang terhubung dengan arsip ini.</p></div></div>
                @forelse ($files as $file)
                    <div class="file-row">
                        <div class="file-icon" aria-hidden="true">PDF</div>
                        <div class="file-information"><strong>{{ $file->nama_file }}</strong><span>{{ \Illuminate\Support\Carbon::parse($file->created_at)->format('d/m/Y H:i') }}</span></div>
                        <div class="file-actions">
                            <a class="button button-small" href="{{ route('archives.files.show', $file->id) }}" target="_blank" rel="noopener">Buka</a>
                            <a class="button button-small" href="{{ route('archives.files.show', ['file' => $file->id, 'download' => 1]) }}">Unduh</a>
                        </div>
                    </div>
                @empty
                    <div class="empty-inline">Tidak ada lampiran digital.</div>
                @endforelse
            </section>

            <section class="panel secondary-panel">
                <div class="panel-heading"><div><h2>Riwayat Arsip</h2><p>Aktivitas pencatatan dan perubahan arsip.</p></div></div>
                @forelse ($history as $entry)
                    <article class="history-entry">
                        <span class="history-dot" aria-hidden="true"></span>
                        <strong>{{ $entry->aktivitas }}</strong>
                        <span class="history-meta">{{ $entry->nama }} · {{ \Illuminate\Support\Carbon::parse($entry->created_at)->format('d/m/Y H:i') }}</span>
                        @if ($entry->keterangan)<p>{{ $entry->keterangan }}</p>@endif
                    </article>
                @empty
                    <div class="empty-inline">Belum ada riwayat untuk arsip ini.</div>
                @endforelse
            </section>
        </div>

        <aside class="detail-side">
            <section class="panel qr-panel">
                <div class="eyebrow">Identifikasi</div>
                <h2>QR Arsip</h2>
                <p>QR ID terhubung ke arsip ini dan dibuat otomatis saat pencatatan.</p>
                <strong class="qr-code-large">{{ $archive->qr_code }}</strong>
                <div class="archive-qr-preview" id="archive-qr-preview" data-qr-code="{{ $archive->qr_code }}" data-qr-size="220" aria-label="QR {{ $archive->qr_code }}"></div>
                <p data-qr-error hidden role="alert">QR tidak dapat dibuat. Muat ulang halaman atau gunakan cetak massal.</p>
                <svg class="archive-barcode-preview" data-barcode="{{ $archive->qr_code }}" role="img" aria-label="Barcode {{ $archive->qr_code }}"></svg>
                <p data-barcode-error hidden role="alert">Barcode tidak dapat dibuat. Gunakan QR ID.</p>
                @if ($canManageArchives)
                    <a class="button button-primary" href="{{ route('qr.archive-label', $archive->id) }}">Cetak / unduh label</a>
                @endif
            </section>

            @if ($lastLoan)
                <section class="panel loan-panel">
                    <h2>Peminjaman Terakhir</h2>
                    <dl>
                        <div><dt>Peminjam</dt><dd>{{ $lastLoan->nama_peminjam }}</dd></div>
                        <div><dt>Unit</dt><dd>{{ $lastLoan->unit ?: '—' }}</dd></div>
                        <div><dt>Tanggal Pinjam</dt><dd>{{ \Illuminate\Support\Carbon::parse($lastLoan->tanggal_pinjam)->format('d/m/Y') }}</dd></div>
                        <div><dt>Rencana Kembali</dt><dd>{{ $lastLoan->tanggal_rencana_kembali ? \Illuminate\Support\Carbon::parse($lastLoan->tanggal_rencana_kembali)->format('d/m/Y') : '—' }}</dd></div>
                        <div><dt>Status</dt><dd>{{ $lastLoan->status }}</dd></div>
                    </dl>
                </section>
            @endif
        </aside>
    </div>
</main>
<style>
.detail-heading{align-items:flex-end}
.detail-actions{display:flex;gap:8px}
.detail-layout{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(250px,.8fr);gap:16px;margin-top:20px;align-items:start}
.detail-main,.detail-side{display:grid;gap:14px}
.detail-panel{padding:22px}
.record-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:15px;padding-bottom:17px;border-bottom:1px solid #edf1f4}
.archive-qr-preview{display:grid;place-items:center;width:100%;min-height:190px}
.archive-qr-preview canvas{display:block;max-width:100%;height:auto!important;image-rendering:pixelated}
.archive-barcode-preview{display:block;width:min(100%,280px);height:62px;margin:0 auto}
.record-heading h2{margin:10px 0 4px;color:#1c405b;font-size:19px}
.record-heading p{margin:0;color:#8294a0;font-size:11px}
.no-qr-notice{display:grid;gap:4px;padding:11px 12px;border:1px solid #cfe6f1;border-radius:9px;background:#f1f9fc}.no-qr-notice strong{color:#17678f;font-size:12px}.no-qr-notice span{color:#637b8b;font-size:10px;line-height:1.5}
.record-qr{display:grid;gap:5px;text-align:right}
.record-qr span,.archive-details dt,.loan-panel dt{color:#8597a3;font-size:10px}
.record-qr strong{color:#0876b3;font-size:11px;overflow-wrap:anywhere}
.archive-details{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 14px;margin:20px 0 0}
.archive-details dt,.loan-panel dt{margin-bottom:5px}
.archive-details dd,.loan-panel dd{margin:0;color:#29485e;font-size:12px;overflow-wrap:anywhere}
.detail-full{grid-column:1/-1}
.secondary-panel{padding:0 18px}
.file-row{display:flex;align-items:center;gap:11px;padding:13px 2px;border-bottom:1px solid #edf1f4}
.file-row:last-child{border-bottom:0}
.file-icon{display:grid;place-items:center;width:36px;height:36px;flex:0 0 36px;border-radius:9px;background:#edf6fa;color:#0876b3;font-size:9px;font-weight:800}
.file-information{display:grid;gap:4px;min-width:0;flex:1}
.file-information strong{overflow:hidden;color:#29485e;font-size:11px;text-overflow:ellipsis;white-space:nowrap}
.file-information span,.history-meta{color:#8294a0;font-size:10px}
.file-actions{display:flex;gap:6px}
.empty-inline{padding:17px 2px;color:#8294a0;font-size:11px}
.history-entry{position:relative;margin:0 0 0 7px;padding:14px 8px 14px 20px;border-left:2px solid #e4edf2}
.history-entry:last-child{border-left-color:transparent}
.history-dot{position:absolute;left:-6px;top:17px;width:10px;height:10px;border:2px solid #fff;border-radius:50%;background:#087fc3;box-shadow:0 0 0 1px #087fc3}
.history-entry strong{display:block;color:#29485e;font-size:11px}
.history-meta{display:block;margin-top:4px}
.history-entry p{margin:6px 0 0;color:#718696;font-size:11px;line-height:1.5}
.qr-panel{display:grid;justify-items:start;gap:9px;padding:20px}
.qr-panel h2,.loan-panel h2{margin:0;color:#1c405b;font-size:15px}
.qr-panel p{margin:0;color:#718696;font-size:11px;line-height:1.55}
.qr-code-large{width:100%;padding:11px;border-radius:8px;background:#f4f8fa;color:#0876b3;font-size:12px;text-align:center;overflow-wrap:anywhere}
.qr-panel .button{width:100%}
.loan-panel{padding:19px}
.loan-panel dl{display:grid;gap:13px;margin:17px 0 0}
.status-hilang,.status-nonaktif{background:#fff0ee;color:#a43b35}
.status-dipinjam,.status-diproses{background:#fff7e6;color:#946516}
.button-small{min-height:32px;padding:0 10px;font-size:10px}
@media(max-width:850px){.detail-layout{grid-template-columns:1fr}.detail-side{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.detail-heading{align-items:flex-start}.detail-actions{width:100%}.detail-actions .button{flex:1}.detail-layout{gap:12px;margin-top:14px}.detail-side{grid-template-columns:1fr}.detail-panel{padding:16px}.record-heading{flex-direction:column}.record-qr{text-align:left}.archive-details{gap:15px 10px}.secondary-panel{padding:0 13px}.file-row{align-items:flex-start;flex-wrap:wrap}.file-information{max-width:calc(100% - 50px)}.file-actions{width:100%;margin-left:47px}.file-actions .button{flex:1}.qr-panel,.loan-panel{padding:16px}}
</style>
@endsection
