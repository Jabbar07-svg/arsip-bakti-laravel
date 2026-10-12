# Audit Role dan Permission

Status: audit dan rancangan saja. Tidak ada perubahan route, controller, middleware,
database, data role, atau migration pada tahap ini.

## Ringkasan

- Aplikasi saat ini mengenali tujuh role legacy. Enum `users.role` belum berisi
  empat role target (`Super Admin`, `Admin`, `Kepala Divisi`, `Pegawai`).
- Pembacaan agregat read-only pada database aktif `arsip_bakti` menemukan akun
  aktif dengan role `Super Admin` (1), `Petugas Gudang` (1), dan `Customer` (1).
  Nilai ini adalah hitungan saat audit, bukan daftar identitas atau jaminan keadaan
  data di kemudian hari. Role legacy lain tetap diizinkan skema/kode walaupun tidak
  muncul dalam hitungan tersebut.
- Route aplikasi yang memerlukan login dikelompokkan dengan middleware `auth` dan
  `active`; pembatasan role umumnya dilakukan kembali di controller/helper, bukan
  middleware role atau Policy terpusat.
- Sebagian menu Blade mengikuti role, tetapi penyembunyian menu bukan kontrol
  keamanan. Controller tetap harus memeriksa akses saat URL atau request dipanggil
  langsung.
- Belum ada dasar yang cukup untuk mengonversi seluruh role legacy ke role target.
  Pemetaan di bawah merupakan kandidat untuk dibahas, bukan keputusan atau
  instruksi perubahan data.

## Cakupan dan sumber audit

Audit membaca route di `routes/web.php`, pemeriksaan akses di controller dan
`app/Support/ArchiveAccess.php`, middleware aktif, menu di
`resources/views/layouts/app.blade.php`, model pengguna, serta tes fitur
`tests/Feature/ArchiveManagementTest.php`. Distribusi role dibaca secara agregat
tanpa menampilkan data pribadi.

Role valid yang tercantum di `UserController` dan skema existing:

1. `Super Admin`
2. `Petugas Gudang`
3. `Admin Unit/Petugas Arsip`
4. `Verifikator`
5. `Pimpinan/Approver`
6. `Customer`
7. `Pengguna Umum`

Daftar tersebut diverifikasi terhadap kode dan dokumentasi skema
[`database-existing-schema.md`](./database-existing-schema.md). `UserController`
juga memakai daftar legacy yang sama untuk validasi pembuatan/perubahan role.

## Pemetaan role legacy ke target

| Role existing | Kandidat target | Status dan alasan | Risiko/keputusan yang dibutuhkan |
|---|---|---|---|
| Super Admin | Super Admin | Paling dekat secara nama dan perilaku: akses administratif global serta pengelolaan pengguna/master data. | Konfirmasi bahwa target Super Admin memang tetap memiliki semua kewenangan global. Pertahankan perlindungan akun Super Admin aktif terakhir. |
| Petugas Gudang | Pegawai | Kandidat, bukan pemetaan final. Dapat mengelola arsip, QR, transaksi peminjaman, dan usulan retensi. | Apakah Pegawai memang petugas operasional arsip? Batas divisi/lokasi dan kewenangan edit arsip lintas divisi perlu ditentukan. |
| Admin Unit/Petugas Arsip | Admin atau Pegawai | Belum dapat diputuskan. Namanya menunjukkan fungsi unit/arsip, tetapi kode menggabungkannya ke kemampuan pengelola operasional, bukan hak admin global. | Tentukan apakah Admin target berarti administrator sistem atau admin arsip per unit. Jangan otomatis memberinya hak pengguna, laporan, impor, kategori, atau lokasi. |
| Verifikator | Tidak dipetakan dahulu | Tidak ada target yang identik. Kode memberi akses review duplikasi, tetapi bukan listing arsip umum dan bukan persetujuan pinjaman/retensi. | Tentukan apakah fungsi verifikator tetap menjadi izin khusus atau digabung ke Admin/Pegawai. Memetakan ke Kepala Divisi akan memperluas hak persetujuan tanpa bukti. |
| Pimpinan/Approver | Kepala Divisi | Kandidat bersyarat. Role ini menyetujui pinjaman dan disposisi retensi serta dapat melihat arsip tertentu. | Kode belum membatasi semua persetujuan berdasarkan divisi. Target Kepala Divisi yang hanya berwenang di divisinya memerlukan keputusan dan pengujian scope sebelum implementasi. |
| Customer | Di luar empat role pegawai | Role portal eksternal dengan pencarian publik, unggah dokumen, permintaan akses/peminjaman, dan riwayat milik sendiri. Tidak setara dengan Pegawai. | Pertahankan sebagai jenis akun/role terpisah atau putuskan model role eksternal; jangan dipaksa menjadi role pegawai. |
| Pengguna Umum | Di luar empat role pegawai | Memiliki akses portal publik serupa Customer, tetapi nama role belum membuktikan bahwa kebijakan bisnisnya sama. | Putuskan apakah tetap terpisah dari Customer atau digabung. Periksa akun/data dan alur pelanggan sebelum migrasi. |

