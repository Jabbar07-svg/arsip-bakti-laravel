# Tahap 5L — Desain Harness Pengujian SQLite dan MariaDB Terpisah

## Status dan batas tahap

Dokumen ini adalah rancangan berdasarkan pembacaan statis terhadap
`ArchiveManagementTest.php`, konfigurasi PHPUnit, dan dokumentasi Tahap 5I–5K.
Tidak ada Composer, Artisan, tes, query, atau koneksi database yang dijalankan.
Tidak ada kode/configuration, database, akun, schema, fixture, permission,
migration, atau kandidat duplikasi yang dibuat/diubah. Desain ini tidak
membuktikan isolasi maupun kompatibilitas MariaDB.

Rujukan:

- [`role-permission-stage-5i-mariadb-validation.md`](./role-permission-stage-5i-mariadb-validation.md)
- [`role-permission-stage-5j-test-environment.md`](./role-permission-stage-5j-test-environment.md)
- [`role-permission-stage-5k-dba-readiness.md`](./role-permission-stage-5k-dba-readiness.md)
- [`role-permission-decision-addendum.md`](./role-permission-decision-addendum.md)

## 1. Audit dan pemisahan suite

### 1.1 Fakta test harness saat ini

`ArchiveManagementTest` berisi **67 metode test**. `setUp()` bersama memanggil
`parent::setUp()`, lalu `createSchema()`, lalu memasukkan Super Admin,
kategori, dan lokasi dasar. `createSchema()` membuat **18 tabel** dengan
`Schema::create()`. Tidak ditemukan `tearDown()`, trait `RefreshDatabase` /
`DatabaseTransactions`, maupun `Schema::drop*()` atau `truncate` dalam class.
Dengan demikian, seluruh 67 test saat ini mencoba DDL sebelum badan test
dijalankan, termasuk test yang isi utamanya tidak membaca database.

Konfigurasi PHPUnit yang diperiksa menetapkan SQLite `:memory:`. Pada pola
tersebut schema yang dibuat test terisolasi untuk koneksi in-memory, bukan
schema MariaDB persisten. Hal ini menjelaskan keberadaan DDL dalam harness
SQLite yang ada, tetapi tidak membuat class tersebut aman diarahkan ke
database MariaDB yang disiapkan DBA.

### 1.2 Inventaris seluruh 67 test

Seluruh test pada tabel berikut memanggil setup bersama yang membuat schema.
Label “DB body” menerangkan kebutuhan badan test; “DDL setup” tetap **Ya**
untuk semuanya pada implementasi sekarang.

