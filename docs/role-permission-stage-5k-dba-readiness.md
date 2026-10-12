# Tahap 5K — Spesifikasi Kesiapan DBA untuk MariaDB Test

Status: spesifikasi untuk ditinjau DBA dan pemilik aplikasi. Tidak ada
database, user, koneksi, schema, fixture, config, credential, tes, migration,
atau discovery yang dibuat/dijalankan/diubah. Isolasi database **belum
terbukti**.

Dokumen ini memperinci rancangan pada
[`role-permission-stage-5j-test-environment.md`](./role-permission-stage-5j-test-environment.md)
dan menyesuaikannya dengan schema/kode yang didokumentasikan. Ini bukan DDL
siap eksekusi; seluruh objek di bagian schema masih harus ditinjau dan
disediakan pada instance test yang disetujui.

## 1. Checklist persetujuan DBA

Sebelum lingkungan dibuat, seluruh item wajib berstatus disetujui:

### Instance dan database

- [ ] Instance MariaDB khusus test disetujui dan dipisahkan dari server
  produksi sejauh mungkin (instance/service terpisah direkomendasikan).
- [ ] DBA mencatat versi server, endpoint, network boundary dan pemilik
  instance melalui kanal internal yang disetujui. Jangan mencatat rahasia.
- [ ] Nama database **`arsip_bakti_test`** disetujui dan belum dibuat sampai
  persetujuan eksplisit diberikan.
- [ ] Database test dipastikan tidak menunjuk, alias, proxy, atau route ke
  `arsip_bakti`.
- [ ] Database disetujui sebagai disposable, tanpa data penting, data pribadi,
  arsip sensitif, dokumen, token, lampiran, atau kandidat produksi.
- [ ] Charset/collation disepakati; skenario pembandingan divisi harus
  menentukan apakah schema mengikuti collation existing terdokumentasi
  (`utf8mb4_unicode_ci`) atau menambahkan test khusus collation lain.
- [ ] Engine tabel transaksional ditetapkan ke InnoDB untuk pengujian
  transaksi, rollback dan `SELECT ... FOR UPDATE`.

### Akun dan hak akses

- [ ] Akun test baru dibuat terpisah dari semua akun runtime/produksi; tidak
  menggunakan credential produksi.
- [ ] Secret test diserahkan melalui secret manager/environment runner, tidak
  melalui source, `phpunit.xml`, command line, dokumentasi, output, atau log.
- [ ] Akun test hanya mendapat hak DML minimum (`SELECT`, `INSERT`, `UPDATE`,
  `DELETE`) pada schema `arsip_bakti_test`, jika test fixture membutuhkan
  masing-masing hak tersebut.
- [ ] Akun runtime test tidak memiliki grant global, tidak memiliki hak pada
  `arsip_bakti`, dan tidak memiliki `DROP DATABASE`, `CREATE USER`, `GRANT`,
  atau DDL.
- [ ] Schema disiapkan DBA sebelum test; dengan pendekatan ini akun aplikasi
  tidak memerlukan `CREATE`, `ALTER`, `INDEX`, atau `REFERENCES`.
- [ ] DBA memverifikasi hak akun melalui grant catalog/server administration,
  bukan hanya menyimpulkan dari keberhasilan koneksi.

### Schema dan fixture

- [ ] DDL uji dipetakan ke controller/query aktual dan diverifikasi terhadap
  definisi tabel yang tersedia; snapshot `database-existing-schema.md` bukan
  pengganti `SHOW CREATE TABLE` terverifikasi dari salinan yang disetujui.
- [ ] Schema tidak dibuat dari dump lama `database/arsip_bakti.sql`, yang
  terdokumentasi tidak lengkap dan mengandung DDL destruktif.
- [ ] Unique key pasangan duplikasi, FK relevan, enum/status, kolom nullable,
  ukuran kolom, index dan tipe angka diperiksa secara khusus.
- [ ] Role target `Admin`, `Kepala Divisi`, dan `Pegawai` yang tidak ada pada
  enum legacy diuji hanya dengan schema fixture yang secara eksplisit
  mengizinkan string role test; tidak ada perubahan ke enum produksi.
- [ ] Data fixture sepenuhnya sintetis dan memiliki namespace/ID terpisah.
- [ ] Prosedur pembersihan fixture disetujui, eksplisit terbatas pada ID
  fixture, dan tidak memakai operasi wipe/drop/truncate otomatis.

### Konfigurasi dan pelaksanaan

- [ ] Konfigurasi PHPUnit MariaDB terpisah dari `.env` dan `phpunit.xml`
  SQLite yang ada.
- [ ] Guard fail-closed diuji untuk koneksi, nama DB, `DB_URL`, environment
  tidak dikenal, config cache dan environment process override.
