# Tahap 5H — Audit dan Matriks Kewenangan Retensi

Status: audit kode, tes, dan dokumentasi. Tidak ada perubahan kode aplikasi,
database, akun, role, grant, konfigurasi, atau migration pada tahap ini.
Perilaku yang diuraikan adalah kondisi Laravel saat audit, bukan persetujuan
untuk memperluas permission.

## Lingkup dan dasar bukti

Audit menelusuri `routes/web.php`, `RetentionController`,
`RolePermissions`, `ArchiveAccess`, `RetentionReminderDispatcher`, view
retensi, skema yang terdokumentasi, serta tes retensi di
`ArchiveManagementTest`. Tidak ditemukan Laravel Policy atau service workflow
disposisi terpisah. Otorisasi berada pada pemeriksaan `RolePermissions` di
controller; service terpisah hanya digunakan untuk pengingat retensi.

Endpoint bisnis berada dalam grup middleware `auth` dan `active`. Route
parameter usulan berupa ID numerik yang diberikan kepada controller. Handler
mutasi dan berita acara mengambil record dari database berdasarkan ID, lalu
memeriksa permission serta status/akses di backend; kontrol tombol pada view
bukan batas keamanannya.

## A. Implementasi dan alur aktual

### Route dan pemilik kewenangan

| Operasi | Route / handler | Permission yang diperiksa | Role yang diizinkan oleh katalog saat ini |
|---|---|---|---|
| Membaca halaman dan usulan | `GET /retensi`, `RetentionController::index` | `RETENTION_VIEW` | Super Admin, Petugas Gudang, Admin Unit/Petugas Arsip, Pimpinan/Approver, Kepala Divisi |
| Menyimpan jadwal retensi | `POST /retensi/jadwal`, `saveSchedule` | `RETENTION_SCHEDULE` | Super Admin |
| Mengusulkan penyusutan | `POST /retensi/usulan`, `requestDisposition` | `RETENTION_PROPOSE` | Super Admin, Petugas Gudang, Admin Unit/Petugas Arsip |
| Menyetujui atau menolak (review) | `PATCH /retensi/usulan/{id}/review`, `reviewDisposition` | `RETENTION_REVIEW` | Super Admin, Pimpinan/Approver, Kepala Divisi |
| Mencatat pelaksanaan | `PATCH /retensi/usulan/{id}/selesai`, `completeDisposition` | `RETENTION_REVIEW` | Super Admin, Pimpinan/Approver, Kepala Divisi |
| Membaca berita acara | `GET /retensi/berita-acara/{id}`, `minutes` | `RETENTION_VIEW` | Super Admin, Petugas Gudang, Admin Unit/Petugas Arsip, Pimpinan/Approver, Kepala Divisi |
| Mengirim pengingat jatuh tempo | `retention:dispatch-reminders` dan pemanggilan dari index | Tidak ada actor HTTP; dijalankan command/scheduler | Command berjalan dengan konteks aplikasi; `index` memanggil dispatcher hanya bila peminta memiliki `RETENTION_SCHEDULE` (saat ini Super Admin) |

Customer, Pengguna Umum, Verifikator, Admin, dan Pegawai tidak memiliki
permission retensi terkait dalam katalog saat ini. Grant kosong untuk Admin
dan Pegawai tidak diganti pada Tahap 5H.

### Validasi dan operasi database

1. **Membaca daftar.** `index` memeriksa `RETENTION_VIEW`. Query arsip dan
   disposisi memakai `ArchiveAccess::scope`; Kepala Divisi dibatasi pada
   arsip dalam divisinya dan arsip tanpa division/di luar divisinya tidak
   masuk. Jadwal dan kategori merupakan data master global. Daftar usulan
   dibatasi pada 200 teranyar.
2. **Menyimpan jadwal.** `saveSchedule` memeriksa permission, kategori,
   rentang bulan/peringatan, dan aksi final yang diizinkan. Dalam transaksi,
   jadwal per kategori di-`updateOrInsert`; `updated_by`/waktu disimpan dan
   satu entri riwayat dibuat. Hanya Super Admin mendapat permission ini.