| No. | Test | Kebutuhan utama badan test | DDL setup saat ini |
|---:|---|---|:---:|
| 1 | `test_login_form_posts_to_same_origin_with_csrf_token` | Render form dan token; badan test tidak memerlukan data DB | Ya |
| 2 | `test_super_admin_can_create_archive_and_get_a_unique_qr_and_audit_entry` | User, kategori/lokasi, arsip, QR, riwayat; insert dan verifikasi | Ya |
| 3 | `test_editing_archive_preserves_its_qr_id` | Arsip sintetis; update dan riwayat | Ya |
| 4 | `test_staff_only_sees_archives_allowed_for_their_division` | User dan arsip beberapa scope; query daftar | Ya |
| 5 | `test_archive_search_matches_division_warehouse_box_and_category_like_legacy` | Kategori, lokasi, arsip; query pencarian | Ya |
| 6 | `test_staff_cannot_create_or_edit_a_confidential_archive` | User; request akses dan penolakan mutasi | Ya |
| 7 | `test_non_manager_cannot_create_or_update_archives` | User dan arsip; penolakan CRUD | Ya |
| 8 | `test_roles_without_legacy_listing_access_cannot_browse_archive_list` | User; kontrol otorisasi daftar | Ya |
| 9 | `test_target_roles_fail_closed_until_their_permissions_are_assigned` | User dan permission existing; penolakan akses | Ya |
| 10 | `test_head_of_division_is_scoped_to_its_archives_and_loan_approvals` | User, arsip, pinjaman/request; scope dan keputusan | Ya |
| 11 | `test_qr_tools_are_available_to_managers_and_scan_obeys_archive_access` | User, arsip, QR; query/akses QR | Ya |
| 12 | `test_qr_print_all_mode_includes_filtered_records_beyond_the_page_size` | Arsip sintetis; query daftar/print | Ya |
| 13 | `test_non_manager_cannot_access_qr_label_management` | User; kontrol akses | Ya |
| 14 | `test_guests_must_authenticate_before_archive_mutations` | Request guest; perilaku auth | Ya |
| 15 | `test_internal_division_archive_is_not_visible_to_a_different_division` | User dan arsip beda divisi; query scope | Ya |
| 16 | `test_super_admin_dashboard_displays_archive_statistics_and_recent_records` | User dan arsip; query dashboard | Ya |
| 17 | `test_dashboard_metrics_use_real_loan_and_category_data` | Kategori, arsip, loan; agregasi dashboard | Ya |
| 18 | `test_warehouse_staff_dashboard_shows_only_archives_they_can_access` | User dan arsip; scope dashboard | Ya |
| 19 | `test_warehouse_staff_can_open_account_and_change_own_password` | User; update profil/password | Ya |
| 20 | `test_report_filters_use_legacy_date_period_and_accept_all_category_and_location` | Arsip, kategori/lokasi; query report | Ya |
| 21 | `test_only_super_admin_can_access_reports_and_exports` | User; akses report/export | Ya |
| 22 | `test_report_query_scopes_division_and_excludes_archives_without_division` | User dan arsip beberapa divisi/null; query scope | Ya |
| 23 | `test_archive_export_query_scopes_division_and_excludes_archives_without_division` | User dan arsip beberapa divisi/null; query export | Ya |
| 24 | `test_super_admin_report_and_exports_remain_global` | Arsip multi-divisi; query global untuk role existing | Ya |
| 25 | `test_super_admin_can_create_categories_and_delete_or_deactivate_safely` | User/kategori; insert dan delete/deactivate record sintetis | Ya |
| 26 | `test_super_admin_can_manage_and_search_locations` | User/lokasi; CRUD dan query | Ya |
| 27 | `test_only_super_admin_can_access_category_and_location_management` | User; kontrol akses | Ya |
| 28 | `test_archive_list_can_filter_by_location` | Arsip/lokasi; query filter | Ya |
| 29 | `test_super_admin_can_search_read_only_activity_history` | User/history; query baca | Ya |
| 30 | `test_activity_history_is_restricted_to_super_admin` | User; kontrol akses history | Ya |
| 31 | `test_activity_history_query_scopes_archive_events_by_division` | User, arsip, history terkait; query scope | Ya |
| 32 | `test_super_admin_history_keeps_global_and_unlinked_events` | History dengan/ tanpa arsip; query | Ya |
| 33 | `test_user_can_update_profile_and_change_password_with_current_password` | User; update profil/password | Ya |
| 34 | `test_account_page_offers_profile_and_password_settings_without_customer_reset_request` | User; render/query akun | Ya |
| 35 | `test_user_can_upload_and_read_only_their_own_private_profile_photo` | User, storage fake; upload dan baca | Ya |
| 36 | `test_user_cannot_change_password_without_valid_current_password` | User; validasi dan penolakan update | Ya |
| 37 | `test_users_can_only_read_and_mark_their_own_notifications` | Users/notifications; query dan update read state | Ya |
| 38 | `test_notification_feed_returns_only_the_authenticated_users_incremental_items` | User/notifications; query feed | Ya |
| 39 | `test_staff_can_create_and_return_a_loan_atomically` | User, arsip, loan; mutasi atomik | Ya |
| 40 | `test_customer_can_request_only_available_public_archives_and_duplicate_is_blocked` | User, arsip, customer request; insert/constraint | Ya |
| 41 | `test_approver_decision_updates_request_archive_loan_and_notification` | User, request, arsip, loan, notification; beberapa update | Ya |
| 42 | `test_loan_request_owner_cannot_approve_or_reject_own_request` | User/request; penolakan perubahan | Ya |
| 43 | `test_customer_submission_can_be_reviewed_into_public_archive_with_private_file` | User, submission, arsip/file; review dan mutasi | Ya |
| 44 | `test_customer_can_resubmit_a_document_marked_for_correction` | User/submission/file; update dan upload sintetis | Ya |
| 45 | `test_customer_access_requests_are_restricted_and_only_public_archives_can_be_approved` | User/request/arsip; review dan kontrol akses | Ya |
| 46 | `test_customer_request_owners_cannot_review_their_own_submissions_or_access_requests` | User/request; larangan self-review | Ya |
| 47 | `test_super_admin_can_manage_users_and_non_super_admins_cannot_access_management` | Users; CRUD dan otorisasi | Ya |
| 48 | `test_last_active_super_admin_cannot_be_deactivated` | Users; update yang ditolak oleh invariant | Ya |
| 49 | `test_super_admin_can_change_another_users_role_but_not_their_own` | Users; update role dan penolakan self-change | Ya |
| 50 | `test_password_reset_link_is_hashed_single_use_and_revokes_remember_tokens` | User, reset request, remember token; mutasi/validasi token | Ya |
| 51 | `test_super_admin_cannot_issue_reset_link_for_own_reset_request` | User/reset request; penolakan | Ya |
| 52 | `test_customer_history_page_shows_only_their_own_requests` | User/customer request/submission; query scope | Ya |
| 53 | `test_customer_service_pages_render_and_catalog_exposes_only_public_archives` | User/arsip; query katalog | Ya |
| 54 | `test_csv_import_requires_preview_and_only_commits_valid_category_rows_after_confirmation` | File CSV sintetis, kategori; preview dan insert | Ya |
| 55 | `test_csv_archive_import_validates_relations_and_skips_duplicate_rows` | File CSV sintetis, kategori/lokasi/arsip; insert dan unique behavior | Ya |
| 56 | `test_csv_location_import_updates_existing_unique_location_and_creates_new_location` | File CSV sintetis/lokasi; update dan insert | Ya |
| 57 | `test_csv_exports_are_super_admin_only_and_keep_native_headers` | User dan data export; query/download | Ya |
| 58 | `test_retention_schedule_reminders_and_disposition_are_authorized_and_transactional` | User, arsip, jadwal, reminder, disposition; transisi dan transaksi | Ya |
| 59 | `test_scheduled_retention_command_notifies_once_and_is_idempotent` | Retensi/reminder/notification; command dan idempotensi | Ya |
| 60 | `test_duplicate_candidates_are_detected_with_role_and_note_controls` | User, arsip, candidate review; discovery/review/update | Ya |
| 61 | `test_duplicate_discovery_is_deterministic_and_continues_past_existing_pairs` | 34 arsip dan existing candidate; batch >500, repeated DML/read | Ya |
| 62 | `test_duplicate_discovery_scopes_both_archive_sides_to_head_division` | User dan pasangan arsip multi-divisi; discovery | Ya |
| 63 | `test_duplicate_discovery_requires_existing_permission_for_target_roles` | User; penolakan endpoint | Ya |
| 64 | `test_duplicate_discovery_rolls_back_batch_and_logs_database_errors` | Arsip/candidate; transaksi, error injection, rollback/log | Ya |
| 65 | `test_report_csv_export_contains_filtered_archive_data` | Arsip/kategori/lokasi; query dan CSV download | Ya |
| 66 | `test_report_xlsx_export_is_a_valid_workbook_download` | Arsip/kategori/lokasi; query dan XLSX output | Ya |
| 67 | `test_report_pdf_export_downloads_a_valid_pdf` | Arsip/kategori/lokasi; query dan PDF output | Ya |