- [ ] Guard berhenti sebelum bootstrap membuka koneksi jika syarat tidak
  terpenuhi.
- [ ] Tidak ada Composer script, Artisan migration, `db:wipe`, atau discovery
  yang dijalankan sebagai bagian dari persiapan.
- [ ] DBA dan pemilik aplikasi menyetujui subset test dan jendela pelaksanaan.

## 2. Rancangan konfigurasi tes Laravel

Tidak ada konfigurasi yang ditambahkan pada tahap ini. Rancangan berikut harus
diimplementasikan dan direview sebelum menyambungkan test:

### PHPUnit profile terpisah

Buat `phpunit.mariadb.xml` (nama usulan) terpisah dari `phpunit.xml`. Profil
SQLite saat ini tetap tidak berubah. Profile MariaDB harus:

1. Memaksa `APP_ENV=testing`, `DB_CONNECTION=mariadb_test`,
   `DB_DATABASE=arsip_bakti_test`, `DB_URL` kosong, serta menonaktifkan cache
   config untuk proses test.
2. Menggunakan `force="true"` untuk nilai environment yang wajib dikunci,
   bukan mengandalkan `<env>` default yang dapat dikalahkan environment proses.
3. Mengambil endpoint dan user dari variabel khusus test, contohnya
   `MARIADB_TEST_HOST`, `MARIADB_TEST_PORT`, `MARIADB_TEST_USERNAME`, dan
   `MARIADB_TEST_PASSWORD`; tidak boleh fallback ke `DB_HOST`, `DB_USERNAME`,
   atau `DB_PASSWORD` dari `.env`.
4. Tidak menyimpan password dalam XML; tidak mencetak atau menulis secret ke
   report/log.
5. Tidak memanggil migration, menjalankan seeder produksi, discovery, atau
   setup schema otomatis.

Nama connection khusus dalam `config/database.php` sebaiknya membaca hanya
variabel bernamespace `MARIADB_TEST_*`, dengan driver `mariadb`, host/port
test, database allowlist tetap `arsip_bakti_test`, user/password test, charset
dan collation yang disetujui. Jangan gunakan generic `DB_URL` untuk koneksi
khusus ini. Penambahan tersebut menunggu tahap implementasi yang disetujui.

### Environment dan urutan precedence

- Gunakan runner/wrapper khusus test yang menghapus atau menolak inherited
  `DB_URL`, `DB_CONNECTION`, `DB_DATABASE`, `DB_HOST`, `DB_PORT`,
  `DB_USERNAME`, `DB_PASSWORD`, `DB_SOCKET`, dan URL alias sebelum
  menginisialisasi aplikasi.
- Hanya variabel `MARIADB_TEST_*` yang diizinkan sebagai credential/endpoint
  test. Variabel wajib yang kosong harus menggagalkan preflight.
- Jangan gunakan `php artisan test` untuk koneksi MariaDB jika command Artisan
  tersebut bootstrap aplikasi sebelum PHPUnit/guard. Setelah disetujui,
  gunakan pemanggil PHPUnit yang memuat preflight terlebih dahulu, atau
  wrapper yang memvalidasi nilai environment dan profile sebelum proses
  Laravel dimulai.
- Jangan gunakan `composer test`: script yang ada menjalankan
  `artisan config:clear` terlebih dahulu dan bukan launcher MariaDB yang
  fail-closed.
- Tidak boleh ada `bootstrap/cache/config.php` pada proses test MariaDB;
  config cache dapat membawa nilai lama. Preflight harus menolak keberadaannya
  atau menggunakan proses bootstrap yang memastikan config efektif berasal
  dari profile yang sudah dikunci tanpa menghapus/menulis cache otomatis.
- `DB_URL` harus kosong, bukan hanya tidak dipakai secara kebetulan. URL
  database dapat mengoverride host/user/database dan karena itu membuat target
  tidak mudah ditinjau.
- `.env` aktif tidak dimodifikasi; `.env.testing` juga tidak dibuat pada tahap
  ini.

## 3. Rancangan guard fail-closed

Guard harus berjalan sebelum koneksi database pertama dapat dibuat, tidak
melakukan ping/query, dan menolak proses jika salah satu kondisi berikut
terpenuhi:

1. Environment bukan persis `testing` atau profile bukan profile MariaDB
   khusus yang dikenal.
2. Connection name bukan `mariadb_test`, driver efektif bukan MariaDB, atau
   driver terkonfigurasi dari fallback SQLite/MySQL.
3. Nama database efektif tidak persis `arsip_bakti_test`, kosong, atau sama
   dengan `arsip_bakti`.
