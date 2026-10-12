# Penyelesaian Tahap 5 dan Kesiapan Tahap 6

## Ringkasan status

- **COMPLETED — pekerjaan lokal yang aman:** audit statis yang diminta,
  fail-closed profile/harness awal, pemeriksaan SQLite, dan paket kesiapan
  migration terdokumentasi.
- **BLOCKED BY DBA — validasi MariaDB:** belum tersedia target test yang telah
  disetujui dan isolasinya dibuktikan. Tidak ada tes MariaDB yang dimulai.
- **BLOCKED BY BUSINESS DECISION — grant/pemetaan role:** kewenangan Admin dan
  Pegawai, pemetaan Customer/Pengguna Umum, dan sebagian kebijakan retensi
  belum disahkan.
- **NOT STARTED — migration role:** tidak ada migration role dibuat atau
  dijalankan; tidak ada akun, enum, grant, permission atau data produksi yang
  diubah. Proyek **belum siap** untuk migration role.

Tidak ada koneksi ke `arsip_bakti`, tes MariaDB, Composer, Artisan, migration,
discovery duplikasi, atau operasi database produksi pada rangkaian ini.

## Lingkungan dan pemeriksaan keselamatan

Pemeriksaan dilakukan tanpa membaca atau mencetak nilai rahasia:

| Pemeriksaan | Hasil |
|---|---|
| Proyek Laravel | `C:\Users\Jabbar\arsip-bakti-laravel` |
| PHP CLI | PHP 8.2.12 |
| `.env.testing` | Tidak tersedia saat pemeriksaan |
| Laravel cached config | `bootstrap/cache/config.php` tidak ada saat pemeriksaan |
| PHPUnit SQLite profile | `phpunit.xml` menetapkan `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL` kosong; tes juga dijalankan dengan variabel proses itu dipaksa eksplisit |
| Process DB/MariaDB-test variables sebelum test | Tidak ditemukan nama variabel `DB_*`, `APP_ENV`, atau `MARIADB_TEST_*` pada proses pemeriksaan; nilainya tidak ditampilkan |
| MariaDB test | Tidak ada endpoint, akun, database, schema, atau attestation DBA test yang dikonfigurasi/dibuktikan; status **belum tersedia/terverifikasi**, bukan klaim bahwa tidak ada server di jaringan |
| Status repository | Folder proyek sebelumnya diketahui bukan Git repository; status perubahan melalui Git tidak dapat digunakan untuk memastikan keadaan historis |

File `database/database.sqlite` ada, tetapi PHPUnit diarahkan ke `:memory:`. Tidak
ada tes yang diarahkan ke file SQLite atau MySQL aktif.

### Composer dan bootstrap

`composer.json` memiliki `post-autoload-dump`: framework hook membersihkan
compiled config/services/packages cache, lalu Composer menjalankan
`artisan package:discover`. Handler `package:discover` membangun package
manifest; pemeriksaan statis hook/provider aplikasi saat ini tidak menemukan
query DB. Namun Artisan tetap bootstrap environment dan provider, sedangkan
cache config dibersihkan lebih dahulu. Karena itu Composer tidak dijalankan.
Script Composer `test`, `setup`, dan project-create juga tidak digunakan.

Profil MariaDB baru memakai PHPUnit langsung, tidak memakai Composer/Artisan.
Bootstrap preflight berjalan sebelum Laravel TestCase dan menolak konfigurasi
yang ambigu. Pada profile MariaDB, config database hanya mengekspos koneksi
`mariadb_test`; koneksi standar MySQL/MariaDB tidak didaftarkan. Nama schema
terikat ke `arsip_bakti_test`, URL tidak dibaca, dan host/user/password
menggunakan namespace environment `MARIADB_TEST_*`.

Guard lokal tidak dapat membuktikan grant efektif maupun mencegah seseorang
yang tidak berwenang mengatur environment marker. Nilai
`MARIADB_TEST_DBA_APPROVED=1` adalah assertion operasional, bukan bukti
kriptografis. DBA tetap harus memverifikasi instance, host mapping, effective
role/grant, proxy/inherited/wildcard grant, dan menegaskan akun test tidak
memiliki akses ke `arsip_bakti`. Tidak boleh mencoba pembuktian dengan
koneksi negatif ke database produksi.

## Implementasi dan file berubah

### Harness / test

- `config/database.php` — tambah connection terpisah `mariadb_test`, target
  schema fixed `arsip_bakti_test`, sumber environment khusus, URL null; mode
  MariaDB profile hanya mendaftarkan connection ini.
- `phpunit.mariadb.xml` — profile suite MariaDB terpisah dari konfigurasi
  SQLite yang ada.
