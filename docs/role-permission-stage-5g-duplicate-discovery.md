# Validasi Tahap 5G — Pemisahan Discovery Duplikasi

Status: implementasi dan tes aplikasi selesai pada database SQLite in-memory.
Tidak ada migration, database produksi, atau kandidat duplikasi produksi yang
diubah. Kompatibilitas MariaDB belum dibuktikan.

## Desain endpoint

| Operasi | Sebelum Tahap 5G | Setelah Tahap 5G |
|---|---|---|
| Membaca daftar kandidat | `GET /verifikasi-duplikasi` sekaligus menjalankan discovery dan menulis kandidat | `GET /verifikasi-duplikasi` hanya membaca pasangan yang sudah tersimpan |
| Membentuk kandidat | Efek samping dari GET | `POST /verifikasi-duplikasi/discovery`, route `duplicates.discover`, dengan CSRF dan pemeriksaan `DUPLICATES_REVIEW` di backend |
| Memutuskan pasangan | `PATCH /verifikasi-duplikasi/{review}` | Tetap `PATCH /verifikasi-duplikasi/{review}`; validasi status, catatan, izin dan scope kedua arsip tetap berjalan |

Form discovery pada halaman verifikasi mengirim POST eksplisit, menampilkan
konfirmasi, serta menjelaskan batas 500 pasangan per batch. GET dan POST
memakai pemeriksaan izin reviewer yang sama. Role tanpa permission tetap
menerima 403; Admin dan Pegawai tidak memperoleh grant baru. Super Admin dan
Verifikator tetap mengikuti katalog permission yang sudah ada.

## Perilaku discovery

`DuplicateCandidateDiscovery` mencari arsip aktif yang memiliki nomor berkas
ternormalisasi sama atau nama, kategori, dan tahun yang sama. Kedua sisi
pasangan harus lolos `ArchiveAccess::scope()`. Pasangan yang telah tersimpan
dikecualikan sebelum seleksi batch, sehingga kandidat lama tidak memakai slot
500. Urutan pemrosesan deterministik berdasarkan ID arsip pertama dan kedua.

Insert dilakukan dalam transaksi dan menggunakan unique key pasangan yang
sudah tersedia bersama `insertOrIgnore`. Retry tidak membentuk pasangan
duplikat. Status, alasan, reviewer, catatan, dan waktu pada pasangan lama tidak
diperbarui; kandidat lama yang stale tetap tersimpan dan tetap dikecualikan.
Kebijakan refresh metadata atau penutupan kandidat stale masih memerlukan
keputusan bisnis.

Daftar yang tampil tetap dibatasi 300 baris dan kini memiliki tie-breaker ID
setelah status dan waktu pembuatan agar urutannya deterministik. Pagination
daftar belum ditambahkan. Pengguna dapat menjalankan POST kembali untuk
memproses batch baru yang tersisa.

Kesalahan query dicatat dengan user ID dan tipe exception, lalu dilempar
kembali; error tidak diubah menjadi hasil sukses. Transaksi menjaga agar batch
gagal tidak meninggalkan sebagian kandidat. Belum ada lock proses lintas
worker. Unique key dan `insertOrIgnore` menjadi pertahanan pasangan ganda,
tetapi perilaku konkurensi dan semantik insert pada MariaDB harus diuji pada
salinan MariaDB sebelum rilis.

## Tes dan bukti

Tes `ArchiveManagementTest` terarah dijalankan dengan:

```text
php artisan test tests\Feature\ArchiveManagementTest.php --filter=duplicate
7 passed (68 assertions)
```

Kasus yang lulus mencakup GET tanpa tulis, POST discovery, fungsi verifikasi
yang sudah ada, izin Customer/Admin/Pegawai dan Verifikator, scope dua sisi
Kepala Divisi, batch 500 yang deterministik, kelanjutan setelah pasangan lama,
idempotensi retry, preservasi metadata/status lama, serta rollback dan logging
saat query gagal.

Semua bukti ini hanya berasal dari SQLite in-memory (`phpunit.xml`). Belum
membuktikan kompatibilitas MariaDB, konkurensi antarproses, maupun keseluruhan
jumlah pasangan untuk data produksi. Tidak ada discovery yang dijalankan pada
database produksi.

## File implementasi

- `routes/web.php` — menambahkan POST discovery eksplisit.
- `app/Http/Controllers/DuplicateReviewController.php` — GET read-only,
  handler discovery terotorisasi, dan urutan daftar deterministik.
- `app/Support/DuplicateCandidateDiscovery.php` — query discovery scoped,
  batch 500, filter pasangan lama, insert transaksional/idempotent, dan
  logging error.
- `resources/views/duplicates/index.blade.php` — tombol POST dengan konfirmasi
  dan keterangan batch.
- `tests/Feature/ArchiveManagementTest.php` — tes regresi route, scope,
  batching, idempotensi, metadata lama, dan kegagalan.

Tahap ini tidak menjalankan migration role atau mengubah grant, akun,
konfigurasi, enum, atau data lama.

## Keputusan dan prasyarat tersisa

1. Tetapkan kebijakan pembaruan `match_reason`, penutupan kandidat stale, dan
   perlakuan pasangan yang telah diputuskan.
2. Uji unique key, `insertOrIgnore`, transaksi/rollback, collation dan
   pemanggilan bersamaan pada salinan MariaDB yang terisolasi.
3. Tinjau kebutuhan pagination daftar 300 baris dan throttling/monitoring
   discovery bila penggunaan operasional memerlukannya.
4. Jangan memberikan grant role baru atau menjalankan migration sebelum
   keputusan dan validasi database tersebut selesai.
