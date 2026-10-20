# Skema Database Existing dan Rencana Baseline

## Lingkup dan metode

Dokumen ini berisi inventaris skema read-only dari koneksi runtime Laravel ke database `arsip_bakti`. Pemeriksaan hanya membaca MySQL `information_schema`; tidak membaca baris data aplikasi, menjalankan DDL, atau mengubah database aktif. Kredensial, data pengguna, dan nilai role tidak disertakan. Nilai `COLUMN_DEFAULT` yang dilaporkan `NULL` dapat berarti default nullable implisit atau tidak ada default eksplisit; simpan keluaran `SHOW CREATE TABLE` dari server saat menulis migration mendatang jika pembedaan ini diperlukan.

> **Status diperbarui 15 Oktober 2026:** inventaris dan hasil Tahap 1B di bawah
> adalah snapshot historis. Pemeriksaan metadata setelahnya menemukan tabel
> `migrations` pada `arsip_bakti` dengan satu catatan migration
> `2026_10_12_000001_add_admin_and_pegawai_to_users_role_enum`; tipe
> `users.role` sekarang mencakup `Admin` dan `Pegawai`. Database bernama
> `arsip_bakti` tetap dilindungi dan tidak boleh menjadi target perubahan baru.
> Migration `Kepala Divisi` ditambahkan ke source setelah pemeriksaan ini,
> tetapi belum dijalankan pada database mana pun.

Inventaris berikut menggambarkan database yang terhubung pada saat pemeriksaan, bukan otomatis berlaku untuk semua environment. Jumlah tabel tidak menyertakan schema lain pada server MySQL.

| Metadata | Hasil |
|---|---:|
| Database | `arsip_bakti` |
| Tables | 19 |
| Kolom | 181 |
| Indeks berbeda (termasuk primary key) | 74 |
| Foreign-key constraint | 36 |
| Engine / collation tabel | `InnoDB` / `utf8mb4_unicode_ci` (semua 19 tabel) |

## Tabel yang ditemukan

`arsip`, `arsip_files`, `customer_arsip_access`, `customer_requests`, `customer_submissions`, `disposition_requests`, `duplicate_reviews`, `jadwal_retensi`, `kategori_arsip`, `loan_requests`, `lokasi`, `notifications`, `password_reset_requests`, `peminjaman`, `qr_batches`, `remember_tokens`, `retention_reminders`, `riwayat_arsip`, `users`.

## Perbandingan aplikasi dan migration

- Laravel memiliki satu model Eloquent, `App\Models\User`, yang secara eksplisit memakai tabel `users`. Sebagian besar pembacaan dan penulisan tabel bisnis menggunakan Query Builder (`DB::table()` dan join) di controller serta kelas support. Route pada `routes/web.php` mengarahkan aksi tersebut ke controller. Tidak ada deklarasi relasi Eloquent untuk relasi bisnis, sehingga foreign key database menjadi sumber relasi yang benar-benar ditegakkan.
- Aplikasi mengakses tabel bisnis yang tercantum di inventaris ini. Nama tabel dan kolom yang diperiksa pada controller serta kode support selaras dengan schema aktif; belum ada migration bisnis yang mendefinisikan 19 tabel tersebut.
- Isi `database/migrations` hanya tiga migration bawaan Laravel. Migration `create_users_table` mengharapkan kolom `name`, `email`, `email_verified_at`, dan `remember_token`; sedangkan tabel `users` aktif memiliki `nama`, `username`, `profile_image`, `role`, `division`, dan `status` (serta password dan timestamp). Menjalankan migration itu terhadap database ini akan berbenturan dengan tabel `users` yang sudah ada dan tidak menggambarkan schemanya.
- Migration cache dan jobs mengharapkan tabel `cache`, `cache_locks`, `jobs`, `job_batches`, dan `failed_jobs`; migration users juga mengharapkan `password_reset_tokens` dan `sessions`. Ketujuh tabel itu tidak ada dalam schema aktif 19 tabel. Konfigurasi runtime saat ini menggunakan MySQL, session berbasis file, cache berbasis file, dan queue sinkron; tabel penyimpanan tersebut tidak dibutuhkan oleh driver yang sedang dipakai. Konfigurasi password broker Laravel bawaan menunjuk ke `password_reset_tokens`, sedangkan alur reset khusus aplikasi memakai `password_reset_requests`; jangan menganggap tabel broker standar tersedia.
- Database aktif tidak memiliki tabel `migrations`. Pemeriksaan `php artisan migrate:status` sebelumnya menghasilkan “Migration table not found”; hasil itu bukan inventaris schema dan tidak digunakan sebagai pengganti audit ini. Dari sudut pandang Laravel, migration bawaan masih pending dan tidak boleh dijalankan apa adanya pada schema existing.
- File SQL lama `database/arsip_bakti.sql` memiliki perintah `DROP TABLE IF EXISTS` dan hanya 13 definisi `CREATE TABLE`. Nama kolom pada 13 tabel yang sama cocok dengan database aktif, tetapi ini tidak membuktikan kecocokan tipe, default, indeks, atau constraint. Enam tabel aktif yang tidak ada di dump tersebut adalah `customer_arsip_access`, `customer_requests`, `customer_submissions`, `password_reset_requests`, `notifications`, dan `remember_tokens`; logika pembuatannya di versi lama ada di file konfigurasi PHP (dan `notifications` juga memiliki jalur pembuatan lain). Karena itu dump SQL tersebut tidak lengkap dan tidak aman diimpor ke database aktif.
- Perbedaan indeks terkonfirmasi: misalnya, `arsip` aktif memiliki indeks bernama `idx_nomor_berkas`, `idx_kode_arsip`, dan `fk_arsip_user`; dump lama memakai nama berbeda dan juga mendefinisikan indeks `nama_berkas` yang tidak ada pada inventaris aktif. Nama dan definisi indeks harus diambil dari database aktif, bukan disalin dari dump.

