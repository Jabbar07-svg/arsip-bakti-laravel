@extends('layouts.app')

@section('title', 'Jadwal Retensi Arsip')

@section('content')
<main class="page retention-page">
    <div class="page-heading-row"><div><div class="eyebrow">Siklus hidup arsip</div><h1>Jadwal Retensi Arsip</h1><p class="page-description">Atur masa retensi resmi dan proses usulan penyusutan dengan persetujuan berjenjang.</p></div></div>
    @if (session('success'))<div class="notice-success" role="status">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="notice-error" role="alert">{{ $errors->first() }}</div>@endif

    <section class="panel retention-section" id="schedule">
        <div class="panel-heading"><div><h2>Aturan JRA per kategori</h2><p>Masukkan durasi sesuai kebijakan organisasi. Sistem tidak menetapkan retensi otomatis.</p></div></div>
        @if ($isSuperAdmin)
            <form class="retention-form" method="post" action="{{ route('retention.schedule') }}">
                @csrf
                <label>Kategori<select name="kategori_id" required><option value="">Pilih kategori</option>@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->nama_kategori }}</option>@endforeach</select></label>
                <label>Masa aktif (bulan)<input type="number" name="active_months" min="0" max="65535" required></label>
                <label>Masa inaktif (bulan)<input type="number" name="inactive_months" min="0" max="65535" required></label>
                <label>Nasib akhir<select name="final_action" required><option value="">Pilih tindakan</option>@foreach ($finalActions as $action)<option value="{{ $action }}">{{ $action }}</option>@endforeach</select></label>
                <label>Pengingat (hari)<input type="number" name="alert_days" min="0" max="3650" value="90" required></label>
                <button class="button button-primary" type="submit">Simpan JRA</button>
            </form>
        @else
            <p class="retention-muted">Hanya Super Admin yang dapat mengubah jadwal retensi.</p>
        @endif
        <div class="retention-schedule-list">
            @forelse ($schedules as $schedule)
                <article class="retention-schedule"><strong>{{ $schedule->nama_kategori }}</strong><span>Aktif {{ $schedule->active_months }} bln · Inaktif {{ $schedule->inactive_months }} bln</span><span>{{ $schedule->final_action }} · Pengingat {{ $schedule->alert_days }} hari</span><small>Diperbarui oleh {{ $schedule->editor }}</small></article>
            @empty
                <div class="empty-inline">Belum ada aturan JRA. Tidak ada nasib retensi yang diasumsikan.</div>
            @endforelse
        </div>
    </section>

    <section class="panel retention-section" id="retention-status">
        <div class="panel-heading"><div><h2>Pemantauan retensi</h2><p>Jatuh tempo dihitung dari tanggal masuk dan aturan kategori.</p></div></div>
        <div class="retention-list">
            @forelse ($retentionRows as $item)
                <article class="retention-row">
                    <div class="retention-record"><strong>{{ $item['archive']->nama_berkas }}</strong><span>{{ $item['archive']->nomor_berkas }} · {{ $item['archive']->nama_kategori }}</span></div>
                    <span class="retention-phase {{ $item['phase'] === 'Jatuh Tempo' ? 'is-due' : '' }}">{{ $item['phase'] }}</span>
                    <div class="retention-due"><small>Jatuh tempo</small><strong>{{ $item['retention_until']->format('d/m/Y') }}</strong><span>{{ $item['final_action'] }}</span></div>
                    @if ($canProposeRetention && $item['phase'] === 'Jatuh Tempo' && $item['archive']->status !== 'Dipinjam')
                        <form class="retention-propose" method="post" action="{{ route('retention.dispositions.store') }}">@csrf<input type="hidden" name="arsip_id" value="{{ $item['archive']->id }}"><input name="reason" maxlength="5000" placeholder="Alasan usulan" aria-label="Alasan usulan untuk {{ $item['archive']->nama_berkas }}" required><button class="button button-small button-primary" type="submit">Usulkan</button></form>
                    @else
                        <span class="retention-muted">{{ $item['archive']->status }}</span>
                    @endif
                </article>
            @empty
                <div class="empty-inline">Belum ada arsip dengan aturan JRA.</div>
            @endforelse
        </div>
    </section>

    <section class="panel retention-section" id="dispositions">
        <div class="panel-heading"><div><h2>Usulan penyusutan & berita acara</h2><p>Usulan harus disetujui oleh approver yang bukan pengusul sebelum pelaksanaan dicatat.</p></div></div>
        <div class="retention-list">
            @forelse ($dispositions as $disposition)
                <article class="disposition-card">
                    <div class="disposition-summary"><span class="status status-{{ \Illuminate\Support\Str::slug($disposition->status) }}">{{ $disposition->status }}</span><h3>{{ $disposition->nama_berkas }}</h3><p>{{ $disposition->nomor_berkas }} · {{ $disposition->action }}</p><p>{{ $disposition->reason }}</p><small>Pengusul: {{ $disposition->requester_name }}@if ($disposition->reviewer_name) · Pemeriksa: {{ $disposition->reviewer_name }}@endif</small>
                        @if ($disposition->reviewer_note)<p class="retention-note">Catatan: {{ $disposition->reviewer_note }}</p>@endif
                    </div>
                    <div class="disposition-action">
                        @if ($isApprover && $disposition->status === 'Menunggu Persetujuan' && (int) $disposition->requested_by !== (int) auth()->id())
                            <form class="disposition-review" method="post" action="{{ route('retention.dispositions.review', $disposition->id) }}">@csrf @method('PATCH')<label>Catatan (wajib jika ditolak)<input name="reviewer_note" maxlength="5000"></label><button class="button button-small button-primary" name="status" value="Disetujui">Setujui</button><button class="button button-small" name="status" value="Ditolak">Tolak</button></form>
                        @elseif ($isApprover && $disposition->status === 'Disetujui')
                            <form class="disposition-review" method="post" action="{{ route('retention.dispositions.complete', $disposition->id) }}">@csrf @method('PATCH')<label>Nomor berita acara<input name="minutes_number" maxlength="100" required></label><label>Keterangan pelaksanaan<input name="minutes_note" maxlength="10000" required></label><button class="button button-small button-primary" type="submit">Catat pelaksanaan</button></form>
                        @elseif ($disposition->status === 'Dilaksanakan')
                            <a class="button button-small" href="{{ route('retention.minutes', $disposition->id) }}" target="_blank" rel="noopener">Lihat berita acara</a>
                        @elseif ($disposition->status === 'Menunggu Persetujuan')
                            <span class="retention-muted">Menunggu approver</span>
                        @endif
                    </div>
                </article>
            @empty
                <div class="empty-inline">Belum ada usulan penyusutan.</div>
            @endforelse
        </div>
    </section>
