# Matriks Permission Finalisasi dan Rencana Migration Role

Status: dokumentasi dan uji aditif hanya pada salinan MariaDB. Tidak ada
perubahan database produksi atau penerapan migration ke database aktif.

## Sumber dan keputusan dasar

Rujukan tahap ini adalah
[`role-permission-audit.md`](./role-permission-audit.md),
[`database-existing-schema.md`](./database-existing-schema.md),
`app/Support/RolePermissions.php`, `app/Support/ArchiveAccess.php`, serta tes
role Tahap 3 di `tests/Feature/ArchiveManagementTest.php`.

Kolom produksi yang tercatat pada audit skema adalah
`users.role ENUM('Super Admin','Petugas Gudang','Admin Unit/Petugas Arsip','Verifikator','Pimpinan/Approver','Customer','Pengguna Umum') NOT NULL DEFAULT 'Customer'`,
tanpa index dan foreign key pada kolom tersebut. Role target `Admin`,
`Kepala Divisi`, dan `Pegawai` belum ada dalam enum. Tes Tahap 3 membuktikan
penolakan Admin/Pegawai tanpa grant, scope Kepala Divisi pada arsip dan
persetujuan, dan akses Verifikator ke verifikasi duplikasi.

**Matriks ini final untuk persiapan desain, bukan persetujuan bisnis atas grant
Admin/Pegawai.** Belum ada dasar dalam audit untuk menentukan kewenangan
operasional keduanya, sehingga tidak ada grant baru diusulkan sebagai aktif.
Semua sel bertanda “Perlu persetujuan” tetap ditolak sampai pemilik sistem
menentukan izin. Super Admin mempertahankan hak administratif global; hak yang
khusus akun eksternal (portal Customer dan mengajukan pinjaman sebagai customer)
tidak diberikan kepadanya.

## Definisi operasi

- **Lihat**: membuka daftar/detail atau membaca data pada scope yang diizinkan.
- **Buat/ubah/hapus**: tindakan tulis pada data. “Hapus” tidak mencakup penghapusan
  fisik kecuali disebutkan; beberapa modul menggunakan nonaktif atau arsip audit.
- **Verifikasi**: meninjau/menetapkan status verifikasi.
- **Setujui**: keputusan persetujuan, terpisah dari verifikasi.
- **Impor/ekspor**: impor mencakup pratinjau dan konfirmasi; ekspor mencakup
  unduhan laporan/data.
- **Kelola pengguna**: buat pengguna, ubah status/divisi/role, atau menyiapkan
  tautan reset. Hak ini sensitif dan tidak otomatis mengikuti nama `Admin`.

`Super Admin` di bawah berarti permission aplikasi saat ini, dengan pengecualian
operasi akun customer-eksternal. Role legacy Verifikator/Customer/Pengguna Umum
tetap berada di luar empat role utama dan mempertahankan jalur masing-masing.

## Matriks izin per modul dan operasi

Singkatan role: **SA** = Super Admin; **A** = Admin; **KD** = Kepala Divisi;
**P** = Pegawai. “—” berarti tidak diberi akses oleh rancangan ini. “Scope KD”
berarti hanya record dalam divisinya dan tidak termasuk arsip rahasia.