## Risiko dan aturan preservasi

1. **Benturan tabel/migration:** migration users bawaan Laravel mencoba `CREATE TABLE users`, padahal tabel aktif sudah ada dengan struktur berbeda. `php artisan migrate` pertama dapat gagal setelah mulai menyiapkan pencatatan migration; jangan jalankan pada database aktif sebelum prosedur adopsi disetujui.
2. **Schema drift dari sumber yang tidak lengkap:** dump SQL lama tidak memuat enam tabel aktif, memiliki perintah `DROP TABLE` destruktif, dan berbeda pada definisi indeks. Jangan jadikan dump tersebut baseline lengkap atau eksekusi terhadap database aktif.
3. **Kesesuaian kolom dan enum:** pertahankan lebar unsigned, primary key campuran `INT`/`BIGINT`, `YEAR`, nilai `ENUM`, default timestamp, dan perilaku `ON UPDATE` secara tepat. Khususnya `users.role` adalah enum dengan nilai existing yang ditampilkan pada definisi kolom; penambahan role atau perubahan enum harus menjadi migration skema/data terpisah dengan persetujuan dan rencana preservasi data.
4. **Perilaku constraint:** pertahankan 36 foreign key aktif, kolom referensi, dan aksi hapus/perbarui. Sebagian relasi memakai cascade, yang lain restrict atau mengatur referensi nullable menjadi `NULL`; membuat ulang kolom saja akan mengubah perilaku bisnis.
5. **Unique constraint dan indeks yang dipakai aplikasi:** pertahankan semua unique key (termasuk QR arsip, nama kategori, kombinasi lokasi, serta key relasi/permintaan) dan indeks biasa. Menghapus atau mengganti namanya bisa membuka peluang duplikasi atau mengubah perilaku query.
6. **Cakupan tes:** konfigurasi PHPUnit saat ini menggunakan SQLite `:memory:`, session/cache array, dan queue sinkron. Tes yang lulus tidak memverifikasi schema MySQL 19 tabel maupun memvalidasi baseline migration terhadapnya.

## Strategi baseline yang direkomendasikan (belum diterapkan atau divalidasi)

Perlakukan 19 tabel bisnis aktif sebagai baseline yang sudah ada; **jangan buat ulang** tabel tersebut. Baseline untuk instalasi existing sebaiknya berupa tahap adopsi/verifikasi yang memeriksa sidik struktur tabel, kolom, indeks, dan foreign key, lalu mencatat riwayat migration hanya setelah cocok. Tahap ini tidak boleh mengubah tabel bisnis atau baris data. Jika sidik struktur tidak cocok, proses harus berhenti dengan error yang jelas, bukan otomatis “memperbaiki” schema.

Sebelum mengaktifkan migration biasa pada instalasi existing, tangani secara eksplisit tiga file migration bawaan Laravel yang masih pending agar tidak menjalankan `CREATE TABLE` yang tidak kompatibel. Tentukan dan uji pengaturan migration-path/riwayat tersebut pada salinan terisolasi; jangan menyimpulkan migration bawaan sudah berjalan hanya karena file-nya tersedia. Untuk instalasi baru, sediakan bootstrap/snapshot schema terpisah yang telah ditinjau dan mendefinisikan schema lengkap yang dimaksud. Perubahan bisnis mendatang harus berupa migration aditif dengan cakupan sempit, pemeriksaan awal, backup, serta rencana rollback; jangan membentuk ulang baseline dengan menghapus/membuat ulang tabel.

Dokumen ini **tidak memvalidasi baseline**. Strategi tetap berupa rekomendasi sampai lulus prosedur pengujian pada salinan berikut.

## Prosedur validasi aman menggunakan salinan database

1. Dapatkan backup/snapshot `arsip_bakti` yang terlindungi dan telah diverifikasi melalui proses backup database yang disetujui organisasi. Simpan di luar repository dan jangan gunakan dump SQL lama sebagai sumber karena berisi perintah destruktif dan tidak mencakup semua tabel.
2. Pulihkan snapshot ke database sementara yang terpisah, misalnya `arsip_bakti_baseline_test`, pada instance MySQL terisolasi atau schema yang benar-benar terpisah. Gunakan koneksi/environment Laravel khusus tes; jangan edit `.env` aktif atau arahkan koneksi tes ke `arsip_bakti`.
3. Sebelum menjalankan perintah migration apa pun, pastikan nama database koneksi aktif tepat sama dengan database tes sementara. Ambil inventaris tabel/kolom/indeks/FK pada salinan dan bandingkan dengan dokumen ini. Batalkan jika nama koneksi atau sidik struktur tidak cocok.
4. Jalankan mekanisme adopsi baseline yang diusulkan hanya pada salinan. Pastikan tidak ada perintah `CREATE`, `ALTER`, `DROP`, atau perubahan baris pada 19 tabel bisnis; penambahan schema yang mungkin diharapkan untuk pencatatan migration Laravel hanya tabel riwayat migration, bila memang menjadi bagian rancangan yang disetujui.
5. Bandingkan sidik schema sebelum dan sesudah untuk seluruh 19 tabel bisnis. Jika menggunakan salinan penuh, bandingkan jumlah baris dan checksum relevan sesuai kebijakan privasi yang disetujui. Pastikan pemeriksaan foreign key, unique constraint, serta pembacaan aplikasi tetap berfungsi.
6. Jalankan `php artisan migrate:status` dan tes Laravel relevan terhadap salinan, lalu jalankan suite PHPUnit normal yang terisolasi secara terpisah. Uji rollback hanya pada salinan sementara dan hanya untuk migration aditif; jangan menguji rollback pada database aktif.
7. Catat perintah yang dijalankan, target koneksi, hasil perbandingan schema, dan hasil tes. Adopsi riwayat migration pada database aktif baru boleh dipertimbangkan setelah peninjauan dan persetujuan eksplisit. Tidak ada adopsi atau migration yang dijalankan dalam audit ini.

