@extends('layouts.app')

@section('title', 'Persetujuan Peminjaman')

@section('content')
<main class="page">
    <div class="page-heading-row"><div><div class="eyebrow">Manajemen Sirkulasi</div><h1>Persetujuan Peminjaman</h1><p class="page-description">Periksa permintaan peminjaman Pegawai. Persetujuan akan membuat transaksi dan mengubah status arsip secara atomik.</p></div><span class="count-badge">{{ number_format($pendingCount, 0, ',', '.') }} menunggu</span></div>
    @if (session('success'))<div class="notice-success" role="status">{{ session('success') }}</div>@endif
    <section class="panel approval-list">
        @forelse ($requests as $item)
            <article class="approval-item">
                <div class="approval-summary">
                    <div><span>Pemohon</span><strong>{{ $item->requester_name }}</strong><small>{{ $item->username }} · {{ $item->division ?: 'Tanpa divisi' }}</small></div>
                    <div><span>Arsip</span><strong>{{ $item->nama_berkas }}</strong><small>{{ $item->nomor_berkas }} · {{ $item->archive_status }}</small></div>
                    <div><span>Keperluan</span><p>{{ $item->purpose }}</p></div>
                    <div><span>Rencana kembali</span><p>{{ $item->requested_due_date ? \Illuminate\Support\Carbon::parse($item->requested_due_date)->format('d/m/Y') : 'Tidak ditentukan' }}</p></div>
                    <div><span>Status</span><strong>{{ $item->status }}</strong>@if ($item->reviewer_note)<small>{{ $item->reviewer_note }}</small>@endif</div>
                </div>
                @if ($item->status === 'Menunggu Persetujuan')
                    <form class="approval-form" method="post" action="{{ route('loan-approvals.decide', $item->id) }}" onsubmit="return confirm('Simpan keputusan untuk permintaan ini?')">
                        @csrf @method('patch')
                        <label>Catatan keputusan<textarea name="reviewer_note" rows="2" maxlength="5000" placeholder="Wajib diisi jika ditolak"></textarea></label>
                        @error('reviewer_note')<span class="form-error">{{ $message }}</span>@enderror
                        <div><button class="button button-primary" name="decision" value="Disetujui">Setujui</button><button class="button reject-button" name="decision" value="Ditolak">Tolak</button></div>
                    </form>
                @elseif ($item->loan_id)
                    <a class="button" href="{{ route('loans.index') }}">Buka transaksi #{{ $item->loan_id }}</a>
                @else
                    <small class="approval-reviewer">Ditinjau {{ $item->reviewer_name ?: '—' }} · {{ $item->reviewed_at ? \Illuminate\Support\Carbon::parse($item->reviewed_at)->format('d/m/Y H:i') : '' }}</small>
                @endif
            </article>
        @empty
            <div class="empty-state">Belum ada permintaan peminjaman.</div>
        @endforelse
    </section>
</main>
<style>
.approval-list{overflow:hidden}.approval-item{padding:17px 19px;border-bottom:1px solid #edf1f4}.approval-item:last-child{border-bottom:0}.approval-summary{display:grid;grid-template-columns:1fr 1.3fr 1.5fr 1fr 1fr;gap:12px}.approval-summary span,.approval-summary small{display:block;color:#8295a3;font-size:10px}.approval-summary strong{display:block;margin-top:5px;color:#29485e;font-size:11px}.approval-summary p{margin:5px 0;color:#526d80;font-size:11px;line-height:1.5}.approval-form{display:grid;grid-template-columns:minmax(180px,1fr) auto;align-items:end;gap:10px;margin-top:13px;padding-top:12px;border-top:1px solid #edf1f4}.approval-form label{display:grid;gap:5px;color:#526d80;font-size:10px;font-weight:700}.approval-form textarea{width:100%;padding:8px;border:1px solid #dce6ec;border-radius:7px;font:inherit}.approval-form>div{display:flex;gap:6px}.reject-button{color:#aa413a}.approval-reviewer{display:block;margin-top:10px;color:#8295a3}
@media(max-width:900px){.approval-summary{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){.approval-item{padding:14px}.approval-form{grid-template-columns:1fr}.approval-form>div{display:grid}.approval-form .button{width:100%}}
</style>
@endsection