| Modul / operasi | SA | A | KD | P |
|---|---|---|---|---|
| Dashboard — lihat | Ya, global | Perlu persetujuan; scope dan metrik | Perlu persetujuan; divisi sendiri saja | Perlu persetujuan; metrik operasional saja |
| Scan QR — lihat/lookup | Ya, sesuai akses arsip | Perlu persetujuan | Perlu persetujuan; Scope KD | Perlu persetujuan; sesuai tugas |
| Label QR/box — lihat/cetak | Ya | Perlu persetujuan; apakah termasuk pengelolaan operasional | — secara default; perlu persetujuan | Perlu persetujuan; kebutuhan tugas |
| Arsip — daftar/cari/detail/riwayat/lampiran | Ya, semua klasifikasi | Perlu persetujuan; global atau divisi | Ya, Scope KD | Perlu persetujuan; scope tugas |
| Arsip — buat | Ya | Perlu persetujuan; administrasi atau operasional | — secara default | Perlu persetujuan |
| Arsip — ubah | Ya | Perlu persetujuan; scope belum jelas | — secara default | Perlu persetujuan |
| Arsip — hapus/nonaktif | Tidak ada hapus umum; tindakan sesuai aturan bisnis dan audit | — sampai aturan disetujui | — | — |
| Arsip rahasia — baca/buat/ubah | Ya sesuai kebijakan Super Admin | — | — | — |
| Peminjaman operasional — lihat/buat/kembali/ubah | Ya | Perlu persetujuan; apakah Admin mengelola sirkulasi | — secara default | Perlu persetujuan; tugas operasional |
| Permintaan pinjaman customer — buat/lihat milik sendiri | Bukan fungsi akun SA | — | — | — |
| Persetujuan pinjaman — lihat/putuskan | Ya, global | Perlu persetujuan; pemisahan tugas dan scope | Ya, Scope KD; tidak boleh memutus lintas divisi | — |
| Retensi — lihat daftar dan usulan | Ya, global | Perlu persetujuan | Ya, Scope KD | Perlu persetujuan |
| Retensi — buat/ubah jadwal | Ya | Perlu persetujuan; tata kelola JRA | — | — |
| Retensi — buat usulan penyusutan | Ya jika sesuai pemisahan tugas | Perlu persetujuan | Perlu persetujuan | Perlu persetujuan |
| Retensi — verifikasi/setujui usulan | Ya, tidak boleh menyetujui usulan sendiri | Perlu persetujuan; reviewer dan scope | Perlu persetujuan; Scope KD dan larangan self-approval | — |
| Retensi — catat pelaksanaan/hapus status arsip sesuai disposisi | Ya, dengan disposisi disetujui dan berita acara | Perlu persetujuan | Perlu persetujuan; Scope KD | — |
| Customer — verifikasi dokumen/permintaan akses | Ya saat ini | Perlu persetujuan; reviewer, scope, pemisahan tugas | — secara default | — |
| Duplikasi — lihat kandidat/verifikasi/tetapkan hasil | Ya | Perlu persetujuan; apakah Verifikator dipindah ke Admin | — secara default | Perlu persetujuan; atau pertahankan izin fungsional terpisah |
| Laporan — lihat/cetak/ekspor | Ya, global sesuai aturan data | Perlu persetujuan; global atau divisi | Perlu persetujuan; Scope KD | — secara default |
| Impor data — preview/konfirmasi | Ya | Perlu persetujuan; jenis data, scope, dan audit | — | — |
| Ekspor data — arsip/master | Ya, global sesuai aturan data | Perlu persetujuan; pembatasan kolom/scope | — secara default | — |
| Kategori — lihat/buat/ubah/nonaktif | Ya | Perlu persetujuan; master global | — | — |
| Lokasi — lihat/buat/ubah/nonaktif | Ya | Perlu persetujuan; master global | — | — |
| Riwayat aktivitas — lihat/ekspor | Ya, audit global | Perlu persetujuan; scope divisi dan privasi | Perlu persetujuan; hanya divisinya bila dibutuhkan | — secara default |
| Pengguna — lihat | Ya | Perlu persetujuan; cakupan unit | — | — |
| Pengguna — buat/ubah role/divisi/status/reset | Ya, dengan proteksi Super Admin terakhir | Perlu persetujuan; batasi eskalasi role dan akun Super Admin | — | — |
| Profil/password/foto akun sendiri | Ya | Ya | Ya | Ya |
| Notifikasi sendiri | Ya | Ya | Ya | Ya |
| Portal customer/dokumen/pengajuan eksternal | Tidak sebagai akun Customer; reviewer memakai permission terpisah | — | — | — |
| Role legacy Verifikator | Di luar matriks empat role; hak lama tetap | Belum dipetakan | Belum dipetakan | Belum dipetakan |
| Role legacy Customer/Pengguna Umum | Di luar matriks pegawai; portal publik dan permintaan milik sendiri tetap | Sama | Sama | Sama |

Operasi penghapusan fisik sengaja tidak diberikan sebagai grant umum. Endpoint
yang ada menggunakan aturan khusus, misalnya nonaktif kategori/lokasi jika telah
dipakai; setiap aksi hapus yang kelak ditambahkan perlu keputusan terpisah dan
audit trail.

### Keputusan bisnis yang masih diperlukan