## Definisi tabel yang diinventarisasi

Flag `key` pada kolom mengikuti laporan MySQL (`PRI`, `UNI`, `MUL`); inventaris indeks dan foreign key di bawah menampilkan definisi gabungan serta aksi referensial lengkap.

### `arsip` — InnoDB, utf8mb4_unicode_ci

| Column | SQL type | Null | Default reported | Key / extra |
|---|---|---:|---|---|
| `id` | `int(10) unsigned` | NO | NULL / none reported | `PRI auto_increment` |
| `nomor_berkas` | `varchar(150)` | NO | NULL / none reported | `MUL` |
| `nama_berkas` | `varchar(255)` | NO | NULL / none reported | — |
| `kode_arsip` | `varchar(150)` | NO | NULL / none reported | `MUL` |
| `kategori_id` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `tahun` | `year(4)` | NO | NULL / none reported | `MUL` |
| `bulan` | `tinyint(3) unsigned` | NO | NULL / none reported | — |
| `tanggal_masuk` | `date` | NO | NULL / none reported | — |
| `lokasi_id` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `classification` | `varchar(32)` | NO | `'Internal'` | — |
| `division` | `varchar(150)` | YES | `NULL` | — |
| `physical_condition` | `varchar(40)` | NO | `'Baik'` | — |
| `status` | `enum('Tersimpan','Dipinjam','Diproses','Hilang','Nonaktif')` | NO | `'Tersimpan'` | `MUL` |
| `qr_code` | `varchar(60)` | NO | NULL / none reported | `UNI` |
| `keterangan` | `text` | YES | `NULL` | — |
| `created_by` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `created_at` | `datetime` | NO | `current_timestamp()` | — |
| `updated_at` | `datetime` | NO | `current_timestamp()` | `on update current_timestamp()` |

**Indexes**

- `fk_arsip_kategori` (non-unique, BTREE): `kategori_id`
- `fk_arsip_lokasi` (non-unique, BTREE): `lokasi_id`
- `fk_arsip_user` (non-unique, BTREE): `created_by`
- `idx_arsip_status` (non-unique, BTREE): `status`
- `idx_arsip_tahun_bulan` (non-unique, BTREE): `tahun`, `bulan`
- `idx_kode_arsip` (non-unique, BTREE): `kode_arsip`
- `idx_nomor_berkas` (non-unique, BTREE): `nomor_berkas`
- `PRIMARY` (unique, BTREE): `id`
- `uq_arsip_qr` (unique, BTREE): `qr_code`

**Foreign keys**

- `fk_arsip_kategori`: `kategori_id` → `kategori_arsip.id` (ON UPDATE RESTRICT, ON DELETE RESTRICT)
- `fk_arsip_lokasi`: `lokasi_id` → `lokasi.id` (ON UPDATE RESTRICT, ON DELETE RESTRICT)
- `fk_arsip_user`: `created_by` → `users.id` (ON UPDATE RESTRICT, ON DELETE RESTRICT)

### `arsip_files` — InnoDB, utf8mb4_unicode_ci

| Column | SQL type | Null | Default reported | Key / extra |
|---|---|---:|---|---|
| `id` | `int(10) unsigned` | NO | NULL / none reported | `PRI auto_increment` |
| `arsip_id` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `nama_file` | `varchar(255)` | NO | NULL / none reported | — |
| `file_path` | `varchar(500)` | NO | NULL / none reported | — |
| `tipe_file` | `varchar(100)` | NO | NULL / none reported | — |
| `uploaded_by` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `created_at` | `datetime` | NO | `current_timestamp()` | — |

**Indexes**

- `fk_files_user` (non-unique, BTREE): `uploaded_by`
- `idx_files_arsip` (non-unique, BTREE): `arsip_id`
- `PRIMARY` (unique, BTREE): `id`

**Foreign keys**

- `fk_files_arsip`: `arsip_id` → `arsip.id` (ON UPDATE RESTRICT, ON DELETE CASCADE)
- `fk_files_user`: `uploaded_by` → `users.id` (ON UPDATE RESTRICT, ON DELETE RESTRICT)

### `customer_arsip_access` — InnoDB, utf8mb4_unicode_ci

| Column | SQL type | Null | Default reported | Key / extra |
|---|---|---:|---|---|
| `id` | `int(10) unsigned` | NO | NULL / none reported | `PRI auto_increment` |
| `user_id` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `arsip_id` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `granted_by` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `created_at` | `datetime` | NO | `current_timestamp()` | — |

**Indexes**

- `fk_customer_access_archive` (non-unique, BTREE): `arsip_id`
- `fk_customer_access_granted_by` (non-unique, BTREE): `granted_by`
- `idx_customer_access_user` (non-unique, BTREE): `user_id`
- `PRIMARY` (unique, BTREE): `id`
- `uq_customer_archive_access` (unique, BTREE): `user_id`, `arsip_id`

**Foreign keys**

- `fk_customer_access_archive`: `arsip_id` → `arsip.id` (ON UPDATE RESTRICT, ON DELETE CASCADE)
- `fk_customer_access_granted_by`: `granted_by` → `users.id` (ON UPDATE RESTRICT, ON DELETE RESTRICT)
- `fk_customer_access_user`: `user_id` → `users.id` (ON UPDATE RESTRICT, ON DELETE CASCADE)

### `customer_requests` — InnoDB, utf8mb4_unicode_ci

