# Tahap 5J — Rancangan Lingkungan MariaDB Test Terisolasi

Status: audit konfigurasi dan rancangan saja. Tidak ada koneksi database,
pembuatan/perubahan schema atau akun, tes, migration, discovery, atau operasi
tulis yang dilakukan. MariaDB test belum tersedia/terverifikasi.

## 1. Konfigurasi yang diperiksa

Audit mencakup `config/database.php`, `phpunit.xml`, `.env.example`, nama file
`.env*`, `composer.json`, catatan skema, dan status Git dari folder project.
Nilai rahasia tidak dibaca atau disalin ke dokumen ini.

| Sumber | Kondisi yang ditemukan | Implikasi |
|---|---|---|
| `.env` aktif | `APP_ENV=local`, default connection `mysql`, database terkonfigurasi bernama `arsip_bakti`; host lokal port 3306 | Perintah Artisan biasa yang bootstrap Laravel dapat memakai koneksi produksi. Tidak aman untuk tes. |
| `.env.testing` | Tidak ditemukan | Tidak ada file environment testing khusus yang dapat diperiksa. |
| `phpunit.xml` | Menetapkan `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL` kosong, session/cache array | Jalur `php artisan test` yang menggunakan konfigurasi ini dimaksudkan untuk SQLite in-memory. Ini bukan MariaDB test. |
| `config/database.php` | Koneksi `mysql` dan `mariadb` sama-sama mengambil host, port, database, user dan password dari variabel generik `DB_*`; `DB_URL` juga tersedia | Jika variabel pengujian tidak mengunci koneksi dan nama database, bootstrap dapat mengambil konfigurasi dari `.env` atau environment proses. |
| `composer.json` script `test` | Menjalankan `artisan config:clear`, lalu `artisan test` | Menghapus cache konfigurasi sebelum test; test tetap bergantung pada bootstrap dan environment PHPUnit. Jangan gunakan script ini untuk MariaDB sampai guard/konfigurasi terisolasi disiapkan dan ditinjau. |
| `composer.json` script `setup` | Menjalankan `artisan migrate --force` | Perintah ini berbahaya untuk database yang salah sasaran; jangan digunakan untuk menyiapkan MariaDB test atau project existing. |
| `composer.json` `post-create-project-cmd` | Menjalankan `artisan migrate --graceful` | Dapat menjalankan migration dalam proses setup project. Jangan dipicu untuk workspace ini atau database existing. |
| Git | Folder workspace tidak dikenali sebagai repository Git | Status perubahan lokal tidak dapat dibuktikan dengan Git. Hindari menimpa file lokal. |

Dalam konfigurasi PHPUnit, `<env>` tanpa `force="true"` tidak boleh dianggap
sebagai perlindungan jika variable yang sama sudah ditetapkan oleh proses yang
menjalankan test. Environment proses dan dotenv juga dapat memengaruhi nilai
yang dibaca Laravel. Oleh sebab itu nama database di `phpunit.xml` saja bukan
allowlist keselamatan yang memadai.

## 2. Jalur risiko menuju `arsip_bakti`

Risiko yang dapat dibuktikan dari konfigurasi/kode:

1. Perintah Artisan biasa tanpa konfigurasi PHPUnit menggunakan `.env` aktif,
   yang default connection-nya adalah MySQL dan database-nya `arsip_bakti`.
2. PHPUnit sekarang menetapkan SQLite, tetapi override environment proses atau
   perubahan file konfigurasi testing dapat mengubah hasil efektif. File
   `.env.testing` yang dibuat di masa depan juga dapat menambah jalur konfigurasi.
3. `DB_URL` dapat mengambil alih sebagian konfigurasi driver jika tidak
   dikosongkan secara eksplisit dalam proses testing.
4. Script Composer `setup` menjalankan `migrate --force`; script ini tidak
   boleh dipakai sebagai bootstrap database test.
5. Database aktif tidak mempunyai baseline migration aplikasi yang aman:
   migration Laravel bawaan masih pending/tidak kompatibel dengan schema
   existing. Menjalankan migration pada target yang keliru dapat berdampak
   langsung pada database.