4. `DB_URL`/URL pada konfigurasi connection tidak kosong.
5. Variabel wajib `MARIADB_TEST_HOST`, port, username, password tidak
   tersedia sesuai kebijakan runner.
6. Ada `DB_*` generik yang bertentangan dengan konfigurasi test, atau
   konfigurasi yang ter-cache/tidak dapat dibuktikan asalnya.
7. Environment runner tidak dikenal, seperti invocation melalui script
   Composer/Artisan yang tidak secara eksplisit diizinkan.

### Pseudocode pemeriksaan (spesifikasi, bukan script)

```text
require APP_ENV == "testing"
require TEST_PROFILE == "mariadb"
require effective connection == "mariadb_test"
require effective driver == "mariadb"
require effective database == "arsip_bakti_test"
require effective URL is empty
require all MARIADB_TEST_* required values are present
require no incompatible inherited DB_* values
require no unreviewed Laravel config cache
if any requirement fails: exit nonzero before Laravel DB connection
never print credential values
```

Guard tidak dapat membuktikan bahwa endpoint yang dikonfigurasi benar-benar
server test tanpa menghubunginya. Karena itu guard lokal perlu didampingi
verifikasi DBA atas instance, grant akun, dan pemetaan schema. Setelah seluruh
prasyarat disetujui, baru prosedur server-side dapat mengidentifikasi versi
dan schema pada koneksi test yang secara independen telah dipastikan.

## 4. Audit risiko khusus

| Risiko | Penyebab | Kontrol yang dirancang |
|---|---|---|
| `DB_URL` mengubah target | Koneksi MySQL/MariaDB Laravel menerima `url` dari variabel generik | Wajib kosong pada profile dan guard; koneksi khusus tidak membaca URL generic |
| Environment variable proses override XML | PHPUnit env default tidak selalu menang atas nilai diwarisi | Gunakan nilai force untuk invariants; wrapper hapus/tolak generic `DB_*`; guard verifikasi config efektif |
| `.env` aktif menjadi fallback | `.env` mengarah ke MySQL database `arsip_bakti` | Connection khusus memakai namespace `MARIADB_TEST_*`, tidak ada fallback; nama database allowlist |
| Config cache stale | `bootstrap/cache/config.php` dapat menyimpan target lain | Tolak cache pada test runner atau gunakan mekanisme profile yang terverifikasi tanpa auto-clear |
| Composer `test` | Menjalankan `artisan config:clear`, lalu test umum | Tidak digunakan untuk MariaDB; gunakan launcher khusus yang guard-nya terjadi sebelum app bootstrap |
| Composer `setup` / project-create hook | Menjalankan migration atau key generation | Dilarang untuk persiapan/test; tidak menjalankan Composer scripts selama fase ini |
| Composer lifecycle `post-autoload-dump` | Menjalankan `artisan package:discover` setelah autoload dump; tidak teridentifikasi menjalankan migration, tetapi tetap bootstrap Artisan | Hindari menjalankan Composer install/update/hooks sebagai langkah pengujian sampai target dan efek bootstrap ditinjau |
| Perintah Artisan umum | Bootstrap memakai `.env` dan dapat membuka koneksi bila suatu command/query menyentuh DB | Jangan jalankan sebelum guard; test launcher harus menghentikan target tidak allowlisted |
| Akun punya grant luas | User test dapat mengakses database lain meski aplikasi menunjuk schema test | DBA meninjau grants; aplikasi memakai user khusus minim hak; tidak menguji akses ke produksi dari client test |
| Schema/test role divergence | Enum production menolak role target yang belum dimigrasikan | Untuk test kode role target gunakan schema khusus `VARCHAR`/enum test yang disetujui; hasilnya bukan validasi enum production |
| Test fixture lifecycle | Test mungkin memerlukan insert/update dan cleanup | Fixture sintetis ber-prefix, cleanup hanya ID fixture; tanpa wipe/drop/truncate otomatis |
| Test harness existing | `ArchiveManagementTest::setUp()` memanggil `createSchema()` untuk membuat tabel pada koneksi test | Suite tidak dapat langsung diarahkan ke schema MariaDB persisten yang sudah disediakan DBA; perlu rencana test harness/fixture tanpa DDL atau penghapusan otomatis sebelum implementasi test MariaDB |

### Temuan statis Composer dan bootstrap

Audit file aktual menunjukkan rangkaian berikut:

1. `composer.json` mengikat `post-autoload-dump` ke
   `Illuminate\Foundation\ComposerScripts::postAutoloadDump`, kemudian
   menjalankan `@php artisan package:discover --ansi`.
