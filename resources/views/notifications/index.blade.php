@extends('layouts.app')

@section('title', 'Notifikasi')

@section('content')
<main class="page">
    <div class="page-heading-row">
        <div><div class="eyebrow">Pemberitahuan</div><h1>Notifikasi Saya</h1><p class="page-description">Pemberitahuan yang dikirim ke akun Anda.</p></div>
        @if ($notifications->contains(fn ($notification) => $notification->read_at === null))
            <form method="post" action="{{ route('notifications.read-all') }}">@csrf @method('patch')<button class="button button-primary" type="submit">Tandai semua dibaca</button></form>
        @endif
    </div>

    @if (session('success'))<div class="notice-success" role="status">{{ session('success') }}</div>@endif
    <section class="panel notification-list">
        @forelse ($notifications as $notification)
            <article class="notification-card {{ $notification->read_at ? 'is-read' : 'is-unread' }}">
                <div class="notification-copy">
                    <div class="notification-title"><strong>{{ $notification->title }}</strong><span>{{ ucfirst($notification->type) }}</span></div>
                    <p>{{ $notification->body }}</p>
                    <small>{{ \Illuminate\Support\Carbon::parse($notification->created_at)->format('d/m/Y H:i') }}</small>
                </div>
                <div class="notification-actions">
                    @if ($notification->url !== '' && $notification->url !== './')
                        <a class="button" href="{{ $notification->url }}">Buka</a>
                    @endif
                    @if (!$notification->read_at)
                        <form method="post" action="{{ route('notifications.read', $notification->id) }}">@csrf @method('patch')<button class="button" type="submit">Tandai dibaca</button></form>
                    @else
                        <span class="notification-read">Sudah dibaca</span>
                    @endif
                </div>
            </article>
        @empty
            <div class="empty-state">Belum ada notifikasi.</div>
        @endforelse
        @if ($notifications->hasPages())<div class="pagination">{{ $notifications->links() }}</div>@endif
    </section>
</main>
<style>
.notification-list{overflow:hidden}.notification-card{display:flex;justify-content:space-between;gap:16px;padding:17px 19px;border-bottom:1px solid #edf1f4}.notification-card:last-child{border-bottom:0}.notification-card.is-unread{background:#f7fbfd}.notification-copy{min-width:0}.notification-title{display:flex;align-items:center;gap:9px}.notification-title strong{color:#29485e;font-size:13px}.notification-title span{padding:4px 7px;border-radius:20px;background:#edf6fa;color:#36708e;font-size:9px}.notification-copy p{margin:7px 0;color:#61798b;font-size:12px;line-height:1.55}.notification-copy small,.notification-read{color:#8295a3;font-size:10px}.notification-actions{display:flex;align-items:center;gap:7px;flex-wrap:wrap}
@media(max-width:600px){.notification-card{flex-direction:column;padding:14px}.notification-actions{width:100%}.notification-actions .button{flex:1}}
</style>
@endsection