- `tests/Support/MariaDbTestPreflight.php` — menolak nama target di luar
  allowlist/produksi, environment DB generik, profile CLI yang salah, config
  cache, versi/collation yang belum disetujui, atau ketiadaan attestation/
  credential test.
- `tests/Support/mariadb-test-bootstrap.php` — preflight sebelum bootstrapping
  aplikasi dan sebelum koneksi.
- `tests/Unit/MariaDbTestPreflightTest.php` — tes pure untuk validasi target,
  DB produksi, DB_URL/generic DB env, approval, cached config, dan profile.
- `tests/MariaDB/MariaDbTestCase.php` — kesiapan MariaDB dibaca sebelum
  fixture: database aktif, versi/collation, engine InnoDB, schema dan tipe
  `users.role` test; fixture berjalan dalam transaksi dan rollback per test.
- `tests/MariaDB/SyntheticMariaDbFixtures.php` — user/kategori/lokasi/arsip
  sintetis melalui DML saja, ID hasil insert, nilai unique per fixture.
- `tests/MariaDB/ArchiveDivisionScopeTest.php` — test integrasi awal
  membandingkan SQL scope `LOWER(TRIM())` dengan pemeriksaan record PHP,
  termasuk casing, spasi, divisi lain, NULL/kosong, dan klasifikasi rahasia.
- `tests/Feature/ArchiveManagementTest.php` dan suite SQLite yang sudah ada
  tidak diubah; seluruh test regresi lama dipertahankan.

### Dokumentasi

- `docs/role-permission-stage-5l-test-harness-design.md` — diperbarui dengan
  status implementasi parsial dan hasil validasi.
- `docs/role-permission-decision-addendum.md` — diperbarui dengan status guard,
  harness, hasil SQLite, dan blocker.
- `docs/role-permission-stage-5-completion-and-stage-6-readiness.md` — laporan
  konsolidasi ini.

## Status test yang benar-benar dilakukan

### SQLite

Perintah langsung PHPUnit dijalankan dengan environment proses eksplisit:
`APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`,
`DB_URL=`; cache array, session array, dan queue sync. Tidak memakai Composer
atau Artisan.

| Test run | Lulus | Gagal | Dilewati | Assertions |
|---|---:|---:|---:|---:|
| Test unit guard terarah | 7 | 0 | 0 | 13 |
| Suite penuh `phpunit.xml` | 76 | 0 | 0 | 532 |

Suite penuh mencakup seluruh 67 test lama `ArchiveManagementTest`, tes lain
yang telah ada, dan 7 unit test guard. Suite lama tetap memakai SQLite
in-memory dan `createSchema()` sesuai desain existing.

### MariaDB

- Test integrasi yang tersedia di `tests/MariaDB` **tidak dijalankan** karena
  database/akun/schema/attestation DBA belum disediakan atau diverifikasi.
- Profile MariaDB dipanggil sekali tanpa nilai target/approval untuk menguji
  penolakan preflight. PHPUnit berhenti di bootstrap dengan exit code 2
  sebelum Laravel app bootstrap dan sebelum koneksi. Ini verifikasi fail-closed
  untuk skenario target belum disetujui, **bukan** hasil test database.
- Setelah DBA menyiapkan target dan approval disahkan, prosedur yang disiapkan
  adalah menyediakan environment `MARIADB_TEST_*` melalui secret manager/
  runner terkontrol lalu memanggil PHPUnit langsung:

  ```powershell
  & 'C:\xampp\php\php.exe' 'vendor\bin\phpunit' --configuration 'phpunit.mariadb.xml'
  ```

  Password tidak boleh diberikan sebagai argumen command, disimpan dalam
  profile XML, atau ditulis ke log. Perintah ini belum dijalankan dengan
  target DB.
- MariaDB test: **0 lulus, 0 gagal, 0 dilewati oleh test runner; 0 dimulai**.
  Tidak ada jumlah assertions MariaDB.
- Validasi scope MariaDB, laporan/ekspor/riwayat, retensi, discovery,
  `insertOrIgnore`, unique key, batch 500, rollback, dan konkurensi belum
  dilakukan.

### Pemeriksaan sintaks

`php -l` berhasil untuk semua file PHP baru/diubah pada konfigurasi database,
preflight, bootstrap, unit tests, dan suite MariaDB. Tidak ada lint/build
Composer yang dijalankan.

## Perbaikan teknis dan batas cakupan

Tidak ditemukan defect baru pada perilaku aplikasi bisnis yang dapat
diperbaiki tanpa keputusan bisnis atau validasi MariaDB. Perubahan implementasi
terbatas pada pengamanan dan persiapan harness test; tidak ada grant role
ditambah. Query scope belum diklaim kompatibel MariaDB hanya berdasarkan kode
atau hasil SQLite.