6. Server lokal yang mendengarkan port 3306 tidak membuktikan bahwa database
   bernama tertentu adalah database test atau bukan produksi. Tidak dilakukan
   koneksi untuk mencoba mengidentifikasinya.

## 3. Rancangan database dan akun pengujian

### Database

Nama yang diusulkan: **`arsip_bakti_test`**. Nama tersebut hanya usulan dan
belum dibuat atau diverifikasi. Sebelum dipakai, DBA/pemilik lingkungan harus
memastikan database tersebut berada pada instance test yang disetujui,
terisolasi dari `arsip_bakti`, disposable, dan tidak berisi data penting.

Jangan menyalin database/dump produksi atau data pengguna, arsip, dokumen,
token, lampiran maupun kandidat duplikasi. Fixture sepenuhnya sintetis dengan
nama, ID, division, dan nilai file buatan khusus untuk test.

### Akun MariaDB test

Minta DBA menyediakan akun khusus test dengan password yang dikelola di luar
repository, misalnya secret environment pada proses CI/test. Jangan memakai
kredensial produksi. Hak minimum yang diperlukan untuk schema test yang sudah
dibuat sebelumnya:

- `SELECT`, `INSERT`, `UPDATE`, `DELETE` hanya pada schema
  `arsip_bakti_test`, untuk test fitur yang membuat/mengubah fixture;
- hak tambahan hanya jika skenario tertentu benar-benar memerlukannya dan
  DBA menyetujuinya;
- tidak ada hak pada schema `arsip_bakti`, tidak ada hak `DROP DATABASE`,
  `CREATE USER`, atau hak global;
- `CREATE`/`ALTER`/`INDEX`/`REFERENCES` hanya bila nanti diputuskan test akan
  membangun schema sintetis otomatis. Rancangan yang direkomendasikan adalah
  schema disiapkan terpisah oleh DBA, sehingga akun runtime test tidak
  memerlukan hak DDL.

Keberhasilan koneksi saja tidak cukup sebagai verifikasi izin minimum.
DBA harus memastikan secara independen grant akun hanya menunjuk schema test.

## 4. Strategi schema dan fixture

### Rekomendasi: schema integrasi sintetis yang representatif

Siapkan schema MariaDB test terpisah dengan DDL yang ditinjau untuk tabel yang
benar-benar disentuh test, sekurangnya mencakup kebutuhan arsip, pengguna,
kategori/lokasi, lampiran, riwayat, laporan/ekspor, retensi/reminder/notifikasi,
serta `duplicate_reviews`. Definisi perlu merepresentasikan jenis tipe dan
perilaku MariaDB yang sedang divalidasi, termasuk:

- `division` nullable dan teks dengan charset/collation yang disetujui untuk
  skenario, dengan variasi capitalization/spasi/NULL/kosong yang dibuat
  sintetis;
- engine InnoDB pada tabel yang menguji transaksi dan `FOR UPDATE`;
- unique key pasangan kandidat `(archive_id, candidate_id)`;
- index dan foreign key relevan untuk join, insert dan integritas relasi;
- status/enum, lebar kolom, unsigned integer, timestamp dan default yang
  memang dipakai query/controller;
- row fixture untuk divisi cocok/beda, arsip tanpa division, tingkat
  klasifikasi, arsip jatuh tempo, kandidat lama, serta audit event yang
  terhubung/tidak terhubung ke arsip.

Schema dapat dibentuk dari definisi tabel yang ditinjau dan didokumentasikan,
tetapi tidak boleh mengimpor dump lama yang tidak lengkap/destruktif atau
mengambil baris produksi. Jangan menjalankan migration Laravel bawaan maupun
migration role. Sebelum dibuat, DBA dan pemilik aplikasi harus menyetujui DDL,
schema target, proses reset data sintetis, dan hak akun.

### Kapan perlu schema penuh?