**Catatan skema:** nilai `Admin`, `Kepala Divisi`, dan `Pegawai` tidak tersedia
sebagai role existing pada enum database. Kode validasi juga hanya menerima tujuh
role legacy. Karena itu, pergantian role tidak dapat dilakukan aman hanya dengan
mengubah label menu; strategi kompatibilitas skema/data dan masa transisi harus
ditetapkan terpisah. Jangan jalankan perubahan enum atau data sebelum persetujuan
dan rencana rollback.

## Matriks akses yang benar-benar ditemukan

Keterangan: “Pengelola” berarti `Super Admin`, `Petugas Gudang`, dan
`Admin Unit/Petugas Arsip`; “Approver” berarti `Super Admin` dan
`Pimpinan/Approver`. Setiap aksi tetap dapat memiliki pemeriksaan tambahan
terhadap status, klasifikasi arsip, kepemilikan, atau status transaksi.

| Modul/aksi | Pemeriksaan backend yang ditemukan |
|---|---|
| Login dan aplikasi terautentikasi | Route aplikasi memakai `auth` dan middleware `active`. Middleware aktif memeriksa status akun, bukan role. Login/reset password berada di route publik/guest sesuai route masing-masing. |
| Dashboard | Hanya `Super Admin` dan `Petugas Gudang` mendapat dashboard. Role lain dialihkan ke daftar arsip; role tanpa hak listing dapat berakhir 403. |
| Daftar/lihat arsip | Listing menerima pengelola dan `Customer`/`Pengguna Umum`. `ArchiveAccess` mengatur klasifikasi: Super Admin dapat melihat semua; pengguna portal hanya publik; `Dokumen Rahasia` ditolak selain Super Admin; `Internal Divisi` memerlukan divisi yang cocok; approver dapat melihat arsip internal dan internal divisi sesuai helper. |
| Tambah/edit arsip | Hanya pengelola; detail dan lampiran tetap memeriksa akses arsip. Non-Super Admin yang membuat `Internal Divisi` memakai divisinya sendiri. |
| Scan/lookup QR | Route terautentikasi; lookup menerapkan pemeriksaan akses terhadap arsip. Label QR, label box, dan pengelolaan QR dibatasi ke pengelola. |
| Peminjaman operasional | Pengelola dapat melihat/membuat/mengembalikan transaksi, dengan pemeriksaan akses dan status arsip. |
| Permintaan/persetujuan pinjaman | `Customer`/`Pengguna Umum` mengajukan dan melihat permintaan sendiri. `Super Admin`/`Pimpinan/Approver` memutuskan permintaan. Pemeriksaan approver belum terbukti membatasi keputusan berdasarkan divisi. |
| Retensi | Halaman retensi untuk pengelola dan approver. Hanya Super Admin mengubah jadwal; pengelola mengusulkan disposisi; Super Admin/approver meninjau atau menyelesaikan disposisi. Arsip dan usulan memakai scope `ArchiveAccess`. |
| Persetujuan/verifikasi dokumen Customer | Hanya Super Admin meninjau pengajuan dokumen dan permintaan akses. Pemohon dapat melihat/mengunduh dokumen miliknya; Super Admin juga dapat mengakses file sesuai pemeriksaan controller. |
| Verifikasi duplikasi | Pengelola dan `Verifikator`; kedua arsip dalam pasangan diperiksa berdasarkan akses. Halaman index menjalankan penemuan kandidat dan menyimpan pasangan baru ke `duplicate_reviews`, sehingga request GET tersebut memiliki efek samping database. |
| Laporan dan ekspor | Pemeriksaan controller membatasi laporan/cetak/ekspor dan ekspor data kepada Super Admin. |
| Impor/ekspor data | Semua aksi import/export memeriksa Super Admin; proses pratinjau/konfirmasi/ekspor tidak sekadar disembunyikan dari menu. |
| Kategori dan lokasi | Pengelolaan dibatasi ke Super Admin. Penonaktifan kategori/lokasi yang sudah dipakai diperlakukan berbeda dari penghapusan yang aman. |
| Riwayat aktivitas | Hanya Super Admin. Controller menampilkan hasil pencarian riwayat, bukan endpoint mutasi riwayat. |
| Pengguna dan reset password oleh admin | Hanya Super Admin; dapat membuat pengguna dari daftar role legacy, mengganti role/divisi/status, dan menerbitkan tautan reset. Role akun sendiri tidak dapat diganti dan Super Admin aktif terakhir tidak dapat dinonaktifkan. |
| Portal Customer | Hanya `Customer`/`Pengguna Umum`; pencarian katalog dibatasi ke arsip publik dan data permintaan/pengajuan di-scope ke pemiliknya. |
| Profil dan foto sendiri | Route membutuhkan akun aktif; perubahan profil/password dan foto terkait akun yang terautentikasi, dengan pemeriksaan file tersendiri. |
| Notifikasi | Route membutuhkan autentikasi; feed, daftar, baca satu, dan baca semua dibatasi pada `user_id` pengguna saat ini. |