3. **Mengusulkan.** `requestDisposition` memeriksa permission dan input, lalu
   dalam transaksi mengunci arsip, memeriksa status aktif, akses record,
   bukan sedang dipinjam, adanya jadwal, dan jatuh tempo. Usulan aktif lain
   dengan status `Menunggu Persetujuan` atau `Disetujui` menolak usulan baru
   dengan 409. Usulan baru menyimpan alasan, tindakan dari jadwal dan
   `requested_by`, lalu menulis riwayat dan mengirim notifikasi ke seluruh
   Super Admin aktif.
4. **Review/keputusan.** `reviewDisposition` menerima hanya `Disetujui` atau
   `Ditolak`, mewajibkan catatan untuk penolakan, mengunci usulan, serta
   menerima hanya status `Menunggu Persetujuan`. Backend menolak pengusul
   memutus usulannya sendiri (403), memastikan arsip masih ada dan dapat
   dilihat reviewer, lalu memperbarui status, catatan, `reviewed_by`, dan
   `reviewed_at` dalam transaksi. Riwayat ditulis dan pengusul diberi
   notifikasi.
5. **Pelaksanaan.** `completeDisposition` memakai permission review yang
   sama; memvalidasi nomor/keterangan berita acara; mengunci usulan dan
   hanya menerima status `Disetujui` (409 jika tidak). Backend menolak
   pengusul melakukan pelaksanaan (403), mengunci arsip dan memastikan
   pelaksana dapat melihatnya. Status menjadi `Dilaksanakan`; berita acara,
   waktu pelaksanaan, dan riwayat disimpan dalam transaksi. Untuk aksi
   `Musnahkan`, arsip menjadi `Nonaktif`; untuk aksi lain, controller tidak
   menonaktifkan arsip.
6. **Berita acara.** Handler hanya mengembalikan usulan dengan status
   `Dilaksanakan`; ID yang tidak ditemukan/tidak pada status itu menjadi
   404. Setelah lookup, akses arsip diperiksa lagi.
7. **Pengingat.** Halaman `index` dapat membuat reminder/notifikasi sebagai
   efek samping ketika actor memiliki `RETENTION_SCHEDULE`. Scheduler juga
   menjalankan command setiap hari pukul 08:00 dengan `withoutOverlapping`.
   Dispatcher memakai unique key reminder dan transaksi untuk mencegah
   pengingat yang sama dibuat berkali-kali. Efek samping GET ini terpisah
   dari status usulan dan tidak mengubah status disposisi.

Skema yang didokumentasikan menyimpan status enum
`Menunggu Persetujuan`, `Disetujui`, `Ditolak`, `Dilaksanakan`, pemohon dan
reviewer, waktu review/pelaksanaan, serta nomor dan catatan berita acara.
Tidak ada kolom pelaksana terpisah: `completed_at` ada, tetapi identitas
pelaksana tidak disimpan secara eksplisit pada row disposisi. Identitas actor
pelaksanaan hanya tampak melalui entri riwayat. Tidak ada migration atau
pemeriksaan database aktif dilakukan pada Tahap 5H.

### Pemisahan tugas: fakta versus keputusan

- **Self-review:** guard backend terbukti berada di `reviewDisposition`:
  `requested_by` tidak boleh sama dengan reviewer. Tes saat ini membuktikan
  keberhasilan alur review oleh actor lain, tetapi tidak menguji langsung
  self-review oleh akun yang juga mempunyai `RETENTION_REVIEW`. Tambahkan tes
  khusus.
- **Self-execution:** guard backend terbukti berada di
  `completeDisposition`: pemohon tidak boleh menyelesaikan usulannya. Tes
  terarah memverifikasi request usulan oleh Super Admin ditolak saat ia
  mencoba melaksanakan sendiri dan data tetap belum berubah.
- **Reviewer menjadi pelaksana:** diperbolehkan oleh kode saat ini. Tidak ada
  guard yang membandingkan `reviewed_by` dengan actor pelaksana dan
  `RETENTION_REVIEW` dipakai untuk kedua operasi. Tes yang ada secara eksplisit
  menggunakan Pimpinan/Approver yang sama untuk menyetujui lalu
  melaksanakan usulan. Ini fakta implementasi, bukan persetujuan bahwa
  pemisahan tersebut sesuai kebijakan bisnis.