1. Hak Admin: administrator sistem atau administrator operasional per unit?
   Tentukan operasi daftar/buat/ubah arsip, sirkulasi, verifikasi Customer,
   duplikasi, retensi, laporan, impor/ekspor, master data, dan pengguna.
2. Hak Pegawai: apakah hanya membaca/scan, atau boleh memasukkan/mengubah arsip,
   meminjamkan/mengembalikan, mencetak label, dan mengusulkan retensi?
3. Apakah Kepala Divisi boleh menyetujui retensi/pinjaman dan melihat data
   lintas-kategori, dengan larangan self-approval? Matriks di atas mengusulkan
   scope divisi, tetapi pemilik bisnis harus menyetujui definisi “divisi arsip”.
4. Apakah verifikasi Customer dan verifikasi duplikasi adalah permission
   fungsional khusus yang tetap terpisah dari empat role?
5. Apakah Admin boleh membuat role/akun eksternal, menyiapkan reset, atau hanya
   melihat pengguna dalam unit? Bagaimana mencegah peningkatan privilege?
6. Apakah Customer dan Pengguna Umum tetap dua role eksternal yang berbeda?
   Jangan ubah keduanya menjadi Pegawai.
7. Apakah perlu tabel permission runtime? Saat ini grant statis di kode; belum
   ada UI atau tabel untuk konfigurasi izin per pengguna.

## Rancangan migration aditif

Migration kelak hanya memodifikasi definisi kolom `users.role` di tabel existing.
Tidak membuat ulang tabel, menghapus nilai enum, mengubah default, mengubah data
role, menambah index/constraint, atau mengubah panjang kolom lain. Bentuk konseptual:

```sql
ALTER TABLE `users`
  MODIFY COLUMN `role`
  ENUM(
    'Super Admin',
    'Petugas Gudang',
    'Admin Unit/Petugas Arsip',
    'Verifikator',
    'Pimpinan/Approver',
    'Customer',
    'Pengguna Umum',
    'Admin',
    'Kepala Divisi',
    'Pegawai'
  )
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci
  NOT NULL
  DEFAULT 'Customer';
```

Perintah tersebut merupakan rencana, bukan file migration dan bukan instruksi
untuk database aktif. Urutan tujuh nilai lama tetap dipertahankan, tiga nilai
baru ditambahkan di akhir. Menambahkan enum dengan urutan lama sebagai prefiks
menghindari perubahan ordinal role existing. `MODIFY COLUMN` harus menyebut
secara eksplisit charset, collation, nullability, dan default agar tidak
menghilangkan atribut kolom.

### Index, constraint, dan panjang kolom

- Audit existing melaporkan `role` tanpa index sendiri dan tanpa foreign key;
  primary key serta unique index username tidak bergantung pada definisi enum.
- `ENUM` bukan `VARCHAR`; panjang literal `Admin Unit/Petugas Arsip` yang ada
  tidak boleh diterjemahkan menjadi lebar kolom atau diganti menjadi `VARCHAR`
  tanpa keputusan skema tersendiri.
- `users` tetap InnoDB, `utf8mb4_unicode_ci`; tabel, index, PK, dan seluruh FK
  tidak perlu dibuat ulang.
- Tetap periksa `SHOW CREATE TABLE users` tepat sebelum migration karena skema
  dokumentasi adalah snapshot dan dapat berubah sejak audit.

### MariaDB dan risiko DDL

Target yang diperiksa adalah MariaDB `10.4.32`. `ALTER TABLE ... MODIFY COLUMN`
untuk memperluas daftar ENUM kompatibel pada versi uji ini. DDL MariaDB melakukan
implicit commit; kegagalan/rollback aplikasi tidak boleh diasumsikan membatalkan
perubahan DDL. Migration produksi perlu preflight, logging, maintenance/change
window, backup terverifikasi, dan postflight.

### Rollback wajib menolak role target yang masih dipakai

Jangan pernah melakukan rollback semacam berikut tanpa pemeriksaan, karena
nilai enum target yang tersimpan dapat menjadi nilai invalid dan diubah/hilang:

```sql
ALTER TABLE `users` MODIFY COLUMN `role`
  ENUM('Super Admin','Petugas Gudang','Admin Unit/Petugas Arsip','Verifikator',
       'Pimpinan/Approver','Customer','Pengguna Umum')
  NOT NULL DEFAULT 'Customer';
```