| Pilihan | Kelebihan | Batasan |
|---|---|---|
| Schema minimal khusus test dengan tabel/constraint representatif | Permukaan kecil, tidak membutuhkan data produksi, mudah diisolasi, cepat untuk test query/fitur yang dibatasi | Tidak membuktikan seluruh schema aplikasi, semua foreign key, semua endpoint, atau baseline migration; fixture/schema bisa berbeda dari production jika tidak direview |
| Salinan struktur lengkap yang direkonstruksi dari DDL terverifikasi, tanpa data produksi | Lebih realistis untuk interaksi tabel/constraint lintas modul | Memerlukan DDL lengkap yang diverifikasi dan review DBA; snapshot schema lama saja dapat drift; bukan database production clone dan tetap tidak menguji data/ukuran production |

Untuk Tahap 5I, gunakan schema sintetis yang cukup untuk rangkaian test fokus
dan cocokkan definisi tabel yang relevan dengan inventaris schema terdokumentasi.
Jangan menyebutnya validasi baseline lengkap. Bila DDL terverifikasi lengkap
belum tersedia, catat tabel/constraint yang tidak tercakup dan jangan
menjalankan test yang memerlukannya.

## 5. Rancangan konfigurasi Laravel yang aman

Belum ada konfigurasi atau script yang dibuat pada Tahap 5J. Sebelum
implementasi konfigurasi, tinjau dan setujui rancangan berikut:

1. Tambahkan konfigurasi PHPUnit khusus, misalnya `phpunit.mariadb.xml`,
   terpisah dari `phpunit.xml`. Tetap pertahankan `phpunit.xml` saat ini untuk
   SQLite in-memory.
2. Paksa environment testing (`APP_ENV=testing`, `DB_URL` kosong, driver
   MariaDB, nama database tetap `arsip_bakti_test`) dengan override yang tidak
   dapat dikalahkan environment proses yang tidak sengaja diwarisi.
3. Ambil host/user/password test dari secret environment khusus test; jangan
   menaruh kredensial dalam XML, source, output command, log, atau dokumentasi.
   Jangan mengambil `DB_*` produksi sebagai fallback.
4. Tambahkan guard preflight yang berjalan sebelum test dapat memperoleh
   koneksi: hanya menerima driver yang diizinkan dan nama database yang cocok
   persis dengan allowlist eksplisit, menolak `arsip_bakti`, menolak nama
   kosong/URL database, dan berhenti dengan error jelas bila environment
   wajib hilang.
5. Guard sebaiknya memeriksa konfigurasi yang sudah efektif tanpa membuka
   koneksi; baru setelah guard lulus boleh suite membuka koneksi ke target yang
   telah disetujui. Tidak boleh ada query untuk “mencoba” menebak apakah suatu
   target adalah produksi.
6. Jangan menambahkan `migrate`, `migrate:fresh`, `db:wipe`, DDL atau
   discovery ke bootstrap test. Schema disediakan lebih dahulu secara manual
   dan disetujui. Jangan jalankan Composer `setup` atau hook setup yang
   menjalankan migration.
7. Pertimbangkan guard kedua pada test bootstrap agar test yang dijalankan
   dengan konfigurasi lain tidak dapat fallback ke `mysql` dari `.env`.
   Jangan mengandalkan `phpunit.xml` saja sebagai satu-satunya boundary.
8. Dokumentasikan cara memastikan koneksi aktif dan nama database sebelum
   suite; rahasia jangan dicetak. Verifikasi server/version dan schema harus
   dilakukan melalui kanal DBA yang disetujui setelah target test dibuat.

## 6. Prosedur verifikasi isolasi dan perintah yang menunggu persetujuan

Tidak ada perintah koneksi/test yang dijalankan. Langkah berikut baru boleh
dilakukan setelah database, akun, schema, konfigurasi dan target disetujui:

1. DBA menyediakan instance/schema test bernama `arsip_bakti_test`, akun
   khusus, secret environment dan grant terbatas; konfirmasi tidak ada hak
   terhadap `arsip_bakti`.
2. DBA/pemilik aplikasi meninjau DDL sintetis, engine, collation, unique key,
   foreign key dan fixture. Tidak ada import data produksi.
