@extends('layouts.app')

@section('title', 'Scan QR / Barcode')

@section('content')
<div id="qr-scanner-app" data-lookup-url="{{ route('qr.lookup') }}">
    <main class="page scanner-page" aria-busy="true">
        <h1>Scan QR / Barcode</h1>
        <p class="page-description">Memuat pemindai kode arsip…</p>
    </main>
</div>
@endsection