2. Implementasi framework `ComposerScripts::postAutoloadDump()` memanggil
   `clearCompiled()`. Fungsi tersebut menghapus file cache bootstrap
   `bootstrap/cache/config.php`, `services.php`, dan `packages.php` bila ada.
   Ini adalah perubahan file lokal yang nyata, bukan operasi database.
3. `artisan` memuat `vendor/autoload.php`, lalu `bootstrap/app.php`, dan
   mengirim perintah ke Laravel Console Kernel. Bootstrapping Console Kernel
   menjalankan pemuatan environment/configuration serta registrasi dan boot
   provider.
4. `LoadEnvironmentVariables` melewati pemuatan `.env` hanya bila konfigurasi
   masih ter-cache. Karena hook sebelumnya dapat menghapus cache konfigurasi,
   bootstrap berikutnya dapat membaca environment proses dan file environment
   proyek (misalnya `.env.testing` hanya jika environment tersebut dipilih).
   `config/database.php` membaca koneksi default, URL, host, nama database,
   username, dan password dari variabel `DB_*` generik.
5. `package:discover` menjalankan `PackageManifest::build()` untuk membangun
   manifest paket. Handler perintah itu tidak menjalankan query database.
   Provider terkonfigurasi tetap diregistrasi/di-boot saat Artisan bootstrap.
   Pada kode aplikasi yang diperiksa, `bootstrap/providers.php` hanya
   mendaftarkan `AppServiceProvider`, dan `AppServiceProvider::register()` serta
   `boot()` kosong. Tidak ditemukan pemanggilan database di jalur aplikasi
   tersebut.

**Kesimpulan berbatas bukti:** hook Composer dapat memuat konfigurasi yang
menunjuk ke database aktif, tetapi pemeriksaan statis pada handler
`package:discover` dan provider aplikasi saat ini tidak menemukan koneksi atau
query database. Koneksi Laravel bersifat lazy pada jalur yang diperiksa.
Karena itu tidak ada bukti bahwa hook saat ini menyentuh database; namun
bootstrap berjalan dengan environment/configuration aktif dan tidak boleh
dianggap aman hanya karena command-nya bernama `package:discover`. Provider
paket pihak ketiga dan perubahan provider aplikasi di masa depan dapat
menambahkan side effect saat boot. Audit ini bukan bukti keamanan eksekusi,
tidak menguji server, dan tidak menjalankan Composer atau Artisan.

Kontrol yang direkomendasikan:

- Jangan menjalankan Composer install/update/autoload dump pada lingkungan
  yang konfigurasi aktifnya dapat mengarah ke produksi sebelum review target
  environment dan hook disetujui.
- Untuk prosedur test mendatang, gunakan checkout/profile yang secara eksplisit
  menetapkan environment test serta variabel khusus test sebelum bootstrap;
  tolak `DB_URL` dan `DB_*` generik yang tidak ditinjau.
- Perlakukan pembersihan cache bootstrap Composer sebagai side effect lokal
  yang harus diketahui operator. Jangan mengandalkan cache konfigurasi lama
  sebagai batas keselamatan, dan jangan menjalankan `config:clear` sebagai
  preflight database.
- Guard pre-bootstrap tetap diperlukan untuk launcher test. Ia harus
  memeriksa environment sumber dan target yang diizinkan sebelum menjalankan
  PHPUnit/Artisan, tanpa mencetak nilai credential.

### Matriks kebutuhan test dan kesesuaian harness

`ArchiveManagementTest` memiliki 67 metode test. Semua metode mewarisi
`setUp()` yang memanggil `createSchema()` sebelum membuat user Super Admin,
kategori, dan lokasi fixture. Helper `createSchema()` membuat 18 tabel dengan
`Schema::create()`. Tidak ditemukan `tearDown()` pada class, trait
`RefreshDatabase`/`DatabaseTransactions`, atau operasi `Schema::drop*()` /
`truncate` dalam file. Maka **saat ini tidak ada satu pun test dalam class
tersebut yang dapat langsung dijalankan pada schema persisten tanpa hak DDL**:
meskipun isi test tertentu hanya menampilkan form, `setUp()` tetap mencoba
membuat ulang tabel.

