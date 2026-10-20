@extends('layouts.app')

@section('title', 'Dashboard Pegawai')

@section('content')
<main class="page employee-dashboard">
    <header class="employee-hero">
        <div>
            <div class="eyebrow">Ruang kerja Pegawai</div>
            <h1>Selamat datang, {{ auth()->user()->nama }}</h1>
            <p class="page-description">Pantau arsip yang dapat Anda akses, transaksi peminjaman, dan aktivitas akun Anda.</p>
        </div>
        <a class="button button-primary" href="{{ route('archives.index') }}">Telusuri arsip</a>
    </header>

    <section class="employee-stats" aria-label="Ringkasan akun">
        <article class="employee-stat"><span>Arsip dapat diakses</span><strong>{{ number_format($archiveCount, 0, ',', '.') }}</strong><small>Sesuai klasifikasi dan divisi {{ auth()->user()->division ?: 'Anda' }}</small></article>
        <article class="employee-stat"><span>Peminjaman aktif</span><strong>{{ number_format($activeLoanCount, 0, ',', '.') }}</strong><small>{{ number_format($loanCount, 0, ',', '.') }} transaksi tercatat · <a href="{{ route('employee-loans.index') }}">Lihat status</a></small></article>
        <article class="employee-stat"><span>Sudah dikembalikan</span><strong>{{ number_format($returnedLoanCount, 0, ',', '.') }}</strong><small>Transaksi selesai yang tercatat</small></article>
        <article class="employee-stat"><span>Notifikasi belum dibaca</span><strong>{{ number_format($unreadNotificationCount, 0, ',', '.') }}</strong><small>Notifikasi untuk akun Anda</small></article>
    </section>

    <section class="employee-status" aria-label="Status permintaan peminjaman dengan persetujuan">
        <div><span class="status-dot status-pending"></span><span>Menunggu persetujuan</span><strong>{{ number_format($pendingRequestCount, 0, ',', '.') }}</strong></div>
        <div><span class="status-dot status-approved"></span><span>Disetujui</span><strong>{{ number_format($approvedRequestCount, 0, ',', '.') }}</strong></div>
        <div><span class="status-dot status-rejected"></span><span>Ditolak</span><strong>{{ number_format($rejectedRequestCount, 0, ',', '.') }}</strong></div>
        <div><span class="status-dot status-returned"></span><span>Langsung dikembalikan</span><strong>{{ number_format($returnedLoanCount, 0, ',', '.') }}</strong></div>
    </section>

    <p class="employee-loan-note">Ada dua jalur: ajukan arsip publik untuk persetujuan, atau catat peminjaman arsip yang dapat diakses tanpa QR. Peminjaman langsung berstatus <strong>Dipinjam</strong> hingga petugas memproses pengembaliannya.</p>

    <section class="panel employee-panel">
        <div class="panel-heading"><div><h2>Transaksi peminjaman terbaru</h2><p>Peminjaman langsung tanpa QR dan permintaan yang disetujui.</p></div><a class="count-badge" href="{{ route('employee-loans.index') }}">Lihat semua</a></div>
        @forelse ($recentLoans as $loan)
            <article class="employee-list-item">
                <div><a href="{{ route('archives.show', $loan->arsip_id) }}"><strong>{{ $loan->nama_berkas }}</strong></a><small>{{ $loan->nomor_berkas }} · Dipinjam {{ \Illuminate\Support\Carbon::parse($loan->tanggal_pinjam)->format('d/m/Y') }}</small>
                    @if ($loan->tanggal_dikembalikan)<small>Dikembalikan {{ \Illuminate\Support\Carbon::parse($loan->tanggal_dikembalikan)->format('d/m/Y') }} · Kondisi: {{ $loan->condition_return }}</small>
                    @elseif ($loan->tanggal_rencana_kembali)<small>Rencana kembali {{ \Illuminate\Support\Carbon::parse($loan->tanggal_rencana_kembali)->format('d/m/Y') }}</small>@endif
                    @if ($loan->keperluan)<p>{{ $loan->keperluan }}</p>@endif</div>
                <span class="status status-{{ \Illuminate\Support\Str::slug($loan->status) }}">{{ $loan->status }}</span>
            </article>
        @empty
            <div class="empty-state">Belum ada transaksi. Pilih peminjaman langsung tanpa QR atau ajukan peminjaman arsip publik.</div>
        @endforelse
    </section>

    <div class="employee-columns">
        <section class="panel employee-panel">
            <div class="panel-heading"><div><h2>Permintaan peminjaman terbaru</h2><p>Permintaan arsip publik yang memerlukan persetujuan.</p></div><a class="count-badge" href="{{ route('loan-requests.index') }}">Lihat semua</a></div>
            @forelse ($recentRequests as $item)
                <article class="employee-list-item">
                    <div><a href="{{ route('archives.show', $item->archive_id) }}"><strong>{{ $item->nama_berkas }}</strong></a><small>{{ $item->nomor_berkas }} · {{ \Illuminate\Support\Carbon::parse($item->created_at)->format('d/m/Y H:i') }}</small></div>
                    <span class="status status-{{ \Illuminate\Support\Str::slug($item->status) }}">{{ $item->status }}</span>
                </article>
            @empty
                <div class="empty-state">Belum ada permintaan peminjaman yang menunggu atau diproses.</div>
            @endforelse
        </section>

        <section class="panel employee-panel">
            <div class="panel-heading"><div><h2>Aktivitas terbaru</h2><p>Aktivitas arsip dari akun Anda.</p></div><a class="count-badge" href="{{ route('history.index') }}">Riwayat</a></div>
            @forelse ($recentActivities as $activity)
                <article class="employee-list-item">
                    <div><strong>{{ $activity->aktivitas }}</strong><small>{{ $activity->nama_berkas }} · {{ \Illuminate\Support\Carbon::parse($activity->created_at)->format('d/m/Y H:i') }}</small>@if ($activity->keterangan)<p>{{ $activity->keterangan }}</p>@endif</div>
                </article>
            @empty
                <div class="empty-state">Belum ada aktivitas arsip.</div>
            @endforelse
        </section>
    </div>

    <section class="panel employee-panel">
        <div class="panel-heading"><div><h2>Notifikasi terbaru</h2><p>Pemberitahuan yang ditujukan kepada akun Anda.</p></div><a class="count-badge" href="{{ route('notifications.index') }}">Semua notifikasi</a></div>
        @forelse ($notifications as $notification)
            <article class="employee-list-item">
                <div><strong>{{ $notification->title }}</strong><small>{{ \Illuminate\Support\Carbon::parse($notification->created_at)->format('d/m/Y H:i') }} · {{ $notification->read_at ? 'Sudah dibaca' : 'Belum dibaca' }}</small><p>{{ $notification->body }}</p></div>
                @unless ($notification->read_at)<span class="status">Baru</span>@endunless
            </article>
        @empty
            <div class="empty-state">Tidak ada notifikasi baru.</div>
        @endforelse
    </section>

    <p class="employee-footnote">Akun Anda · {{ auth()->user()->division ?: 'Divisi belum ditetapkan' }} · <a href="{{ route('account.show') }}">Lihat profil</a></p>
