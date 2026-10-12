# Audit Implementasi Tahap 5E — Scope Divisi, Retensi, dan Duplikasi

Status: audit kode dan dokumentasi saja. Tidak ada kode aplikasi, database,
akun, permission, konfigurasi, atau kandidat duplikasi yang diubah. Tidak ada
migration atau endpoint discovery yang dijalankan.

## Lingkup dan dasar bukti

Audit membaca route web, permission helper, `ArchiveAccess`, controller,
reminder dispatcher, tes fitur, serta dokumentasi skema dan role yang ada.
Dokumentasi terkait:

- [`role-permission-decision-addendum.md`](./role-permission-decision-addendum.md)
- [`role-permission-migration-plan.md`](./role-permission-migration-plan.md)
- [`role-permission-audit.md`](./role-permission-audit.md)
- [`database-existing-schema.md`](./database-existing-schema.md)

Tidak ada policy Laravel atau service khusus scope divisi yang ditemukan pada
jalur ini; pemeriksaan berada terutama di `RolePermissions`,
`ArchiveAccess`, dan controller. `routes/web.php` menempatkan endpoint bisnis
di middleware `auth` dan `active`; otorisasi granular tetap dilakukan oleh
controller. Tidak ditemukan route API terpisah untuk alur yang diaudit.

Fakta data divisi mengacu pada snapshot SELECT/metadata Tahap 5D di MariaDB
10.4.32: `users.division` dan `arsip.division` adalah `varchar(150) NULL`;
ketiga baris `users` dan kelima baris `arsip` saat itu memiliki division
`NULL`. Snapshot bisa berubah dan tidak digunakan sebagai klaim untuk
environment lain. Tes yang disebutkan adalah tes SQLite in-memory, bukan bukti
kolasi atau perilaku concurrent MariaDB.

## A. Audit scope divisi

### Aturan helper aktual

- `RolePermissions::matchesDivision()` memangkas whitespace dan melakukan
  lowercase Unicode untuk pengecekan record.
- `RolePermissions::scopeToDivision()` menolak Kepala Divisi yang tidak punya
  nilai division, membandingkan `LOWER(TRIM(arsip.division))`, dan
  mengecualikan `Dokumen Rahasia`.
- `ArchiveAccess::canView()` memeriksa division Kepala Divisi sebelum
  memeriksa classification. Akibatnya record tanpa division tidak dapat
  dilihat Kepala Divisi, termasuk record `Publik` atau `Internal`; record pada
  division lain juga ditolak.
- Untuk role legacy non-Kepala Divisi, query scope arsip
  `Internal Divisi` memakai perbandingan kolom langsung terhadap division
  user yang di-trim, sedangkan record check memakai lowercase dan trim pada
  kedua sisi. Keduanya bukan implementasi normalisasi yang sama; hasil bisa
  berbeda pada nilai dengan whitespace/case tertentu dan perlu dites pada
  collation database yang dipakai.
- Seluruh aturan memakai string, bukan foreign key atau daftar alias
  kanonis. Form arsip mengambil pilihan dari nilai division pengguna;
  import menerima nilai division dari CSV; review Customer dapat membentuk
  arsip Internal Divisi dengan nilai division yang dipilih/diinput.

### Matriks endpoint dan query

