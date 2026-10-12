# Tahap 5I — Validasi Integrasi MariaDB

Status: pemeriksaan prasyarat dan tinjauan kode selesai; pengujian integrasi
MariaDB **belum dijalankan**. Tidak ada koneksi database, tes, migration,
discovery duplikasi, atau operasi tulis database yang dijalankan selama tahap
ini. Kompatibilitas MariaDB belum terverifikasi.

## 1. Lingkungan dan pemeriksaan awal

Pemeriksaan dilakukan pada workspace Laravel
`C:\Users\Jabbar\arsip-bakti-laravel`.

| Pemeriksaan | Hasil |
|---|---|
| `APP_ENV` dari `.env` | `local` |
| `DB_CONNECTION` aktif | `mysql` |
| Host/port dari `.env` | `127.0.0.1:3306` |
| Nama database aktif dari `.env` | `arsip_bakti` |
| User database | Dikonfigurasi; nilainya tidak dicatat |
| Password dan APP_KEY | Tidak dibaca/dicatat dalam laporan |
| Versi PHP CLI | 8.2.12 |
| Ekstensi PDO | `pdo_mysql` dan `pdo_sqlite` tersedia |
| Konfigurasi PHPUnit | `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` |
| `.env.testing` | Tidak tersedia |
| Klien `mariadb` / `mysql` | Tidak ditemukan |
| Listener lokal port 3306 | Terlihat, tetapi server, versi, dan database di balik port tidak diidentifikasi |
| Git repository | Folder Laravel tidak dikenali sebagai repository Git; perubahan lokal tidak dapat dibandingkan dengan status Git |

Konfigurasi aktif menunjuk tepat ke database produksi yang dilarang digunakan
untuk tes, yaitu `arsip_bakti`. Demi batasan keselamatan, tidak dilakukan
`php artisan test`, `migrate:status`, query SQL, atau perintah Artisan lain yang
dapat membuka koneksi melalui konfigurasi tersebut.

Dokumentasi audit sebelumnya mencatat MariaDB 10.4.32 dan schema
`arsip_bakti` dengan 19 tabel, InnoDB, serta collation `utf8mb4_unicode_ci`.
Informasi itu merupakan snapshot terdahulu dari database aktif/produksi; bukan
identifikasi server saat ini dan bukan database uji. Tidak ada versi MariaDB
atau schema database testing yang dapat dikonfirmasi pada Tahap 5I.

**Database pengujian MariaDB terpisah belum tersedia/terkonfirmasi.** Listener
pada port lokal saja tidak membuktikan adanya database uji terisolasi dan tidak
menjadi alasan untuk mencoba kredensial `.env`.

## 2. Tinjauan kode aktual

Audit memeriksa `ArchiveAccess`, `RolePermissions`, route web, controller
arsip, laporan, ekspor, riwayat, QR, retensi dan duplikasi, discovery service,
serta tes dalam `ArchiveManagementTest`. Tidak ditemukan policy Laravel
terpusat untuk scope yang diaudit; izin dan scope sebagian besar diterapkan
oleh helper/controller.

### Scope divisi

- `RolePermissions::scopeToDivision()` untuk Kepala Divisi menghasilkan
  `LOWER(TRIM(alias.division)) = ?`, dengan nilai user telah di-trim dan
  dilowercase di PHP. User tanpa division menghasilkan kondisi kosong
  (`1 = 0`); hasil scope juga mengecualikan `Dokumen Rahasia`.
- Kondisi SQL tidak akan mencocokkan `NULL` dengan nilai division nonkosong.
  Nilai arsip kosong setelah trim juga tidak cocok dengan user yang memiliki
  division. Ini pembacaan logika SQL; perilaku eksekusi database belum diuji
  MariaDB pada tahap ini.