| Column | SQL type | Null | Default reported | Key / extra |
|---|---|---:|---|---|
| `id` | `int(10) unsigned` | NO | NULL / none reported | `PRI auto_increment` |
| `user_id` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `requested_code` | `varchar(180)` | NO | NULL / none reported | — |
| `purpose` | `text` | NO | NULL / none reported | — |
| `status` | `varchar(40)` | NO | `'Menunggu Verifikasi'` | `MUL` |
| `reviewer_note` | `text` | YES | `NULL` | — |
| `reviewed_by` | `int(10) unsigned` | YES | `NULL` | `MUL` |
| `created_at` | `datetime` | NO | `current_timestamp()` | — |
| `updated_at` | `datetime` | NO | `current_timestamp()` | `on update current_timestamp()` |

**Indexes**

- `fk_customer_request_reviewer` (non-unique, BTREE): `reviewed_by`
- `idx_customer_requests_status` (non-unique, BTREE): `status`
- `idx_customer_requests_user` (non-unique, BTREE): `user_id`
- `PRIMARY` (unique, BTREE): `id`

**Foreign keys**

- `fk_customer_request_reviewer`: `reviewed_by` → `users.id` (ON UPDATE RESTRICT, ON DELETE SET NULL)
- `fk_customer_request_user`: `user_id` → `users.id` (ON UPDATE RESTRICT, ON DELETE CASCADE)

### `customer_submissions` — InnoDB, utf8mb4_unicode_ci

| Column | SQL type | Null | Default reported | Key / extra |
|---|---|---:|---|---|
| `id` | `int(10) unsigned` | NO | NULL / none reported | `PRI auto_increment` |
| `user_id` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `arsip_id` | `int(10) unsigned` | YES | `NULL` | `MUL` |
| `nomor_dokumen` | `varchar(180)` | NO | NULL / none reported | — |
| `nama_dokumen` | `varchar(255)` | NO | NULL / none reported | — |
| `kategori_id` | `int(10) unsigned` | YES | `NULL` | `MUL` |
| `tanggal_dokumen` | `date` | NO | NULL / none reported | — |
| `keterangan` | `text` | YES | `NULL` | — |
| `nama_file` | `varchar(255)` | NO | NULL / none reported | — |
| `file_path` | `varchar(500)` | NO | NULL / none reported | — |
| `mime_type` | `varchar(100)` | NO | NULL / none reported | — |
| `file_size` | `int(10) unsigned` | NO | NULL / none reported | — |
| `status` | `varchar(40)` | NO | `'Menunggu Verifikasi'` | `MUL` |
| `reviewer_note` | `text` | YES | `NULL` | — |
| `reviewed_by` | `int(10) unsigned` | YES | `NULL` | `MUL` |
| `created_at` | `datetime` | NO | `current_timestamp()` | — |
| `updated_at` | `datetime` | NO | `current_timestamp()` | `on update current_timestamp()` |

**Indexes**

- `fk_customer_submission_archive` (non-unique, BTREE): `arsip_id`
- `fk_customer_submission_category` (non-unique, BTREE): `kategori_id`
- `fk_customer_submission_reviewer` (non-unique, BTREE): `reviewed_by`
- `idx_customer_submissions_status` (non-unique, BTREE): `status`
- `idx_customer_submissions_user` (non-unique, BTREE): `user_id`
- `PRIMARY` (unique, BTREE): `id`

**Foreign keys**

- `fk_customer_submission_archive`: `arsip_id` → `arsip.id` (ON UPDATE RESTRICT, ON DELETE SET NULL)
- `fk_customer_submission_category`: `kategori_id` → `kategori_arsip.id` (ON UPDATE RESTRICT, ON DELETE SET NULL)
- `fk_customer_submission_reviewer`: `reviewed_by` → `users.id` (ON UPDATE RESTRICT, ON DELETE SET NULL)
- `fk_customer_submission_user`: `user_id` → `users.id` (ON UPDATE RESTRICT, ON DELETE CASCADE)

### `disposition_requests` — InnoDB, utf8mb4_unicode_ci

| Column | SQL type | Null | Default reported | Key / extra |
|---|---|---:|---|---|
| `id` | `bigint(20) unsigned` | NO | NULL / none reported | `PRI auto_increment` |
| `arsip_id` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `action` | `enum('Musnahkan','Permanen','Serah ke ANRI')` | NO | NULL / none reported | — |
| `reason` | `text` | NO | NULL / none reported | — |
| `status` | `enum('Menunggu Persetujuan','Disetujui','Ditolak','Dilaksanakan')` | NO | `'Menunggu Persetujuan'` | `MUL` |
| `requested_by` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `reviewed_by` | `int(10) unsigned` | YES | `NULL` | `MUL` |
| `reviewer_note` | `text` | YES | `NULL` | — |
| `reviewed_at` | `datetime` | YES | `NULL` | — |
| `completed_at` | `datetime` | YES | `NULL` | — |
| `minutes_number` | `varchar(100)` | YES | `NULL` | — |
| `minutes_note` | `text` | YES | `NULL` | — |
| `created_at` | `datetime` | NO | `current_timestamp()` | — |
| `updated_at` | `datetime` | NO | `current_timestamp()` | `on update current_timestamp()` |

**Indexes**

- `fk_disposition_requester` (non-unique, BTREE): `requested_by`
- `fk_disposition_reviewer` (non-unique, BTREE): `reviewed_by`
- `idx_disposition_archive` (non-unique, BTREE): `arsip_id`
- `idx_disposition_status` (non-unique, BTREE): `status`, `created_at`
- `PRIMARY` (unique, BTREE): `id`

**Foreign keys**