**Kesimpulan pemisahan:** berdasarkan badan test, hanya test 1 tidak
memerlukan database. Kandidat tersebut dapat dipindahkan ke test class tanpa
database setup, namun harus diverifikasi setara setelah implementasi. Tidak
ada test lain yang cukup dipindahkan hanya dengan melepas setup karena
memerlukan model/query/request yang berinteraksi dengan DB. Suite SQLite
sekarang tetap sumber regresi sampai suite pengganti dibuktikan setara;
perubahan pembagian test tidak boleh menghapus atau menonaktifkan cakupan lama.

### 1.3 Dua target suite

**Suite SQLite (regresi yang ada):**

- Pertahankan seluruh 67 test dan fixture/schema lifecycle yang ada sebagai
  baseline sampai pemisahan/pengganti lulus perbandingan.
- Tetap gunakan koneksi SQLite `:memory:` sebagaimana PHPUnit sekarang.
- Jangan mengubahnya agar menunjuk ke MariaDB.
- Setelah kelak ada pemisahan, wajib ada pemetaan satu-ke-satu setiap test
  lama ke test yang tetap berjalan; pengecualian hanya dapat disetujui dan
  dicatat, tidak boleh diam-diam hilang.

**Suite MariaDB (integrasi terpisah):**

- PHPUnit suite/profile khusus dan connection name khusus yang hanya memakai
  MariaDB test yang disetujui DBA.