Sebelum DDL rollback, hitung nilai target dan hentikan dengan error yang
eksplisit bila masih ada:

```sql
SELECT `role`, COUNT(*) AS `account_count`
FROM `users`
WHERE `role` IN ('Admin', 'Kepala Divisi', 'Pegawai')
GROUP BY `role`;
```

Jika query mengembalikan baris, rollback **harus gagal aman** dan mempertahankan
enum yang mencakup target. Operator perlu memutuskan migrasi akun target kembali
ke role yang disetujui melalui prosedur terpisah dan dengan persetujuan pemilik
data; migration rollback tidak boleh memetakan atau menghapus akun. Setelah
query kosong, rollback dapat mengembalikan enum lama dengan charset/collation,
nullability, default, dan atribut persis yang tercatat pada preflight.

## Hasil uji salinan MariaDB terisolasi

Database salinan `arsip_bakti_test_copy` yang dibuat pada Tahap 1B dipakai untuk
uji. Harness pengujian mewajibkan environment variable `DB_DATABASE` sama persis
dengan nama salinan, lalu memaksa koneksi aplikasi ke database itu dan memeriksa
database, driver, host, dan port sebelum DDL. Database aktif `arsip_bakti` tidak
menjadi target DDL.

Snapshot sebelum uji pada salinan:

| Metadata | Sebelum |
|---|---:|
| MariaDB | `10.4.32-MariaDB` |
| `users.role` | Enum tujuh role lama; `NOT NULL`; default `'Customer'`; `utf8mb4_unicode_ci` |
| Akun per role | Super Admin 1; Petugas Gudang 1; Customer 1 |
| Base tables | 20 (19 tabel bisnis + tabel `migrations` milik proses baseline Tahap 1B) |
| Kolom | 184 |
| Indeks berbeda | 75 |
| Foreign-key constraint | 36 |

Pemeriksaan read-only setelah uji pada database aktif `arsip_bakti`
mengonfirmasi MariaDB `10.4.32`, enum tujuh role lama, `NOT NULL`, default
`'Customer'`, `utf8mb4_unicode_ci`, 19 tabel, 181 kolom, 74 indeks berbeda,
dan 36 foreign key. Akun saat pemeriksaan: Super Admin 1, Petugas Gudang 1,
Customer 1. Skrip tersebut hanya membaca metadata/hitungan dan tidak mencetak
kredensial.

Hasil forward `ALTER` pada salinan: enum bertambah tepat tiga nilai target.
Default, charset/collation, nullability, hitungan akun per role, jumlah tabel,
kolom, indeks, FK, dan jumlah baris setiap tabel tetap sama. Indeks dihitung per
nama index unik tabel agar jumlah 75 konsisten dengan metode inventory.

Rollback guard diuji dengan memasukkan fixture nonaktif sementara ke role
`Admin`; pemeriksaan menolak rollback ketika role target sedang digunakan.
Fixture itu hanya ditambahkan dan dibuang pada `arsip_bakti_test_copy`. Setelah
fixture dibuang, rollback DDL memulihkan definisi role lama. Snapshot sesudah
rollback cocok dengan snapshot awal: definisi kolom, hitungan role, tabel,
kolom, index, FK, dan hitungan baris tabel sama. Tidak ada akun role target
tersisa pada salinan.

Uji DDL di atas memakai harness ad hoc, bukan migration Laravel final. Belum
memvalidasi lifecycle migration repository Laravel, deploy concurrency,
backup/restore produksi, atau perilaku schema drift lain. Snapshot salinan
berisi 20 base tables karena menyimpan satu row history `migrations`; dokumentasi
skema bisnis 19 tabel tetap benar. Uji ini tidak menjalankan migration bawaan
Laravel dan tidak memakai `migrate:fresh`/`db:wipe`.

Untuk verifikasi read-only database aktif dan rincian uji salinan, gunakan
skrip lokal [role-migration-live-readonly.php](../scripts/role-migration-live-readonly.php)
dan [role-migration-copy-validation.php](../scripts/role-migration-copy-validation.php).
Skrip salinan menjalankan DDL dan hanya boleh dijalankan dengan target salinan
yang diverifikasi di dalam harness; keduanya tidak mencetak kredensial.

## Rekomendasi sebelum tahap implementasi migration