- `fk_disposition_archive`: `arsip_id` → `arsip.id` (ON UPDATE RESTRICT, ON DELETE RESTRICT)
- `fk_disposition_requester`: `requested_by` → `users.id` (ON UPDATE RESTRICT, ON DELETE RESTRICT)
- `fk_disposition_reviewer`: `reviewed_by` → `users.id` (ON UPDATE RESTRICT, ON DELETE SET NULL)

### `duplicate_reviews` — InnoDB, utf8mb4_unicode_ci

| Column | SQL type | Null | Default reported | Key / extra |
|---|---|---:|---|---|
| `id` | `bigint(20) unsigned` | NO | NULL / none reported | `PRI auto_increment` |
| `archive_id` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `candidate_id` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `match_reason` | `varchar(100)` | NO | NULL / none reported | — |
| `status` | `enum('Menunggu Verifikasi','Duplikat','Bukan Duplikat')` | NO | `'Menunggu Verifikasi'` | `MUL` |
| `reviewer_note` | `text` | YES | `NULL` | — |
| `reviewed_by` | `int(10) unsigned` | YES | `NULL` | `MUL` |
| `reviewed_at` | `datetime` | YES | `NULL` | — |
| `created_at` | `datetime` | NO | `current_timestamp()` | — |

**Indexes**

- `fk_duplicate_candidate` (non-unique, BTREE): `candidate_id`
- `fk_duplicate_reviewer` (non-unique, BTREE): `reviewed_by`
- `idx_duplicate_status` (non-unique, BTREE): `status`, `created_at`
- `PRIMARY` (unique, BTREE): `id`
- `uq_duplicate_pair` (unique, BTREE): `archive_id`, `candidate_id`

**Foreign keys**

- `fk_duplicate_archive`: `archive_id` → `arsip.id` (ON UPDATE RESTRICT, ON DELETE CASCADE)
- `fk_duplicate_candidate`: `candidate_id` → `arsip.id` (ON UPDATE RESTRICT, ON DELETE CASCADE)
- `fk_duplicate_reviewer`: `reviewed_by` → `users.id` (ON UPDATE RESTRICT, ON DELETE SET NULL)

### `jadwal_retensi` — InnoDB, utf8mb4_unicode_ci

| Column | SQL type | Null | Default reported | Key / extra |
|---|---|---:|---|---|
| `id` | `int(10) unsigned` | NO | NULL / none reported | `PRI auto_increment` |
| `kategori_id` | `int(10) unsigned` | NO | NULL / none reported | `UNI` |
| `active_months` | `smallint(5) unsigned` | NO | NULL / none reported | — |
| `inactive_months` | `smallint(5) unsigned` | NO | NULL / none reported | — |
| `final_action` | `enum('Musnahkan','Permanen','Serah ke ANRI')` | NO | NULL / none reported | — |
| `alert_days` | `smallint(5) unsigned` | NO | `90` | — |
| `updated_by` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `created_at` | `datetime` | NO | `current_timestamp()` | — |
| `updated_at` | `datetime` | NO | `current_timestamp()` | `on update current_timestamp()` |

**Indexes**

- `fk_retention_user` (non-unique, BTREE): `updated_by`
- `PRIMARY` (unique, BTREE): `id`
- `uq_retention_category` (unique, BTREE): `kategori_id`

**Foreign keys**

- `fk_retention_category`: `kategori_id` → `kategori_arsip.id` (ON UPDATE RESTRICT, ON DELETE CASCADE)
- `fk_retention_user`: `updated_by` → `users.id` (ON UPDATE RESTRICT, ON DELETE RESTRICT)

### `kategori_arsip` — InnoDB, utf8mb4_unicode_ci

| Column | SQL type | Null | Default reported | Key / extra |
|---|---|---:|---|---|
| `id` | `int(10) unsigned` | NO | NULL / none reported | `PRI auto_increment` |
| `nama_kategori` | `varchar(150)` | NO | NULL / none reported | `UNI` |
| `keterangan` | `text` | YES | `NULL` | — |
| `status` | `enum('Aktif','Nonaktif')` | NO | `'Aktif'` | — |
| `created_at` | `datetime` | NO | `current_timestamp()` | — |
| `updated_at` | `datetime` | NO | `current_timestamp()` | `on update current_timestamp()` |

**Indexes**

- `PRIMARY` (unique, BTREE): `id`
- `uq_kategori_nama` (unique, BTREE): `nama_kategori`

**Foreign keys**

- None reported.

### `loan_requests` — InnoDB, utf8mb4_unicode_ci

| Column | SQL type | Null | Default reported | Key / extra |
|---|---|---:|---|---|
| `id` | `bigint(20) unsigned` | NO | NULL / none reported | `PRI auto_increment` |
| `user_id` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `arsip_id` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `purpose` | `text` | NO | NULL / none reported | — |
| `requested_due_date` | `date` | YES | `NULL` | — |
| `status` | `enum('Menunggu Persetujuan','Disetujui','Ditolak','Dibatalkan')` | NO | `'Menunggu Persetujuan'` | `MUL` |
| `reviewer_note` | `text` | YES | `NULL` | — |
| `reviewed_by` | `int(10) unsigned` | YES | `NULL` | `MUL` |
| `reviewed_at` | `datetime` | YES | `NULL` | — |
| `loan_id` | `int(10) unsigned` | YES | `NULL` | `MUL` |
| `created_at` | `datetime` | NO | `current_timestamp()` | — |
| `updated_at` | `datetime` | NO | `current_timestamp()` | `on update current_timestamp()` |

**Indexes**

- `fk_loan_request_archive` (non-unique, BTREE): `arsip_id`
- `fk_loan_request_loan` (non-unique, BTREE): `loan_id`
- `fk_loan_request_reviewer` (non-unique, BTREE): `reviewed_by`
- `idx_loan_request_status` (non-unique, BTREE): `status`, `created_at`
- `idx_loan_request_user` (non-unique, BTREE): `user_id`
- `PRIMARY` (unique, BTREE): `id`