</main>
<style>
.retention-page{max-width:1280px}.retention-section{margin-top:15px;padding:0 18px 16px}.retention-form{display:grid;grid-template-columns:repeat(5,minmax(0,1fr)) auto;gap:9px;align-items:end;padding:15px 0;border-bottom:1px solid #edf1f4}.retention-form label,.retention-propose{display:grid;gap:5px;color:#526d7f;font-size:10px;font-weight:650}.retention-form input,.retention-form select,.retention-propose input,.disposition-review input{width:100%;min-height:37px;padding:8px;border:1px solid #dce5ea;border-radius:8px;background:white;color:#29485e;font:inherit}.retention-schedule-list,.retention-list{display:grid;gap:8px;padding-top:13px}.retention-schedule{display:grid;grid-template-columns:1.2fr 1fr 1fr .8fr;gap:12px;align-items:center;padding:12px;border:1px solid #e7edf1;border-radius:9px}.retention-schedule strong,.retention-record strong{color:#1c405b;font-size:12px}.retention-schedule span,.retention-schedule small,.retention-record span,.retention-due span{color:#8294a0;font-size:10px}.retention-muted{color:#8294a0;font-size:10px}.retention-row{display:grid;grid-template-columns:minmax(150px,1.3fr) auto auto minmax(180px,1fr);gap:14px;align-items:center;padding:12px;border:1px solid #e7edf1;border-radius:9px}.retention-record,.retention-due{display:grid;gap:4px;min-width:0}.retention-phase{padding:6px 8px;border-radius:7px;background:#eef6fa;color:#39718e;font-size:10px}.retention-phase.is-due{background:#fff0ee;color:#a43b35}.retention-due small{color:#8294a0;font-size:9px}.retention-due strong{color:#29485e;font-size:11px}.retention-propose{grid-template-columns:1fr auto}.disposition-card{display:grid;grid-template-columns:minmax(220px,.8fr) minmax(0,1.2fr);gap:14px;align-items:center;padding:14px;border:1px solid #e7edf1;border-radius:9px}.disposition-summary h3{margin:8px 0 4px;color:#1c405b;font-size:13px}.disposition-summary p{margin:3px 0;color:#718696;font-size:10px;overflow-wrap:anywhere}.disposition-summary small{color:#8294a0;font-size:9px}.disposition-summary .retention-note{color:#9b5b19}.disposition-review{display:flex;align-items:end;flex-wrap:wrap;gap:7px}.disposition-review label{display:grid;flex:1 1 140px;gap:4px;color:#526d7f;font-size:9px}
@media(max-width:900px){.retention-form{grid-template-columns:repeat(3,minmax(0,1fr))}.retention-row{grid-template-columns:1fr 1fr}.retention-propose{grid-column:1/-1}.disposition-card{grid-template-columns:1fr}}
@media(max-width:600px){.retention-section{padding:0 12px 12px}.retention-form{grid-template-columns:1fr 1fr}.retention-form button{grid-column:1/-1}.retention-schedule{grid-template-columns:1fr}.retention-row{grid-template-columns:1fr 1fr}.retention-record,.retention-propose{grid-column:1/-1}.retention-propose button{width:100%}.disposition-review{align-items:stretch;flex-direction:column}.disposition-review>*{width:100%}}
</style>
@endsection