1. Setujui matriks atau tentukan nilai untuk seluruh sel “Perlu persetujuan”,
   khususnya Admin, Pegawai, verifikasi, dan ekspor/pengelolaan pengguna.
2. Setujui migration aditif dan pastikan backup baru database aktif dibuat,
   diverifikasi, serta pemulihannya diuji sebelum jendela penerapan.
3. Implementasikan migration Laravel dengan preflight yang membandingkan enum
   aktual penuh (termasuk default/nullability/charset/collation), dan hentikan
   jika struktur bukan yang diharapkan.
4. Implementasikan rollback dengan pemeriksaan nilai role target sebelum DDL;
   jangan otomatis mengubah nilai pengguna.
5. Terapkan hanya ke salinan QA, bandingkan ulang metadata/row counts, jalankan
   suite aplikasi, lalu minta persetujuan terpisah sebelum penerapan produksi.
6. Setelah schema diizinkan menyimpan target, putuskan pemetaan akun secara
   terpisah. Jangan mengganti Super Admin, role khusus Verifikator, atau role
   eksternal Customer/Pengguna Umum tanpa persetujuan eksplisit.

## Paket eksekusi, pemetaan, dan verifikasi

Pemetaan berikut adalah kandidat tinjauan, bukan perubahan akun. Perluasan enum
hanya menambah nilai target; ia tidak memetakan akun atau mengubah permission.

| Role legacy | Kandidat role target | Tindakan aman sebelum persetujuan |
|---|---|---|
| Super Admin | Super Admin | Pertahankan; jangan ubah akun administratif terakhir. |
| Petugas Gudang | Pegawai | Tinjau tugas dan record terkait per akun; jangan konversi otomatis. |
| Admin Unit/Petugas Arsip | Admin | Tinjau scope unit dan kewenangan; jangan menyamakan dengan Admin global. |
| Pimpinan/Approver | Kepala Divisi | Petakan hanya setelah scope divisi dan cakupan persetujuan disetujui. |
| Verifikator | Belum dipetakan | Pertahankan role/fungsi verifikasi sampai pengganti dan regresinya disetujui. |
| Customer | Belum dipetakan | Pertahankan fungsi portal eksternal; jangan konversi menjadi Pegawai. |
| Pengguna Umum | Belum dipetakan | Pertahankan terpisah dari Customer dan role internal. |

Urutan eksekusi yang diperlukan setelah persetujuan:

1. **Pra-penerapan:** DBA dan pemilik aplikasi menyetujui target serta window
   perubahan. Pada salinan baru, verifikasi identitas database/server, versi,
   `SHOW CREATE TABLE users`, atribut `role`, seluruh nilai enum, migration
   baseline, dan hitungan agregat role. Buat backup konsisten dan buktikan
   restore pada database terpisah.
2. **QA:** jalankan migration Laravel final hanya pada salinan terisolasi yang
   disetujui. Bandingkan definisi schema serta hitungan tabel, kolom, index,
   foreign key, baris, dan role sebelum/sesudah. Pastikan hanya tiga literal
   target yang ditambahkan; tidak ada akun, role tersimpan, grant, atau data
   relasi yang berubah. Jalankan suite SQLite dan seluruh suite MariaDB yang
   disetujui.
3. **Pascamigrasi:** verifikasi ulang database/server dan `SHOW CREATE TABLE
   users`; pastikan urutan seluruh enum, `NOT NULL`, default `Customer`,
   charset/collation, PK/index/FK, serta hitungan akun per role sama dengan
   baseline. Pastikan tidak ada akun target yang tercipta tanpa proses
   penetapan akun terpisah. Jalankan pemeriksaan smoke-test akses untuk setiap
   role yang sudah disetujui, penolakan role tanpa grant, scope divisi, login,
   dan log/error aplikasi; catat hasil dan persetujuan operasional.
4. **Rollback/pemulihan:** sebelum menghapus nilai enum target, hitung akun
   `Admin`, `Kepala Divisi`, dan `Pegawai`. Migration harus berhenti sebelum
   DDL jika ada akun target; jangan memetakan atau menghapus akun otomatis.
   Jika tidak ada akun target, kembalikan definisi enum persis ke baseline dan
   validasi ulang semua atribut serta hitungan. Karena MariaDB DDL dapat
   implicit commit, bila hasil schema/data tidak sesuai, hentikan perubahan
   lanjutan dan pulihkan backup terverifikasi sesuai prosedur DBA.