| Kelompok test (nomor baris/metode di file aktual) | Kebutuhan data dan operasi | DDL saat ini | Transaksi/CRUD dan kesesuaian schema persisten |
|---|---|---|---|
| Login form (1 test, `test_login_form_posts_to_same_origin_with_csrf_token`) | Tidak membutuhkan fixture DB berdasarkan badan test | Tetap membuat 18 tabel karena `setUp()` bersama | Kandidat dipindah ke class terpisah tanpa setup database; dapat berjalan tanpa grant DB |
| Arsip, pencarian, scope, QR, dashboard (baris 62–648) | User, kategori, lokasi, arsip, QR dan peminjaman sintetis; request HTTP dan query | Ya, seluruh suite membuat schema | Banyak tes melakukan insert/update dan membaca hasil; dapat ditargetkan ke tabel yang disiapkan DBA setelah setup diubah menjadi fixture DML-only |
| Akun, laporan, ekspor, kategori/lokasi, riwayat, foto/notifikasi (baris 649–1354) | User, archive/history fixtures; upload memakai `Storage::fake()` pada test terkait | Ya | CRUD/read; kebutuhan storage lokal dapat tetap difake. Tidak boleh ada fallback ke session/cache database |
| Peminjaman, permintaan Customer, submission, user dan reset password (baris 1355–2041) | Relasi user/arsip/loan/request, data sintetis, token dan upload file | Ya | CRUD; sebagian alur menguji perubahan atomik dan notifikasi. Memerlukan schema tabel tambahan yang tepat dan efek samping mail/queue terisolasi |
| Import/export CSV dan pengelolaan relasi (baris 2042–2174) | CSV/unggahan sintetis, kategori/lokasi/arsip; validasi dan perubahan data | Ya | DML plus file sementara/fake; import mengubah data sehingga tiap test perlu isolasi dan rollback atau fixture-run terdedikasi |
| Retensi (baris 2175–2308) | Arsip, jadwal, reminder, usulan, user dan notifikasi; command `retention:dispatch-reminders` | Ya | Secara eksplisit menguji transaksi dan idempotensi; command menggunakan aplikasi/test connection yang sama harus diverifikasi dalam harness. Hindari transaksi luar untuk kasus yang memang menguji commit/rollback |
| Kandidat duplikasi (baris 2309–2546) | Pasangan arsip, kandidat lama, reviewer dan metadata; error/rollback/logging sintetis | Ya | DML serta transaksi dan unique-key; uji konkurensi memerlukan koneksi/worker terpisah dan fixture yang tidak berbagi state. Metadata lama harus dibuktikan tidak berubah |
| Ekspor report CSV/XLSX/PDF (baris 2547–2586) | Arsip, kategori, lokasi dan filter sintetis | Ya | Query baca serta download; membutuhkan ekstensi/output path test yang sesuai, tanpa data produksi |

Rentang baris di atas adalah pengelompokan untuk perencanaan, bukan klaim
bahwa masing-masing test hanya menyentuh tabel yang tertulis. Dependencies
akhir harus diverifikasi saat test dipisah berdasarkan relasi/controller
aktual. Seluruh tabel yang dibuat helper saat ini adalah tabel tanpa foreign
key declarations; schema test tersebut berbeda dari schema MariaDB representatif
yang diusulkan pada bagian 5. Mengganti `createSchema()` dengan schema DBA
memerlukan validasi semua tipe, kolom, index, unique key dan FK yang dipakai
oleh test.

### Desain harness untuk schema persisten

Pemisahan yang direkomendasikan sebelum menjalankan test MariaDB:

1. Pertahankan suite SQLite yang ada sebagai lapisan terisolasi yang memiliki
   schema lifecycle sendiri. Jangan mengarahkannya ke MariaDB.
2. Pindahkan test tanpa kebutuhan database (saat ini login form) ke class
   tersendiri agar tidak melewati `createSchema()`.
3. Tambahkan suite/class integrasi MariaDB terpisah yang hanya memakai
   `connection` khusus yang telah di-guard. `setUp()` suite ini **tidak**
   memanggil `Schema::create`, migration, atau perintah Artisan schema.
4. DBA menyiapkan schema dan index sekali pada database test yang disetujui.
   Akun runtime test tidak diberi DDL. Suite memeriksa kesiapan schema melalui
   guard/preflight yang disetujui; jika tabel/kolom/index wajib tidak cocok,
   suite berhenti sebelum fixture/test berjalan—bukan mencoba memperbaikinya.
5. Buat fixture sintetis per test/run melalui DML pada ID/nama namespace unik.
   Pilihan isolasi yang perlu disetujui: (a) transaksi per test lalu rollback
   untuk test biasa; (b) test transaksi/command yang membutuhkan commit
   dijalankan pada schema khusus terisolasi dan dibereskan oleh operator/DBA
   melalui reset yang ditinjau; atau (c) database test terpisah per run/worker
   yang DBA siapkan ulang. Jangan memakai `TRUNCATE`, `DROP`, `migrate:fresh`,
   atau teardown yang menghapus tabel.
6. Jangan memakai transaksi luar pada kasus yang harus membuktikan batas
   transaksi nyata atau concurrency, karena transaksi pembungkus dapat
   mengubah visibilitas commit dan interaksi koneksi kedua. Kasus tersebut
   harus memakai schema/fixture khusus yang sudah disetujui dan cleanup/reset
   DML yang dibatasi ke namespace run, atau database terpisah yang dapat
   disiapkan ulang DBA.
