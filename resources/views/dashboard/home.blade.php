@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $dashboardData = [
        'userName' => auth()->user()->nama,
        'year' => $year,
        'currentMonth' => $currentMonth,
        'monthName' => now()->translatedFormat('F Y'),
        'dateLabel' => now()->timezone(config('app.timezone'))->translatedFormat('l, d F Y'),
        'timeLabel' => now()->timezone(config('app.timezone'))->format('H:i:s'),
        'timezone' => config('app.timezone'),
        'totalArchives' => $totalArchives,
        'thisMonth' => $thisMonth,
        'thisYear' => $thisYear,
        'locationCount' => $locationCount,
        'statusCounts' => $statusCounts,
        'monthly' => array_values($monthly),
        'monthlyLoans' => array_values($monthlyLoans),
        'activeLoans' => $activeLoans,
        'overdueLoans' => $overdueLoans,
        'categoryDistribution' => $categoryDistribution,
        'recentArchives' => $recentArchives->map(static fn ($archive): array => [
            'id' => (int) $archive->id,
            'qrCode' => (string) $archive->qr_code,
            'number' => (string) $archive->nomor_berkas,
            'name' => (string) $archive->nama_berkas,
            'category' => (string) ($archive->nama_kategori ?? ''),
            'date' => \Illuminate\Support\Carbon::parse($archive->tanggal_masuk)->format('d/m/Y'),
            'status' => (string) $archive->status,
            'url' => route('archives.show', $archive->id),
        ])->values(),
        'archivesUrl' => route('archives.index'),
        'createArchiveUrl' => $canManageArchives ? route('archives.create') : null,
        'createArchiveLabel' => $canManageArchives ? 'Tambah Arsip' : null,
        'reportsUrl' => $canViewReports ? route('reports.index', ['tahun' => $year]) : null,
        'scannerUrl' => route('qr.scanner'),
        'labelsUrl' => $canManageQrLabels ? route('qr.index') : null,
        'loansUrl' => $canManageLoans ? route('loans.index') : null,
    ];
@endphp
<script id="dashboard-props" type="application/json">@json($dashboardData)</script>
<div id="dashboard-app" data-dashboard-app data-dashboard-clock data-timezone="{{ config('app.timezone') }}">
    <main class="page dashboard-page dashboard-loading" aria-busy="true">
        <div class="dashboard-loading-bar"></div>
        <p>Memuat ringkasan dashboard…</p>
    </main>
</div>
@endsection