Route aplikasi pada umumnya tidak memiliki middleware role terpisah pada
`routes/web.php`. Ini bukan bukti bahwa semua akses terbuka: pemeriksaan utama
berada pada metode controller/helper yang dirangkum di atas. Namun, setiap aksi
route tetap harus diaudit dan diuji satu per satu, khususnya route mutasi dan
download/file.

## Matriks izin target — rancangan awal, belum disahkan

Matriks ini adalah baseline least-privilege untuk keputusan pemilik bisnis; bukan
representasi izin yang sudah aktif. “Perlu putusan” berarti jangan implementasikan
hingga makna role, scope divisi, dan pemisahan akun internal/eksternal disepakati.

| Modul | Super Admin | Admin | Kepala Divisi | Pegawai |
|---|---|---|---|---|
| Dashboard | Global | Ringkasan sesuai scope yang disetujui | Ringkasan divisinya | Ringkasan operasional sesuai scope |
| Lihat/ cari arsip | Semua klasifikasi sesuai kebijakan | Scope administrasi yang disetujui | Publik/internal serta arsip divisinya; rahasia tetap ditolak kecuali ada aturan eksplisit | Arsip yang diizinkan untuk pekerjaan; divisi/klasifikasi dibatasi |
| Tambah/edit arsip | Ya, termasuk klasifikasi sensitif sesuai aturan | Perlu putusan: global atau hanya administrasi unit | Tidak disarankan sebagai hak default | Ya hanya jika tugas Pegawai mencakup input; batas divisi dan klasifikasi wajib tegas |
| Lampiran dan QR | Semua sesuai akses arsip | Mengikuti scope arsip | Mengikuti scope arsip | Mengikuti scope arsip; aksi cetak label sesuai kebutuhan kerja |
| Pinjaman operasional/pengembalian | Ya | Ya jika termasuk operasi arsip | Tidak sebagai default | Ya jika petugas transaksi; tidak boleh memutus permintaan sendiri |
| Persetujuan pinjaman | Ya; audit trail wajib | Tidak sebagai default | Hanya permintaan dalam divisinya setelah scope backend ditetapkan | Tidak |
| Jadwal/usulan/persetujuan retensi | Jadwal global dan keputusan final | Jadwal hanya jika peran tata kelola disetujui; usulan sesuai scope | Review disposisi dalam divisinya setelah isolasi divisi diterapkan | Membuat usulan sesuai tugas; tidak menyetujui sendiri |
| Review dokumen/customer | Hanya jika ditetapkan sebagai reviewer global | Perlu putusan tentang peran reviewer dan pemisahan tugas | Tidak otomatis; perlu aturan reviewer/divisi | Tidak otomatis |
| Verifikasi duplikasi | Ya | Perlu putusan | Tidak sebagai default | Perlu putusan apakah fungsi Verifikator dipertahankan sebagai izin terpisah |
| Laporan | Global | Perlu putusan tentang laporan global vs divisi | Laporan divisi bila disetujui | Tidak sebagai default; statistik terbatas bila perlu |
| Impor/ekspor | Ya, dengan pratinjau, validasi, dan audit | Perlu putusan; hindari hak global tanpa pembatasan | Tidak | Tidak |
| Kategori/lokasi | Ya | Perlu putusan tentang master data global dan dampak arsip | Tidak | Tidak |
| Riwayat aktivitas | Semua sesuai kebutuhan audit | Perlu putusan; data pribadi/aktivitas lintas unit harus dibatasi | Riwayat divisinya bila tersedia dan disetujui | Riwayat aktivitas sendiri/transaksi yang terkait saja bila perlu |
| Pengguna, role, status, reset password | Administrasi penuh dengan pengamanan akun terakhir | Perlu putusan; sebaiknya tidak dapat menaikkan role/menonaktifkan Super Admin | Tidak | Tidak |
| Portal Customer | Tidak sebagai akun Customer biasa; dapat melakukan review hanya bila izin khusus diputuskan | Tidak sebagai akun Customer biasa | Tidak otomatis | Tidak otomatis |
| Akun Customer/Pengguna Umum | Tetap jenis akun eksternal terpisah di luar empat role pegawai, kecuali keputusan produk menyatukannya | Sama | Sama | Sama |

