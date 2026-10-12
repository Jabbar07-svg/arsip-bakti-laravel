<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $notifications = DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('account.show', compact('notifications'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $updates = [
            'nama' => trim($validated['nama']),
            'updated_at' => now(),
        ];

        $newPath = null;
        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $extension = strtolower((string) $file->guessExtension());
            abort_unless(in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true), 422, 'Format foto profil tidak didukung.');
            $filename = bin2hex(random_bytes(20)).'.'.$extension;
            $newPath = $file->storeAs('profile-images', $filename, 'local');
            if ($newPath === false) {
                throw new RuntimeException('Foto profil gagal disimpan.');
            }
            $updates['profile_image'] = $newPath;
        }

        try {
            DB::table('users')->where('id', $request->user()->id)->update($updates);
        } catch (Throwable $exception) {
            if (is_string($newPath)) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }

        $oldPath = (string) $request->user()->profile_image;
        if (
            is_string($newPath)
            && preg_match('#\Aprofile-images/[a-f0-9]{40}\.(jpg|jpeg|png|webp)\z#i', $oldPath) === 1
        ) {
            Storage::disk('local')->delete($oldPath);
            Storage::disk('public')->delete($oldPath);
        }

        return redirect()->route('account.show')->with('success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            return back()
                ->withErrors(['current_password' => 'Password saat ini tidak sesuai.'])
                ->withInput(['nama' => $request->user()->nama]);
        }

        DB::transaction(function () use ($request, $validated): void {
            DB::table('users')->where('id', $request->user()->id)->update([
                'password' => Hash::make($validated['password']),
                'updated_at' => now(),
            ]);
            DB::table('remember_tokens')->where('user_id', $request->user()->id)->delete();
        });

        $request->session()->regenerate();

        return redirect()->route('account.show')->with('success', 'Password berhasil diperbarui. Silakan masuk kembali di perangkat lain.');
    }

    public function photo(Request $request, string $filename): StreamedResponse
    {
        abort_unless(preg_match('/\A[a-f0-9]{40}\.(jpg|jpeg|png|webp)\z/i', $filename) === 1, 404);
        $path = 'profile-images/'.$filename;
        $currentPhoto = (string) DB::table('users')->where('id', $request->user()->id)->value('profile_image');
        abort_unless($currentPhoto === $path, 404);
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            $disk = Storage::disk('public');
        }
        abort_unless($disk->exists($path), 404);

        return response()->stream(
            static function () use ($disk, $path): void {
                $stream = $disk->readStream($path);
                abort_unless(is_resource($stream), 404);
                fpassthru($stream);
                fclose($stream);
            },
            200,
            [
                'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
                'Cache-Control' => 'private, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