</main>

<style>
.employee-hero{display:flex;align-items:flex-end;justify-content:space-between;gap:18px}.employee-hero .button{flex:none}
.employee-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px;margin-top:24px}
.employee-stat{min-width:0;padding:18px;border:1px solid #e0e9ef;border-radius:13px;background:#fff;box-shadow:0 8px 28px rgba(18,56,80,.04)}
.employee-stat span,.employee-stat small{display:block;color:#748a9a;font-size:11px}.employee-stat strong{display:block;margin:11px 0;color:#153f5b;font-size:30px;letter-spacing:-1px}.employee-stat small{line-height:1.5}
.employee-status{display:flex;gap:10px;margin-top:14px;padding:14px 17px;border:1px solid #e0e9ef;border-radius:12px;background:#fff}.employee-status>div{display:flex;align-items:center;gap:8px;flex:1;color:#647d8f;font-size:11px}.employee-status strong{margin-left:auto;color:#244e68}
.employee-loan-note{margin:14px 0 0;padding:12px 15px;border-left:3px solid #087fc3;border-radius:7px;background:#eef7fb;color:#526d7f;font-size:11px;line-height:1.6}
.status-dot{width:8px;height:8px;border-radius:50%;background:#d29a36}.status-approved{background:#2b9a71}.status-rejected{background:#c95b55}.status-returned{background:#567da8}
.employee-columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.employee-panel{overflow:hidden}.employee-list-item{display:flex;align-items:flex-start;justify-content:space-between;gap:13px;padding:15px 18px;border-bottom:1px solid #edf1f4}.employee-list-item:last-child{border-bottom:0}.employee-list-item strong{color:#24465f;font-size:12px}.employee-list-item small{display:block;margin-top:5px;color:#8295a3;font-size:10px}.employee-list-item p{margin:7px 0 0;color:#637b8b;font-size:11px;line-height:1.5}.employee-list-item .status{flex:none}.employee-footnote{margin:16px 0;color:#8295a3;font-size:11px;text-align:right}
@media(max-width:800px){.employee-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.employee-columns{grid-template-columns:1fr}}
@media(max-width:560px){.employee-hero{align-items:flex-start;flex-direction:column}.employee-status{align-items:stretch;flex-direction:column}.employee-status>div{min-height:22px}.employee-list-item{padding:14px}.employee-footnote{text-align:left}}
</style>
@endsection