**Foreign keys**

- `fk_loan_request_archive`: `arsip_id` → `arsip.id` (ON UPDATE RESTRICT, ON DELETE RESTRICT)
- `fk_loan_request_loan`: `loan_id` → `peminjaman.id` (ON UPDATE RESTRICT, ON DELETE SET NULL)
- `fk_loan_request_reviewer`: `reviewed_by` → `users.id` (ON UPDATE RESTRICT, ON DELETE SET NULL)
- `fk_loan_request_user`: `user_id` → `users.id` (ON UPDATE RESTRICT, ON DELETE CASCADE)

### `lokasi` — InnoDB, utf8mb4_unicode_ci

| Column | SQL type | Null | Default reported | Key / extra |
|---|---|---:|---|---|
| `id` | `int(10) unsigned` | NO | NULL / none reported | `PRI auto_increment` |
| `gudang` | `varchar(100)` | NO | NULL / none reported | `MUL` |
| `rak` | `varchar(100)` | NO | NULL / none reported | — |
| `lemari` | `varchar(100)` | NO | NULL / none reported | — |
| `box` | `varchar(100)` | NO | NULL / none reported | — |
| `capacity_boxes` | `int(10) unsigned` | YES | `NULL` | — |
| `keterangan` | `text` | YES | `NULL` | — |
| `status` | `enum('Aktif','Nonaktif')` | NO | `'Aktif'` | — |
| `created_at` | `datetime` | NO | `current_timestamp()` | — |
| `updated_at` | `datetime` | NO | `current_timestamp()` | `on update current_timestamp()` |

**Indexes**

- `PRIMARY` (unique, BTREE): `id`
- `uq_lokasi` (unique, BTREE): `gudang`, `rak`, `lemari`, `box`

**Foreign keys**

- None reported.

### `notifications` — InnoDB, utf8mb4_unicode_ci

| Column | SQL type | Null | Default reported | Key / extra |
|---|---|---:|---|---|
| `id` | `int(10) unsigned` | NO | NULL / none reported | `PRI auto_increment` |
| `user_id` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `title` | `varchar(255)` | NO | NULL / none reported | — |
| `body` | `text` | NO | NULL / none reported | — |
| `type` | `varchar(50)` | NO | `'info'` | — |
| `url` | `varchar(255)` | NO | `'./'` | — |
| `data` | `text` | YES | `NULL` | — |
| `is_sent` | `tinyint(1)` | NO | `0` | — |
| `read_at` | `datetime` | YES | `NULL` | — |
| `created_at` | `datetime` | NO | `current_timestamp()` | — |

**Indexes**

- `idx_notifications_user` (non-unique, BTREE): `user_id`, `created_at`
- `PRIMARY` (unique, BTREE): `id`

**Foreign keys**

- `fk_notifications_user`: `user_id` → `users.id` (ON UPDATE RESTRICT, ON DELETE CASCADE)

### `password_reset_requests` — InnoDB, utf8mb4_unicode_ci

| Column | SQL type | Null | Default reported | Key / extra |
|---|---|---:|---|---|
| `id` | `int(10) unsigned` | NO | NULL / none reported | `PRI auto_increment` |
| `user_id` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `status` | `varchar(40)` | NO | `'Menunggu Verifikasi'` | `MUL` |
| `requested_at` | `datetime` | NO | `current_timestamp()` | — |
| `reviewed_by` | `int(10) unsigned` | YES | `NULL` | `MUL` |
| `token_hash` | `char(64)` | YES | `NULL` | — |
| `expires_at` | `datetime` | YES | `NULL` | — |
| `completed_at` | `datetime` | YES | `NULL` | — |

**Indexes**

- `fk_password_reset_reviewer` (non-unique, BTREE): `reviewed_by`
- `idx_password_reset_status` (non-unique, BTREE): `status`
- `idx_password_reset_user` (non-unique, BTREE): `user_id`
- `PRIMARY` (unique, BTREE): `id`

**Foreign keys**

- `fk_password_reset_reviewer`: `reviewed_by` → `users.id` (ON UPDATE RESTRICT, ON DELETE SET NULL)
- `fk_password_reset_user`: `user_id` → `users.id` (ON UPDATE RESTRICT, ON DELETE CASCADE)

### `peminjaman` — InnoDB, utf8mb4_unicode_ci

| Column | SQL type | Null | Default reported | Key / extra |
|---|---|---:|---|---|
| `id` | `int(10) unsigned` | NO | NULL / none reported | `PRI auto_increment` |
| `arsip_id` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `nama_peminjam` | `varchar(150)` | NO | NULL / none reported | — |
| `unit` | `varchar(150)` | YES | `NULL` | — |
| `tanggal_pinjam` | `date` | NO | NULL / none reported | — |
| `tanggal_rencana_kembali` | `date` | YES | `NULL` | — |
| `tanggal_dikembalikan` | `date` | YES | `NULL` | — |
| `condition_return` | `varchar(40)` | YES | `NULL` | — |
| `keperluan` | `text` | YES | `NULL` | — |
| `status` | `enum('Dipinjam','Dikembalikan')` | NO | `'Dipinjam'` | `MUL` |
| `created_by` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `created_at` | `datetime` | NO | `current_timestamp()` | — |
| `updated_at` | `datetime` | NO | `current_timestamp()` | `on update current_timestamp()` |

**Indexes**

- `fk_peminjaman_user` (non-unique, BTREE): `created_by`
- `idx_peminjaman_arsip` (non-unique, BTREE): `arsip_id`
- `idx_peminjaman_status` (non-unique, BTREE): `status`
- `PRIMARY` (unique, BTREE): `id`