- `ArchiveAccess::canView()` memakai perbandingan trim + lowercase Unicode
  di PHP untuk Kepala Divisi. Role legacy non-Kepala Divisi menggunakan
  perbandingan kolom `division` langsung terhadap division user yang di-trim
  dalam query scope, sedangkan pemeriksaan record menggunakan helper
  normalisasi PHP. Kesetaraan kedua jalur pada MariaDB belum dibuktikan.
- Pembandingan `LOWER()`, `TRIM()`, nilai NULL/kosong, kolasi kolom dan
  parameter, serta perbedaan Unicode/case/accent belum diukur pada MariaDB.
  Secara khusus, collation `utf8mb4_unicode_ci` tercatat pada snapshot schema
  terdahulu, tetapi tidak diasumsikan sebagai konfigurasi server saat ini.
- Daftar arsip, query laporan, query ekspor CSV arsip, riwayat, QR berbasis
  daftar/box, daftar retensi, dan discovery duplikasi menggunakan
  `ArchiveAccess::scope()` pada query arsip yang terkait. Detail arsip,
  lampiran, QR lookup/label, keputusan duplikasi, aksi retensi dan berita acara
  melakukan pemeriksaan record dengan `ArchiveAccess::canView()`.
- Report/print/CSV/XLSX/PDF memakai `ReportController::reportQuery()`.
  `ImportExportController::archiveExportQuery()` memakai scope yang sama.
  Riwayat menggunakan `LEFT JOIN` ke arsip lalu scope; untuk Super Admin,
  event tanpa arsip tetap dapat terlihat. Role lain yang kelak memiliki
  permission riwayat memerlukan penilaian eksplisit karena event tanpa arsip
  tidak dapat dibatasi ke division.
- Permission tidak diubah. Kepala Divisi tidak diberi grant laporan, ekspor,
  atau riwayat; Admin/Pegawai juga tetap tanpa grant. Tes query langsung di
  SQLite sebelumnya tidak mengaktifkan permission route baru.

### Verifikasi duplikasi

- `GET /verifikasi-duplikasi` hanya mengambil kandidat yang sudah ada dan
  tidak memanggil service discovery. Perilaku read-only dibuktikan sebelumnya
  oleh tes SQLite, bukan MariaDB.
- `POST /verifikasi-duplikasi/discovery` memeriksa `DUPLICATES_REVIEW`;
  discovery mem-scope kedua sisi pasangan, mengecualikan pasangan yang telah
  tercatat, mengurutkan berdasarkan kedua ID, dan membatasi query ke 500
  pasangan. Insert menggunakan transaksi dan `insertOrIgnore`.
- Keputusan kandidat memakai transaksi, mengunci row kandidat, memastikan
  status masih `Menunggu Verifikasi`, lalu memeriksa akses pada kedua arsip.
- Dokumentasi schema sebelumnya menyatakan unique key
  `(archive_id, candidate_id)`, tetapi constraint pada database uji belum
  diperiksa atau diuji. Perilaku MariaDB `INSERT IGNORE`, transaksi,
  constraint, trigger, dan dua proses bersamaan belum diverifikasi.
- Service discovery mencatat dan melempar `QueryException`; tes rollback dan
  logging tersedia untuk SQLite. Kegagalan tengah batch dan rollback InnoDB
  belum dites.
- Metadata kandidat lama tidak diperbarui. Kandidat stale tetap ada dan tetap
  dikecualikan; kebijakan refresh/penutupan kandidat masih tertunda.

### Laporan, ekspor, riwayat, dan retensi

- Scope report/export/history ada pada query masing-masing dan telah diuji
  sebelumnya di SQLite. Tidak ada hasil MariaDB untuk memastikan perbedaan
  SQL/collation.
- Permission `HISTORY_VIEW`, `REPORTS_VIEW`, dan `IMPORTS_MANAGE` tetap tidak
  diberikan kepada Kepala Divisi; tidak ada grant tambahan untuk role lain.
- Retensi menggunakan `ArchiveAccess::scope()` untuk daftar arsip dan usulan,
  serta `canView()` untuk pengusulan, review, pelaksanaan dan berita acara.
  Kepala Divisi tetap ditolak untuk arsip tanpa division dan lintas divisi
  oleh aturan helper saat ini.
