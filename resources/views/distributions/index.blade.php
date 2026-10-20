@extends('layouts.app')

@section('title', 'Distribusi Arsip')

@section('content')
<main class="page">
    <div class="eyebrow">Pengelolaan Antarunit</div>
    <h1>Distribusi arsip</h1>
    <p class="page-description">Catat perpindahan arsip antarunit secara terpisah dari peminjaman, dengan persetujuan dan bukti serah-terima.</p>

    @if (session('success'))<div class="notice notice-success" role="status">{{ session('success') }}</div>@endif
    @if ($errors->any())
        <div class="notice notice-error" role="alert">
            <strong>Permintaan gagal diproses.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="panel">
        <div class="panel-heading">
            <div><h2>Daftar distribusi</h2><p>Hanya transaksi dalam cakupan peran, divisi, atau partisipasi Anda yang ditampilkan.</p></div>
            @if ($canRequest)
                <a class="button button-primary" href="{{ route('distributions.create') }}">Ajukan distribusi</a>
            @endif
            <span class="count-badge">{{ $distributions->total() }} transaksi</span>
        </div>
        <div class="distribution-list">
            @forelse ($distributions as $item)
                <article class="distribution-card">
                    <div class="distribution-top">
                        <div>
                            <span class="distribution-number">#{{ $item->id }} · {{ $item->nomor_berkas }}</span>
                            <h3>{{ $item->nama_berkas }}</h3>
                            <p>{{ $item->origin_division }} <span aria-hidden="true">→</span> {{ $item->destination_division }}</p>
                        </div>
                        <span class="distribution-status status-{{ \Illuminate\Support\Str::slug($item->status) }}">{{ $item->status }}</span>
                    </div>
                    <dl class="distribution-meta">
                        <div><dt>Pengusul</dt><dd>{{ $item->requester_name }}</dd></div>
                        <div><dt>Penerima</dt><dd>{{ $item->recipient_name }}</dd></div>
                        <div><dt>Tanggal pengajuan</dt><dd>{{ \Illuminate\Support\Carbon::parse($item->created_at)->format('d M Y H:i') }}</dd></div>
                        @if ($item->sent_at)<div><dt>Dikirim</dt><dd>{{ \Illuminate\Support\Carbon::parse($item->sent_at)->format('d M Y H:i') }} · {{ $item->sender_id ? $item->dispatcher_name : '—' }}</dd></div>@endif
                        @if ($item->received_at)<div><dt>Diterima</dt><dd>{{ \Illuminate\Support\Carbon::parse($item->received_at)->format('d M Y H:i') }} · {{ $item->receiver_name }}</dd></div>@endif
                    </dl>
                    @if ($item->notes)<p class="distribution-note"><strong>Keterangan:</strong> {{ $item->notes }}</p>@endif
                    @if ($item->rejection_note)<p class="distribution-note rejection"><strong>Alasan ditolak:</strong> {{ $item->rejection_note }}</p>@endif
                    @if ($item->receipt_note)<p class="distribution-note"><strong>Bukti serah-terima:</strong> {{ $item->receipt_note }}</p>@endif
                    <div class="distribution-actions">
                        @if ($canApprove && (\App\Support\RolePermissions::isAdmin(auth()->user()) || \App\Support\RolePermissions::matchesDivision(auth()->user(), $item->origin_division)) && $item->status === 'Menunggu Persetujuan' && (int) $item->requested_by !== (int) auth()->id())
                            <form method="post" action="{{ route('distributions.review', $item->id) }}" class="decision-form">
                                @csrf @method('PATCH')
                                <input type="hidden" name="decision" value="Disetujui">
                                <button class="button button-primary" type="submit">Setujui</button>
                            </form>
                            <form method="post" action="{{ route('distributions.review', $item->id) }}" class="decision-form rejection-form">
                                @csrf @method('PATCH')
                                <input type="hidden" name="decision" value="Ditolak">
                                <label class="visually-hidden" for="rejection_note_{{ $item->id }}">Alasan penolakan</label>
                                <input id="rejection_note_{{ $item->id }}" name="rejection_note" required maxlength="5000" placeholder="Alasan penolakan">
                                <button class="button" type="submit">Tolak</button>
                            </form>
                        @endif
                        @if ($canDispatch && (\App\Support\RolePermissions::isAdmin(auth()->user()) || \App\Support\RolePermissions::matchesDivision(auth()->user(), $item->origin_division)) && $item->status === 'Disetujui')
                            <form method="post" action="{{ route('distributions.dispatch', $item->id) }}">
                                @csrf @method('PATCH')
                                <button class="button button-primary" type="submit">Catat pengiriman</button>
                            </form>
                        @endif
                        @if ($item->status === 'Dalam Pengiriman' && ((int) $item->recipient_id === (int) auth()->id() || ($canReceive && (\App\Support\RolePermissions::isAdmin(auth()->user()) || \App\Support\RolePermissions::matchesDivision(auth()->user(), $item->destination_division)))))
                            <form method="post" action="{{ route('distributions.receive', $item->id) }}" enctype="multipart/form-data" class="receipt-form">
                                @csrf @method('PATCH')
                                <label for="receipt_note_{{ $item->id }}">Catatan penerimaan</label>
                                <textarea id="receipt_note_{{ $item->id }}" name="receipt_note" rows="2" required maxlength="5000" placeholder="Kondisi arsip saat diterima"></textarea>
                                <label for="receipt_file_{{ $item->id }}">Lampiran bukti (PDF/JPG/PNG, maks. 10 MB)</label>
                                <input id="receipt_file_{{ $item->id }}" type="file" name="receipt_attachment" accept=".pdf,.jpg,.jpeg,.png">
                                <button class="button button-primary" type="submit">Konfirmasi penerimaan</button>
                            </form>
                        @endif
                        @if ($item->receipt_attachment)
                            <a class="button" href="{{ route('distributions.evidence', $item->id) }}">Unduh bukti</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada transaksi distribusi dalam cakupan akses Anda.</div>
            @endforelse
        </div>
        @if ($distributions->hasPages())
            <div class="pagination"><span>Halaman {{ $distributions->currentPage() }} dari {{ $distributions->lastPage() }}</span>{{ $distributions->links() }}</div>
        @endif
    </section>
    <p class="footer-note">Distribusi bukan peminjaman. Arsip tetap tercatat dan statusnya tidak dihapus saat dipindahkan.</p>