Sebelum matriks ini diubah menjadi konfigurasi permission, pemilik sistem perlu
menetapkan setidaknya: apakah Admin global atau per-unit; apakah Pegawai boleh
mengubah/meminjamkan arsip; apakah Kepala Divisi hanya menyetujui hal di
divisinya; siapa reviewer dokumen customer dan duplikasi; apakah Customer dan
Pengguna Umum tetap berbeda; serta siapa yang berwenang mengelola klasifikasi
rahasia, jadwal retensi, laporan, dan master data.

## Divisi dan isolasi data

- `users.division` dan `arsip.division` saat ini berupa teks; audit skema tidak
  menemukan tabel divisi/relasi foreign key. Daftar pilihan divisi pada form arsip
  diturunkan dari nilai teks pengguna.
- `ArchiveAccess::canView` membandingkan divisi tanpa membedakan kapitalisasi,
  sedangkan `ArchiveAccess::scope` memakai kondisi SQL yang membandingkan nilai
  teks. Hasil listing dan pemeriksaan pada satu record dapat berbeda untuk variasi
  kapitalisasi/penulisan. Normalisasi atau aturan kanonis perlu disepakati dan
  diuji sebelum menjadikan divisi sebagai batas keamanan.
- Pengelola dapat melihat arsip klasifikasi `Internal` tanpa pembatasan divisi
  menurut helper saat ini. Kepala Divisi target tidak boleh langsung disamakan
  dengan approver lama sebelum endpoint daftar/persetujuan benar-benar dibatasi
  ke divisinya.
- Pengelolaan pengguna saat ini menerima teks divisi bebas. Perubahan nama atau
  ejaan dapat memecah scope dan membuat data yang seharusnya satu divisi tidak
  cocok. Jangan membuat relasi/tabel divisi atau mengubah skema pada tahap audit.

## Implementasi authorization dan kontrol antarmuka

- Middleware `auth` dan `active` diterapkan pada grup route aplikasi; middleware
  `active` tidak mengotorisasi role.
- Pemeriksaan role tersebar sebagai string literal dalam controller dan helper
  (`ArchiveAccess`), tanpa `app/Policies` yang ditemukan. Pendekatan ini memberi
  penolakan backend pada banyak aksi, tetapi rentan tidak konsisten ketika role
  baru ditambah atau ejaan role berubah.
- Menu utama dirender Blade dan memakai kondisi role/helper untuk menampilkan
  tautan. Menu Customer berbeda dari menu operasional; menu approver, Verifikator,
  dan Super Admin juga berbeda. Ini hanya kontrol visibilitas, bukan pengganti
  otorisasi server.
- Penelusuran `resources/js` hanya menemukan skrip pengendali drawer sidebar; tidak
  ditemukan navigasi React yang mengotorisasi berdasarkan role. UI React tidak
  boleh dianggap sebagai lapisan authorization backend.
- Controller, query scope, dan kepemilikan resource tetap perlu memvalidasi setiap
  GET, POST, PATCH, PUT, DELETE, ekspor, cetak, dan download secara independen.

## Risiko prioritas

