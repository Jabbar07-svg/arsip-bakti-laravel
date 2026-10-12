@extends('layouts.app')

@section('title', 'Riwayat Aktivitas')

@section('content')
<main class="page">
    <div class="page-heading-row">
        <div>
            <div class="eyebrow">Audit Sistem</div>
            <h1>Riwayat Aktivitas</h1>
            <p class="page-description">Cari aktivitas terbaru pengguna dan perubahan yang tercatat pada arsip.</p>
        </div>
    </div>

    <form class="history-search panel" method="get" action="{{ route('history.index') }}">
        <label class="visually-hidden" for="activity-search">Cari riwayat</label>
        <input id="activity-search" type="search" name="q" value="{{ $search }}" placeholder="Cari aktivitas, pengguna, atau keterangan">
        <button class="button button-primary" type="submit">Cari</button>
        @if ($search !== '')<a class="button" href="{{ route('history.index') }}">Reset</a>@endif
    </form>

    <section class="panel history-panel">
        <div class="panel-heading">
            <div><h2>Log aktivitas</h2><p>Menampilkan paling banyak 300 aktivitas terbaru.</p></div>
            <span class="count-badge">{{ number_format($activities->count(), 0, ',', '.') }} catatan</span>
        </div>
        @if ($activities->isEmpty())
            <div class="empty-state">Belum ada riwayat yang sesuai dengan pencarian.</div>
        @else
            <div class="table-scroll">
                <table class="report-table history-table">
                    <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th><th>Keterangan</th></tr></thead>
                    <tbody>
                    @foreach ($activities as $activity)
                        <tr>
                            <td>{{ $activity->created_at ? \Illuminate\Support\Carbon::parse($activity->created_at)->format('d/m/Y H:i:s') : '—' }}</td>
                            <td><strong>{{ $activity->nama ?? 'Pengguna tidak ditemukan' }}</strong><small class="history-user">{{ $activity->username ?? '—' }}</small></td>
                            <td>{{ $activity->aktivitas }}</td>
                            <td>{{ $activity->keterangan ?: '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</main>
<style>
.history-search{display:flex;gap:8px;padding:13px}.history-search input{min-width:0;flex:1;min-height:42px;padding:0 11px;border:1px solid #dce6ec;border-radius:8px;font-size:13px}.history-panel{overflow:hidden}.history-table{min-width:680px}.history-table td{vertical-align:top}.history-user{display:block;margin-top:4px;color:#8295a3;font-size:10px}
@media(max-width:520px){.history-search{flex-wrap:wrap}.history-search input{flex-basis:100%}.history-search .button{flex:1}}
</style>
@endsection