| Endpoint / operasi | Role yang saat ini dapat mencapai handler | Scope/pemeriksaan aktual | Kondisi penolakan | Temuan dan rekomendasi |
|---|---|---|---|---|
| `GET /arsip` — daftar, filter, pencarian, pagination | Role dengan `ARCHIVES_LIST`; termasuk Kepala Divisi dan role customer lama | `ArchiveAccess::scope()` diterapkan sebelum filter pencarian/status/lokasi. Query pencarian division tidak melewati scope dasar. | 403 tanpa izin daftar; record di luar hasil scope tidak ditampilkan. | Scope berlaku pada query. Tambahkan tes pencarian, status, lokasi, dan variasi division untuk memastikan kombinasi filter tidak memperlebar hasil. |
| `GET /arsip/{id}` — detail dan data relasi | Handler dibuka di grup auth; akses record dikendalikan `ArchiveAccess::canView()` | Lookup berdasarkan ID diikuti pemeriksaan classification/division; setelah lolos, history, lampiran, dan peminjaman terkait ikut dimuat. | 403 untuk record di luar akses; 404 bila record tidak ada. | Pemeriksaan record backend ada. Tes ID langsung untuk arsip beda division, NULL, rahasia, dan file/history terkait tetap diperlukan. |
| `GET /arsip/{id}/edit`, `PUT/PATCH /arsip/{id}` | `ARCHIVES_MANAGE`; saat ini tidak diberikan kepada Kepala Divisi | Edit dan update memeriksa akses record lama. Data target divisi/classification divalidasi lalu ditulis; pengguna tanpa izin pengelolaan rahasia dipaksa memakai division akun untuk Internal Divisi. | 403 bila tidak memiliki manage atau record lama tak dapat dilihat. | Tidak ada grant manage bagi Kepala Divisi sekarang. Jika grant dipertimbangkan, tambahkan aturan bahwa hasil akhir tetap dalam scope; uji perubahan classification/division dan akses sesudah update, bukan hanya akses record lama. |
| `GET /arsip/files/{file}` — unduh lampiran | Handler auth; record permission diterapkan | File di-join ke arsip lalu `canView()` dijalankan sebelum file disajikan. | 403/404 sebelum download jika record tak dapat dilihat/file tak valid atau tak ada. | Pemeriksaan backend teridentifikasi; perlu tes direct file ID lintas division dan untuk arsip tanpa division. |
| `GET /scan/cari` — lookup QR/nomor/id arsip | `QR_SCAN` | Cari record tanpa scope di query, tetapi selalu cek `canView()` sebelum mencatat scan dan mengarahkan ke detail. | 403 bila record ditemukan tetapi tidak dapat dilihat; 404 bila tidak ada. | Record guard ada; uji kode QR dan nomor arsip lintas division, NULL, serta rahasia. |
| `GET /qr/cetak` — daftar/cetak label arsip | `QR_MANAGE` | Daftar arsip memakai `ArchiveAccess::scope()`. | 403 tanpa izin; item di luar scope tak masuk hasil. | Scope list ada. Tidak ada permission QR_MANAGE untuk Kepala Divisi saat ini; bila diberikan, pertahankan tes cetak seluruh hasil/pagination. |
| `GET /qr/arsip/{id}` — label arsip | `QR_MANAGE` | Lookup ID, kemudian `canView()`. | 403 untuk record di luar scope. | Guard record ada; uji direct URL. |
| `GET /box/{location}` dan `/box/{location}/label` | `QR_MANAGE` | Daftar dan hitungan arsip memakai scope; lookup metadata lokasi sendiri dilakukan tanpa division scope. | 403 tanpa permission; arsip yang tidak cocok tak ditampilkan/dihitung. | Jika akses QR diberikan ke Kepala Divisi, putuskan apakah nama/lokasi box global boleh terlihat melalui ID langsung. |
| `GET /dashboard` — agregat dan arsip terkini | `DASHBOARD_VIEW`; Kepala Divisi tidak memiliki grant ini sekarang | Total/status/bulanan/kategori/recent archives dan statistik loan bergabung ke query dengan `ArchiveAccess::scope()`. | Tanpa izin dialihkan ke daftar arsip. | Query yang diperiksa menggunakan scope. Tambahkan tes agregat yang membuktikan angka/arsip beda division tidak ikut sebelum grant dashboard diberikan. |
| `GET /persetujuan/peminjaman`, `PATCH /persetujuan/peminjaman/{id}` | `LOANS_APPROVE`; Kepala Divisi memilikinya | Daftar mem-scope arsip; aksi memeriksa pemohon bukan dirinya, status pending, lalu `canView()` pada arsip sebelum keputusan. | 403 lintas division/self-decision; 409 status berubah; 404 record/arsip hilang. | Tes tersedia untuk daftar dan aksi lintas division serta keputusan valid. Tes tersebut belum membuktikan laporan, lampiran, atau semua variasi division. |
| `GET /retensi`, `POST /retensi/usulan`, `PATCH .../review`, `PATCH .../selesai`, `GET .../berita-acara` | View/propose/review/schedule sesuai permission; Kepala Divisi punya view/review | Arsip dan disposisi mem-scope `ArchiveAccess`; setiap aksi dan berita acara melakukan `canView()` pada arsip. Jadwal retensi dan daftar kategori dibaca global per kategori. | 403 untuk permission/scope; status salah menghasilkan 409; aturan input/jatuh tempo menghasilkan 422. | Record arsip dibatasi, tetapi metadata jadwal/kategori global bukan data per division. Pemilik bisnis perlu memutuskan apakah metadata global memang boleh terlihat Kepala Divisi. |
| `GET /verifikasi-duplikasi`, `PATCH /verifikasi-duplikasi/{id}` | `DUPLICATES_REVIEW`; Super Admin dan Verifikator legacy memilikinya | Discovery dan list membatasi kedua arsip dengan scope; keputusan memeriksa `canView()` untuk dua record. | 403 tanpa permission atau pasangan di luar akses; 409 jika pasangan tak lagi menunggu. | Scope kedua sisi ada. GET memicu write; detailnya pada bagian C. |
| `GET /laporan`, `/laporan/cetak`, `/laporan/ekspor` | `REPORTS_VIEW`; hanya Super Admin memperoleh permission ini saat ini | Query report menerapkan filter tanggal/status/kategori/lokasi, tetapi tidak memanggil `ArchiveAccess::scope()` dan tidak memfilter classification/division. | 403 role tanpa REPORTS_VIEW. | Bukan celah Kepala Divisi yang dapat dieksploitasi dengan permission aktual sekarang. Ini blocker sebelum grant laporan ke Kepala Divisi/Admin: seluruh arsip, termasuk classification lain, akan mengikuti filter saja. Terapkan scope eksplisit pada setiap format dan uji URL langsung/ekspor. |
| `GET /data/import-export/ekspor/{type}` | `IMPORTS_MANAGE`; saat ini hanya Super Admin | Ekspor kategori/lokasi global; ekspor arsip query tidak memakai `ArchiveAccess`. Dapat mencakup field classification dan division. | 403 tanpa permission. | Tidak terbatas per division. Jangan grant kepada role division-scoped sebelum scope ekspor didefinisikan. |
| `GET /riwayat` — activity history | `HISTORY_VIEW`; saat ini hanya Super Admin | Query membaca riwayat global, tidak join ke arsip untuk division scope. | 403 tanpa permission. | Jika role lain mendapat history permission, tentukan scope melalui arsip dan perlakuan terhadap audit yang `arsip_id`-nya NULL. |
| `GET /verifikasi-customer` dan PATCH review | `CUSTOMER_REVIEW`; saat ini hanya Super Admin | Daftar submission/access request global. Submission dapat membentuk arsip dengan division yang ditentukan reviewer; bukan filter Kepala Divisi. | 403 tanpa permission; self-review ditolak di backend. | Admin belum dapat review. Sebelum grant, putuskan assignment division dan larangan reviewer mengubah classification/division melewati cakupan yang disetujui. |