1. **Perubahan enum dan kompatibilitas kode:** database dan validator aplikasi
   menerima role legacy, bukan empat target. Mengubah daftar role tanpa
   kompatibilitas skema dapat gagal saat menyimpan akun atau memutus akses akun
   lama.
2. **Privilege creep saat pemetaan:** `Petugas Gudang` dan `Admin Unit/Petugas
   Arsip` saat ini berada dalam grup pengelola yang dapat mengubah arsip,
   mengelola peminjaman, dan mengusulkan retensi. Menamai ulang keduanya menjadi
   Pegawai/Admin tidak otomatis mempersempit hak.
3. **Persetujuan lintas divisi:** approver lama menerima permintaan pinjaman dan
   keputusan disposisi; bukti scope divisi untuk semua permintaan tidak ditemukan.
   Membuat role Kepala Divisi tanpa pembatasan query/aksi berisiko memberi akses
   lintas divisi.
4. **Perbedaan listing dan view:** role yang tidak memiliki hak listing dapat
   tetap memiliki akses tertentu pada arsip publik/scan. Uji perlu membedakan
   akses halaman, akses record, lookup QR, lampiran, dan mutasi.
5. **Role khusus yang hilang:** Verifikator, Customer, dan Pengguna Umum tidak
   mempunyai padanan pegawai yang aman berdasarkan bukti saat ini.
6. **Divisi berupa teks:** variasi kapitalisasi, spasi, alias, dan penggantian
   nama dapat menghasilkan salah scope atau akses yang tidak konsisten.
7. **Role authorization tersebar:** literal role pada controller, helper, serta
   Blade perlu diperbarui bersama; perubahan UI saja tidak mengamankan endpoint.
8. **Efek samping GET verifikasi duplikasi:** endpoint index dapat menulis
   kandidat baru. Dampak ini harus dipertimbangkan saat menetapkan akses baca
   versus izin proses/verifikasi.
9. **Pengujian DB:** tes fitur memakai SQLite in-memory; hasilnya belum
   membuktikan semantik enum, collation perbandingan divisi, atau perilaku
   authorization pada MySQL/MariaDB. Uji kompatibilitas harus menggunakan
   database salinan terisolasi seperti prosedur Tahap 1B.

## Tes authorization yang disarankan sebelum implementasi

Tambahkan tes Feature berbasis tabel role × route × metode HTTP, termasuk hasil
yang diharapkan (200/redirect, 403, 404, 409 atau validasi) dan pemeriksaan bahwa
data tidak berubah saat request ditolak:

1. Uji setiap empat role target pada setiap route baca dan mutasi; sertakan guest,
   akun nonaktif, serta setiap role eksternal/special yang dipertahankan.
2. Uji arsip publik, internal, internal divisi yang sama/berbeda, dan rahasia untuk
   listing, detail, edit, file attachment, scan QR, lookup QR, label, pinjaman,
   retensi, dan verifikasi duplikasi.
3. Uji bahwa Kepala Divisi tidak dapat melihat, memutus, atau mengunduh permintaan
   divisi lain, termasuk pemanggilan endpoint langsung dengan ID resource.
4. Uji Pegawai/Admin tidak dapat menaikkan role, mengubah role sendiri,
   menonaktifkan Super Admin, mengubah jadwal retensi, atau mengakses fungsi
   global kecuali matriks final secara eksplisit mengizinkan.
5. Uji permintaan Customer/Pengguna Umum selalu dibatasi ke pemiliknya; satu akun
   tidak dapat membaca/mengubah/mengunduh submission, request, atau notifikasi
   akun lain.
6. Uji route ekspor, impor pratinjau/konfirmasi/buang, laporan, master kategori
   dan lokasi, pengguna, reset password, riwayat, serta verifikasi Customer untuk
   role yang ditolak.
7. Uji setiap route dengan request langsung tanpa membuka halaman/menu terlebih
   dahulu; pastikan penolakan tidak mengubah tabel atau file.
8. Uji perbedaan kapitalisasi/spasi nama divisi dan bandingkan hasil query listing
   dengan `canView`; jalankan pula pada salinan MySQL/MariaDB.
9. Uji regresi login, session, middleware aktif, CSRF, akses akun sendiri,
   notifikasi, audit trail, dan proteksi Super Admin aktif terakhir.
10. Uji bahwa navigasi Blade dan UI lain hanya menampilkan tindakan yang diizinkan
    setelah otorisasi backend final ditetapkan.