3. Tambahkan dan review config/test preflight yang fail-closed sebagaimana
   rancangan bagian 5; lakukan pemeriksaan konfigurasi tanpa koneksi dan
   pastikan kasus `DB_DATABASE=arsip_bakti`, DB name lain, `DB_URL` terisi,
   koneksi kosong, atau environment override yang tidak diizinkan semuanya
   berhenti sebelum test.
4. Hanya setelah review eksplisit, jalankan preflight read-only untuk memastikan
   target efektif. Kemudian identifikasi versi server serta schema test melalui
   query metadata read-only ke database test yang disetujui.
5. Jalankan subset MariaDB terarah pada scope divisi, laporan/ekspor/riwayat,
   lampiran/QR/retensi, dan discovery duplikasi. Pastikan test tidak memanggil
   migration atau discovery terhadap target selain fixture test.
6. Jalankan tes transaksi/rollback dan pengulangan discovery; uji dua proses
   bersamaan jika runner/instance test mendukungnya. Catat versi, test count,
   assertions, hasil dan batasan.
7. Jalankan kembali suite SQLite terpisah sebagai lapisan regresi. SQLite tidak
   menggantikan bukti MariaDB.

Contoh nama perintah yang dapat diputuskan pada implementasi mendatang,
**bukan perintah yang boleh dijalankan sekarang**:

```text
php artisan test --configuration=phpunit.mariadb.xml --filter=division
php artisan test --configuration=phpunit.mariadb.xml --filter=duplicate
php artisan test --configuration=phpunit.mariadb.xml --filter=retention
php artisan test --configuration=phpunit.xml
```

Perintah tersebut hanya boleh dipakai setelah konfigurasi dan guard benar-benar
dibuat, ditinjau, serta target database test diverifikasi. Jangan jalankan
`composer setup`, `php artisan migrate`, `migrate:fresh`, `db:wipe`, atau
command apa pun yang mengarah ke database aktif.

## 7. Risiko dan prasyarat persetujuan

- Database test, versi MariaDB, schema test dan akun dengan grant minimum
  belum tersedia/terverifikasi.
- Port lokal yang terbuka tidak membuktikan isolasi atau identitas instance.
- Folder bukan Git repository; perubahan lokal tidak dapat diperiksa dengan
  `git status`. Dokumentasi lokal lain harus dipertahankan.
- Schema production yang didokumentasikan adalah snapshot lama; jangan
  merekonstruksi schema penuh tanpa DDL terverifikasi dan review DBA.
- PHPUnit SQLite saat ini tidak membuktikan MariaDB. Override proses,
  `.env.testing` mendatang, config cache dan `DB_URL` adalah jalur yang perlu
  dikunci sebelum test.
- Migration bawaan dan Composer setup tidak aman untuk dipakai sebagai proses
  pembuatan schema pada project existing ini.
- Pengujian concurrency bergantung pada runner dan instance yang disetujui.
- Keputusan role/retensi Tahap 5H yang belum disahkan tetap menjadi prasyarat
  terpisah sebelum grant baru atau Tahap 6; validasi lingkungan tidak memberi
  persetujuan kebijakan tersebut.

### Persetujuan manual sebelum lingkungan dibuat

1. Persetujuan DBA atas instance test, nama `arsip_bakti_test`, akun khusus dan
   hak akses yang dibatasi ke schema tersebut.
2. Persetujuan DDL/schema sintetis, charset/collation, engine, constraint dan
   fixture; konfirmasi tidak memakai data personal/produksi.
3. Persetujuan rancangan konfigurasi fail-closed dan mekanisme secret yang
   tidak mengubah `.env` aktif, APP_KEY atau kredensial produksi.
4. Persetujuan target serta subset test sebelum koneksi dibuka dan operasi
   fixture apa pun dilakukan.

Tahap 5J berhenti pada rancangan. Tidak ada database test yang dibuat atau
diubah; jangan menganggap MariaDB test tersedia sampai identitas dan isolasinya
dikonfirmasi secara independen.
