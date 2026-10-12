# Tahap 5F — Validasi Scope Divisi pada Laporan, Ekspor, dan Riwayat

Status: perubahan backend dan tes pada database Laravel testing saja. Tidak ada
perubahan role/grant, konfigurasi, migration, atau database produksi.

## Perubahan implementasi

- `ReportController::reportQuery()` menerapkan `ArchiveAccess::scope()` sebelum
  filter tanggal, status, kategori, atau lokasi. Daftar, cetak, CSV, XLSX, dan
  PDF semuanya menggunakan query yang sama. Untuk Super Admin, helper scope
  mempertahankan akses global existing.
- `ImportExportController` menerapkan scope yang sama pada query ekspor CSV
  arsip. Ekspor kategori/lokasi tetap berupa data master, bukan record arsip.
- `ActivityHistoryController` menghubungkan riwayat ke arsip dengan `LEFT JOIN`
  dan menerapkan `ArchiveAccess::scope()`. Event tanpa arsip tetap terlihat
  bagi Super Admin seperti sebelumnya; untuk role dengan scope division,
  event tanpa arsip atau tanpa division tidak lolos query scope. Search tetap
  diterapkan setelah scope.
- Tidak ada permission/grant yang ditambahkan. `REPORTS_VIEW`,
  `IMPORTS_MANAGE`, dan `HISTORY_VIEW` tetap tidak tersedia bagi Kepala Divisi
  dalam matriks aktual; request route langsung dari Kepala Divisi tetap 403.
  Query scoped menjadi defense-in-depth jika izin tertentu kelak disetujui.
- Perilaku Kepala Divisi yang menolak arsip tanpa division, termasuk arsip
  Publik/Internal, tidak diubah.

## Tes yang dijalankan

Tes terarah:

```text
php artisan test tests\Feature\ArchiveManagementTest.php --filter="report_query_scopes_division|archive_export_query_scopes_division|super_admin_report_and_exports_remain_global|only_super_admin_can_access_reports_and_exports|activity_history_query_scopes_archive_events_by_division|super_admin_history_keeps_global_and_unlinked_events|activity_history_is_restricted_to_super_admin|super_admin_can_search_read_only_activity_history|report_filters_use_legacy_date_period"
9 passed (40 assertions)
```

Suite penuh:

```text
php artisan test
65 passed (483 assertions)
```

Tes mencakup query laporan dan CSV arsip untuk record dalam divisi, divisi lain,
arsip tanpa division, dan dokumen rahasia; query riwayat arsip-linked serta
event tanpa arsip; penolakan route bagi Kepala Divisi/staf tanpa permission;
dan akses global Super Admin ke laporan, ekspor, serta riwayat.

Query-builder scope untuk Kepala Divisi dites langsung karena role tersebut
belum memiliki permission laporan/ekspor/riwayat. Route HTTP-nya secara benar
tetap berhenti di 403 dan tidak mengembalikan data. Ini membuktikan filter
query yang disiapkan, bukan grant atau akses route yang baru.

Semua tes memakai SQLite in-memory dari konfigurasi PHPUnit. Hasil ini tidak
membuktikan perilaku `LOWER(TRIM())`, collation, atau query pada MariaDB.
Tidak ada migration, query discovery kandidat duplikasi, atau query tulis ke
database produksi yang dijalankan.

## Cakupan dan hal yang masih terbuka

- File CSV ekspor arsip dan format laporan (CSV/XLSX/PDF) menggunakan query
  arsip yang diberi scope. Super Admin tetap global sesuai helper existing.
- Filter kategori/lokasi pada pilihan UI laporan masih metadata master global;
  kebijakan apakah metadata tersebut perlu discope per divisi belum ditetapkan.
- Riwayat tanpa `arsip_id` tidak dapat dikaitkan dengan divisi. Query
  division-scoped menolaknya; tetap terlihat bagi Super Admin. Kebijakan akses
  untuk event global oleh role selain Super Admin memerlukan keputusan sebelum
  grant `HISTORY_VIEW`.
- Endpoint riwayat hanya daftar pencarian terbatas 300 baris; tidak ada route
  detail riwayat tersendiri yang ditemukan.
- Customer/Pengguna Umum, Admin, Pegawai, role legacy, dan grant verifikasi
  tidak diubah. Tidak ada validasi MariaDB pada tahap ini.

File implementasi dan tes yang berubah:

- `app/Http/Controllers/ReportController.php`
- `app/Http/Controllers/ImportExportController.php`
- `app/Http/Controllers/ActivityHistoryController.php`
- `tests/Feature/ArchiveManagementTest.php`

Tidak ada migration role atau perubahan database yang termasuk Tahap 5F.