Suite `tests/Feature/ArchiveManagementTest.php` saat ini sudah mencakup beberapa
kasus penting (isolasi divisi arsip, role tanpa hak listing, role pengelola,
laporan/master data, peminjaman, Customer, retensi, duplikasi, pengguna dan
notifikasi). Itu belum merupakan cakupan lengkap untuk empat role baru atau
matriks route × metode. Baseline sebelumnya adalah 55 tes lulus dan 406
assertion; tidak ada tes yang dijalankan ulang pada tahap dokumentasi ini karena
tidak ada perubahan kode aplikasi.

## Rencana tahap implementasi berikutnya

1. Minta keputusan tertulis atas baris “perlu putusan” di pemetaan dan matriks,
   termasuk kebutuhan mempertahankan akun Customer/Pengguna Umum/Verifikator.
2. Tetapkan matriks izin final dan batas divisi untuk baca, tulis, persetujuan,
   file, ekspor, dan laporan sebelum mengedit route/controller.
3. Rancang kompatibilitas role legacy serta perubahan skema secara terpisah,
   termasuk backup, migration reversible, strategi pemetaan per akun, validasi di
   salinan MySQL/MariaDB, dan rollback. Jangan menerapkan ke database aktif pada
   tahap ini.
4. Implementasikan satu lapisan backend authorization yang sesuai keputusan
   (middleware/Policy/Gate atau service yang konsisten), lalu pastikan setiap
   endpoint dan query scope memakai aturan yang sama.
5. Tambahkan tes penolakan dan keberhasilan untuk matriks final; jalankan pada
   SQLite untuk regresi umum dan MySQL/MariaDB salinan untuk perilaku khusus DB.
6. Sesudah backend aman, sesuaikan visibilitas menu Blade/React agar mencerminkan
   izin yang sama, tanpa menjadikannya satu-satunya kontrol.

Berhenti di sini sampai pemetaan, matriks, scope divisi, dan perlakuan role
eksternal disetujui. Tidak ada perubahan role, route, authorization, skema, atau
fitur yang dilakukan dalam Tahap 2.

## Addendum Tahap 3 — fondasi authorization tanpa perubahan skema

Tahap 3 mengimplementasikan katalog permission pada kode tanpa menjalankan atau
membuat migration. Definisi aktual terdapat di `app/Support/RolePermissions.php`;
`ArchiveAccess`, controller yang relevan, dan navigasi menggunakan permission itu.
Role tetap menjadi identitas di `users.role`, sedangkan permission seperti
`archives.manage`, `duplicates.review`, dan `loans.approve` menjadi unit kontrol
fungsional.

### Grant yang sekarang ditetapkan

| Role | Permission yang ditetapkan pada kode |
|---|---|
| Super Admin | Semua permission administratif/operasional pada katalog, kecuali `customer.portal` dan `loans.request` yang khusus akun eksternal. Pengecualian menjaga perilaku lama: Super Admin bukan akun portal dan tidak mengajukan pinjaman sebagai Customer. |
| Petugas Gudang | Dashboard; daftar/lihat/kelola arsip non-rahasia; scan dan kelola label QR; kelola transaksi pinjaman; lihat retensi dan mengusulkan penyusutan; verifikasi duplikasi. |
| Admin Unit/Petugas Arsip | Daftar/lihat/kelola arsip non-rahasia; scan dan kelola label QR; kelola transaksi pinjaman; lihat retensi dan mengusulkan penyusutan; verifikasi duplikasi. Hak ini mempertahankan grup pengelola lama dan bukan hak admin global. |
| Verifikator | Scan QR dan verifikasi duplikasi; tidak diberi listing arsip umum atau hak admin. Pemeriksaan akses arsip tetap berlaku saat review. |
| Pimpinan/Approver | Lihat arsip sesuai aturan lama; scan QR; menyetujui pinjaman; melihat dan meninjau retensi. |
| Customer / Pengguna Umum | Portal customer, daftar/lihat arsip publik, scan QR dengan pemeriksaan klasifikasi, dan mengajukan permintaan pinjaman. Keduanya tetap role eksternal terpisah. |
| Kepala Divisi | Daftar/lihat arsip, scan QR, menyetujui pinjaman, melihat dan meninjau retensi; seluruh record yang disentuh harus memiliki divisi yang cocok, dan arsip rahasia tetap ditolak. |
| Admin / Pegawai | Belum diberikan grant. Role ini belum dapat disimpan pada skema MySQL existing; menolak akses sampai matriks permission dan perubahan skema disetujui. |