7. Gunakan `Storage::fake()` untuk unggahan, `MAIL_MAILER=array`, queue/cache/
   session driver non-database, dan cegah network dispatch. Test command tetap
   wajib melewati guard connection yang sama.
8. Jangan menganggap kesiapan hanya dari keberadaan tabel. Preflight harus
   memastikan environment/profile, nama DB exact allowlist, connection/driver,
   tidak ada `DB_URL`/override generic yang bertentangan, config cache tidak
   ambigu, schema fingerprint/daftar objek cocok, serta status fixture siap.
   Kegagalan salah satu pemeriksaan harus menghentikan test tanpa koneksi atau
   perubahan data.

DBA harus menyetujui secara eksplisit strategi isolasi fixture, hak DML yang
diperlukan, tanggung jawab reset, cara menjalankan kasus transaksi/concurrency,
dan apakah schema test disposable. Sampai suite MariaDB tanpa DDL dan guard
benar-benar diimplementasikan serta diuji, desain ini belum membuktikan
isolasi atau keamanan.

## 5. Spesifikasi schema test dan rancangan DDL

Bagian ini menentukan objek/constraint yang dibutuhkan, bukan SQL siap
dijalankan. DBA harus meninjau seluruh kolom terhadap controller aktual dan
DDL terverifikasi. Semua tabel transaksional yang dicakup menggunakan InnoDB.
Charset/collation disetujui eksplisit per database/table, dengan skenario
tambahan bila diperlukan untuk menguji case/accent.

### Objek inti

| Tabel | Kolom/tipe penting dan tujuan test | Index/constraint minimum |
|---|---|---|
| `users` | `id INT UNSIGNED`; `nama`, `username`, `password`, `role`, `status`; `division VARCHAR(150) NULL`; timestamps sesuai query/model | PK; username unik; index role/status bila fixture/query memerlukannya. **Test-only** `role VARCHAR` atau enum yang mencakup role target, bukan enum legacy production yang belum mencakup role target |
| `kategori_arsip` | `id INT UNSIGNED`, `nama_kategori`, `keterangan`, `status`, timestamps | PK; nama unik |
| `lokasi` | `id INT UNSIGNED`, `gudang`, `rak`, `lemari`, `box`, metadata yang dipilih test | PK; jangan menambah unique key selain bila schema rujukan memerlukannya |
| `arsip` | `id INT UNSIGNED`; nomor/nama/kode; `kategori_id`, `lokasi_id`, `created_by` unsigned; `tahun YEAR`, `bulan TINYINT UNSIGNED`, `tanggal_masuk DATE`; `classification VARCHAR(32)`; `division VARCHAR(150) NULL`; `status` enum existing; `qr_code VARCHAR(60)`; kondisi/keterangan/timestamps | PK; unique `qr_code`; index nomor/kode/status/tahun-bulan; FK kategori, lokasi, created_by |
| `arsip_files` | ID, `arsip_id`, nama/path/tipe file, `uploaded_by`, created timestamp | PK; index/FK archive dan uploader; file fixture berupa konten sintetis/temp lokal, bukan dokumen produksi |
| `riwayat_arsip` | Bigint ID; nullable `arsip_id`; `user_id`; aktivitas, keterangan, created timestamp | PK; index arsip/user/tanggal sesuai query; FK mengikuti schema test yang disetujui, khusus event tanpa arsip tetap boleh null |
| `jadwal_retensi` | ID, unique `kategori_id`, active/inactive months unsigned smallint, `final_action` enum, `alert_days`, `updated_by`, timestamps | PK; unique kategori; FK kategori dan updater |
| `disposition_requests` | Bigint ID; `arsip_id`, action, reason, status enum legacy, `requested_by`, nullable `reviewed_by`, note, reviewed/completed times, minutes number/note, timestamps | PK; index arsip; index status/created; FKs arsip/requester/reviewer; tidak membuat status baru |
| `retention_reminders` | Bigint ID, arsip ID, reminder type enum, due date, created timestamp | PK; unique `(arsip_id, reminder_type, due_date)`; FK arsip |
| `notifications` | ID/user ID/title/body/type/url/data/is_sent/read_at/created_at sesuai pemakaian | PK; index/FK user jika sesuai schema tervalidasi |
| `duplicate_reviews` | Bigint ID; `archive_id`, `candidate_id`; reason; status enum; reviewer note/ID/time; created timestamp | PK; unique pair `(archive_id, candidate_id)` persis; index status/created; FKs ke dua arsip dan reviewer |