### Arsip tanpa division dan variasi data

1. Kode Kepala Divisi menolak pengguna yang division-nya kosong dan semua
   arsip yang division-nya kosong. Tidak terlihat fallback yang mengizinkan
   akses kepada arsip orphan.
2. Aturan itu juga menolak arsip Publik/Internal yang division-nya NULL bagi
   Kepala Divisi. Apakah arsip publik seharusnya dapat dilihat lintas division
   belum diputuskan; jangan mengubah aturan ini sebelum kebijakan eksplisit.
3. Data aktual yang dilaporkan pada snapshot Tahap 5D seluruhnya NULL pada dua
   kolom. Karena belum ada sample non-NULL di snapshot tersebut, konsistensi
   pemetaan akun-arsip dan distribusi alias belum terbukti.
4. Karena division bebas teks, perubahan ejaan/alias akun bisa memutus
   kesesuaian dengan division arsip. Import dan Customer review adalah sumber
   tambahan nilai arsip selain form arsip.
5. Prioritas sebelum migration/grant: inventaris per environment, daftar
   kanonis/alias yang disetujui, staging mapping/orphan, lalu tes query dan
   direct record/download/action pada kolasi MariaDB aktual.

## B. Audit alur retensi

### Endpoint, tahap, dan role aktual

| Route/tahap | Permission aktual di controller | Role yang diizinkan oleh matriks kode sekarang | Backend/status/access guard |
|---|---|---|---|
| `GET /retensi` — daftar | `RETENTION_VIEW` | Super Admin (melalui grant global), Petugas Gudang, Admin Unit/Petugas Arsip, Pimpinan/Approver, Kepala Divisi | Arsip dan disposisi dipasangkan dengan `ArchiveAccess::scope()`. Jadwal dan kategori global. Jika peminta memiliki `RETENTION_SCHEDULE`, GET juga memanggil reminder dispatcher yang menulis reminder/notifikasi. |
| `POST /retensi/jadwal` — membuat/memperbarui jadwal | `RETENTION_SCHEDULE` | Super Admin saja | Validasi category/angka/aksi final dan total bulan minimum; `updateOrInsert` dalam transaksi dan audit. Tidak ada endpoint hapus jadwal. |
| `POST /retensi/usulan` — mengusulkan | `RETENTION_PROPOSE` | Super Admin, Petugas Gudang, Admin Unit/Petugas Arsip | Arsip harus aktif, punya jadwal, tidak dipinjam, dapat dilihat, sudah jatuh tempo, dan belum punya usulan pending/disetujui. Mencatat requester, audit, dan notifikasi Super Admin. |
| `PATCH /retensi/usulan/{id}/review` — setujui/tolak | `RETENTION_REVIEW` | Super Admin, Pimpinan/Approver, Kepala Divisi | Hanya status Menunggu Persetujuan; pengusul tidak boleh memutuskan usulannya sendiri; reviewer harus dapat melihat arsip. Penolakan mewajibkan catatan. Aksi langsung ke URL tetap melewati seluruh guard controller. |
| `PATCH /retensi/usulan/{id}/selesai` — pelaksanaan | `RETENTION_REVIEW` | Super Admin, Pimpinan/Approver, Kepala Divisi | Hanya status Disetujui; pengusul tidak boleh melaksanakan usulannya sendiri; arsip dikunci dan harus dapat dilihat; mencatat berita acara/audit. `Musnahkan` mengubah status arsip menjadi Nonaktif. |
| `GET /retensi/berita-acara/{id}` — baca berita acara | `RETENTION_VIEW` | Sama seperti daftar | Hanya disposition Dilaksanakan; arsip diperiksa dengan `canView()`. |
| Pembatalan usulan | Tidak ditemukan route atau method cancellation | Tidak ada role yang dapat membatalkan melalui route yang diaudit | Tidak ada transisi pembatalan terimplementasi. Perlu keputusan apakah fitur dibutuhkan, siapa berwenang, status yang dapat dibatalkan, dan jejak audit. |