- **Pemberi persetujuan terpisah:** tidak ditemukan tahap atau permission
  formal terpisah. Handler `reviewDisposition` sendiri memutuskan
  `Disetujui` atau `Ditolak`; tidak ada status “review selesai menunggu
  persetujuan lain”, endpoint lain, atau actor `RETENTION_APPROVE`.
  `Menunggu Persetujuan` adalah status awal yang diproses handler review,
  sehingga nama status itu saja bukan bukti adanya dua tahap.
- **Pembatalan:** tidak ada route/controller action untuk membatalkan atau
  menghapus usulan. Tidak ada status `Dibatalkan` pada enum `disposition_requests`
  yang terdokumentasi.
- **Ditolak/kedaluwarsa/pernah dilaksanakan:** penolakan bersifat terminal
  untuk row tersebut karena review berikutnya mensyaratkan status menunggu.
  Tidak ada status kedaluwarsa atau proses expiry. Usulan baru dapat dibuat
  setelah `Ditolak` atau `Dilaksanakan`, selama arsip tetap aktif dan
  memenuhi cek retensi; riwayat lama tetap tersimpan. Untuk tindakan selain
  `Musnahkan`, arsip tidak otomatis menjadi nonaktif, sehingga usulan ulang
  setelah pelaksanaan secara teknis mungkin.

### Matriks kewenangan berdasarkan kode

`Ada` berarti handler dan permission tersedia sekarang. `Belum ada` berarti
kode tidak menyediakan operation/permission khusus. `Perlu keputusan` adalah
status kebijakan untuk role target atau pilihan workflow yang tidak dapat
disimpulkan dari kode.

| Tanggung jawab | Super Admin | Petugas Gudang / Admin Unit-Petugas Arsip | Pimpinan/Approver | Kepala Divisi | Admin / Pegawai | Customer / Pengguna Umum / Verifikator |
|---|---|---|---|---|---|---|
| Mengusulkan | **Ada**; dapat mengusulkan, tetapi tidak boleh meninjau atau melaksanakan usulannya sendiri | **Ada** (`RETENTION_PROPOSE`) | Belum ada | Belum ada | Belum ada grant; kebijakan target perlu keputusan | Belum ada |
| Meninjau dan menetapkan Setujui/Tolak | **Ada**, kecuali usulan sendiri; akses global | Belum ada | **Ada**; scope melalui akses arsip | **Ada**; scope divisi pada arsip | Belum ada grant; perlu keputusan role dan scope | Belum ada |
| Persetujuan formal tahap terpisah | Tidak ada tahap/permission terpisah saat ini; apakah perlu perlu keputusan | Belum ada | Sama | Sama | Perlu keputusan | Tidak ada |
| Melaksanakan usulan yang disetujui | **Ada** untuk usulan orang lain; dapat menjadi reviewer juga | Belum ada | **Ada** untuk usulan orang lain; dapat melaksanakan keputusan sendiri | **Ada** untuk usulan orang lain dalam scope; dapat melaksanakan keputusan sendiri | Belum ada grant; perlu keputusan pemisahan tugas dan scope | Tidak ada |
| Mengelola jadwal | **Ada** (`RETENTION_SCHEDULE`) | Belum ada | Belum ada | Belum ada | Belum ada grant; perlu keputusan | Belum ada |
| Mengawasi/membaca usulan dan berita acara | **Ada**, global | Ada untuk daftar dan berita acara sesuai akses arsip | Ada sesuai akses arsip | Ada hanya pada arsip divisinya | Belum ada grant | Belum ada |
| Membatalkan usulan | **Belum ada** endpoint untuk semua role; aktor dan status perlu keputusan | Belum ada | Belum ada | Belum ada | Belum ada | Belum ada |

Tidak ada dasar di implementasi untuk menyatakan reviewer dan pemberi
persetujuan sebagai dua jabatan berbeda. Saat ini satu keputusan `Disetujui`
atau `Ditolak` oleh pemilik `RETENTION_REVIEW` adalah keseluruhan review/
approval yang tersedia.

## B. Keputusan bisnis yang masih memerlukan persetujuan