Controller report join kategori/lokasi; archive detail, QR, retensi dan
history memerlukan tabel pendukung di atas. Tabel `peminjaman`/`loan_requests`
tidak termasuk suite fokus Tahap 5I kecuali test file yang dipilih melewati
dashboard/loan relation; jika test tersebut dimasukkan, DBA harus menambah
DDL representatifnya secara eksplisit, bukan membuat stub kolom tidak
terverifikasi.

`ArchiveManagementTest` saat ini membangun 18 tabel pada setiap `setUp()`
melalui `createSchema()`, dengan definisi tes yang lebih sederhana dari
schema yang didokumentasikan. Class tidak memiliki cleanup table-level dan
tidak memakai trait transaksi umum. Karena schema MariaDB persisten sudah
disiapkan DBA serta akun runtime tidak memiliki DDL, class ini belum dapat
langsung dijalankan di sana. Pemisahan test, fixture DML-only, rollback/reset,
dan cakupan schema harus disetujui dan diimplementasikan lebih dahulu sesuai
desain harness di atas.

### Constraint dan perbedaan schema yang wajib disadari

- Dokumen schema existing mencatat `users.role` enum legacy berisi tujuh
  nilai. Role `Admin`, `Kepala Divisi`, dan `Pegawai` belum termasuk enum
  tersebut. Test scope Kepala Divisi memerlukan salah satu dari:
  1. schema test khusus dengan `role VARCHAR` dan kolom lainnya representatif;
     atau
  2. profile fixture terpisah dengan enum test yang diperluas.
- Opsi tersebut sengaja berbeda dari schema production pada kolom role.
  Hasilnya menguji helper/controller dengan role target, bukan kompatibilitas
  enum production atau kesiapan migration role.
- Gunakan tabel existing/representative per test yang dibutuhkan, tetapi
  jangan menyebut schema subset sebagai baseline 19 tabel penuh.
- Tetapkan aksi FK agar test tidak diam-diam menghapus relasi yang mestinya
  restrict/cascade. Ikuti dokumentasi DDL tervalidasi; jangan menyalin semua
  FK dari ringkasan ini tanpa mengecek tipe signedness dan referensi.
- Test duplikasi membutuhkan `uq_duplicate_pair` dan engine InnoDB agar
  pengujian transaksi/concurrency bermakna. Jangan menghapus atau mengupdate
  kandidat lama; kandidat test sepenuhnya sintetis.
- Test retensi membutuhkan `disposition_requests` dengan status yang
  terdokumentasi; tidak ada status cancellation/expiry baru.

### Fixture sintetis yang direncanakan

Gunakan nilai buatan konsisten, bukan identitas/berkas nyata. Gunakan ID
eksplisit hanya bila test perlu urutan deterministik; selain itu ambil ID
hasil insert. Set `created_by`/foreign key valid.

1. **User fixture:** satu Super Admin; satu Petugas Gudang; satu
   Pimpinan/Approver; satu Kepala Divisi untuk test-only role schema; satu
   user tanpa division; Admin/Pegawai untuk membuktikan fail-closed/no grant.
   Role Customer/Verifikator hanya dibuat pada tes yang memerlukannya.
2. **Division fixture:** `Keuangan`, variasi casing dan whitespace yang
   eksplisit seperti ` keuangan `, division lain seperti `Pengadaan`, serta
   NULL/kosong pada user/arsip. Uji hasil SQL dan PHP record check terpisah.
3. **Archive fixture:** klasifikasi Publik, Internal, Internal Divisi,
   Dokumen Rahasia; record dalam divisi yang sama, casing/spasi berbeda,
   divisi lain, division NULL/kosong; satu arsip nonaktif dan satu dipinjam
   untuk negative retensi.
4. **Files/QR:** file metadata sintetis terkait satu arsip per scope; path
   lokal temporer/fake dengan payload dummy. Kode QR unik sintetis.
5. **Report/export:** arsip dalam dan luar divisi dengan kategori/lokasi
   berbeda agar query filter, CSV, XLSX/PDF teruji tanpa mengandalkan UI saja.
6. **History:** satu event dengan arsip dalam divisi, satu dengan arsip beda
   divisi, satu dengan `arsip_id=NULL`, masing-masing actor sintetis. Gunakan
   ini untuk membuktikan semantics LEFT JOIN dan permission existing.
7. **Retention:** satu kategori dan jadwal dengan action per test; arsip
   bertanggal sintetis yang pasti jatuh tempo relatif pada tanggal test
   terkendali; usulan menunggu, disetujui, ditolak, dan dilaksanakan untuk
   jalur state berbeda. Jangan membuat status non-schema.