- Tidak memakai `createSchema()`, migration, `RefreshDatabase`, schema
  builder, atau Artisan schema command.
- Mengandalkan schema persisten yang DBA siapkan dan telah lolos preflight.
- Mulai dengan test integrasi terarah untuk query/constraint/transaksi yang
  rawan perbedaan driver, tidak serta-merta mengulang seluruh 67 test.
- Test yang dipindahkan/diadaptasi wajib direkonsiliasi terhadap baseline
  SQLite, termasuk semua assertion dan jalur penolakan akses.
- Menjalankan MariaDB serial pada satu schema bersama; tes paralel hanya
  setelah isolasi per-worker dan pembuktian tidak berbagi fixture disetujui.
- Implementasi lokal saat ini mencakup preflight, schema readiness, fixture
  DML dasar, dan satu test scope divisi saja. Cakupan report/export/history,
  retensi, duplikasi, dan concurrency belum dipindahkan ke suite ini dan masih
  menunggu schema DBA serta implementasi/validasi terarah.

## 2. Lifecycle fixture DML-only

### 2.1 Isolasi dasar

Untuk test MariaDB biasa, rancangan utama adalah transaksi per test:

1. Preflight keselamatan lokal lulus, lalu pemeriksaan metadata read-only
   memastikan server/database/schema yang telah disetujui.
2. Fixture dibuat melalui `INSERT` sintetis dalam urutan parent-before-child
   yang mematuhi foreign key. Gunakan ID hasil insert; hindari ID tetap yang
   dipakai suite sekarang.
3. Test menjalankan request/query yang menjadi target dan melakukan assertion.
4. Transaksi test di-rollback untuk mengisolasi test berikutnya; tidak ada
   `DELETE`/`TRUNCATE`/`DROP` sebagai cleanup otomatis.

Transaksi luar hanya cocok untuk test yang seluruh operasi terjadi pada
connection yang sama dan yang tidak perlu membuktikan commit eksternal. Harus
diverifikasi apakah command yang dipanggil test memakai connection yang sama,
apakah ada koneksi tambahan, dan bagaimana framework memproses nested
transaction/savepoint pada versi server/driver yang disetujui. Test yang
mengandalkan perubahan committed atau melakukan verifikasi dari koneksi lain
tidak boleh diasumsikan terisolasi oleh rollback pembungkus.