| Keputusan | Pilihan yang perlu ditetapkan | Dampak dan pertanyaan sebelum implementasi |
|---|---|---|
| Reviewer harus berbeda dari pelaksana? | Tetap boleh actor yang sama seperti sekarang; atau wajib actor berbeda; atau pemisahan hanya untuk tindakan tertentu | Jika wajib berbeda, perlu simpan identitas pelaksana secara eksplisit, guard backend, UI, audit, dan skenario concurrency/regresi. Tentukan apakah Super Admin juga terikat. |
| Perlu persetujuan formal terpisah? | Review sekaligus keputusan seperti sekarang; atau review/rekomendasi diikuti approval actor lain | Tahap kedua memerlukan status baru/state transition, permission tersendiri, notifikasi, daftar kerja, dan pemetaan enum/skema. Jangan menyamakan role tanpa keputusan. |
| Pembatalan usulan? | Tidak menyediakan; pemohon boleh membatalkan hanya saat menunggu; reviewer/Super Admin boleh membatalkan pada kondisi tertentu | Tentukan aktor, state yang boleh dibatalkan, alasan wajib, race dengan keputusan/pelaksanaan, notifikasi, dan retensi audit. Saat ini tidak ada endpoint. |
| Usulan ditolak | Terminal row dan usulan baru seperti sekarang; atau revisi/reopen row yang sama | Reopen akan mengubah histori dan memerlukan aturan catatan/nomor versi. Keputusan harus mempertahankan alasan penolakan. |
| Usulan kedaluwarsa | Tidak ada expiry otomatis; atau expiry setelah durasi/kondisi yang disetujui | Tentukan pemicu, status, notifikasi, apakah pengajuan ulang boleh, dan bagaimana mencegah expiry bersamaan dengan review. Tidak ada expiry saat ini. |
| Usulan pernah dilaksanakan | Pertahankan row dan larang usulan ulang; atau izinkan permohonan baru untuk arsip yang masih aktif | Kode saat ini dapat mengizinkan pengajuan ulang sesudah `Dilaksanakan` bila arsip masih aktif, khususnya aksi selain `Musnahkan`. Konsekuensi retensi dan audit harus diputuskan. |
| Audit trail | Pertahankan event riwayat saat ini; atau wajibkan log transisi terstruktur/versioned | Tentukan actor, waktu, status lama/baru, alasan, perubahan jadwal/action, berita acara dan identitas pelaksana. Saat ini pelaksana hanya dapat dicari lewat riwayat; tidak ada field khusus di row disposisi. |
| Notifikasi | Pemberitahuan pada submit/review seperti sekarang; atau menambah notifikasi pelaksanaan/pembatalan/expiry | Saat ini proposal memberi notifikasi kepada semua Super Admin aktif; review memberi notifikasi ke pemohon; penyelesaian tidak mengirim notifikasi eksplisit ke pemohon. Tentukan penerima dan isi, termasuk kemungkinan pemohon Super Admin mendapat notifikasi proposalnya sendiri. |
| Grant role target | Tidak memberi grant sampai keputusan selesai; atau grant granular per tahap | Admin/Pegawai belum memiliki permission retensi. Tentukan tugas dan batas division sebelum grant; Kepala Divisi tetap terbatas dengan `ArchiveAccess` dan arsip tanpa division ditolak. |
| Efek samping pengingat GET | Pertahankan sementara; atau pindahkan hanya ke scheduler/command | `GET /retensi` oleh pemilik `RETENTION_SCHEDULE` dapat menulis reminder/notifikasi. Pemilik produk perlu menetapkan apakah perilaku ini dikehendaki. Bukan perubahan status usulan. |

Tidak ada keputusan di atas yang diterapkan oleh dokumen ini.

## C. Skenario tes regresi sebelum perubahan workflow/grant

Tes harus memakai database pengujian, tanpa koneksi ke database produksi.
Setiap penolakan perlu memastikan tidak ada perubahan status, arsip,
notifikasi, maupun audit trail yang seolah-olah berhasil.