### Self-review dan pemisahan tugas

- Kode review membandingkan `requested_by` dengan ID reviewer dan menolak
  self-review sebelum update.
- Kode pelaksanaan membandingkan `requested_by` dengan ID pelaksana dan
  menolak self-execution oleh pengusul.
- Kedua guard berjalan di controller, bukan hanya pada visibilitas tombol.
  Endpoint PATCH langsung tetap melewati gate permission, validasi status,
  ownership guard, dan pemeriksaan akses arsip.
- Guard pelaksanaan tidak melarang `reviewed_by` yang sama melakukan
  pelaksanaan. Schema disposition mencatat `requested_by` dan `reviewed_by`,
  tetapi tidak memiliki kolom pelaksana tersendiri. Karena itu bukti kode
  menunjukkan requester tidak dapat menjalankan proposal sendiri; kode tidak
  membuktikan segregasi tiga orang atau larangan reviewer menjadi pelaksana.
  Apakah pelaksana harus orang ketiga adalah keputusan bisnis.
- Aksi pembatalan tidak ditemukan, sehingga tidak ada guard/status cancellation
  yang dapat diverifikasi.
- GET halaman retensi dapat menghasilkan reminder/notifikasi untuk role yang
  memiliki izin jadwal. Implementasi juga menyediakan command reminder dan tes
  idempotensi. Ini efek samping baca yang berbeda dari keputusan disposisi dan
  perlu diputuskan apakah tetap di halaman atau hanya pada scheduler.

