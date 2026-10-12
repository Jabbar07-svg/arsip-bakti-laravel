# Sistem Arsip BAKTI — Migrasi Laravel

Laravel berjalan berdampingan dengan aplikasi PHP lama. Database `arsip_bakti` dan aplikasi lama tidak dihapus atau dimigrasikan ulang.

## Menjalankan di Windows/XAMPP

Kebutuhan: PHP 8.2+ dengan ekstensi `pdo_mysql`, Composer, dan MySQL aktif.

```powershell
Set-Location C:\Users\Jabbar\arsip-bakti-laravel
C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8011
```

Buka `http://127.0.0.1:8011`. Jika port 8011 sudah digunakan, pilih port lain dan sesuaikan `APP_URL` pada `.env`.

## Tahap yang sudah dipindahkan

- Login memakai tabel `users` dan hash password dari database yang ada. Akun nonaktif tetap ditolak.
- Pengelolaan arsip mencakup daftar, pencarian, filter status, tambah, edit, dan detail dengan tabel lama `arsip`, `kategori_arsip`, serta `lokasi`.
- Akses arsip mengikuti klasifikasi, peran, dan divisi pengguna. Perubahan dicatat ke tabel audit lama dan QR ID memakai format yang sama dengan aplikasi sebelumnya.
- Lampiran arsip disimpan pada penyimpanan privat dan hanya dapat diunduh melalui pemeriksaan akses; QR cetak masih menggunakan modul aplikasi lama.
- Modul yang belum dipindahkan tetap dapat digunakan melalui aplikasi lama di `http://localhost/Arsip-Bakti/`.
- Halaman daftar arsip menyediakan layout tabel di layar besar dan kartu di ponsel.

## Keamanan dan data

- `.env` menyimpan konfigurasi koneksi lokal; jangan commit file tersebut.
- Aplikasi Laravel berada di luar `htdocs`, sehingga root project, `.env`, dan kode sumber tidak disajikan sebagai file publik oleh Apache.
- `SESSION_DRIVER=file` dan `CACHE_STORE=file` mencegah pembuatan tabel tambahan di database lama.
- Jangan jalankan `php artisan migrate` terhadap database `arsip_bakti`. Skema yang ada sengaja dipakai apa adanya; migrasi skema terpisah perlu direncanakan dan dicadangkan dahulu.
- Tes fitur memakai SQLite sementara dan tidak mengubah data arsip pada database XAMPP.
