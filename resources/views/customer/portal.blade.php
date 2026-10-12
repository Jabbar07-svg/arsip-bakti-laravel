@extends('layouts.app')

@section('title', 'Portal Customer')

@section('content')
<main class="page customer-dashboard">
    <section class="customer-welcome">
        <div>
            <span class="customer-kicker">Portal layanan Arsip BAKTI</span>
            <h1>Selamat datang, {{ auth()->user()->nama }}</h1>
            <p>Temukan arsip publik, kirim dokumen, dan pantau setiap permintaan Anda dari satu tempat.</p>
        </div>
        <a class="button button-primary" href="{{ route('customer.archives.index') }}">Jelajahi katalog <span aria-hidden="true">→</span></a>
    </section>

    @if (session('success'))<div class="customer-alert customer-alert-success" role="status"><span aria-hidden="true">✓</span>{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="customer-alert customer-alert-error" role="alert">{{ $errors->first() }}</div>@endif

    <section class="customer-stat-grid" aria-label="Ringkasan portal">
        <a class="customer-stat" href="{{ route('customer.archives.index') }}"><span>Arsip publik tersedia</span><strong>{{ number_format($publicArchiveCount, 0, ',', '.') }}</strong><small>Jelajahi koleksi arsip</small></a>
        <a class="customer-stat" href="{{ route('customer.history') }}"><span>Menunggu verifikasi</span><strong>{{ number_format($statusCounts['Menunggu Verifikasi'], 0, ',', '.') }}</strong><small>Pengajuan dan permintaan Anda</small></a>
        <a class="customer-stat" href="{{ route('customer.history') }}"><span>Perlu tindak lanjut</span><strong>{{ number_format($statusCounts['Perlu Perbaikan'], 0, ',', '.') }}</strong><small>Lihat catatan pemeriksa</small></a>
    </section>

    <section class="customer-status-panel" aria-label="Status seluruh permintaan">
        <div><span class="status-dot status-waiting"></span><span>Menunggu</span><strong>{{ $statusCounts['Menunggu Verifikasi'] }}</strong></div>
        <div><span class="status-dot status-approved"></span><span>Disetujui</span><strong>{{ $statusCounts['Disetujui'] }}</strong></div>
        <div><span class="status-dot status-rejected"></span><span>Ditolak</span><strong>{{ $statusCounts['Ditolak'] }}</strong></div>
        <div><span class="status-dot status-revision"></span><span>Perlu perbaikan</span><strong>{{ $statusCounts['Perlu Perbaikan'] }}</strong></div>
    </section>

    <section class="customer-quick-actions" aria-label="Layanan cepat">
        <a href="{{ route('customer.submissions.create') }}"><span class="quick-action-icon">↑</span><span><strong>Kirim dokumen</strong><small>Ajukan dokumen untuk diperiksa</small></span><span class="quick-action-arrow">→</span></a>
        <a href="{{ route('customer.access-requests.create') }}"><span class="quick-action-icon quick-action-icon-light">⌕</span><span><strong>Minta pemeriksaan</strong><small>Tanyakan kode arsip yang dicari</small></span><span class="quick-action-arrow">→</span></a>
        <a href="{{ route('customer.history') }}"><span class="quick-action-icon quick-action-icon-soft">◷</span><span><strong>Lacak permintaan</strong><small>Lihat status dan catatan terbaru</small></span><span class="quick-action-arrow">→</span></a>
    </section>

    <div class="customer-dashboard-columns">
        <section class="panel customer-dash-panel">
            <div class="customer-panel-heading"><div><span class="customer-section-label">Katalog</span><h2>Arsip publik terbaru</h2><p>Dokumen publik yang dapat Anda telusuri.</p></div><a href="{{ route('customer.archives.index') }}">Lihat katalog <span aria-hidden="true">→</span></a></div>
            <div class="customer-recent-archives">
                @forelse ($recentArchives as $archive)
                    <a class="customer-recent-archive" href="{{ route('archives.show', $archive->id) }}">
                        <span class="customer-document-icon" aria-hidden="true">▤</span>
                        <span class="customer-recent-copy"><strong>{{ $archive->nama_berkas }}</strong><small>{{ $archive->nomor_berkas }} · {{ $archive->nama_kategori ?: 'Tanpa kategori' }}</small></span>
                        <span class="status status-{{ \Illuminate\Support\Str::slug($archive->status) }}">{{ $archive->status }}</span>
                        <span class="customer-row-arrow" aria-hidden="true">›</span>
                    </a>
                @empty
                    <div class="customer-empty"><strong>Belum ada arsip publik</strong><span>Arsip berklasifikasi publik akan muncul di katalog.</span></div>
                @endforelse
            </div>
        </section>

        <section class="panel customer-dash-panel">
            <div class="customer-panel-heading"><div><span class="customer-section-label">Aktivitas</span><h2>Aktivitas terbaru</h2><p>Perubahan terbaru pada permintaan Anda.</p></div><a href="{{ route('customer.history') }}">Semua riwayat <span aria-hidden="true">→</span></a></div>
            <div class="customer-activity-list">
                @forelse ($recentActivity as $activity)
                    <article class="customer-activity">
                        <span class="activity-marker" aria-hidden="true"></span>
                        <div><strong>{{ $activity->title }}</strong><small>{{ $activity->kind }} · {{ \Illuminate\Support\Carbon::parse($activity->updated_at)->format('d/m/Y H:i') }}</small></div>
                        <span class="status status-{{ \Illuminate\Support\Str::slug($activity->status) }}">{{ $activity->status }}</span>
                    </article>
                @empty
                    <div class="customer-empty"><strong>Belum ada aktivitas</strong><span>Kirim dokumen atau ajukan pemeriksaan untuk memulai.</span></div>
                @endforelse
            </div>
        </section>
    </div>
</main>
<style>
.customer-dashboard{max-width:1320px}.customer-welcome{display:flex;align-items:center;justify-content:space-between;gap:22px;padding:29px 31px;border:1px solid #dfeaf0;border-radius:16px;background:#fff;box-shadow:0 10px 30px rgba(23,59,88,.045)}.customer-kicker,.customer-section-label{color:#087fc3;font-size:10px;font-weight:800;letter-spacing:1px;text-transform:uppercase}.customer-welcome h1{margin:9px 0 7px;font-size:clamp(24px,3vw,34px);letter-spacing:-.7px}.customer-welcome p{max-width:660px;margin:0;color:#718696;font-size:13px;line-height:1.65}.customer-welcome .button{flex:0 0 auto}.customer-alert{display:flex;align-items:center;gap:10px;margin-top:14px;padding:12px 15px;border-radius:10px;font-size:12px}.customer-alert-success{background:#eaf7f0;color:#1a7652}.customer-alert-error{background:#fff0ee;color:#a43b35}.customer-stat-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:13px;margin-top:16px}.customer-stat{display:grid;gap:4px;padding:17px 19px;border:1px solid #e0e9ef;border-radius:12px;background:#fff;color:#29485e;transition:transform .16s ease,border-color .16s ease}.customer-stat:hover{transform:translateY(-2px);border-color:#b7d7e6}.customer-stat>span{color:#758b9a;font-size:10px;font-weight:700}.customer-stat strong{color:#173b58;font-size:26px;font-variant-numeric:tabular-nums}.customer-stat small{color:#087fc3;font-size:10px}.customer-status-panel{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:13px;padding:13px 17px;border:1px solid #e0e9ef;border-radius:12px;background:#fafdff}.customer-status-panel>div{display:flex;align-items:center;gap:8px;color:#667e8f;font-size:10px}.customer-status-panel strong{margin-left:auto;color:#29485e;font-size:13px}.status-dot{width:8px;height:8px;border-radius:50%}.status-waiting{background:#e7a433}.status-approved{background:#31a879}.status-rejected{background:#d4615b}.status-revision{background:#4d8fc5}.customer-quick-actions{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:15px}.customer-quick-actions>a{display:flex;align-items:center;gap:11px;padding:14px;border:1px solid #e0e9ef;border-radius:12px;background:#fff;color:#29485e;transition:border-color .16s ease,box-shadow .16s ease}.customer-quick-actions>a:hover{border-color:#b7d7e6;box-shadow:0 7px 20px rgba(23,59,88,.06)}.quick-action-icon{display:grid;width:37px;height:37px;flex:0 0 37px;place-items:center;border-radius:10px;background:#e8f4fa;color:#087fc3;font-size:20px;font-weight:700}.quick-action-icon-light{background:#edf3fb;color:#527db3}.quick-action-icon-soft{background:#eef7f4;color:#388e70}.customer-quick-actions strong,.customer-quick-actions small{display:block}.customer-quick-actions strong{font-size:11px}.customer-quick-actions small{margin-top:3px;color:#8495a2;font-size:9px;line-height:1.4}.quick-action-arrow{margin-left:auto;color:#087fc3}.customer-dashboard-columns{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:14px;margin-top:15px}.customer-dash-panel{margin:0;overflow:hidden}.customer-panel-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:17px 19px;border-bottom:1px solid #edf1f4}.customer-panel-heading h2{margin:4px 0;color:#1c405b;font-size:15px}.customer-panel-heading p{margin:0;color:#8295a3;font-size:10px}.customer-panel-heading>a{flex:0 0 auto;font-size:10px;font-weight:700}.customer-recent-archive{display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:1px solid #edf1f4;color:#29485e}.customer-recent-archive:last-child{border-bottom:0}.customer-document-icon{display:grid;width:34px;height:34px;flex:0 0 34px;place-items:center;border-radius:9px;background:#edf6fa;color:#087fc3;font-size:17px}.customer-recent-copy{min-width:0;flex:1}.customer-recent-copy strong,.customer-recent-copy small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.customer-recent-copy strong{font-size:11px}.customer-recent-copy small{margin-top:4px;color:#8193a0;font-size:9px}.customer-row-arrow{color:#9aaab5}.customer-activity{display:flex;align-items:center;gap:10px;padding:13px 16px;border-bottom:1px solid #edf1f4}.customer-activity:last-child{border-bottom:0}.activity-marker{width:8px;height:8px;flex:0 0 8px;border-radius:50%;background:#1688bf;box-shadow:0 0 0 4px #e8f4fa}.customer-activity>div{min-width:0;flex:1}.customer-activity strong,.customer-activity small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.customer-activity strong{color:#29485e;font-size:10px}.customer-activity small{margin-top:4px;color:#8797a3;font-size:9px}.customer-empty{display:grid;gap:5px;padding:24px 18px;color:#8295a3;font-size:10px}.customer-empty strong{color:#456478;font-size:11px}
@media(max-width:900px){.customer-dashboard-columns{grid-template-columns:1fr}.customer-welcome{padding:24px}.customer-quick-actions{gap:8px}.customer-quick-actions>a{padding:11px}}
@media(max-width:600px){.customer-welcome{align-items:flex-start;flex-direction:column;padding:21px}.customer-welcome .button{width:100%}.customer-stat-grid{gap:8px}.customer-stat{padding:13px 11px}.customer-stat>span{min-height:26px;font-size:9px}.customer-stat strong{font-size:22px}.customer-stat small{font-size:9px}.customer-status-panel{grid-template-columns:repeat(2,minmax(0,1fr));gap:13px;padding:14px}.customer-quick-actions{grid-template-columns:1fr}.customer-quick-actions>a{padding:12px}.customer-dashboard-columns{margin-top:12px}.customer-panel-heading{align-items:flex-start;padding:14px}.customer-panel-heading>a{font-size:9px}.customer-recent-archive{gap:8px;padding:11px}.customer-recent-archive .status{font-size:8px}.customer-row-arrow{display:none}}
@media(prefers-reduced-motion:reduce){.customer-stat,.customer-quick-actions>a{transition:none}}
</style>
@endsection