### 2.2 Test yang membuktikan transaksi atau concurrency

- Test retensi, rollback discovery, dan atomicity dapat memiliki efek transisi
  transaksi tersendiri. Uji harus membuktikan hasil sebelum/sesudah kegagalan
  dengan cara yang tidak disamarkan oleh transaksi pembungkus.
- Concurrent discovery memerlukan dua proses/connection nyata. Fixture harus
  sudah disiapkan sebelum kedua worker berjalan. Gunakan database/schema khusus
  test untuk run tersebut, jangan menjalankan worker yang berlomba pada fixture
  yang dipakai suite serial.
- Jika test memerlukan commit lintas koneksi atau tidak dapat dibersihkan
  dengan rollback, DBA harus menyediakan database test disposable khusus run
  atau prosedur reset DML yang dibatasi secara eksplisit. Reset hanya boleh
  dilakukan oleh prosedur terotorisasi terhadap database test allowlist, dan
  bukan bagian otomatis suite. Tidak boleh memerlukan DDL atau grant DDL.
- Jika isolasi/concurrency tidak dapat dipastikan tanpa reset destruktif,
  tandai test sebagai **belum dapat dijalankan**; jangan melemahkan assertion
  atau mengklaim lulus.

### 2.3 Foreign key, unique constraints, dan ID

- Fixture harus memasukkan referenced users/categories/locations/archives
  sebelum child rows; hapus ketergantungan pada ID eksplisit seperti 1..34
  dengan helper ID-returning dan pasangan ID yang disimpan dalam test.
- Tabel sintetis harus memenuhi FK; jangan menonaktifkan FK checks untuk
  mempermudah setup.
- Uniqueness diuji sebagai sifat schema MariaDB yang disiapkan DBA, khususnya
  username, QR, jadwal/reminder, dan pasangan kandidat duplikasi.
- Hindari benturan nilai unique lewat namespace per-run/test yang disetujui.
  Nilai fixture sepenuhnya sintetis; tidak menggunakan berkas/dokumen/user
  production.
- Jika akun hanya diberi SELECT/INSERT/UPDATE/DELETE pada schema test, DML
  fixture dapat dilakukan, namun hak database umumnya tidak membatasi DELETE
  per row. Karena itu akun tidak diberi akses lintas schema, dan cleanup tidak
  boleh berupa operasi luas. Transaksi rollback lebih disukai untuk test biasa.

### 2.4 Batas reset tanpa DDL

Akun runtime tidak memiliki `CREATE`, `ALTER`, `DROP`, atau `INDEX`. Suite
tidak boleh menjalankan migration, `migrate:fresh`, `db:wipe`, `Schema::create`,
atau membuat trigger untuk fault injection. Schema rusak/tidak lengkap berarti
preflight gagal dan perlu perbaikan DBA di luar suite setelah persetujuan.
Tabel persisten tidak dibersihkan otomatis. Bila data test committed tertinggal
atau state test tidak dapat dipulihkan dengan rollback, hentikan suite dan
minta DBA menjalankan prosedur reset yang ditinjau pada target test yang
diverifikasi—jangan jalankan reset terhadap database produksi.

## 3. Guard koneksi fail-closed

Guard yang akan dirancang/diimplementasikan kemudian wajib membedakan
pemeriksaan **sebelum koneksi** dan pemeriksaan **read-only setelah koneksi**.
Pada tahap ini guard tidak dibuat maupun dijalankan.

### Pemeriksaan wajib sebelum koneksi

Tolak proses dengan status gagal, tanpa membuka koneksi database, jika:

1. Profile/runner bukan profile MariaDB test eksplisit yang disetujui atau
   `APP_ENV` bukan `testing`.