- Review mengunci usulan dan hanya menerima status `Menunggu Persetujuan`;
  menolak self-review. Pelaksanaan mengunci usulan dan arsip, hanya menerima
  `Disetujui`, dan menolak self-execution. Transisi dan audit ditulis dalam
  transaksi. Reviewer yang sama dapat menjadi pelaksana saat ini; kebijakan
  ini belum diputuskan.
- Skema yang terdokumentasi memakai InnoDB untuk tabel tersebut. `FOR UPDATE`,
  transaksi, dan rollback di InnoDB belum diuji karena database uji tidak
  tersedia. Workflow retensi tidak diubah.

## 3. Hasil skenario validasi MariaDB

Status “Belum dijalankan” berarti tidak ada tes atau query MariaDB. Perilaku
yang diperkirakan dari kode tidak dihitung sebagai hasil lulus.

| Area | Skenario yang diminta | Hasil Tahap 5I |
|---|---|---|
| Isolasi DB | Identifikasi versi MariaDB dan schema database uji terpisah | **Belum dijalankan** — DB uji tidak terkonfirmasi; versi server tidak diketahui |
| Scope helper | Capitalization sama/berbeda, spasi awal/akhir, division user NULL/kosong | **Belum dijalankan** pada MariaDB; hasil kode yang diharapkan dijelaskan di atas |
| Scope arsip | Divisi sendiri/lain, arsip tanpa division, klasifikasi Publik/Internal/Internal Divisi/Rahasia | **Belum dijalankan** pada MariaDB |
| Endpoint scope | Daftar, detail, laporan, cetak, export, history, attachment, QR, retensi | **Belum dijalankan** pada MariaDB |
| GET duplikasi | Tidak melakukan insert/update | **Belum dijalankan** pada MariaDB; tes SQLite terdahulu tidak menggantikan tes ini |
| Discovery | Otorisasi, scope kedua arsip, batch 500, urutan, kelanjutan | **Belum dijalankan** pada MariaDB |
| Idempotensi | Pengulangan tidak menghasilkan pasangan ganda; unique key | **Belum dijalankan**; constraint terdokumentasi tidak diverifikasi pada database uji |
| Metadata lama | Kandidat lama tidak berubah setelah discovery | **Belum dijalankan** pada MariaDB |
| Error/rollback | Error tengah batch rollback seluruh transaksi | **Belum dijalankan** pada MariaDB |
| Concurrency discovery | Dua proses discovery bersamaan | **Belum dijalankan**; tidak ada lingkungan uji terisolasi |
| Report/export/history | Hasil dalam scope saja dan event tanpa arsip sesuai permission | **Belum dijalankan** pada MariaDB |
| Retensi | Scope, self-review, self-execution, status, transaksi dan rollback | **Belum dijalankan** pada MariaDB |

### Tes yang dijalankan, gagal, dilewati

- Tes MariaDB: **0 dijalankan, 0 lulus, 0 gagal, 0 dilewati oleh test runner**.
  Suite tidak dimulai karena konfigurasi aktif menunjuk database produksi.
- Tes SQLite: **tidak dijalankan pada Tahap 5I**. Hasil terdahulu Tahap 5F
  (9 passed/40 assertions dan 65 passed/483 assertions), Tahap 5G
  (7 passed/68 assertions dan 69 passed/519 assertions), serta Tahap 5H
  (2 passed/33 assertions) tetap merupakan bukti historis SQLite saja.
- Perbedaan SQLite–MariaDB aktual yang ditemukan saat eksekusi: **tidak ada
  yang dapat dilaporkan**, sebab tidak ada eksekusi MariaDB. Potensi perbedaan
  tetap terbuka, khususnya collation string, `LOWER/TRIM`, semantik unique
  constraint/`INSERT IGNORE`, lock, transaksi, engine dan concurrency.

