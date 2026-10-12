<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function feed(Request $request)
    {
        $userId = $request->user()->id;
        $query = DB::table('notifications')->where('user_id', $userId);
        if ($request->boolean('baseline')) {
            $latest = (int) $query->max('id');

            return response()
                ->json(['ok' => true, 'latest' => $latest, 'items' => []])
                ->header('Cache-Control', 'no-store, private')
                ->header('X-Content-Type-Options', 'nosniff');
        }

        $validated = $request->validate(['after' => ['nullable', 'integer', 'min:0']]);
        $afterId = (int) ($validated['after'] ?? 0);
        $items = DB::table('notifications')
            ->where('user_id', $userId)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'title', 'body', 'type', 'url', 'created_at']);
        $latest = $items->isNotEmpty() ? (int) $items->last()->id : $afterId;

        return response()
            ->json(['ok' => true, 'latest' => $latest, 'items' => $items])
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function index(Request $request): View
    {
        $notifications = DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('notifications.index', compact('notifications'));
    }

    public function markRead(Request $request, int $notification): RedirectResponse
    {
        $updated = DB::table('notifications')
            ->where('id', $notification)
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        abort_unless($updated > 0 || DB::table('notifications')
            ->where('id', $notification)
            ->where('user_id', $request->user()->id)
            ->exists(), 404);

        return back()->with('success', 'Notifikasi ditandai sudah dibaca.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return redirect()->route('notifications.index')->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
