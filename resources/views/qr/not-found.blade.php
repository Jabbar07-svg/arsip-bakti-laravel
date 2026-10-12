@extends('layouts.app')

@section('title', 'Kode Tidak Ditemukan')

@section('content')
<main class="page"><section class="panel qr-not-found"><div class="eyebrow">Scan selesai</div><h1>Kode tidak ditemukan</h1><p>Kode <strong>{{ $code }}</strong> tidak cocok dengan QR ID atau nomor berkas yang dapat dibuka akun ini.</p><a class="button button-primary" href="{{ route('qr.scanner') }}">Kembali ke scanner</a></section></main>
<style>.qr-not-found{max-width:560px;margin:8vh auto;padding:30px;text-align:center}.qr-not-found h1{color:#1c405b;font-size:20px}.qr-not-found p{color:#718696;font-size:12px;line-height:1.6;overflow-wrap:anywhere}.qr-not-found .button{margin-top:10px}</style>
@endsection