2. Connection name bukan yang disetujui (misalnya `mariadb_test`), driver
   efektif bukan `mariadb`, atau effective database tidak sama persis dengan
   allowlist DBA (usulan saat ini `arsip_bakti_test`).
3. Nama database kosong atau sama dengan nama produksi `arsip_bakti`; nama
   produksi harus selalu ditolak, walau profile lain menyatakan test.
4. `DB_URL` atau URL pada connection ada/bernilai; `DB_*` generik yang tidak
   ditinjau hadir/bertentangan; atau variabel proses mengalahkan profile
   khusus.
5. Config cache ada dan sumber konfigurasi efektif tidak dapat dibuktikan
   berasal dari profile yang disetujui. Jangan menghapus cache otomatis untuk
   “memperbaiki” kondisi ini.
6. Runner/invocation tidak dikenal atau melewati launcher/guard yang disetujui.
7. Identitas akun yang diminta tidak sama dengan akun test khusus yang disetujui
   (jangan tampilkan username/password/token pada output).

Pemeriksaan sumber environment dilakukan sebelum bootstrap aplikasi bila
memungkinkan; guard sesudah bootstrap juga memverifikasi effective config
sebelum test melakukan query. Environment variable credential hanya dibaca
secara rahasia dan tidak pernah dimasukkan ke error/log.

### Validasi DBA dan pemeriksaan sesudah koneksi

Local allowlist tidak membuktikan server aktual maupun hak akun. Sebelum
menyediakan credential, DBA harus meninjau instance/host, account host mapping,
effective roles, proxy/inherited/wildcard grants, serta memastikan tidak ada
grant ke `arsip_bakti`. Jangan mencoba membuktikannya dengan koneksi negatif
ke database produksi.

Setelah pemeriksaan lokal lulus dan DBA menyetujui koneksi ke instance test,
preflight dapat menjalankan query **read-only** untuk membuktikan database
aktif, versi, collation, engine, dan fingerprint objek/schema. Jika database
aktif bukan target allowlist, objek wajib hilang/berbeda, atau marker fixture
belum dinyatakan siap, hentikan test sebelum fixture DML dimulai. Jangan
menjalankan query metadata pada target yang belum melalui pemeriksaan
pre-connection. Pemeriksaan ini belum dilakukan dan tidak membuktikan akun
test tidak memiliki grant produksi tanpa bukti DBA.

## 4. Matriks validasi lintas driver

Semua baris di bawah berstatus **belum dijalankan**. SQLite tetap baseline;
MariaDB memerlukan schema dan akun test yang disetujui DBA, fixture sintetis,
guard, dan runner terpisah. Hasil dicatat per test: passed hanya jika test
benar-benar berjalan dan assertion lulus; failed hanya jika berjalan dan
assertion/error gagal; skipped/not run bila prasyarat tidak tersedia. Tes yang
tidak dijalankan tidak dihitung sebagai passed.