**Prasyarat implementasi migration Laravel yang belum beres:** migrasi bawaan
[`0001_01_01_000000_create_users_table.php`](../database/migrations/0001_01_01_000000_create_users_table.php)
membuat tabel `users` bergaya Laravel (`name`, `email`) dan bukan tabel legacy
Arsip-Bakti. Karena itu jangan menjalankan `artisan migrate` pada database
legacy hanya berdasarkan folder migration saat ini. Sebelum membuat migration
role final, pemilik aplikasi dan DBA harus menetapkan serta menguji bagaimana
migration repository/baseline Laravel merepresentasikan schema existing pada
salinan; hal ini tidak boleh diselesaikan dengan menghapus atau membuat ulang
tabel.

**Status akhir tahap:** matriks dan migration plan terdokumentasi; operasi enum
diuji pada salinan MariaDB dan rollback aman diuji. Tidak ada migration Laravel
yang dibuat/dijalankan, tidak ada grant Admin/Pegawai yang diaktifkan, dan
tidak ada perubahan pada database produksi. Tunggu persetujuan sebelum tahap
implementasi migration.

## Addendum Tahap 5A — Tinjauan keputusan permission

Status addendum: tinjauan dan dokumentasi saja. Tidak mengubah kode, data, atau
database. Belum membuat atau menjalankan migration.

### Matriks aktual pada `RolePermissions.php`

Matriks ini mencantumkan grant statis aktual menurut katalog. Grant tidak
menggantikan pemeriksaan tambahan controller, klasifikasi, kepemilikan, atau
scope divisi. **KD** = Kepala Divisi. `—` = tidak diberikan katalog.

| Permission katalog | Super Admin | Admin | KD | Pegawai |
|---|---:|---:|---:|---:|
| `dashboard.view` | Ya | — | — | — |
| `archives.list` | Ya | — | Ya | — |
| `archives.view` | Ya | — | Ya | — |
| `archives.manage` | Ya | — | — | — |
| `archives.manage_confidential` | Ya | — | — | — |
| `qr.scan` | Ya | — | Ya | — |
| `qr.manage` | Ya | — | — | — |
| `loans.manage` | Ya | — | — | — |
| `loans.request` | Tidak* | — | — | — |
| `loans.approve` | Ya | — | Ya** | — |
| `retention.view` | Ya | — | Ya | — |
| `retention.schedule` | Ya | — | — | — |
| `retention.propose` | Ya | — | — | — |
| `retention.review` | Ya | — | Ya** | — |
| `customer.portal` | Tidak* | — | — | — |
| `customer.review` | Ya | — | — | — |
| `duplicates.review` | Ya | — | — | — |
| `reports.view` | Ya | — | — | — |
| `imports.manage` | Ya | — | — | — |
| `categories.manage` | Ya | — | — | — |
| `locations.manage` | Ya | — | — | — |
| `history.view` | Ya | — | — | — |
| `users.manage` | Ya | — | — | — |

`*` Super Admin sengaja tidak diberi `customer.portal` atau `loans.request`:
fitur tersebut menggunakan konteks akun eksternal Customer. `**` Grant
Kepala Divisi di katalog ada, tetapi hanya boleh efektif setelah pemeriksaan
scope divisi dan record-level authorization; tes Tahap 3 mencakup scope arsip
dan keputusan pinjaman, bukan pengganti audit setiap endpoint.

Katalog saat ini belum memisahkan seluruh operasi yang diminta menjadi permission
terpisah. Sebagai contoh `archives.manage` menggabungkan kemampuan pengelolaan
arsip, sedangkan hapus/nonaktif beberapa master mengikuti aturan khusus. Karena
itu, permission “buat”, “ubah”, “hapus”, “verifikasi”, dan “setujui” yang lebih
rinci di matriks rancangan merupakan **usulan kebijakan**, bukan kapabilitas
yang seluruhnya dapat diberikan granular melalui konfigurasi saat ini.
`GLOBAL_PERMISSIONS` adalah allowlist key yang dapat diperiksa; key lain selalu
ditolak oleh `allows()`.

### Perbandingan aktual dengan usulan target