**Foreign keys**

- `fk_peminjaman_arsip`: `arsip_id` → `arsip.id` (ON UPDATE RESTRICT, ON DELETE RESTRICT)
- `fk_peminjaman_user`: `created_by` → `users.id` (ON UPDATE RESTRICT, ON DELETE RESTRICT)

### `qr_batches` — InnoDB, utf8mb4_unicode_ci

| Column | SQL type | Null | Default reported | Key / extra |
|---|---|---:|---|---|
| `id` | `int(10) unsigned` | NO | NULL / none reported | `PRI auto_increment` |
| `tahun` | `year(4)` | NO | NULL / none reported | `MUL` |
| `bulan` | `tinyint(3) unsigned` | NO | NULL / none reported | — |
| `jumlah_qr` | `int(10) unsigned` | NO | `0` | — |
| `created_by` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `created_at` | `datetime` | NO | `current_timestamp()` | — |
| `updated_at` | `datetime` | NO | `current_timestamp()` | `on update current_timestamp()` |

**Indexes**

- `fk_qr_batch_user` (non-unique, BTREE): `created_by`
- `PRIMARY` (unique, BTREE): `id`
- `uq_qr_batch` (unique, BTREE): `tahun`, `bulan`

**Foreign keys**

- `fk_qr_batch_user`: `created_by` → `users.id` (ON UPDATE RESTRICT, ON DELETE RESTRICT)

### `remember_tokens` — InnoDB, utf8mb4_unicode_ci

| Column | SQL type | Null | Default reported | Key / extra |
|---|---|---:|---|---|
| `id` | `bigint(20) unsigned` | NO | NULL / none reported | `PRI auto_increment` |
| `user_id` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `token_hash` | `char(64)` | NO | NULL / none reported | `UNI` |
| `expires_at` | `datetime` | NO | NULL / none reported | — |
| `last_used_at` | `datetime` | YES | `NULL` | — |
| `created_at` | `datetime` | NO | `current_timestamp()` | — |

**Indexes**

- `idx_remember_tokens_user` (non-unique, BTREE): `user_id`
- `PRIMARY` (unique, BTREE): `id`
- `uq_remember_token_hash` (unique, BTREE): `token_hash`

**Foreign keys**

- `fk_remember_tokens_user`: `user_id` → `users.id` (ON UPDATE RESTRICT, ON DELETE CASCADE)

### `retention_reminders` — InnoDB, utf8mb4_unicode_ci

| Column | SQL type | Null | Default reported | Key / extra |
|---|---|---:|---|---|
| `id` | `bigint(20) unsigned` | NO | NULL / none reported | `PRI auto_increment` |
| `arsip_id` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `reminder_type` | `enum('Mendekati Retensi','Retensi Berakhir')` | NO | NULL / none reported | — |
| `due_date` | `date` | NO | NULL / none reported | — |
| `created_at` | `datetime` | NO | `current_timestamp()` | — |

**Indexes**

- `PRIMARY` (unique, BTREE): `id`
- `uq_retention_reminder` (unique, BTREE): `arsip_id`, `reminder_type`, `due_date`

**Foreign keys**

- `fk_retention_reminder_archive`: `arsip_id` → `arsip.id` (ON UPDATE RESTRICT, ON DELETE CASCADE)

### `riwayat_arsip` — InnoDB, utf8mb4_unicode_ci

| Column | SQL type | Null | Default reported | Key / extra |
|---|---|---:|---|---|
| `id` | `bigint(20) unsigned` | NO | NULL / none reported | `PRI auto_increment` |
| `arsip_id` | `int(10) unsigned` | YES | `NULL` | `MUL` |
| `user_id` | `int(10) unsigned` | NO | NULL / none reported | `MUL` |
| `aktivitas` | `varchar(150)` | NO | NULL / none reported | — |
| `keterangan` | `text` | YES | `NULL` | — |
| `created_at` | `datetime` | NO | `current_timestamp()` | `MUL` |

**Indexes**

- `idx_riwayat_arsip` (non-unique, BTREE): `arsip_id`
- `idx_riwayat_tanggal` (non-unique, BTREE): `created_at`
- `idx_riwayat_user` (non-unique, BTREE): `user_id`
- `PRIMARY` (unique, BTREE): `id`

**Foreign keys**

- `fk_riwayat_arsip`: `arsip_id` → `arsip.id` (ON UPDATE RESTRICT, ON DELETE SET NULL)
- `fk_riwayat_user`: `user_id` → `users.id` (ON UPDATE RESTRICT, ON DELETE RESTRICT)

### `users` — InnoDB, utf8mb4_unicode_ci

| Column | SQL type | Null | Default reported | Key / extra |
|---|---|---:|---|---|
| `id` | `int(10) unsigned` | NO | NULL / none reported | `PRI auto_increment` |
| `nama` | `varchar(150)` | NO | NULL / none reported | — |
| `profile_image` | `varchar(255)` | YES | `NULL` | — |
| `username` | `varchar(80)` | NO | NULL / none reported | `UNI` |
| `password` | `varchar(255)` | NO | NULL / none reported | — |
| `role` | `enum('Super Admin','Petugas Gudang','Admin Unit/Petugas Arsip','Verifikator','Pimpinan/Approver','Customer','Pengguna Umum')` | NO | `'Customer'` | — |
| `division` | `varchar(150)` | YES | `NULL` | — |
| `status` | `enum('Aktif','Nonaktif')` | NO | `'Aktif'` | — |
| `created_at` | `datetime` | NO | `current_timestamp()` | — |
| `updated_at` | `datetime` | NO | `current_timestamp()` | `on update current_timestamp()` |

**Indexes**

- `PRIMARY` (unique, BTREE): `id`
- `uq_users_username` (unique, BTREE): `username`

