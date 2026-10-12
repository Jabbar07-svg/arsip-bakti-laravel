@extends('layouts.app')

@section('title', 'Permintaan Peminjaman Saya')

@section('content')
<main class="page">
    <div class="page-heading-row"><div><div class="eyebrow">Portal Customer</div><h1>Permintaan Peminjaman Saya</h1><p class="page-description">Lihat status permintaan arsip publik yang Anda ajukan.</p></div><a class="button" href="{{ route('archives.index') }}">Cari arsip publik</a></div>
    @if (session('success'))<div class="notice-success" role="status">{{ session('success') }}</div>@endif
    <section class="panel request-list">
        @forelse ($requests as $item)
            <article class="request-item">
                <div><a href="{{ route('archives.show', $item->arsip_id) }}"><strong>{{ $item->nama_berkas }}</strong></a><small>{{ $item->nomor_berkas }} · Diajukan {{ \Illuminate\Support\Carbon::parse($item->created_at)->format('d/m/Y H:i') }}</small><p>{{ $item->purpose }}</p><small>Rencana kembali: {{ $item->requested_due_date ? \Illuminate\Support\Carbon::parse($item->requested_due_date)->format('d/m/Y') : 'Tidak ditentukan' }}</small>@if ($item->reviewer_note)<p class="review-note">Catatan: {{ $item->reviewer_note }}</p>@endif</div>
                <span class="status status-{{ \Illuminate\Support\Str::slug($item->status) }}">{{ $item->status }}</span>
            </article>
        @empty
            <div class="empty-state">Anda belum mengajukan permintaan peminjaman.</div>
        @endforelse
        @if ($requests->hasPages())<div class="pagination">{{ $requests->links() }}</div>@endif
    </section>
</main>
<style>
.request-list{overflow:hidden}.request-item{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:17px 19px;border-bottom:1px solid #edf1f4}.request-item:last-of-type{border-bottom:0}.request-item strong{color:#29485e;font-size:13px}.request-item small{display:block;margin-top:5px;color:#8295a3;font-size:10px}.request-item p{margin:8px 0;color:#526d80;font-size:12px}.review-note{color:#a05a22!important}
@media(max-width:560px){.request-item{flex-direction:column;padding:14px}}
</style>
@endsection