| Kelompok | Cakupan/skenario | Schema dan fixture | Hak DB minimum | Pembeda SQLite–MariaDB / status |
|---|---|---|---|---|
| Scope divisi & collation | Divisi sama beda kapitalisasi; spasi; NULL/kosong; in/out division; kerahasiaan; list/detail/lampiran/QR/retensi. Pastikan kedua sisi pasangan discovery juga scoped | `users`, `arsip`, serta kategori/lokasi dan tabel terkait route. Data sintetis untuk casing/spasi/null dan classification | SELECT; DML fixture. UPDATE hanya bila jalur yang dites memang mutasi | Bandingkan hasil `LOWER`/`TRIM`, collation dan NULL tanpa menganggap hasil case-insensitive identik. Periksa nama divisi exact vs normalisasi PHP |
| Laporan, ekspor, riwayat | Scope query, detail/filter langsung, export CSV/XLSX/PDF; history terkait dan tanpa arsip; global Super Admin | `arsip`, `users`, `kategori_arsip`, `lokasi`, `riwayat_arsip`; kategori/lokasi fixture dan history nullable | SELECT plus DML fixture | Validasi join, collation, sort, tipe tanggal, agregasi dan format output. Kepala Divisi tidak diberi grant baru |
| Retensi | Usulan/review/penolakan/pelaksanaan yang ada; self-review/self-execution; status validasi; atomicity dan rollback | `arsip`, `users`, `jadwal_retensi`, `disposition_requests`, `retention_reminders`, `notifications`; fixture due-date terkontrol | SELECT/INSERT/UPDATE untuk jalur; DELETE tidak dibutuhkan pada transaksi rollback biasa | InnoDB lock/transaction semantics harus diuji di MariaDB. Reviewer sebagai pelaksana tetap perilaku kode saat ini, bukan keputusan kebijakan baru |
| Discovery duplikasi | GET read-only; endpoint POST berizin; scope dua arsip; 500/batch deterministic; rerun idempotent; metadata lama; unique pair; error rollback | `arsip`, `users`, `duplicate_reviews`; >500 pasangan sintetis dan satu existing sentinel | SELECT/INSERT; UPDATE hanya untuk jalur keputusan kandidat yang memang ada | Verifikasi unique key persis dan engine InnoDB. Error injection existing membuat SQLite trigger melalui `DB::statement`; sintaks `RAISE(ABORT)` khusus SQLite dan DDL trigger tidak boleh dijalankan dengan akun DML-only. Perlu desain test error MariaDB tanpa DDL runtime (mis. seam/fake koneksi yang disetujui) atau nyatakan coverage error MariaDB belum tersedia |
| Dua proses bersamaan | Dua worker discovery atas pasangan sama; tidak ada pasangan ganda atau perubahan metadata lama | Database/schema khusus concurrency, candidate/archive fixtures sintetis terisolasi; dua connection terpisah | SELECT/INSERT; izin test terbatas pada schema test | SQLite in-memory tidak membuktikan MariaDB locking/race. Jalankan hanya bila isolasi per-run disetujui dan kedua worker memakai target yang sama yang telah di-guard |
| Perbedaan SQLite–MariaDB | Case sensitivity, collation, `LOWER`/`TRIM`, NULL, tipe tanggal/enum, unique index, transaction/rollback, locking, FK/index | Schema MariaDB yang ditinjau DBA dan fixture yang sama secara semantik; tidak menyalin data production | Per kebutuhan skenario, DML only | Catat hasil per skenario, driver/version/collation dan test ID. Jangan menggeneralisasi satu versi/collation ke seluruh MariaDB |

### Aturan pelaporan status

- **Lulus:** test dijalankan pada target terverifikasi, preflight dan fixture
  berhasil, semua assertion lulus, driver/schema/version tercatat tanpa rahasia.
- **Gagal:** test dimulai setelah guard lulus tetapi assertion atau operasi
  aplikasi gagal. Simpan error yang aman dan lakukan triage; jangan menghapus
  data kandidat yang telah ada.
- **Dilewati/belum dapat dilakukan:** MariaDB, schema, grant, guard, atau
  strategi isolasi/concurrency belum tersedia; laporkan penyebab dan jangan
  menghitungnya sebagai lulus.
- **Tidak aman untuk dijalankan:** pemeriksaan target/configuration gagal.
  Runner berhenti sebelum koneksi dan melaporkan nama pemeriksaan yang gagal
  tanpa secret. Ini bukan test database gagal, tetapi blocker keselamatan.

## 5. Persetujuan sebelum implementasi

**Persetujuan DBA yang diperlukan**

- Instance MariaDB test, version, network boundary, database allowlist,
  charset/collation, engine InnoDB dan schema fingerprint yang disetujui.
- Akun khusus test; pemeriksaan effective grants membuktikan tidak dapat
  mengakses `arsip_bakti`; hak DML minimum hanya pada schema test; tanpa DDL.