Grant di atas adalah peta statis di kode, bukan permission per pengguna yang dapat
dikonfigurasi dari UI. Akses customer-only tidak otomatis diberikan kepada
Super Admin meskipun ia memiliki seluruh permission administratif. Pembatasan
khusus Kepala Divisi membandingkan divisi tanpa
membedakan kapitalisasi/spasi tepi untuk scope arsip dan keputusan. Arsip rahasia
tetap hanya dapat dibaca/dikelola Super Admin.

### Perubahan implementasi

- `ArchiveAccess` menggunakan permission katalog dan membatasi role target
  Kepala Divisi ke record dengan divisi yang cocok. Role legacy mempertahankan
  akses lama; scope query dan pemeriksaan record diselaraskan untuk divisi.
- Controller memakai permission terpisah untuk pengelolaan arsip, pinjaman,
  label QR, retensi, persetujuan, verifikasi Customer/duplikasi, laporan,
  impor/ekspor, kategori, lokasi, riwayat, dan pengguna. Endpoint tetap memeriksa
  authorization backend dan bukan hanya menu.
- Persetujuan pinjaman Kepala Divisi memfilter daftar berdasarkan divisi arsip
  dan menolak baik persetujuan maupun penolakan request yang arsipnya di luar
  divisinya. Review dan pelaksanaan retensi memakai pemeriksaan arsip yang sama.
- Menu Blade menyembunyikan aksi menurut permission. Ini hanya konsistensi UI;
  perlindungan tetap berada di controller/scope query.
- Daftar role yang dapat dibuat/diubah di `UserController` tetap tujuh role
  legacy. Tidak ada data pengguna atau enum database yang diubah. `Admin`,
  `Kepala Divisi`, dan `Pegawai` tidak dapat dipilih/ditulis di database existing
  sebelum perubahan enum disetujui dan diuji.
- Middleware autentikasi dan middleware status aktif tidak diubah. Tidak ada
  perubahan autentikasi/session/CSRF.

### Validasi yang diwajibkan

Tes fitur baru memeriksa bahwa role Admin/Pegawai tanpa grant ditolak, Kepala
Divisi hanya melihat/menyetujui request divisinya dan penolakan lintas divisi
tidak memutasi data, dan Verifikator masih dapat membuka fungsi verifikasi
duplikasi tetapi tidak mendapat akses laporan/pengelolaan pengguna. Tes regresi
yang sudah ada untuk permission role legacy tetap harus lulus.

Tes tersebut menggunakan SQLite in-memory. Hasilnya tidak memvalidasi enum,
collation, atau migration MySQL/MariaDB.

### Prasyarat dan rancangan perubahan skema berikutnya

Jangan membuat atau menjalankan migration sebelum persetujuan. Untuk
mengaktifkan nama target pada database, migration yang kelak diajukan harus:

1. Memperluas definisi `users.role` dengan `Admin`, `Kepala Divisi`, dan
   `Pegawai`, sambil mempertahankan semua tujuh nilai legacy dan default
   `Customer`. Tidak ada pemetaan massal atau update nilai pengguna.
2. Memvalidasi struktur/type/default enum pada salinan MySQL/MariaDB dari skema
   existing terlebih dahulu. Pastikan `users` tidak dibuat ulang dan seluruh
   nilai serta hitungan role tetap sama setelah operasi.
3. Menyediakan rollback yang memeriksa apakah nilai role target sudah dipakai;
   rollback harus berhenti dengan pesan jelas jika masih ada, bukan mengonversi
   akun secara diam-diam.
4. Menunggu keputusan pemetaan akun legacy satu per satu. `Verifikator`,
   `Customer`, dan `Pengguna Umum` harus tetap memiliki jalur kompatibel.
5. Hanya jika izin perlu dikelola runtime tanpa deployment kode, ajukan desain
   tabel permission/role-permission terpisah dan backfill yang dapat diulang.
   Fitur tersebut belum dibuat karena perubahan schema belum disetujui.

Sebelum migration, pemilik sistem masih perlu menyetujui grant final untuk Admin
dan Pegawai serta memastikan Kepala Divisi berwenang meninjau retensi dan
permintaan pinjaman hanya di divisinya. Sampai keputusan itu tersedia, jangan
membuat akun role target atau mengklaim empat role sudah siap digunakan.
