# Sistem Arsip BAKTI — Migrasi Laravel

Laravel berjalan berdampingan dengan aplikasi PHP lama. Database `arsip_bakti`
tidak dihapus atau diimpor ulang. Riwayat perubahan enum role yang sudah
diterapkan pada instalasi ini dicatat di
[`docs/database-existing-schema.md`](./docs/database-existing-schema.md).

## Menjalankan di Windows/XAMPP

Kebutuhan: PHP 8.2+ dengan ekstensi `pdo_mysql`, Composer, dan MySQL aktif.

```powershell
Set-Location C:\Users\Jabbar\arsip-bakti-laravel
C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8011
```

Buka `http://127.0.0.1:8011`. Jika port 8011 sudah digunakan, pilih port lain dan sesuaikan `APP_URL` pada `.env`.

## Database lokal

Laravel menggunakan koneksi MySQL XAMPP (`127.0.0.1:3306`). `.env.example`
memakai nama database lokal terpisah `arsip_bakti_local`; jangan mengganti
`DB_DATABASE` menjadi `arsip_bakti` untuk pengembangan atau pengujian.
Pada saat audit, `.env` aktif masih menunjuk ke `arsip_bakti`; file tersebut
tidak diubah dan jangan gunakan konfigurasi itu untuk uji tulis.

Database kosong dapat dibuat melalui phpMyAdmin atau SQL berikut:

```sql
CREATE DATABASE arsip_bakti_local
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

Pembuatan database hanya menyiapkan wadah kosong. Repository ini belum
menyertakan baseline migration lengkap untuk 19 tabel bisnis legacy, sehingga
database kosong belum siap dipakai aplikasi. Gunakan schema/backup lengkap yang
telah disetujui dan diverifikasi; jangan impor `database/arsip_bakti.sql` dari
aplikasi PHP lama karena dump tersebut tidak lengkap dan berisi perintah
`DROP TABLE`. Sampai baseline lengkap tersedia, jangan jalankan `php artisan
migrate`, `migrate:fresh`, atau `db:seed` pada database aktif.

Setelah menyiapkan database yang benar, atur `DB_CONNECTION`, `DB_HOST`,
`DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` di `.env`, lalu
jalankan `php artisan config:clear` agar konfigurasi lama tidak tertinggal.
Jangan salin `.env` ke repository.

Setelah schema-only backup lengkap diimpor ke database lokal yang sudah
diverifikasi, pastikan `DB_DATABASE=arsip_bakti_local` dan jalankan
`SELECT DATABASE();` di koneksi yang sama untuk memastikan targetnya tepat.
Setelah migration schema aplikasi yang lebih awal tercatat selesai, migrasi
penyederhanaan role dapat dijalankan secara terpisah:

```powershell
php artisan migrate --path=database/migrations/2026_10_18_000003_simplify_internal_user_roles.php
```

Migration menolak target selain `arsip_bakti_local`, memetakan `Super Admin`
menjadi `Admin` dan role pengelola arsip lama menjadi `Petugas Arsip`; password,
status, divisi, dan identitas akun tidak diubah. Jika ditemukan role legacy yang
memerlukan keputusan akses (misalnya Customer, Verifikator, atau Approver),
migration berhenti tanpa perubahan agar akun tersebut diaudit terlebih dahulu.
Path eksplisit mencegah migration starter Laravel yang tidak cocok dengan tabel
legacy ikut dijalankan. Admin awal hanya dapat dibuat melalui
`php artisan app:create-initial-admin` saat tabel pengguna masih kosong.

## Tahap yang sudah dipindahkan

- Login memakai tabel `users` dan hash password dari database yang ada. Akun nonaktif tetap ditolak.
- Pengelolaan arsip mencakup daftar, pencarian, filter status, tambah, edit, dan detail dengan tabel lama `arsip`, `kategori_arsip`, serta `lokasi`.
- Akses arsip mengikuti klasifikasi, peran, dan divisi pengguna. Perubahan dicatat ke tabel audit lama dan QR ID memakai format yang sama dengan aplikasi sebelumnya.
- Role internal terdiri dari Admin (administrasi dan persetujuan), Petugas Arsip (pengelolaan operasional dalam divisinya), dan Pegawai (akses arsip sesuai divisi dan peminjaman milik sendiri).
- Pegawai dapat meminjam arsip yang boleh diakses dari halaman detail tanpa memindai QR; transaksi langsung tercatat sebagai `Dipinjam`, muncul pada menu Peminjaman Saya, dan dapat dikelola Admin melalui daftar transaksi. Permintaan peminjaman Pegawai dapat dipantau dan diputuskan Admin.
- Lampiran arsip disimpan pada penyimpanan privat dan hanya dapat diunduh melalui pemeriksaan akses; QR cetak masih menggunakan modul aplikasi lama.
- Modul yang belum dipindahkan tetap dapat digunakan melalui aplikasi lama di `http://localhost/Arsip-Bakti/`.
- Halaman daftar arsip menyediakan layout tabel di layar besar dan kartu di ponsel.

## Keamanan dan data

- `.env` menyimpan konfigurasi koneksi lokal; jangan commit file tersebut.
- Aplikasi Laravel berada di luar `htdocs`, sehingga root project, `.env`, dan kode sumber tidak disajikan sebagai file publik oleh Apache.
- `SESSION_DRIVER=file` dan `CACHE_STORE=file` mencegah pembuatan tabel tambahan di database lama.
- Script Composer tidak lagi menjalankan migration otomatis. Migration role baru hanya boleh diterapkan pada salinan lokal yang sudah dipastikan aman; migration secara eksplisit menolak database bernama `arsip_bakti`.
- Tes fitur memakai SQLite sementara dan tidak mengubah data arsip pada database XAMPP.