8. **Duplicate:** pasangan cocok berdasarkan nomor normalisasi dan pasangan
   cocok berdasarkan nama+kategori+tahun; pasangan beda division untuk
   scope; pasangan existing pending/decided dengan metadata sentinel yang
   dibuktikan tetap; sedikitnya 32+ arsip identik memberi lebih dari 500
   pasangan untuk batch test.
9. **Notifikasi/reminder:** recipient Super Admin synthetic; reminder unique
   key dengan tanggal sintetis. Hindari email/network dispatch; queue/mail
   driver test harus sinkron/array/logless sesuai harness yang disetujui.

## 6. Verifikasi akun test tidak dapat mengakses `arsip_bakti`

Prosedur yang disarankan tidak mencoba login ke database produksi dengan akun
test dan tidak menjalankan query terhadap `arsip_bakti` dari aplikasi test:

1. DBA meninjau grants efektif akun test menggunakan mekanisme administrasi
   server seperti `SHOW GRANTS FOR ...` dari sesi DBA yang disetujui. Jangan
   menyalin output yang mengandung informasi sensitif ke log umum.
2. DBA memastikan tidak ada grant global/schema wildcard atau role yang
   memberi akses ke `arsip_bakti`; grants yang diperlukan hanya menyebut
   `arsip_bakti_test`.
3. DBA meninjau effective role/default role, host mapping akun, proxy grants,
   dan grants inherited; jangan hanya memeriksa grant langsung.
4. Aplikasi test memakai username khusus dan connection name khusus yang tidak
   membaca `.env` atau `DB_*` generik.
5. Preflight lokal menolak database selain exact allowlist `arsip_bakti_test`
   sebelum koneksi.
6. Simpan bukti approval/checklist dari DBA dalam tiket/internal change record,
   bukan dalam repository bila bukti tersebut mengandung metadata sensitif.

Keberhasilan koneksi ke `arsip_bakti_test` dan allowlist lokal saja **tidak
membuktikan** user tidak memiliki hak pada database lain; pemeriksaan grant
server-side oleh DBA wajib. Tidak perlu dan tidak direkomendasikan mencoba
koneksi negatif ke database produksi dari test runner.

## 7. Perintah yang menunggu persetujuan

Tidak ada perintah yang dijalankan pada Tahap 5K. Setelah seluruh checklist
disetujui dan profile/guard benar-benar diimplementasikan, calon urutan
operasional adalah:

1. Static/preflight test konfigurasi fail-closed tanpa koneksi.
2. Verifikasi DBA read-only terhadap grants/instance melalui kanal yang
   disetujui.
3. Pemeriksaan metadata read-only pada database test yang telah dikonfirmasi
   (server version, `DATABASE()`, charset/collation, engine, kolom/index/FK).
4. Pemeriksaan jumlah fixture sintetis dan subset tes terarah.
5. Test scope/report/export/history/attachment/QR, lalu duplicate discovery,
   unique key/idempotence/rollback/concurrency, kemudian retention.
6. Suite SQLite lama sebagai regresi terpisah.

Daftar ini bukan izin untuk melakukan koneksi atau menjalankan test. Perintah
aktual harus direview setelah guard terpasang. Jangan menjalankan
`composer setup`, `composer test`, `php artisan test`, migration, `db:wipe`,
`migrate:fresh`, SQL DDL, atau discovery sebelum target disetujui dan
terverifikasi.

## 8. Keputusan dan prasyarat DBA yang masih terbuka

- Identitas/version/isolasi instance MariaDB dan nama database test.
- Akun test baru, pengelolaan secret dan bukti tidak memiliki grant
  `arsip_bakti`.
- Hak DML minimum serta siapa yang menyiapkan schema/fixture.
- DDL representative yang telah dicocokkan dengan server schema yang
  disetujui; schema snapshot lama saja tidak cukup untuk baseline lengkap.
- Charset/collation yang diuji serta apakah diperlukan profil test kedua
  dengan collation berbeda.
- Cara mendukung role target pada fixture tanpa mengklaim menguji enum legacy.
- Batas test concurrency, transaction isolation dan runtime/jumlah data.
- Approval aplikasi atas wrapper/guard/config cache handling sebelum profile
  test MariaDB ditambahkan.
- Keputusan bisnis Tahap 5H tetap terpisah dan belum disahkan; kesiapan DBA
  tidak memberi grant baru atau mengizinkan migration role.

Tahap 5K berhenti pada spesifikasi. Database test belum dibuat; koneksi dan
isolasi belum diuji. Tahap 5I baru dapat dilanjutkan sesudah DBA menyetujui
serta menyediakan prasyarat di atas, dan Tahap 6 tetap tertunda hingga
validasi MariaDB benar-benar dijalankan serta keputusan permission/migration
yang relevan disetujui.