## 4. Prasyarat penyiapan uji aman

Jangan mengubah `.env` atau mengandalkan konfigurasi lokal yang sekarang.
Sebelum pengujian dapat dilakukan:

1. DBA/pemilik lingkungan menyediakan MariaDB test terisolasi yang memang
   disposable dan bukan `arsip_bakti`, dengan nama/schema/server yang dapat
   diverifikasi tanpa memakai kredensial produksi.
2. Konfirmasi secara independen nama database, server/version, engine dan
   collation. Hentikan bila target tak dapat dibedakan secara meyakinkan dari
   database produksi atau berisi data penting.
3. Tentukan sumber schema uji yang telah ditinjau. Jangan jalankan migration
   aplikasi atau dump lama; baseline aplikasi tidak mendefinisikan schema
   existing secara aman dan dump lama diketahui berisi DDL destruktif/tidak
   lengkap. Gunakan schema uji sintetis yang disediakan secara eksplisit untuk
   kebutuhan test tanpa data personal/produksi.
4. Sediakan mekanisme konfigurasi test yang tidak mengubah `.env`, misalnya
   environment proses khusus dan perintah yang secara eksplisit memakai
   koneksi test. Sebelum test, lakukan guard yang menolak nama database
   `arsip_bakti`; jangan log nilai password/token.
5. Jalankan test subset scope/discovery/report/export/history/retention
   terhadap data fixture sintetis. Uji transaksi/rollback dan concurrency
   secara terpisah; rekam versi MariaDB dan jumlah kasus/assertion.
6. Pertahankan suite SQLite sebagai lapisan tambahan. Tes yang menghasilkan
   tulis hanya boleh menuju database test yang sudah diverifikasi, tidak
   menuju server/database produksi.

Tidak ada database, schema, user, atau file konfigurasi uji yang dibuat pada
tahap ini karena belum ada target test yang disediakan/diotorisasi.

## 5. Risiko terbuka dan pekerjaan sebelum Tahap 6

1. Sediakan dan verifikasi database MariaDB test terisolasi; sampai itu
   tersedia, Tahap 5I belum tervalidasi dan Tahap 6 belum siap.
2. Uji `LOWER(TRIM())`, perbandingan legacy division, collation/case/accent,
   NULL/kosong, dan konsistensi query-scope versus pemeriksaan record.
3. Verifikasi schema uji memiliki constraint unique pasangan duplikasi yang
   benar; uji batch, retry, rollback dan dua proses konkuren. Hindari
   menyentuh kandidat produksi.
4. Verifikasi `LEFT JOIN` riwayat + scope, seluruh format ekspor, akses file
   langsung, QR dan endpoint retensi dengan URL/ID langsung.
5. Uji `SELECT ... FOR UPDATE`, transaksi dan rollback retensi pada InnoDB;
   tambahkan tes self-review eksplisit di database test. Jangan ubah kebijakan
   reviewer/pelaksana sebelum keputusan bisnis.
6. Selesaikan keputusan tertunda dalam audit Tahap 5H: pemisahan reviewer dan
   pelaksana, approval formal terpisah, pembatalan/expiry, pengajuan ulang,
   identitas pelaksana, audit trail/notifikasi dan scope grant role baru.
7. Verifikasi baseline schema dan prosedur adopsi migration pada salinan
   terisolasi sebelum merancang/menjalankan migration role. Jangan menjalankan
   migration produksi.
8. Tinjau dokumentasi status repository: folder workspace saat ini bukan Git
   repository, sehingga perubahan lokal tidak dapat dibuktikan melalui `git
   status`. Hindari menimpa atau menghapus file lokal.

Kesimpulan: **belum siap untuk Tahap 6**. Pekerjaan Tahap 5I berhenti pada
validasi prasyarat, tinjauan kode, dan dokumentasi. Tidak ada klaim bahwa
integrasi atau kompatibilitas MariaDB berhasil.