Harness MariaDB saat ini baru memiliki preflight, readiness, fixture dasar,
dan satu integration test scope divisi. Test MariaDB report/export/history,
retensi, duplicate discovery/rollback, dan concurrency belum dibuat sebagai
suite eksekusi terpisah. Schema exact dan constraint harus ditinjau DBA
sebelum test tersebut diporting; test error yang ada di SQLite membuat trigger
SQLite melalui DDL, yang tidak boleh dipakai akun DML-only MariaDB.

Pemeriksaan metadata/schema setelah koneksi memakai query read-only dan baru
dilakukan sesudah guard lokal lulus. Walaupun nama database di konfigurasi
tetap allowlisted, client-side check tidak dapat membuktikan host instance
atau grant efektif sebelum koneksi pertama. Approval DBA dan akun tanpa
privilege produksi adalah batas keamanan yang wajib, bukan pengujian opsional.

## Paket persiapan Tahap 6

### Matriks role/permission existing dan target

Kode sumber aktual `app/Support/RolePermissions.php` mendefinisikan
permission statis. Daftar berikut adalah grant kode saat ini, bukan rekomendasi
grant baru:

| Role | Permission katalog yang diizinkan kode saat ini |
|---|---|
| Super Admin | Semua permission global kecuali `customer.portal` dan `loans.request` |
| Petugas Gudang | `dashboard.view`, `archives.list`, `archives.view`, `archives.manage`, `qr.scan`, `qr.manage`, `loans.manage`, `retention.view`, `retention.propose`, `duplicates.review` |
| Admin Unit/Petugas Arsip | `archives.list`, `archives.view`, `archives.manage`, `qr.scan`, `qr.manage`, `loans.manage`, `retention.view`, `retention.propose`, `duplicates.review` |
| Verifikator | `qr.scan`, `duplicates.review` |
| Pimpinan/Approver | `archives.view`, `qr.scan`, `loans.approve`, `retention.view`, `retention.review` |
| Customer | `archives.list`, `archives.view`, `qr.scan`, `loans.request`, `customer.portal` |
| Pengguna Umum | `archives.list`, `archives.view`, `qr.scan`, `loans.request`, `customer.portal` |
| Admin target | Tidak ada permission (`[]`) |
| Pegawai target | Tidak ada permission (`[]`) |
| Kepala Divisi target | `archives.list`, `archives.view`, `qr.scan`, `loans.approve`, `retention.view`, `retention.review`; scope divisi dan bukan dokumen rahasia |

Permission katalog lain yang tidak diberikan ke Admin/Pegawai secara default
meliputi pengelolaan user, report, imports, categories, locations, history,
customer review, confidential archive, serta operasi yang tak tercantum di
role entry. Tidak ada grant baru yang diberikan untuk mempermudah test.

Dokumen matriks terperinci dan seluruh sel persetujuan bisnis tetap berlaku:
[`role-permission-migration-plan.md`](./role-permission-migration-plan.md) dan
[`role-permission-audit.md`](./role-permission-audit.md).

### Schema yang diperlukan menurut rencana existing

Rencana role migration hanya mengubah definisi kolom `users.role` pada tabel
existing. Audit terdahulu mencatat enum tujuh nilai legacy, `NOT NULL`,
default `Customer`; role target `Admin`, `Kepala Divisi`, dan `Pegawai` belum
ada dalam enum. Rencana aditif menambahkan tiga literal itu di akhir, tanpa
mengubah nilai akun, default, nullability, atau tujuh nilai lama.

Ini tetap **rencana**, bukan DDL untuk dijalankan. Schema active inventory
perlu diambil ulang read-only pada salinan yang disetujui sebelum migration
final, karena dokumentasi merupakan snapshot historis. Jangan menjalankan
migration bawaan Laravel yang mencoba membuat ulang `users`; baseline existing
belum memiliki tabel `migrations`, dan migration Laravel final belum diuji.

### Customer menjadi Pegawai

Tidak ada update data yang direncanakan/dijalankan. Role `Customer` adalah
role portal eksternal dan tidak dapat dianggap sinonim `Pegawai`. Pemindahan
akun hanya boleh dipertimbangkan setelah pemilik sistem menetapkan akun mana
yang benar-benar pegawai, pemilik bisnis menyetujui perubahan per-record, dan
ketergantungan alur Customer ditinjau. `Pengguna Umum` juga tidak otomatis
menjadi Pegawai. Migration enum saja tidak mengubah record role.

Perubahan role suatu user dapat mengubah akses ke/kemampuan memakai relasi
berbasis `users.id` pada `customer_requests`, `customer_submissions`,
`customer_arsip_access`, `loan_requests`, `peminjaman`, `notifications`,
`riwayat_arsip`, `disposition_requests`, `duplicate_reviews`,
`password_reset_requests`, dan `remember_tokens`. PK/FK tidak direncanakan
berubah. Tidak ada daftar akun atau data pribadi dicantumkan dalam laporan.