| Role target | Aktual dalam kode | Usulan/keputusan sebelum aktivasi |
|---|---|---|
| Super Admin | Memiliki semua key katalog kecuali portal dan permintaan pinjaman eksternal. Pengelolaan rahasia dan pengguna juga tercantum; beberapa controller tetap memiliki aturan tambahan. | Pertahankan akses administratif global dengan audit trail dan proteksi Super Admin aktif terakhir. Setujui batas akses data rahasia dan pemisahan tugas, termasuk larangan menyetujui usulan sendiri bila berlaku. |
| Admin | Tidak memiliki grant (`ROLE_PERMISSIONS['Admin']` kosong). Selain itu Admin tidak termasuk `LEGACY_ROLES`, sehingga validasi role legacy belum menerima nilai ini. | Jangan berikan grant sebelum pemilik sistem menentukan apakah Admin global atau per-unit dan menyetujui setiap operasi serta scope. |
| Kepala Divisi | Sudah memiliki `archives.list`, `archives.view`, `qr.scan`, `loans.approve`, `retention.view`, `retention.review`; helper mengenali role ini sebagai division-scoped. Tidak punya `dashboard.view`, manage archives, atau user management. | Setujui secara eksplisit hak daftar/detail, scan, persetujuan pinjaman, dan review retensi pada scope divisi. Pastikan semua endpoint, lampiran/QR, keputusan terima/tolak, dan query retensi memakai scope yang sama. Tentukan aturan data tanpa divisi, normalisasi nama divisi, kerahasiaan, serta self-approval. |
| Pegawai | Tidak memiliki grant (`ROLE_PERMISSIONS['Pegawai']` kosong), dan tidak termasuk `LEGACY_ROLES`. | Tetap tanpa akses modul sampai tugas konkret disetujui. Jangan turunkan izin hanya dari nama role legacy yang dianggap setara. |

Permission statis Kepala Divisi adalah kondisi kode saat ini, bukan persetujuan
bisnis bahwa setiap akun boleh diberi role tersebut. Database enum existing
belum dapat menyimpan role target. Sebaliknya, menambahkan nilai enum tidak
akan mengaktifkan grant Admin/Pegawai: katalog keduanya kosong dan validasi
role saat ini tetap memakai daftar legacy. Perubahan skema, validasi role,
pemetaan akun, dan keputusan grant merupakan langkah-langkah terpisah.

### Permission Verifikator yang harus dipertahankan

Role legacy `Verifikator` saat ini mendapat tepat:

- `qr.scan`
- `duplicates.review`

Tidak mendapat `archives.list`, `archives.view`, `archives.manage`,
`customer.review`, `loans.approve`, `retention.review`, laporan, atau
pengelolaan pengguna. Pemeriksaan akses arsip tetap berlaku saat QR lookup dan
review kandidat. Audit Tahap 2 juga mencatat index verifikasi duplikasi dapat
mendeteksi dan menyimpan pasangan kandidat, sehingga akses ke endpoint tersebut
bukan sekadar hak baca tanpa efek samping.

Jangan menghapus role/dua izin tersebut atau mengalihkannya ke Admin/Pegawai
hanya karena role Verifikator tidak termasuk empat role utama. Sebelum
penggantian, harus ada pemilik fungsi, permission pengganti yang eksplisit,
authorization backend untuk semua route/mutasi dan unduhan terkait, serta tes
regresi yang membuktikan Verifikator lama tetap berfungsi selama transisi.

### Dampak terhadap Customer dan Pengguna Umum

- Keduanya masih dua role legacy yang terpisah dan memiliki set permission
  katalog yang sama: `archives.list`, `archives.view`, `qr.scan`,
  `loans.request`, dan `customer.portal`.
- Kesamaan katalog tidak membuktikan bahwa kebijakan produk, status verifikasi,
  kepemilikan data, maupun siklus hidup kedua tipe akun harus disatukan.
- Portal tetap terbatas pada konten publik dan permintaan/riwayat milik akun
  sesuai pemeriksaan controller. QR tidak melewati pembatasan klasifikasi.
- Jangan mengonversi mereka menjadi Pegawai, memberikan akses arsip internal,
  atau menghapus salah satu role sebagai efek samping migration enum.
- Perluasan enum aditif di rencana mempertahankan kedua nilai lama dan default
  `Customer`; tidak mengubah satu pun akun. Migration enum saja juga tidak
  mengubah validasi role aplikasi atau permission mereka.