### Matriks pemisahan tugas untuk disetujui

| Pengusul | Reviewer | Pelaksana | Status bukti/kebijakan |
|---|---|---|---|
| Super Admin / Petugas Gudang / Admin Unit legacy | Role dengan RETENTION_REVIEW yang berbeda dari pengusul | Role dengan RETENTION_REVIEW yang berbeda dari pengusul | Pengusul ≠ reviewer dan pengusul ≠ pelaksana ditegakkan; reviewer ≟ pelaksana belum dilarang. |
| Admin target | Belum diberi grant | Belum diberi grant | Perlu persetujuan siapa yang mengajukan, menyetujui, dan melaksanakan; jangan mengasumsikan Admin mewarisi izin legacy. |
| Pegawai target | Belum diberi grant | Belum diberi grant | Perlu persetujuan eksplisit; default tetap tidak ada akses. |
| Kepala Divisi | Saat ini dapat review hanya melalui scope arsip yang dapat dilihat | Memakai permission review yang sama untuk pelaksanaan | Perlu persetujuan pemilik bisnis tentang scope division, keputusan atas orphan, dan apakah reviewer boleh menjadi pelaksana. |

Keputusan bisnis tertunda: role pengusul/reviewer/pelaksana; syarat dua atau
tiga pihak independen; ruang lingkup Kepala Divisi; visibilitas jadwal global;
fitur pembatalan dan transisi status; tata kelola JRA; penerima notifikasi;
bukti berita acara dan audit. Tidak ada kebijakan baru ditetapkan di sini.

## C. Audit verifikasi duplikasi

### Jalur GET dan operasi database

`GET /verifikasi-duplikasi` memanggil controller `index()`, memeriksa
`DUPLICATES_REVIEW`, lalu menjalankan `discoverCandidates($user)` **sebelum**
query daftar. Discovery:

1. Membentuk pasangan arsip dengan `a.id < b.id` agar satu urutan pasangan
   dihasilkan.
2. Membatasi kedua arsip yang statusnya bukan Nonaktif.
3. Mencari nomor berkas yang sama setelah trim dan menghapus spasi, `/`, `-`
   serta membandingkan uppercase; atau nama uppercase-trim sama dengan kategori
   dan tahun sama.
4. Menerapkan `ArchiveAccess::scope()` kepada kedua sisi.
5. Mengambil paling banyak 500 row menggunakan `LIMIT 500`, tanpa `ORDER BY`
   atau cursor/pagination deterministik.
6. Menjalankan `insertOrIgnore` satu per satu ke `duplicate_reviews`.

Sesudah itu halaman melakukan operasi baca daftar `duplicate_reviews`, join
kedua arsip dan reviewer, scope kedua arsip lagi, mengurutkan pending dahulu
dan terbaru berikutnya, lalu membatasi tampilan sampai 300 row.