### Strategi migration, rollback, backup, dan pascamigration

Dokumentasi sebelumnya menyatakan forward DDL dan rollback guard pernah
diuji secara ad hoc pada salinan MariaDB 10.4.32, termasuk penolakan rollback
saat role target dipakai dan perbandingan snapshot setelah rollback. Namun
eksperimen itu **bukan Laravel migration**; versi tersebut historis, bukan
bukti server test saat ini. Belum ada validasi terhadap lifecycle migration
repository Laravel, schema drift saat ini, deployment concurrency, backup/
restore, atau recovery pada salinan yang baru disediakan.

Urutan minimum sebelum implementasi atau penerapan:

1. Selesaikan approval bisnis; ambil snapshot schema dan row counts terbaru
   secara read-only dari salinan QA yang terisolasi.
2. Buat migration Laravel sempit setelah desain disetujui: preflight exact
   enum/default/nullability/charset/collation; fail closed pada drift; DDL
   aditif; jangan update users.
3. Uji `up` dan `down` final Laravel migration pada clone disposable MariaDB
   yang cocok versi/schema; uji rollback saat target role masih dipakai dan
   pastikan gagal sebelum DDL.
4. Bandingkan schema metadata (enum literal/order/default/nullability,
   charset/collation, tables/columns/indexes/FKs) dan row counts sebelum,
   sesudah `up`, sesudah `down`; pastikan tidak ada perubahan role/data.
5. Verifikasi prosedur backup serta restore pada environment terisolasi dan
   kriteria abort/rollback sebelum jendela produksi. Persiapkan maintenance,
   backup baru, monitoring, operator, serta recovery runbook.
6. Produksi hanya setelah approval terpisah, backup/restore siap, smoke test
   dan postmigration read-only verification disepakati. Tidak ada bagian
   langkah ini dilakukan sekarang.

## Keputusan tertunda

### DBA / infrastruktur

- Instance MariaDB test terpisah, database `arsip_bakti_test`, versi/host/
  network boundary, collation, InnoDB dan schema fingerprint.
- Akun khusus dan bukti effective grants tidak dapat mengakses `arsip_bakti`;
  secret management yang disetujui.
- DDL fixture test yang representatif, `users.role` test-only mendukung role
  target, index/unique key/FK, dan fixture reset tanpa DDL.
- Strategy isolasi transaksi, test yang perlu commit, schema disposable per
  concurrency run, serta prosedur cleanup/reset DBA.

### Pemilik bisnis/aplikasi

- Semua kewenangan Admin/Pegawai dan apakah scope Admin global/unit.
- Apakah Verifikator tetap permission terpisah atau dipetakan ke target.
- Customer dan Pengguna Umum tetap terpisah; akun mana (jika ada) yang secara
  eksplisit disetujui berpindah ke Pegawai.
- Hak Kepala Divisi dan definisi scope divisi untuk data tanpa division/
  metadata/global event; laporan, ekspor, history dan approval.
- Retensi: apakah reviewer berbeda dari pelaksana, apakah approval formal
  tahap terpisah, pembatalan usulan, status rejected/expired/executed, dan
  audit trail. Jangan anggap reviewer harus berbeda dari pelaksana.
- Apakah permission tetap statis di kode atau memerlukan tabel/config runtime.

## Kesiapan dan pekerjaan yang belum selesai

**Tahap 6 belum siap.** Local harness mulai tersedia, suite SQLite lulus, dan
design migration terdokumentasi; tetapi tidak ada MariaDB test environment
terverifikasi, implementasi/full suite integrasi MariaDB belum lengkap,
final Laravel migration belum diuji pada clone, keputusan bisnis kritis belum
disahkan, dan backup/restore belum dibuktikan.

Sebelum tahap migration:

- DBA menyediakan/menyetujui test target dan membuktikan isolasi/grants.
- Pemilik aplikasi menyetujui role/permission, Customer transition, serta
  policy retensi yang masih terbuka.
- Lengkapi suite MariaDB untuk scope, report/export/history, retensi,
  discovery/idempotensi/unique/rollback/concurrency dan perbedaan driver.
- Uji keseluruhan test MariaDB dengan schema approved, lalu suite SQLite
  regresi; laporkan hasil per driver.
- Implementasikan dan uji Laravel migration/rollback hanya pada clone
  terisolasi, verifikasi backup/restore; **jangan** mengubah production sebelum
  review dan approval deployment terpisah.

Tidak ada akun, role, permission, grant, enum, database, schema production,
kandidat duplikasi, atau kebijakan bisnis yang berubah pada pekerjaan ini.