</main>
<style>
    .notice{margin-top:17px;padding:12px 15px;border-radius:9px;font-size:12px;line-height:1.55}
    .notice-success{background:#edf8f2;color:#187653}
    .notice-error{background:#fff1ef;color:#a43b35}
    .notice ul{margin:6px 0 0;padding-left:20px}
    .distribution-list{display:grid;gap:12px;padding:16px}
    .distribution-card{padding:17px;border:1px solid #e8eef2;border-radius:12px;background:#fff}
    .distribution-top{display:flex;justify-content:space-between;gap:15px}
    .distribution-number{color:#0876b3;font-size:10px;font-weight:800}
    .distribution-top h3{margin:6px 0;color:#1c405b;font-size:16px}
    .distribution-top p{margin:0;color:#738a9a;font-size:12px}
    .distribution-top p span{padding:0 5px;color:#087fc3;font-weight:800}
    .distribution-status{height:max-content;padding:6px 9px;border-radius:30px;background:#eef3f7;color:#50687a;font-size:10px;font-weight:800;white-space:nowrap}
    .status-menunggu-persetujuan{background:#fff5dc;color:#8a6414}
    .status-disetujui,.status-dalam-pengiriman{background:#eaf4ff;color:#176aa2}
    .status-diterima{background:#edf8f2;color:#187653}
    .status-ditolak{background:#fff1ef;color:#a43b35}
    .distribution-meta{display:flex;flex-wrap:wrap;gap:10px 24px;margin:15px 0 0;padding:12px 0;border-top:1px solid #eef2f4;border-bottom:1px solid #eef2f4}
    .distribution-meta div{min-width:120px}
    .distribution-meta dt{color:#8295a3;font-size:9px}
    .distribution-meta dd{margin:4px 0 0;color:#405d71;font-size:11px;font-weight:700}
    .distribution-note{margin:11px 0 0;color:#61798b;font-size:11px;line-height:1.6;overflow-wrap:anywhere}
    .distribution-note.rejection{color:#a43b35}
    .distribution-actions{display:flex;flex-wrap:wrap;align-items:flex-start;gap:8px;margin-top:14px}
    .distribution-actions form{display:flex;flex-wrap:wrap;align-items:center;gap:8px}
    .rejection-form input{width:min(240px,100%);min-height:44px;padding:0 10px;border:1px solid #dce6ec;border-radius:9px;font-size:11px}
    .receipt-form{flex-basis:min(100%,440px);padding:12px;border-radius:10px;background:#f7f9fa}
    .receipt-form label{flex-basis:100%;color:#50687a;font-size:10px;font-weight:700}
    .receipt-form textarea,.receipt-form input[type=file]{width:100%;padding:9px;border:1px solid #dce6ec;border-radius:8px;background:#fff;color:#405d71;font:inherit;font-size:11px}
    @media(max-width:560px){.distribution-list{padding:10px}.distribution-card{padding:13px}.distribution-top{flex-direction:column}.distribution-meta{gap:10px}.distribution-meta div{min-width:calc(50% - 12px)}.distribution-actions>form{width:100%}.distribution-actions .button{width:100%}.rejection-form input{flex:1;width:auto}}
</style>
@endsection