| Area | Skenario | Ekspektasi |
|---|---|---|
| Otorisasi route | GET index, POST jadwal/usulan, PATCH review/complete, GET berita acara untuk setiap role legacy/target | Hanya role dengan permission saat ini berhasil; role tanpa izin mendapat 403, termasuk direct URL/ID |
| Pengusulan | Arsip jatuh tempo dengan jadwal; tanpa jadwal; belum jatuh tempo; nonaktif; dipinjam; lintas divisi; tanpa divisi; reason invalid | Hanya kasus yang memenuhi syarat membuat row, riwayat, dan notifikasi |
| Pengusulan berulang | Sudah ada status Menunggu Persetujuan atau Disetujui; sebelumnya Ditolak atau Dilaksanakan; retry bersamaan | Status aktif menolak 409; perilaku row lama dan usulan ulang sesuai kebijakan yang disetujui; tidak ada duplicate request race |
| Self-review | Pemohon mengirim Setujui dan Ditolak memakai akun yang juga punya `RETENTION_REVIEW` | 403 dan status/reviewer/audit/notifikasi tidak berubah |
| Review antar pengguna | Reviewer lain menyetujui; menolak dengan catatan; menolak tanpa catatan; status selain menunggu; ID hilang; arsip tidak ada/di luar divisi | Alur valid tersimpan; invalid input 422, missing 404, invalid state 409, akses 403 tanpa mutasi |
| Reviewer/pelaksana sama | Actor yang menetapkan Disetujui mencoba melaksanakan | Hasil harus mengikuti keputusan bisnis; saat ini diizinkan dan sudah tercakup oleh tes existing |
| Self-execution | Pemohon mencoba melaksanakan status Disetujui | 403; row tetap Disetujui, `completed_at` null, arsip tidak berubah |
| Pelaksanaan | Status menunggu/ditolak/dilaksanakan; nomor/keterangan kosong; tindakan Musnahkan/Permanen/Serah ke ANRI | Hanya status Disetujui dapat dilaksanakan; aksi arsip dan berita acara sesuai keputusan, tidak ada eksekusi ganda |
| Scope Kepala Divisi | Daftar, direct review/complete/minutes untuk divisi sendiri, divisi lain, arsip tanpa division dan rahasia | Hanya data divisi yang diizinkan; aksi lain ditolak tanpa mutasi |
| Berita acara | Status bukan Dilaksanakan, ID salah, record lintas division, record yang sah | 404 untuk non-executed/tidak ditemukan; 403 untuk out-of-scope; konten hanya terlihat bila authorized |
| Pembatalan/expiry | Setelah kebijakan disetujui: aktor/state sah, actor/state tak sah, race dengan review atau complete | State machine sesuai kebijakan, audit dan notifikasi tepat; tidak ada endpoint/test sebelum keputusan |
| Audit trail | Tiap transisi submit/review/reject/execute/(cancel/expire jika disetujui) | Actor, waktu, status, alasan dan evidence tersedia sesuai kebijakan; error tidak meninggalkan audit parsial |
| Pengingat | GET index berulang, command scheduler, unique reminder, role dan penerima | Efek samping idempotent dan terpisah dari transisi status; keputusan apakah index boleh memicu write harus eksplisit |

## D. Tes yang dijalankan dan batas klaim

Perintah yang dijalankan:

```text
php artisan test tests\Feature\ArchiveManagementTest.php --filter=retention
2 passed (33 assertions)
```

Tes berjalan memakai SQLite in-memory yang ditetapkan pada `phpunit.xml`.
Tes memvalidasi schedule, pengingat idempotent, alur usulan, larangan
self-execution, persetujuan dan pelaksanaan oleh reviewer yang sama, perubahan
status arsip, berita acara dan audit riwayat. Tes tersebut tidak membuktikan
perilaku MariaDB, concurrency, semua cabang role/scope, self-review denial,
setiap status invalid, atau kebijakan bisnis masa depan. Tidak ada migration,
discovery, atau operasi database produksi yang dijalankan.

## File dokumentasi Tahap 5H

- `docs/role-permission-stage-5h-retention-policy.md` — audit route dan
  operasi database, matriks peran, guard self-action, risiko, keputusan bisnis,
  skenario tes, serta bukti tes yang dijalankan.
- `docs/role-permission-decision-addendum.md` — tautan ke laporan Tahap 5H dan
  status keputusan retensi yang belum disetujui.

Tahap 5H berhenti pada audit dan dokumentasi. Jangan memberikan grant baru,
mengubah workflow, atau membuat/menjalankan migration sebelum pemilik bisnis
menyetujui pemisahan tugas, tahap persetujuan, pembatalan/expiry, pengajuan
ulang, audit trail, notifikasi, dan scope role target.