Dengan demikian GET index bukan read-only. Audit ini tidak memanggil route,
method discovery, atau query insert dan tidak mengubah kandidat.

### Idempotensi, kandidat lama, dan kegagalan

- `uq_duplicate_pair` unik pada `(archive_id, candidate_id)` dan `insertOrIgnore`
  menjadikan pengulangan pasangan yang sama tidak membuat row duplikat dalam
  operasi biasa.
- Query discovery tidak mengecualikan pasangan yang sudah ada sebelum
  mengambil 500 row. Bila kandidat lama memenuhi urutan hasil, kandidat itu
  dapat terus memakai slot batch dan kandidat baru setelah batas dapat terus
  tertunda pada pemanggilan berikutnya. Karena urutan tidak eksplisit, subset
  yang terpilih tidak dijamin stabil.
- Ketika pasangan lama sudah ada, `insertOrIgnore` tidak memperbarui
  `match_reason`, status, reviewer, atau waktu. Perubahan source archive tidak
  menyegarkan metadata dan pasangan yang tidak lagi cocok tidak ditutup/dihapus.
- Batas 500 bukan paging; query berikutnya tidak memiliki offset/cursor.
  Tampilan 300 juga menyembunyikan row di luar limit tanpa kontrol halaman.
- Discovery tidak dibungkus dalam satu transaksi batch eksplisit. Unique key
  membantu retry/concurrency untuk pasangan sama, tetapi error di tengah loop
  dapat meninggalkan hasil parsial. Perilaku detail `INSERT IGNORE` dan error
  harus diuji pada MariaDB aktual, bukan diasumsikan dari SQLite.
- Tidak ditemukan logging/progress terstruktur untuk jumlah ditemukan,
  dimasukkan, dilewati, atau error discovery.
- Aksi keputusan berbeda dari discovery: PATCH memeriksa permission,
  mengunci review row, menerima hanya status Menunggu Verifikasi, memvalidasi
  catatan saat Duplikat, memastikan kedua arsip dapat dilihat, lalu menulis
  keputusan dan audit. Tidak ada operasi auto-delete terhadap arsip yang
  dibuktikan oleh jalur review ini.

### Rancangan pemisahan (belum diimplementasikan)

1. Jadikan GET index query read-only; tambahkan pagination daftar dan jangan
   menjalankan insert atau perubahan status saat memuat halaman.
2. Pindahkan pembentukan kandidat ke aksi eksplisit yang diotorisasi dengan
   permission fungsional khusus. Pertahankan Verifikator legacy sampai
   pengganti diuji; jangan memberi grant otomatis kepada Admin/Pegawai.
3. Gunakan job/batch deterministik dengan keyset cursor dan urutan pasangan
   stabil. Exclude existing pending/decided pairs dari pencarian kandidat
   baru, atau gunakan strategi resume yang setara sehingga row lama tidak
   menghabiskan batch baru.
4. Pertahankan unique pair sebagai lapisan idempotensi; tetapkan aturan
   terpisah untuk refresh match reason, source yang berubah, keputusan lama,
   dan kandidat yang stale. Jangan menimpa keputusan reviewer secara otomatis.
5. Catat batch ID, actor/initiator, rentang/cursor, jumlah kandidat, inserted,
   skipped, error dan waktu; log error tanpa menyembunyikannya di fallback
   sukses. Tentukan retry untuk batch parsial dan concurrency/locking.
6. Tampilkan status pekerjaan/progress dengan pagination, bukan menunggu GET
   melakukan kerja berat.

## D. Rencana pengujian regresi

