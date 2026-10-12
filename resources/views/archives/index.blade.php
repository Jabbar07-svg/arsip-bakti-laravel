@extends('layouts.app')

@section('title', 'Data Arsip')

@section('content')
@php
    $archiveData = [
        'archives' => $archives->map(static fn ($archive): array => [
            'id' => (int) $archive->id,
            'name' => (string) $archive->nama_berkas,
            'number' => (string) $archive->nomor_berkas,
            'code' => (string) $archive->kode_arsip,
            'category' => (string) ($archive->nama_kategori ?? ''),
            'date' => \Illuminate\Support\Carbon::parse($archive->tanggal_masuk)->format('d/m/Y'),
            'location' => implode(' / ', array_filter([$archive->gudang, $archive->rak, $archive->lemari, $archive->box])),
            'qrCode' => (string) $archive->qr_code,
            'status' => (string) $archive->status,
            'url' => route('archives.show', $archive->id),
        ])->values(),
        'filters' => $filters,
        'statusOptions' => $statusOptions,
        'locations' => $locations->map(static fn ($location): array => [
            'id' => (int) $location->id,
            'label' => implode(' / ', array_filter([$location->gudang, $location->rak, $location->lemari, $location->box])),
        ])->values(),
        'archiveCount' => $archiveCount,
        'canManageArchives' => $canManageArchives,
        'createUrl' => $canManageArchives ? route('archives.create') : null,
        'indexUrl' => route('archives.index'),
        'previousUrl' => $archives->previousPageUrl(),
        'nextUrl' => $archives->nextPageUrl(),
        'currentPage' => $archives->currentPage(),
        'lastPage' => $archives->lastPage(),
        'hasPages' => $archives->hasPages(),
        'flash' => [
            'success' => session('success'),
            'error' => session('error'),
        ],
    ];
@endphp
<script id="archives-props" type="application/json">@json($archiveData)</script>
<div id="archives-app">
    <main class="page archive-index archive-loading" aria-busy="true">
        <p role="status">Memuat daftar arsip…</p>
    </main>
</div>
@endsection