**Foreign keys**

- None reported.

## Hasil Tahap 1B: salinan MySQL dan uji baseline

### Koneksi dan backup

- Konfigurasi runtime yang diperiksa tanpa menampilkan kredensial: driver `mysql`, host `127.0.0.1`, port `3306`, database `arsip_bakti`; server lokal melaporkan MariaDB `10.4.32`. Driver runtime memakai session file, cache file, dan queue sinkron.
- Hak database yang diperlukan tersedia. Sebelum membuat salinan, pemeriksaan memastikan `arsip_bakti_test_copy` belum ada, nama sumber tepat `arsip_bakti`, dan sumber/target berbeda. Target dibuat dengan `utf8mb4_unicode_ci`; setelahnya koneksi dikonfirmasi memilih target tersebut sebelum restore.
- Backup konsisten dibuat dengan `C:\xampp\mysql\bin\mysqldump.exe` memakai `--single-transaction --quick --skip-lock-tables --skip-add-drop-table --hex-blob --triggers --routines --events --no-tablespaces --no-create-db --default-character-set=utf8mb4`. Semua 19 tabel menggunakan InnoDB, sehingga snapshot transaksional konsisten berlaku. Pemeriksaan metadata menemukan nol trigger, routine, dan event.
- Backup tersimpan di luar repository, dalam direktori session lokal: `arsip_bakti_stage1b_backup.sql` (35.276 byte; SHA-256 `eca913d87537f5af4465ed4f4129c94b6cfc4bf667862d95e8b7c3018a5645b3`). File ini tidak ditimpa. Dump hasil baru berisi 19 `CREATE TABLE` dan tidak berisi `DROP TABLE`; dump lama `database/arsip_bakti.sql` tidak digunakan.
- Backup memuat data database, termasuk hash password, dan berupa SQL lokal tanpa enkripsi file. ACL direktori membatasi akses ke akun Windows saat ini, Administrator, dan SYSTEM; jangan masukkan file ini ke repository, unggah, atau bagikan.
- Pemulihan dilakukan ke database terpisah `arsip_bakti_test_copy` pada server lokal yang sama, bukan ke `arsip_bakti`. Ini merupakan isolasi pada tingkat database/schema di server yang sama, bukan instance atau server terpisah.

### Uji adopsi baseline di salinan

Migration verifikasi sementara, disimpan di luar repository dan tidak menjadi kode aplikasi, hanya dijalankan memakai `php artisan migrate` dengan satu file melalui `--path` dan `--realpath`. Migration memverifikasi nama target, 19 tabel bisnis, 181 kolom, 87 komponen kolom indeks, 36 kolom foreign key, serta fingerprint metadata lengkap sebelum mencatat bahwa baseline existing telah dikenali. Migration itu tidak membuat ulang atau mengubah tabel bisnis.

| Pemeriksaan | Sebelum baseline | Sesudah baseline |
|---|---:|---:|
| Tabel bisnis existing | 19 | 19 |
| Kolom bisnis | 181 | 181 |
| Komponen kolom indeks | 87 | 87 |
| Komponen foreign key | 36 | 36 |
| Tabel keseluruhan di salinan | 19 | 20 |
| Baris pada tabel riwayat migration | Belum ada | 1 (baseline verifier) |

Tabel tambahan `migrations` dan satu catatan migration adalah hasil yang diharapkan dari Laravel migrator. Fingerprint skema 19 tabel bisnis sebelum dan sesudah cocok dengan fingerprint sumber. Jumlah baris setiap tabel bisnis juga tetap sama: 84 baris total, dengan 5 pada `arsip`, 6 pada `kategori_arsip`, 6 pada `lokasi`, 4 pada `qr_batches`, 60 pada `riwayat_arsip`, 3 pada `users`, dan 0 pada 13 tabel lainnya.

Pemeriksaan akhir pada database aktif `arsip_bakti` tetap menunjukkan 19 tabel, fingerprint skema identik dengan kondisi sebelum uji, jumlah baris yang sama, dan tidak ada tabel `migrations`. Tidak ada migration yang dijalankan pada database aktif.

### Migration bawaan Laravel dan batas validasi

Tes hanya menjalankan migration verifier dengan path sementara yang ditentukan secara eksplisit. `php artisan migrate:status` pada salinan menunjukkan ketiga migration bawaan (`create_users_table`, `create_cache_table`, `create_jobs_table`) masih **Pending**; tidak ada yang dijalankan. Ini penting karena migration users bawaan tidak cocok dengan kolom `users` existing, dan tabel framework lain yang diharapkannya tidak ada. Jangan menjalankan `php artisan migrate` tanpa pembatas path pada instalasi existing sebelum penanganan migration bawaan dirancang dan diuji.

Untuk langkah implementasi mendatang, migration existing dan migration instalasi baru perlu dipisahkan secara eksplisit: jalur existing hanya menjalankan baseline verifier dan migration aditif yang disetujui; tiga migration starter harus dikecualikan dari jalur itu, bukan dianggap sudah diterapkan. Jalur instalasi baru harus diuji terpisah dengan definisi schema lengkap yang cocok.

Suite `php artisan test` setelah uji salinan lulus **55 tes dengan 406 assertion**. PHPUnit tetap memakai SQLite `:memory:`; kompatibilitas MySQL dibuktikan terpisah oleh restore dan baseline verifier pada MariaDB `10.4.32`, bukan disimpulkan dari suite SQLite.

Hasil ini memvalidasi strategi **adopsi baseline existing yang memeriksa fingerprint lalu hanya mencatat riwayat** pada salinan aktual. Ini bukan baseline schema final untuk instalasi baru, bukan migration produksi yang disimpan dalam proyek, dan bukan persetujuan untuk mengubah database aktif.