| Area | Kasus yang perlu diuji | Bukti yang diminta |
|---|---|---|
| Division query | Sama persis; beda uppercase/lowercase; spasi awal/akhir; alias disetujui; NULL/kosong; pengguna tanpa division | Hasil list, detail, direct URL dan query filter konsisten dengan kebijakan yang disahkan. |
| Arsip tanpa division | Publik/Internal/Internal Divisi, tiap role target | Tolak atau izinkan sesuai keputusan eksplisit; tidak ada fallback yang melewati aturan orphan. |
| Arsip lintas division | Daftar, pencarian, lokasi, status, pagination, detail, update, lampiran/file ID, QR lookup, label, box, loan approval, duplikasi, retensi | Record/aksi lintas division ditolak tanpa mutasi atau kebocoran data. |
| Dashboard | Setiap agregat/statistik termasuk peminjaman dan kategori | Hitungan/recent item tidak mengandung arsip di luar scope. |
| Laporan/ekspor/riwayat | Index, print, CSV, XLSX, PDF, arsip export, activity history termasuk direct URL | Sebelum grant division-scoped: backend scope berlaku pada semua format, termasuk record rahasia/orphan dan audit dengan arsip_id NULL. |
| Import | Preview/commit Internal Divisi dengan alias/whitespace, data division kosong, role di luar scope | Validasi assignment division dan otorisasi teruji pada preview dan commit; tidak ada perpindahan divisi tanpa izin. |
| Retensi view | List, jadwal global, kategori, berita acara, direct ID, arsip beda division/orphan | Scope arsip dan kebijakan visibilitas metadata global konsisten. |
| Retensi pengusulan | Jatuh tempo/tidak, status dipinjam/nonaktif, jadwal hilang, pending/disetujui yang sudah ada, lintas division | Pengusulan hanya pada kasus yang diizinkan; kasus ditolak tidak membuat disposition/notification/audit palsu. |
| Retensi review | Pengusul sendiri, role tanpa review, division lain/orphan, status bukan pending, setujui dan tolak dengan/tanpa catatan | Self-review/out-of-scope/invalid-state ditolak tanpa mutasi; path valid mencatat reviewer/audit. |
| Retensi pelaksanaan | Pengusul sendiri, reviewer yang sama, actor berbeda, status belum disetujui, division lain/orphan, aksi Musnahkan/Permanen/ANRI | Verifikasi requester self-execution; uji/putusan reviewer-as-executor setelah kebijakan bisnis ditetapkan; arsip hanya berubah pada status/action yang sah. |
| Retensi cancellation | Jika disetujui untuk dibangun: role, status awal, alasan, notification, audit, race dengan review/complete | Tidak ada implementasi/test sebelum state machine dan kewenangan disetujui. |
| Reminder | GET retensi, command terjadwal, retry, concurrent dispatch | Pastikan pilihan trigger dan idempotensi reminder/notifikasi pada MariaDB. |
| Duplikasi GET | Ambil baseline jumlah rows; GET berulang setelah refactor | Jumlah dan isi `duplicate_reviews` tidak berubah oleh GET. |
| Discovery | >500 pasangan, existing pair di awal batch, retry, concurrency, error tengah batch, perubahan data dan keputusan lama | Kandidat baru dapat dijangkau deterministik; no duplicates; tidak menimpa status/reviewer; error/progress tercatat; hasil dapat dilanjutkan. |
| Verifikasi duplikasi | Verifikator/role tanpa izin, dua record lintas division, status sudah diputuskan, catatan wajib | Unauthorized dan out-of-scope ditolak; keputusan valid tercatat untuk kedua arsip sesuai kebijakan. |
| Database | Query division collation dan duplicate insert/retry/error | Jalankan integrasi pada salinan MariaDB; SQLite bukan bukti kompatibilitas MariaDB. |

## E. Prioritas perbaikan dan prasyarat

### Perbaikan teknis sebelum grant/migration role

1. **P0 — Matriks permission versus scope:** jangan memberi `REPORTS_VIEW`,
   `IMPORTS_MANAGE`, `HISTORY_VIEW`, `QR_MANAGE`, atau permission tulis arsip
   kepada Kepala Divisi sebelum query/record scope masing-masing disepakati
   dan diuji. Laporan dan export yang ada tidak menerapkan `ArchiveAccess`.