- Penyediaan/maintenance schema, FK, index/unique key dan strategi fixture.
- Apakah schema boleh disposable; prosedur reset yang dijalankan DBA bila
  rollback tidak cukup; batas worker concurrency.
- Bukti approval disimpan di kanal internal yang sesuai, bukan repository bila
  memuat data sensitif.

**Persetujuan pemilik aplikasi yang diperlukan**

- Pemisahan test login dari class DB dan penerimaan test MariaDB baru, sambil
  mempertahankan seluruh suite SQLite sampai kesetaraan terbukti.
- Strategi transaction-per-test, test yang dikecualikan dari transaksi luar,
  dan reset DML/fixture run untuk command, commit, concurrency.
- Cara menangani test `CREATE TRIGGER` SQLite-only agar error/rollback tetap
  diuji tanpa DDL test atau pelemahan coverage.
- Scope awal suite MariaDB serta pemetaan seluruh 67 test lama ke coverage
  pengganti/terpisah.
- Kebijakan log/status test, ambang penghentian saat fixture tertinggal, dan
  larangan mengaktifkan permission role baru untuk mempermudah tes.

## 6. Status akhir Tahap 5L

Rancangan awal Tahap 5L menginventarisasi seluruh 67 test;
test login adalah satu-satunya yang badan test-nya tidak memerlukan database,
tetapi saat itu tetap terkena DDL `setUp()`. Suite MariaDB persisten belum
tersedia dan guard/harness belum dibuat pada saat rancangan awal disusun.
Implementasi lokal aman setelah tahap desain dicatat pada bagian berikutnya.

**Prasyarat tertunda:** persetujuan DBA atas instance/database/account/grants,
schema/index/collation, fixture dan reset; persetujuan pemilik aplikasi atas
pemetaan coverage dan strategi harness; serta validasi terarah setelah isolasi
terbukti. Sampai semua prasyarat tersebut terpenuhi, kompatibilitas MariaDB
dan keamanan database test belum dapat dinyatakan terverifikasi dan Tahap 6
belum siap.

## 7. Catatan implementasi harness setelah Tahap 5L

Pekerjaan lokal aman yang telah diterapkan:

- connection `mariadb_test` terpisah dengan nama database tetap
  `arsip_bakti_test`, endpoint dan akun dari variabel khusus `MARIADB_TEST_*`,
  serta tanpa URL/generic `DB_*`;
- preflight PHPUnit khusus yang mensyaratkan profile config, nilai target
  allowlist, persetujuan DBA eksplisit, versi/collation yang disetujui,
  penolakan variabel `DB_*` inherited, dan tidak adanya cached config;
- mode config Laravel profile MariaDB yang hanya menyediakan koneksi
  `mariadb_test`, sehingga koneksi eksplisit `mysql`/`mariadb` tidak tersedia
  pada profile tersebut;
- suite terpisah `tests/MariaDB` dengan pemeriksaan metadata read-only sebelum
  fixture, pemeriksaan schema InnoDB/kolom/role test, transaksi rollback
  per-test, fixture sintetis DML-only, dan satu test scope divisi SQL/PHP;
- test unit preflight tanpa koneksi.

Pemeriksaan yang dijalankan pada implementasi: lint PHP tanpa error; 7 test
unit preflight lulus (13 assertions); suite SQLite penuh lulus 76 test (532
assertions). Pemanggilan PHPUnit MariaDB tanpa attestation
dan target yang disetujui berhenti saat bootstrap preflight seperti yang
diharapkan; tidak ada test MariaDB yang dimulai dan tidak ada koneksi dibuat.
Suite MariaDB selain scope divisi belum dilengkapi/dijalankan. Rincian bukti
dan blocker ada di
[`role-permission-stage-5-completion-and-stage-6-readiness.md`](./role-permission-stage-5-completion-and-stage-6-readiness.md).