### Risiko enum dan rollback aman

1. `users.role` existing adalah enum tujuh nilai, `NOT NULL`, default
   `Customer`. Nilai Admin/Kepala Divisi/Pegawai belum diterima skema; audit
   aktif mencatat distribusi role hanya sebagai snapshot.
2. DDL `ALTER TABLE ... MODIFY COLUMN` mengubah definisi skema dan MariaDB dapat
   melakukan implicit commit. Jangan mengandalkan rollback transaksi Laravel
   untuk membatalkan DDL yang gagal di tengah proses.
3. Migration hanya boleh memperluas daftar enum dengan ketiga nilai baru di
   akhir, sambil mempertahankan semua nilai, urutan lama, default, nullability,
   charset/collation dan atribut aktual. Preflight wajib berhenti jika definisi
   saat dijalankan menyimpang dari yang ditinjau.
4. Enum yang diperluas tidak memetakan akun legacy dan tidak memberi hak akses.
   Perubahan validasi `UserController`, katalog permission, route/controller,
   serta tes merupakan pekerjaan kode terpisah dan perlu persetujuan.
5. Rollback harus menghitung akun dengan role `Admin`, `Kepala Divisi`, atau
   `Pegawai`. Bila ada setidaknya satu, rollback berhenti dengan error eksplisit
   sebelum DDL. Jangan menghapus akun, mengganti role, atau menurunkan hak secara
   diam-diam. Penanganan akun target, jika memang disetujui, dilakukan terpisah
   dengan keputusan pemilik data.
6. Uji DDL pada MariaDB 10.4.32 dilakukan di salinan terisolasi, bukan melalui
   migration Laravel final. Sebelum deployment tetap diperlukan backup terbaru
   dan pemulihan teruji, QA migration Laravel pada salinan baru, pemeriksaan
   definisi/data sebelum-sesudah, serta persetujuan produksi yang terpisah.

### Persetujuan yang diperlukan sebelum migration Laravel dibuat

Migration belum boleh dibuat sampai pemilik sistem menyetujui atau menetapkan:

1. Apakah empat role target akan ditambahkan sebagai nilai enum sambil
   mempertahankan ketujuh nilai legacy, tanpa pemetaan akun otomatis.
2. Makna `Admin`: administrator sistem atau administrator operasional per unit;
   modul, operasi, scope data dan larangan eskalasi privilege yang diizinkan.
3. Daftar kerja `Pegawai`: hak baca/scan, input/edit arsip, transaksi pinjaman,
   cetak QR, usulan retensi, dan batas divisi/klasifikasi untuk masing-masing.
4. Apakah grant statis Kepala Divisi yang ada disetujui; definisi divisi kanonis,
   perilaku jika divisi kosong/berbeda ejaan, akses klasifikasi, semua route yang
   wajib scoped, serta batas persetujuan dan self-approval.
5. Apakah `Verifikator` tetap role khusus sementara atau akan punya pengganti
   permission; siapa yang menangani review Customer dan siapa yang menangani
   kandidat duplikasi.
6. Apakah Customer dan Pengguna Umum tetap terpisah. Jika kebijakan hendak
   menyatukan, setujui rancangan transisi dan audit akun terlebih dahulu, bukan
   bagian dari enum expansion.
7. Apakah permission tetap statis dalam kode atau kelak perlu dikelola runtime.
   Kebutuhan permission per-user/runtime berpotensi memerlukan desain skema
   terpisah dan bukan bagian migration enum ini.
8. Prosedur rollback aman: guard terhadap akun target, pemilik persetujuan
   perubahan akun, pesan kegagalan, serta rencana pemulihan bila DDL berhasil
   tetapi proses deployment berikutnya gagal.
9. Bukti preflight yang diperlukan dari target: database/server yang benar,
   `SHOW CREATE TABLE users`, seluruh atribut kolom role, hitungan role tanpa
   data pribadi, backup/restore teruji, QA pada salinan dan jendela perubahan.

Sampai keputusan ini disetujui, tidak dibuat migration Laravel, tidak diubah
allowlist/validator role, tidak diberikan grant Admin atau Pegawai, dan tidak
diubah database. Hasil Tahap 5A berhenti pada addendum ini dan menunggu
persetujuan pemilik sistem.