2. **P0 — Kebijakan data orphan:** putuskan apakah Public/Internal tanpa
   division boleh dibaca Kepala Divisi. Perilaku sekarang fail-closed dan
   menolak, tetapi keputusan target perlu jelas.
3. **P1 — Konsistensi division:** inventaris nilai dari semua sumber, setujui
   alias/kanonis, dan tetapkan tes serta migration plan relasi terpisah.
4. **P1 — Retensi segregation:** sepakati reviewer-versus-executor, cancellation,
   cakupan jadwal global, dan reminder GET; baru setelah itu desain perubahan.
5. **P1 — Duplikasi side effect:** jadikan GET read-only dan discovery eksplisit
   dengan pagination/cursor deterministik, logging, dan kebijakan stale.
6. Uji seluruh perubahan pada database tes; validasi MariaDB hanya pada salinan
   terisolasi yang dipastikan targetnya. Tahap ini tidak menyiapkan atau
   menjalankan migration.

### Keputusan bisnis yang tertunda

- Apakah Kepala Divisi dapat membaca arsip Publik/Internal tanpa division atau
  hanya arsip yang secara eksplisit terikat ke division-nya.
- Apakah daftar jadwal retensi/kategori yang global boleh dibaca seluruh
  Kepala Divisi dan apakah reminder hanya dijalankan scheduler.
- Apakah reviewer boleh menjadi pelaksana sesudah menyetujui, atau harus ada
  tiga pihak berbeda.
- Apakah pembatalan usulan dibutuhkan; siapa pelaku yang sah dan pada state
  apa pembatalan diperbolehkan.
- Siapa yang berhak menerima grant report/export/history/import/QR/arsip
  untuk Admin dan Pegawai; cakupan division serta data rahasia yang boleh
  terlihat.
- Daftar division kanonis dan alias; penanganan arsip/user tanpa division.
- Kebijakan kandidat duplikasi stale, refresh alasan, keputusan yang sudah
  dibuat, batching dan frekuensi discovery.

## Validasi dan batas kesimpulan

Tes terarah Tahap 5D sebelumnya lulus:

```text
php artisan test tests\Feature\ArchiveManagementTest.php --filter="head_of_division|internal_division_archive|retention_schedule_reminders|duplicate_candidates"
4 passed (58 assertions)
```

Tes tersebut mencakup list/detail Kepala Divisi dan approval pinjaman lintas
division, validasi dasar retensi/self-process, reminder, dan pembuatan serta
keputusan kandidat duplikasi. Tes menggunakan SQLite in-memory. Tes itu tidak
membuktikan seluruh endpoint pada tabel di atas, perilaku MariaDB, laporan
division-scoped, candidate starvation >500, review-versus-execution
segregation, atau pembacaan GET tanpa tulis. Tidak ada tes dijalankan ulang
karena Tahap 5E hanya menambahkan dokumentasi.

Tahap 5E berhenti sebelum perubahan implementasi, migration, grant permission,
atau perubahan data/database. Temuan yang merupakan risiko jika grant masa
depan diberikan dibedakan dari akses aktual: `REPORTS_VIEW`, impor/ekspor,
history, QR management, dan archive management saat ini tidak diberikan
kepada Kepala Divisi; query unscoped pada modul tersebut menjadi blocker
sebelum grant semacam itu, bukan bukti bahwa Kepala Divisi sekarang dapat
memanggilnya.

### Catatan status setelah Tahap 5G

Temuan historis Tahap 5E bahwa GET `/verifikasi-duplikasi` menjalankan discovery
dan menulis kandidat telah ditangani di Tahap 5G: GET kini hanya membaca,
sedangkan discovery dijalankan melalui POST eksplisit yang memeriksa
`DUPLICATES_REVIEW`. Implementasi, hasil tes SQLite, batas bukti MariaDB, dan
keputusan metadata stale dicatat dalam
[`role-permission-stage-5g-duplicate-discovery.md`](./role-permission-stage-5g-duplicate-discovery.md).
