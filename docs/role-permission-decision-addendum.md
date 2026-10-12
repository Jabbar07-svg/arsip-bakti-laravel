# Addendum Keputusan Role dan Permission

Status: audit, keputusan kebijakan, dan persiapan saja. Tidak ada perubahan
kode, akun, data, atau database. Belum ada migration yang dibuat atau dijalankan.

Dokumen ini merekonsiliasi keputusan terbaru dengan kondisi implementasi yang
tercatat di [`role-permission-audit.md`](./role-permission-audit.md),
[`role-permission-migration-plan.md`](./role-permission-migration-plan.md), dan
[`database-existing-schema.md`](./database-existing-schema.md). Keputusan
tentang kebijakan target tidak berarti permission atau perubahan skema tersebut
sudah diterapkan.

## Keputusan role target

| Role target | Keputusan kebijakan |
|---|---|
| Super Admin | Kewenangan administratif global sesuai kebijakan, termasuk pengguna/role dan pemantauan aktivitas. Tidak otomatis menjadi akun Customer atau dapat mengajukan pinjaman eksternal. |
| Admin | Mengelola operasional arsip, memproses peminjaman, memverifikasi dokumen dan duplikasi, mengelola kategori/lokasi, serta mengakses laporan dan impor/ekspor sesuai izin. Tidak mewarisi semua hak Super Admin dan tidak dapat meningkatkan haknya sendiri. Operasi create/update/delete/approval/user management dan scope yang belum ditetapkan tetap perlu keputusan. |
| Kepala Divisi | Dashboard/laporan divisi, arsip dalam divisinya, dan persetujuan yang diizinkan. Backend wajib menolak lintas-divisi dan self-approval. Arsip tanpa divisi tertutup kecuali ada kewenangan khusus eksplisit. |
| Pegawai | Dapat membaca/mencari arsip sesuai izin, mengajukan pinjaman, dan melihat status/riwayat permintaannya sendiri. QR, operasi lain, serta chat hanya jika permission eksplisit diberikan. Tidak memiliki hak admin, CRUD arsip, atau persetujuan secara default. |

Permission tetap statis di kode untuk tahap awal. Katalog implementasi belum
memisahkan setiap operasi secara granular. Karena itu, keputusan kebijakan di
atas perlu diterjemahkan menjadi permission backend terpisah dan cakupan endpoint
yang jelas sebelum diaktifkan.

## Distribusi akun aktual

Pemeriksaan agregat read-only database aktif `arsip_bakti` pada 2026-10-12
mencatat:

| Role legacy | Jumlah akun saat pemeriksaan |
|---|---:|
| Super Admin | 1 |
| Petugas Gudang | 1 |
| Admin Unit/Petugas Arsip | 0 |
| Verifikator | 0 |
| Pimpinan/Approver | 0 |
| Customer | 1 |
| Pengguna Umum | 0 |

Jumlah tersebut adalah jumlah baris per role tanpa filter status aktif; nilai
role yang tidak muncul pada hasil agregasi dihitung nol. Ini snapshot yang dapat
berubah, bukan daftar identitas atau instruksi pemindahan akun. Pemeriksaan
read-only juga mengonfirmasi MariaDB 10.4.32, 19 tabel, dan definisi
`users.role` berupa enum tujuh nilai legacy, `NOT NULL`, default `Customer`,
collation `utf8mb4_unicode_ci`. Tidak ada kredensial atau data pribadi yang
dicatat di addendum ini.

## Pemetaan role lama ke role target

Perilaku aktual di bawah merujuk pada katalog `RolePermissions` dan pemeriksaan
backend yang diaudit. Hak aktual dapat dipersempit oleh controller, klasifikasi,
status, kepemilikan, dan scope data.

| Role lama | Jumlah akun | Fungsi aktual dalam kode | Kandidat target | Tindakan yang direncanakan | Risiko perubahan |
|---|---:|---|---|---|---|
| Super Admin | 1 | Seluruh permission katalog kecuali `customer.portal` dan `loans.request`; akses administratif global, termasuk pengelolaan pengguna, master data, laporan, impor/ekspor, review customer/duplikasi, dan akses arsip rahasia. Proteksi Super Admin aktif terakhir tersedia. | Super Admin | Dipertahankan sebagai Super Admin. Hak eksternal tidak ditambahkan. | Perubahan tak disengaja dapat menghilangkan satu-satunya akun administratif; aturan self-approval dan pembatasan akses data sensitif perlu tetap konsisten. |
| Petugas Gudang | 1 | Dashboard, arsip list/view/manage non-rahasia, scan dan pengelolaan QR, peminjaman operasional, lihat/usulkan retensi, serta review duplikasi. | Pegawai, kandidat | Audit tugas akun dan pemilik data; kemudian rencanakan pemetaan satu per satu setelah izin Pegawai dan dampaknya disetujui. Tidak ada konversi pada tahap ini. | Kandidat Pegawai hanya diizinkan melihat/mencari dan mengajukan pinjaman menurut kebijakan baru; pemetaan langsung dapat mencabut tugas operasional lama atau sebaliknya mempertahankan hak berlebih. |
| Admin Unit/Petugas Arsip | 0 | Seperti pengelola operasional: arsip list/view/manage non-rahasia, QR scan/manage, peminjaman operasional, retensi view/propose, dan duplicate review. Bukan admin global dalam katalog. | Admin operasional, kandidat | Petakan sebagai Admin operasional hanya setelah memastikan batas unit serta grant operasional tidak menjadi hak global. Tidak ada konversi pada tahap ini. | Penamaan “Admin” dapat mendorong eskalasi ke pengelolaan pengguna/config global yang tidak dimiliki role lama. |
| Verifikator | 0 | Tepat `qr.scan` dan `duplicates.review`; tidak memiliki listing arsip umum, persetujuan pinjaman/retensi, review customer, laporan, atau pengelolaan pengguna. Review kandidat duplikasi memiliki efek samping pencatatan kandidat. | Tidak dipetakan otomatis | Pertahankan role legacy sementara. Pertahankan fungsi sebagai permission khusus setelah ada pengganti dan regresi semua route terkait lulus. | Menghapus role atau sekadar memindahkan ke Admin/Pegawai dapat memutus verifikasi atau memperluas akses lebih dari tugas verifikator. |
| Pimpinan/Approver | 0 | `archives.view`, `qr.scan`, `loans.approve`, `retention.view`, dan `retention.review`. Role legacy ini belum division-scoped secara otomatis. | Kepala Divisi, kandidat | Pemetaan hanya setelah scope divisi, isolasi request/arsip tanpa divisi, dan larangan self-approval terpasang serta teruji. | Hak lama berpotensi lintas-divisi; pembatasan baru dapat membuat daftar dan keputusan berbeda bila semua query/mutasi tidak memakai scope sama. |
| Customer | 1 | Portal eksternal: melihat arsip publik, scan QR sesuai pembatasan akses, mengajukan permintaan pemeriksaan/akses serta dokumen, revisi pengajuan, melihat status/riwayat sendiri, dan meminta pinjaman arsip publik. | Pegawai, direncanakan tetapi belum disetujui sebagai transisi akun | Audit semua alur dan relasi customer terlebih dahulu. Jangan mengubah akun/nilai role massal. Pisahkan permission portal dari role pegawai sebelum memindahkan akun apa pun. | Menjadikan akun ini Pegawai tanpa desain terpisah dapat membuka operasi internal; menghapus izin portal dapat memutus akses ke pengajuan, lampiran, permintaan, pinjaman, notifikasi, dan riwayat milik akun. |
| Pengguna Umum | 0 | Memiliki set permission katalog sama seperti Customer: daftar/lihat arsip publik, scan QR, portal, dan permintaan pinjaman. Kode tidak membuktikan kedua kelompok mempunyai kebijakan atau siklus hidup yang identik. | Tetap terpisah sementara | Pertahankan role selama audit dan jangan gabungkan ke Pegawai atau Customer sampai fungsi portal dan data terdampak dipahami. | Penggabungan dapat mengubah akses portal, kepemilikan data, kebijakan verifikasi, dan komunikasi pengguna tanpa konfirmasi. |

Jumlah nol tidak berarti role dapat dihapus: nilai role masih ada di enum,
validasi, dan/atau katalog kode; akun dapat dibuat atau bertambah sebelum
perubahan berikutnya.

## Konflik Customer menjadi Pegawai

Keputusan terbaru menyatakan Customer **direncanakan** menjadi Pegawai, tetapi
keputusan sebelumnya mempertahankan Customer dan Pengguna Umum sebagai role
eksternal yang terpisah. Karena permintaan ini meminta transisi Customer tetapi
tetap melarang penghilangan fungsi, hasilnya adalah konflik kebijakan yang harus
disetujui sebelum implementasi. Belum ada perubahan akun yang dilakukan.

Audit menemukan setidaknya alur berikut bergantung pada konteks Customer:

- Route portal `/portal-customer/*` dan pemeriksaan `customer.portal`.
- Akses arsip publik, pencarian, filter, dashboard portal, dan scan QR
  terotorisasi.
- Pengajuan dan revisi dokumen serta lampiran privat; review dilakukan lewat
  route terpisah dan `customer.review`.
- Permintaan pemeriksaan/akses dan riwayat status milik sendiri.
- Permintaan pinjaman arsip publik; halaman riwayat membatasi dengan `user_id`.
- Notifikasi keputusan dan pemberitahuan kepada akun pemohon.
- Data terkait pada tabel existing mencakup `customer_submissions`,
  `customer_requests`, `customer_arsip_access`, `loan_requests`, `arsip_files`,
  dan `notifications` (relasi pastinya mengikuti foreign key inventaris skema).

Kontrol portal dan pengajuan pinjaman saat ini memeriksa role/permission
Customer, bukan tipe akun eksternal yang terpisah dari role pegawai. Maka
perubahan `Customer` ke `Pegawai` saja kemungkinan akan menolak endpoint portal
yang mengandalkan `customer.portal` dan `loans.request`. Sebaliknya, memberi
permission portal secara luas kepada Pegawai berisiko membuat semua pegawai
bertindak sebagai Customer.

Sebelum transisi, keputusan yang diperlukan:

1. Apakah akun Customer lama benar-benar akun pegawai internal, atau pengguna
   eksternal yang kebetulan perlu role Pegawai untuk fungsi lain?
2. Jika dialihkan, apakah pengajuan dan riwayat lamanya tetap tersedia untuk
   akun yang sama; siapa dapat membaca/mengelola lampiran dan permintaan lama?
3. Apakah portal Customer tetap dipakai oleh Pengguna Umum, oleh subset Pegawai
   dengan izin portal eksplisit, atau diganti dengan alur lain?
4. Bagaimana memisahkan hak dasar pegawai dari hak pemohon eksternal: permission
   fungsional terpisah, atribut akun, atau mekanisme lain yang disetujui?
5. Bagaimana akun dan semua permintaan pending/approved/needs revision
   ditangani satu per satu tanpa mengubah pemilik atau audit trail?

Migration enum tidak boleh melakukan konversi Customer menjadi Pegawai.
Transisi akun adalah perubahan data dan authorization terpisah, hanya setelah
audit hubungan, pemetaan manual yang disetujui, rencana pemulihan, serta uji
salinan.

## Matriks permission target

Status matriks:

- **Diizinkan**: keputusan kebijakan terbaru menetapkan kapabilitas tingkat
  modul; bukan klaim bahwa route/kode telah memberi hak tersebut.
- **Terbatas divisi**: hanya data divisi sendiri; semua query, detail, lampiran,
  QR, mutasi, dan keputusan harus menerapkan batas yang sama.
- **Ditolak default**: tidak ada grant kecuali keputusan khusus dibuat.
- **Perlu keputusan**: rincian operasi/scope belum ditentukan.
- **Belum ada fitur**: belum ditemukan route/controller/permission aplikasi
  Laravel untuk fungsi tersebut.

| Modul / operasi | Super Admin | Admin | Kepala Divisi | Pegawai |
|---|---|---|---|---|
| Dashboard — lihat | Diizinkan global | Perlu keputusan atas cakupan operasional | Terbatas divisi | Perlu keputusan metrik yang boleh dilihat |
| Arsip — lihat/cari | Diizinkan sesuai kebijakan global | Diizinkan untuk operasi arsip; cakupan unit perlu keputusan | Terbatas divisi; arsip tanpa divisi ditolak kecuali kewenangan khusus | Diizinkan hanya arsip yang diberi akses; klasifikasi dan scope perlu ditegaskan |
| Arsip — buat | Diizinkan sesuai kebijakan dan audit | Perlu keputusan operasi dan scope | Ditolak default; kewenangan khusus perlu keputusan | Ditolak default |
| Arsip — ubah | Diizinkan sesuai kebijakan dan audit | Perlu keputusan operasi dan scope | Ditolak default; kewenangan khusus perlu keputusan | Ditolak default |
| Arsip — hapus/nonaktif | Perlu aturan spesifik, audit, dan preservasi referensi | Perlu keputusan; bukan grant implisit dari kelola operasional | Ditolak default | Ditolak default |
| Arsip rahasia | Diizinkan sesuai kebijakan administratif | Perlu keputusan eksplisit; tidak otomatis | Ditolak; akses khusus harus disetujui | Ditolak default |
| QR — scan/lookup | Diizinkan sesuai akses arsip | Diizinkan untuk operasi sesuai izin arsip | Terbatas divisi | Perlu keputusan permission operasional; tidak boleh melewati akses arsip |
| QR — buat/cetak label | Diizinkan | Perlu keputusan scope dan tugas | Perlu keputusan dalam scope divisi | Perlu keputusan tugas |
| Peminjaman — ajukan dan lihat permintaan sendiri | Tidak sebagai Customer eksternal | Perlu keputusan jika dibutuhkan untuk akun sendiri | Perlu keputusan | Diizinkan; hanya status/riwayat sendiri |
| Peminjaman — proses operasional/transaksi | Diizinkan | Diizinkan sesuai kewenangan operasional; detail create/update/return perlu keputusan | Perlu keputusan; tidak otomatis sama dengan approval | Ditolak default; tugas operasional khusus perlu keputusan |
| Peminjaman — setujui/tolak | Diizinkan dengan larangan self-approval | Perlu keputusan terpisah; tidak otomatis dari “memproses peminjaman” | Terbatas divisi, tidak lintas divisi dan tidak self-approval | Ditolak default |
| Dokumen Customer — verifikasi | Diizinkan sesuai tugas reviewer | Diizinkan oleh keputusan fungsi Admin; cakupan reviewer/self-review perlu keputusan | Perlu keputusan, scope divisi dan pemisahan tugas | Ditolak default |
| Duplikasi — verifikasi/putusan | Diizinkan | Diizinkan oleh keputusan fungsi Admin; perubahan status wajib audit | Perlu keputusan | Ditolak default kecuali ditetapkan sebagai izin tugas khusus |
| Retensi — lihat | Diizinkan | Perlu keputusan cakupan operasional | Terbatas divisi | Perlu keputusan tugas |
| Retensi — jadwal/buat usulan | Diizinkan sesuai tata kelola | Perlu keputusan operasi yang tepat | Perlu keputusan | Perlu keputusan; bukan otomatis dari hak lihat |
| Retensi — review/setujui/selesaikan | Diizinkan dengan pemisahan tugas dan larangan self-approval | Perlu keputusan siapa reviewer dan pelaksana | Terbatas divisi; larangan self-approval | Ditolak default |
| Kategori/lokasi — lihat | Diizinkan | Diizinkan untuk operasional | Perlu keputusan | Perlu keputusan |
| Kategori/lokasi — buat/ubah/hapus/nonaktif | Diizinkan sesuai aturan referensial dan audit | Diizinkan mengelola modul menurut keputusan; per-operation delete/nonaktif dan scope perlu keputusan | Ditolak default | Ditolak default |
| Laporan — lihat | Diizinkan global sesuai kebijakan data | Diizinkan sesuai izin; laporan dan scope data perlu keputusan | Terbatas divisi | Ditolak default; laporan sendiri bila kelak disetujui |
| Impor/ekspor | Diizinkan sesuai kebijakan, preview, validasi, audit | Diizinkan sesuai izin; jenis data, batas baris/kolom, dan scope perlu keputusan | Ditolak default kecuali ekspor divisi disetujui | Ditolak default |
| Pengguna — lihat | Diizinkan administratif | Ditolak default; perlu keputusan eksplisit jika Admin perlu fungsi terbatas | Ditolak default | Ditolak |
| Pengguna/role — buat/ubah/hapus/eskalasi | Diizinkan administratif dengan proteksi akun terakhir; perubahan hak tercatat | Tidak boleh mengubah hak sendiri/menaikkan role; hak lain perlu keputusan | Ditolak | Ditolak |
| Riwayat aktivitas — lihat | Diizinkan sesuai kebutuhan audit global | Perlu keputusan scope dan sensitivitas | Terbatas divisi jika disetujui | Ditolak default; bukan riwayat aktivitas pribadi |
| Chat internal — baca/kirim | Perlu keputusan kebijakan dan fitur | Perlu keputusan kebijakan dan fitur | Perlu keputusan; bila ada, scope grup divisi | Perlu keputusan hak akses |
| Chat/grup divisi — kelola anggota/grup | Perlu keputusan administratif | Perlu keputusan | Perlu keputusan apakah Kepala Divisi mengelola grupnya | Ditolak default |

**Catatan implementasi:** `RolePermissions.php` saat ini masih memberi
Kepala Divisi grant `archives.list`, `archives.view`, `qr.scan`,
`loans.approve`, `retention.view`, dan `retention.review`, tetapi tidak memberi
`dashboard.view` atau `reports.view`. Helper menandai Kepala Divisi sebagai
division-scoped. Super Admin memiliki seluruh key katalog kecuali
`customer.portal` dan `loans.request`. Admin dan Pegawai masih memiliki array
permission kosong. Matriks target di atas bukan cerminan grant yang telah
diaktifkan. Khusus dashboard/laporan Kepala Divisi dan permission baru Admin/
Pegawai belum diterapkan.

## Chat dan grup divisi

Pencarian pada proyek Laravel menemukan route yang tersedia di
`routes/web.php` saja untuk alur aplikasi; tidak ditemukan route API terpisah,
controller/model, atau halaman React/Blade untuk chat internal dan grup chat.
`resources/js` berisi pengendali sidebar, QR scanner, dashboard, dan daftar
arsip, bukan fitur chat. Karena itu, chat/grup divisi berstatus **belum ada
fitur**, bukan permission yang sudah bisa diaktifkan. Sebelum membangun fitur,
tentukan apakah chat termasuk scope, siapa dapat membaca/mengirim/menghapus,
moderasi/retensi pesan, membership berbasis divisi, notifikasi, dan audit.

## Kondisi teknis authorization saat ini

- `routes/web.php` menaruh aplikasi di middleware `auth` dan `active`; tidak
  ditemukan middleware role terpusat. Sebagian besar otorisasi dilakukan ulang
  di controller/helper melalui `RolePermissions`, `ArchiveAccess`, atau
  pemeriksaan role customer khusus.
- Menu Blade menyembunyikan navigasi berdasarkan role/permission, tetapi
  tampilan menu bukan kontrol keamanan. Endpoint langsung harus tetap diuji.
- Validasi role pembuatan/perubahan pengguna di `UserController` memakai
  `RolePermissions::LEGACY_ROLES` yang berisi tujuh role lama. Dropdown
  `resources/views/users/index.blade.php` mendapat daftar tersebut dari
  controller. Role target belum muncul di dropdown maupun validasi; database
  enum juga belum menerimanya.
- Tidak ditemukan role-authorization pada file React yang diperiksa. React
  tidak boleh menjadi batas keamanan.
- Tidak ditemukan implementasi chat. Tidak ditemukan `routes/api.php` dalam
  struktur route yang diaudit.
- Query divisi saat ini memakai teks (`users.division` dan
  `arsip.division`), bukan relasi divisi terstruktur. Helper scope Kepala Divisi
  ada, tetapi aturan arsip tanpa divisi dan keseragaman semua endpoint harus
  diuji.

## Perencanaan migration tanpa menerapkannya

Database aktif memakai MariaDB 10.4.32; definisi saat pemeriksaan adalah enum
tujuh role legacy, `NOT NULL`, default `Customer`, `utf8mb4_unicode_ci`.
Rencana di [role-permission-migration-plan.md](./role-permission-migration-plan.md)
menambahkan `Admin`, `Kepala Divisi`, dan `Pegawai` di akhir enum sambil
mempertahankan nilai legacy, urutannya, default, nullability, charset/collation,
index, constraint, dan semua baris. Rencana tersebut tidak mengubah nilai akun.
Forward DDL aditif pernah diuji pada salinan MariaDB, tetapi **belum diuji sebagai
Laravel migration**.

Rencana tahapan, belum dieksekusi:

1. Sebelum migration dibuat, setujui matriks dan keputusan yang masih terbuka
   pada bagian berikut. Perluasan enum tidak otomatis mengaktifkan role, grant,
   atau pemetaan akun.
2. Buat backup baru yang konsisten, verifikasi restore pada database terpisah,
   dan rekam `SHOW CREATE TABLE users`, jumlah akun per role, jumlah tabel,
   kolom, index, foreign key, dan row count sebelum perubahan.
3. Buat migration aditif dengan preflight yang menolak database/definisi kolom
   yang tidak sesuai. Jangan membuat ulang tabel existing atau mengubah
   `.env`/`APP_KEY`.
4. Uji migration Laravel pada salinan MariaDB 10.4.32, bukan hanya SQLite.
   Bandingkan definisi enum, default, akun per role, schema counts dan row
   counts sebelum/sesudah.
5. Rollback wajib memeriksa jumlah pengguna untuk setiap role target. Jika ada
   pengguna, berhenti sebelum DDL; jangan menghapus atau memetakan akun.
6. Customer → Pegawai, bila kelak disetujui, adalah transisi data dan
   authorization terpisah; jangan masukkan ke migration enum atau lakukan
   update massal.
7. Tidak ada penerapan pada produksi sebelum hasil salinan, backup/restore,
   regresi authorization, dan persetujuan terpisah ditinjau.

## Daftar permukaan yang berpotensi berubah pada tahap implementasi berikutnya

Daftar ini inventaris dampak potensial, **bukan izin untuk mengubah file**:

- Skema: file migration baru untuk perluasan enum; tidak ada perubahan schema
  produksi dalam tahap ini.
- Katalog dan cakupan: `app/Support/RolePermissions.php`,
  `app/Support/ArchiveAccess.php`.
- Role validation/administrasi: `app/Http/Controllers/UserController.php`,
  `resources/views/users/index.blade.php`, dan bila diputuskan mekanisme
  authorization terpusat/middleware.
- Modul operasional yang melakukan pemeriksaan permission:
  `ArchiveController`, `DashboardController`, `LoanController`,
  `LoanRequestController`, `RetentionController`, `QrController`,
  `CustomerPortalController`, `CustomerReviewController`,
  `DuplicateReviewController`, `CategoryController`, `LocationController`,
  `ReportController`, `ImportExportController`,
  `ActivityHistoryController`, `NotificationController`, dan
  `AccountController`.
- Route dan navigasi: `routes/web.php`, `resources/views/layouts/app.blade.php`,
  view arsip/pinjaman/retensi/Customer/duplikasi/QR/laporan/pengguna/notifikasi.
  Hanya ubah route/view yang hasil audit permission menunjukkan perlu.
- React: `resources/js/components/Dashboard.tsx`, `ArchiveIndex.tsx`,
  `QrScanner.tsx`, `ResponsiveSidebarControls.tsx` dan entry point bila suatu
  navigasi role-aware memang diputuskan; saat ini otorisasi tidak boleh
  dipindahkan hanya ke React.
- Pengingat Super Admin: `app/Support/RetentionReminderDispatcher.php` dan
  `app/Console/Commands/DispatchRetentionReminders.php` bila kebijakan
  penerima berubah.
- Regresi: `tests/Feature/ArchiveManagementTest.php` dan tes fitur baru
  role×route/aksi untuk seluruh alur yang disetujui.

Alur API terpisah tidak ditemukan dalam route yang diperiksa. File yang akhirnya
perlu diubah baru dapat ditentukan setelah permission matrix dan endpoint
terkait disetujui serta ditelusuri per tindakan.

## Rencana pengujian regresi

Sebelum mengaktifkan perubahan, tes Feature berbasis kombinasi role × route ×
metode HTTP perlu memeriksa status response dan memastikan request yang ditolak
tidak memutasi data. Minimal:

1. Keempat role target dan seluruh tujuh role legacy, termasuk guest/nonaktif,
   untuk route dashboard, arsip, peminjaman, portal, QR, retensi, review
   dokumen/duplikasi, master data, laporan, impor/ekspor, pengguna, riwayat,
   notifikasi, dan chat bila kelak ada.
2. Arsip publik, internal, internal divisi yang sama/berbeda, rahasia, dan
   tanpa divisi untuk list/detail/search, file privat, QR, edit, dan ekspor.
3. Kepala Divisi tidak dapat melihat, mengubah, atau menyetujui lintas-divisi;
   record tanpa divisi ditolak kecuali grant khusus eksplisit; request sendiri
   tidak dapat disetujui/ditolak oleh pembuatnya.
4. Verifikator tetap dapat scan/review duplikasi tetapi tidak mendapat listing
   umum atau fitur admin yang tidak diberikan.
5. Customer dan Pengguna Umum mempertahankan portal publik, kepemilikan
   submission/access request/loan history, file, revisi dan notifikasi;
   transisi Customer tidak mengubah pemilik atau status historis.
6. Admin hanya mendapat operasi yang disetujui; tidak dapat mengubah role
   sendiri, menaikkan privilege, mengubah Super Admin, atau mengakses modul di
   luar matriks.
7. Pegawai hanya melihat data yang diizinkan, membuat permintaan atas dirinya,
   dan melihat status/riwayat sendiri; bukan approval, administration, atau
   akses Customer massal.
8. Uji navigasi dan dropdown role terhadap backend: nilai yang ditampilkan harus
   diterima validator, role yang ditolak tidak boleh disisipkan via request.
9. Uji MariaDB pada salinan untuk migration/rollback. SQLite in-memory tetap
   berguna untuk tes perilaku, tetapi bukan bukti kompatibilitas enum, collation,
   DDL, atau FK MariaDB.
10. Jika chat disetujui, uji isolasi keanggotaan divisi, akses pesan langsung,
    perubahan keanggotaan, notifikasi dan audit.

## Keputusan yang masih perlu persetujuan sebelum implementasi

1. Konfirmasi strategi Customer: apakah transisi satu akun saat ini ke Pegawai
   tetap diinginkan setelah dampak portal di atas, dan bagaimana hak/riwayat
   eksternal dipisahkan tanpa kehilangan akses atau memperluas izin ke semua
   Pegawai.
2. Tetapkan apakah nilai `Customer` tetap diterima untuk akun lain selama
   transisi. Pengguna Umum tetap terpisah sampai keputusan berbeda disetujui.
3. Tetapkan detail operasi Admin: create/update/delete arsip, pemrosesan vs
   persetujuan pinjaman, verifikasi Customer/duplikasi, operasi retensi, CRUD
   kategori/lokasi, tipe dan cakupan laporan/impor/ekspor, serta riwayat
   aktivitas.
4. Tetapkan apakah Admin boleh melihat/mengelola akun dan role; default saat ini
   tidak. Tetapkan batasan agar Admin tidak dapat meningkatkan role sendiri.
5. Tetapkan detail Pegawai: klasifikasi/lingkup arsip, QR scan/cetak, operasi
   peminjaman selain request sendiri, dan modul operasional tambahan.
6. Konfirmasi grant Kepala Divisi yang sudah ada dan tambahkan dashboard/laporan
   divisi hanya setelah semua endpoint dan scope divisi diterapkan. Tentukan
   perlakuan record tanpa divisi, normalisasi teks divisi, dan larangan
   self-approval untuk tiap jenis approval.
7. Konfirmasi pemilik fungsi Verifikator dan berapa lama role/izin legacy
   dipertahankan sebelum penggantinya boleh diaktifkan.
8. Putuskan apakah chat/grup divisi termasuk tahap fitur berikutnya, dan
   kebijakan akses, membership, moderasi serta retensi pesan.
9. Setujui perluasan enum hanya setelah migration Laravel diuji di salinan
   MariaDB, backup/restore terverifikasi, dan rollback guard terhadap akun
   target disepakati.
10. Tentukan proses persetujuan terpisah sebelum perubahan akun satu per satu.
    Tidak ada pemetaan massal yang dilakukan oleh migration.

**Status:** keputusan kebijakan dan dokumentasi diperbarui; implementasi,
perubahan enum, perubahan akun, dan grant permission baru belum dilakukan.
Berhenti di tahap persiapan dan tunggu persetujuan sebelum perubahan kode atau
migration.

## Addendum Tahap 5B — Finalisasi matriks Admin/Pegawai dan rancangan kompatibilitas

Status: audit kode dan rancangan. Tidak mengubah kode, grant aktif, data akun,
atau database. Migration tidak dibuat atau dijalankan.

### Hasil pencocokan keputusan dengan implementasi

| Area | Kondisi aktual | Rancangan kompatibel / gap |
|---|---|---|
| `RolePermissions` | `Admin` dan `Pegawai` terdaftar sebagai role target di map permission, tetapi kedua array kosong. `Kepala Divisi` sudah diberi arsip list/view, QR scan, approval pinjaman, dan lihat/review retensi. `Super Admin` mendapat semua key allowlist kecuali portal dan permintaan pinjaman eksternal. Role legacy tetap punya grant terpisah. | Jangan menambah grant Admin/Pegawai sebelum operasi dan scope di matriks berikut disetujui. Grant Kepala Divisi harus dilengkapi dashboard/laporan divisi hanya sesudah query-nya di-scope. Pertahankan allowlist permission statis dan role legacy selama transisi. |
| `ArchiveAccess` | Kepala Divisi dikenali sebagai division-scoped. `canView` menolak divisi kosong/tidak cocok, dan menolak dokumen rahasia selain Super Admin. Query scope memakai divisi yang dinormalisasi dengan trim/lowercase; divisi akun kosong menghasilkan hasil kosong. | Terapkan helper pada setiap list, detail, search, lampiran, QR, laporan, ekspor, dashboard, pinjaman, duplikasi, dan retensi target. Arsip tanpa divisi tetap ditolak kepada Kepala Divisi kecuali ada grant khusus eksplisit. Uji konsistensi query SQL dan record-level; relasi divisi saat ini masih teks bebas. |
| Route/middleware | Route aplikasi berada pada grup `auth` dan `active`; tidak ditemukan middleware role sentral atau file route API. Otorisasi modul dilakukan pada controller/helper. | Middleware login/status tetap diperlukan, tetapi bukan pengganti otorisasi per-action backend. Tidak cukup menyembunyikan menu. |
| Validasi/dropdown pengguna | `UserController` memakai `LEGACY_ROLES` untuk create/update role; `resources/views/users/index.blade.php` menampilkan `$roles` yang sama. Nilai enum DB juga tujuh role legacy. | Strategi transisi harus menerima role target dan legacy secara eksplisit hanya setelah enum tersedia, sambil mempertahankan role lama. Dropdown tidak boleh menampilkan role yang tidak dapat disimpan. Batasi siapa boleh memberikan role administratif dan cegah eskalasi sendiri. |
| Pengajuan loan/portal | Customer/Pengguna Umum mengajukan melalui `CUSTOMER_PORTAL`/`LOANS_REQUEST`; `LoanRequestController` membatasi history menggunakan `r.user_id`. | Permission `loans.request` Pegawai harus terpisah dari `customer.portal`. Akses request dan history Pegawai harus berbasis `user_id` dirinya sendiri. Jangan menyamakan semua Pegawai dengan pemohon customer eksternal. |
| Verifikasi | Verifikator legacy hanya mendapat QR scan dan review duplikasi. `CustomerReviewController` saat ini hanya mengizinkan `CUSTOMER_REVIEW`, yang katalog berikan kepada Super Admin. | Admin direncanakan melakukan verifikasi dokumen dan duplikasi, tetapi verifikasi harus menjadi izin fungsional dan guard setiap endpoint. Pertahankan Verifikator sampai transisi permission dan tesnya lengkap. |
| Dashboard/laporan | Dashboard tidak memiliki grant untuk Kepala Divisi. `reports.view` hanya Super Admin; laporan controller saat ini dibatasi Super Admin. | Target Kepala Divisi adalah dashboard dan laporan divisi; belum diterapkan. Query agregat, ekspor/cetak dan semua filter harus memakai scope divisi, tidak hanya halaman daftar. Admin ditetapkan mengakses laporan, tetapi perlu putusan apakah cakupannya global atau operasional/divisi. |
| Chat | Tidak ditemukan route/controller/model/view chat maupun API chat di proyek Laravel yang diaudit. | Pegawai “menggunakan chat internal sesuai hak akses” belum dapat diberikan sebagai grant yang berfungsi; fitur dan skema belum ada. Desain produk/akses masih prasyarat terpisah. |

### Matriks keputusan operasional Admin dan Pegawai

Status **Diizinkan (modul)** berarti kebijakan telah menetapkan modul
tersebut untuk role, bukan klaim permission backend telah diberikan. Setiap
operasi yang disebut “Perlu keputusan” tetap deny-by-default. Permission harus
statis dan ditegakkan backend pada setiap endpoint, bukan hanya menu.

| Modul/operasi | Admin | Pegawai |
|---|---|---|
| Dashboard | Perlu keputusan: apakah dashboard operasional dibutuhkan dan cakupannya | Perlu keputusan metrik/dashboard yang boleh dilihat |
| Arsip — cari/lihat | Diizinkan untuk operasional arsip; perlu keputusan batas global/unit dan klasifikasi | Diizinkan hanya untuk arsip yang memang boleh diakses; scope/klasifikasi ditetapkan oleh policy arsip |
| Arsip — buat | Perlu keputusan terpisah | Ditolak default; perlu keputusan tugas khusus |
| Arsip — ubah | Perlu keputusan terpisah | Ditolak default; perlu keputusan tugas khusus |
| Arsip — hapus/nonaktif | Perlu keputusan; wajib mempertimbangkan FK, audit, preservasi data | Ditolak default |
| Arsip rahasia | Ditolak default; grant khusus perlu persetujuan eksplisit | Ditolak default |
| Pinjaman — lihat daftar operasional | Diizinkan sesuai kewenangan Admin; cakupan data perlu keputusan | Hanya permintaan milik sendiri diizinkan; daftar transaksi operasional lain ditolak default |
| Pinjaman — proses/transaksi (buat/kembali dan operasi lain bila tersedia) | Modul peminjaman diizinkan; setiap operasi dan scope perlu keputusan | Mengajukan dan melihat permintaan sendiri diizinkan; memproses transaksi ditolak default |
| Pinjaman — ajukan | Perlu keputusan apakah Admin boleh bertindak sebagai pemohon pada akun sendiri | Diizinkan; requester harus akun Pegawai yang sedang login |
| Pinjaman — setujui/tolak | Perlu keputusan terpisah; “memproses” tidak otomatis berarti approver | Ditolak default |
| Verifikasi dokumen Customer | Diizinkan sebagai fungsi modul; cakupan reviewer, self-review, dan keputusan final perlu guard/putusan | Ditolak default |
| Verifikasi duplikasi | Diizinkan sebagai fungsi modul; kedua record harus berada dalam scope yang boleh dilihat | Ditolak default; Verifikator legacy tetap diizinkan berdasarkan grant legacy |
| Retensi — lihat/jadwal/usulan/review/pelaksanaan | Perlu keputusan operasi. Hak operasional Admin tidak otomatis mencakup jadwal, persetujuan, atau pelaksanaan penyusutan | Ditolak default; pengusulan hanya jika tugas eksplisit disetujui, review/approval ditolak |
| Kategori dan lokasi — lihat | Diizinkan untuk operasional | Perlu keputusan apakah referensi dibutuhkan |
| Kategori dan lokasi — buat/ubah | Diizinkan pada tingkat pengelolaan master sesuai keputusan; perlu keputusan apakah scope global atau unit | Ditolak default |
| Kategori dan lokasi — hapus/nonaktif | Perlu keputusan tersendiri; ikuti proteksi referensi yang sudah ada | Ditolak default |
| Laporan — lihat/cetak/ekspor | Diizinkan mengakses laporan; perlu keputusan apakah global/divisi, detail sensitif, dan izin ekspor | Ditolak default; laporan permintaan sendiri bila perlu keputusan |
| Impor/ekspor | Modul tersedia “sesuai izin”; perlu keputusan tipe data, scope, ambang/validasi, dan siapa mengonfirmasi impor | Ditolak default |
| Pengguna/role | Ditolak default. Tidak boleh eskalasi diri. Hak lihat/kelola akun non-role masih perlu keputusan | Ditolak |
| Riwayat aktivitas | Perlu keputusan scope/unit dan sensitivitas data | Ditolak default; bukan pengganti history permintaan sendiri |
| Notifikasi | Hanya data milik sendiri; operasi administratif notifikasi perlu keputusan | Notifikasi sendiri yang terkait permintaan diizinkan |
| Chat internal/grup divisi | Belum ada fitur; perlu keputusan desain dan permission | Belum ada fitur; perlu keputusan desain, keanggotaan divisi, dan operasi baca/kirim |

Keputusan Admin secara eksplisit mencakup modul operasional arsip, peminjaman,
verifikasi dokumen/duplikasi, master data, dan laporan. Matriks tidak
menyamakan izin modul dengan izin setiap aksi CRUD, approval, ekspor, atau akses
global; rinciannya di atas harus diputuskan sebelum grant dikodekan.

Keputusan Pegawai secara eksplisit mencakup lihat/search arsip yang diizinkan,
pengajuan peminjaman dan melihat status/riwayat permintaannya sendiri. QR dan
fungsi operasional lain belum otomatis diberi grant. Chat belum dapat
diimplementasikan karena tidak ada fitur chat yang ditemukan.

### Kepala Divisi: cakupan divisi dan self-approval

Audit jalur arsip menemukan:

- Daftar arsip, dashboard query arsip, query retensi, query duplikasi, pinjaman
  operasional, daftar approval pinjaman, dan beberapa query QR menggunakan
  `ArchiveAccess::scope`.
- Detail/edit/attachment, keputusan loan, usulan/review/penyelesaian retensi,
  dan keputusan duplikasi menggunakan `ArchiveAccess::canView` atau scope
  record-level.
- `RolePermissions::scopeToDivision` menolak semua hasil bila divisi akun
  kosong, membandingkan divisi dengan trim/lowercase, dan membuang klasifikasi
  rahasia. `canView` menolak jika divisi tidak cocok; jadi arsip tanpa divisi
  tidak lolos bagi Kepala Divisi dengan divisi terisi.
- Tes `test_head_of_division_is_scoped_to_its_archives_and_loan_approvals`
  memeriksa daftar/detail arsip, approval list, serta penolakan keputusan
  lintas-divisi tanpa mutasi. Tes retensi terpisah memeriksa scope dan alur
  review.

Namun ini belum menyelesaikan seluruh keputusan target:

1. `dashboard.view` dan `reports.view` belum diberi kepada Kepala Divisi.
   Dashboard target/laporan target perlu mem-filter semua agregat, cetak dan
   ekspor, serta menguji akses record yang dibentuk dari parameter.
2. Approval list/action pinjaman sudah membatasi record melalui arsip terkait,
   tetapi **self-approval pinjaman belum dicegah**: `LoanRequestController::decide`
   tidak menolak `submission.user_id === reviewer.id`. Policy Tahap 5B
   melarangnya; ini harus diperbaiki dan diuji sebelum permission approve
   diaktifkan untuk role target.
3. Review retensi sudah menolak pengusul menyetujui usulannya sendiri melalui
   `requested_by === reviewer.id`. Endpoint pelaksanaan retensi mengecek arsip
   di scope, namun perlu keputusan apakah pelaksana yang sama dengan pengusul
   atau approver boleh melaksanakan disposisi.
4. `CustomerReviewController` belum terbukti menolak reviewer yang merupakan
   pemilik submission/permintaan. Sebelum izin review diberikan ke Admin,
   tambahkan/putuskan guard self-review pada submission dan access request.
5. Divisi disimpan sebagai teks bebas, bukan FK ke master divisi. Perbedaan alias,
   ejaan dan perubahan nama dapat salah membatasi/mengekspos record. Tetapkan
   normalisasi/daftar kanonis sebelum scope ini dianggap kebijakan final.

Semua temuan adalah rencana/risiko untuk implementasi mendatang; tidak ada
perubahan authorization yang dilakukan di Tahap 5B.

### Audit alur Customer/Pengguna Umum dan risiko transisi

- `CustomerPortalController` memeriksa `customer.portal` pada landing portal,
  pencarian arsip publik, formulir dan aksi permintaan pemeriksaan/pengajuan,
  revisi, serta riwayat.
- `LoanRequestController` menggunakan `customer.portal` untuk route request dan
  history; rows history dibatasi ke `loan_requests.user_id` pemohon saat ini.
- `CustomerReviewController` adalah alur reviewer terpisah dan memakai
  `customer.review`; tabel `customer_submissions`/`customer_requests` mengikat
  permintaan ke `user_id`, lampiran pengajuan memakai penyimpanan privat, dan
  notifikasi ditujukan ke pemohon.
- `Customer` dan `Pengguna Umum` mendapat permission portal yang sama di katalog
  sekarang, tetapi masih nilai role terpisah dalam enum dan validator.
- Rencana Customer menjadi Pegawai tidak boleh diterapkan atau ditafsirkan
  sebagai grant portal ke semua Pegawai. `loans.request` perlu dipisah dari
  `customer.portal`; permintaan/riwayat Pegawai tetap terikat ke ID pemohon.
- Selama audit, kedua role eksternal dipertahankan. Transisi Customer
  memerlukan inventaris baris relasi dan status pending/approved/revision,
  rencana akses file/riwayat, mekanisme identitas pemohon, komunikasi, serta
  prosedur migrasi akun individual yang disetujui. Tidak ada data yang diubah.

### Rancangan implementasi kompatibel dengan role legacy

1. **Katalog statis terpisah dari role:** gunakan key izin spesifik untuk
   arsip lihat/buat/ubah, pinjaman request/manage/approve, review dokumen,
   review duplikasi, master kategori/lokasi, dashboard/laporan dan izin lain
   yang disetujui. Hindari satu flag `admin` yang memberi akses global.
2. **Jembatan transisi:** pertahankan entri tujuh role legacy dan perilaku
   masing-masing. Tambahkan pemetaan/permission target hanya untuk akun yang
   role targetnya telah disetujui. Jangan mengubah role existing massal;
   Verifikator, Customer, dan Pengguna Umum tetap berfungsi selama transisi.
3. **Allowlist validasi dan dropdown:** setelah skema target disetujui, backend
   validasi harus mengizinkan nilai legacy dan target yang memang dapat
   disimpan. Form harus memakai sumber allowlist yang sama dan mencegah
   pemberian Super Admin oleh Admin. Hindari default form Customer yang
   tidak disengaja ketika daftar role target ditambahkan.
4. **Enforcement:** semua GET/download/cetak/POST/PATCH/DELETE mengotorisasi di
   backend. Gunakan scope query untuk list dan agregat, kemudian record-level
   check untuk detail dan mutasi. Role `Kepala Divisi` selalu division-scoped;
   jangan gunakan route/view saja.
5. **Self-approval:** sebelum permission approval Admin/Kepala Divisi diaktifkan,
   tolak reviewer yang ID-nya sama dengan pemohon/pengusul pada pinjaman,
   retensi, dan review customer yang relevan. Uji request tertolak tidak
   mengubah status, transaksi, file, notification, atau audit selain bila
   kebijakan audit penolakan secara eksplisit menghendaki event penolakan.
6. **Perubahan bertahap:** implementasikan kode dan tes pada branch/staging,
   tetapi jangan menggabungkan/menerapkan sebelum persetujuan. Uji peran
   legacy dan role target berdampingan; hapus role lama hanya dalam tahap
   terpisah setelah tak ada akun/route/data yang bergantung padanya dan ada
   persetujuan.

### Rencana migration Laravel (belum dibuat)

Database aktif terakhir diperiksa read-only sebagai MariaDB 10.4.32 dengan
`users.role` enum tujuh nilai lama, `NOT NULL`, default `'Customer'`,
`utf8mb4_unicode_ci`; 19 tabel, 181 kolom, 74 indeks, 36 foreign key. Rancangan
di [`role-permission-migration-plan.md`](./role-permission-migration-plan.md)
menjadi acuan, dengan urutan tahap berikut:

1. Ambil backup konsisten baru dan uji restore ke DB terpisah; jangan memakai
   dump parsial yang tidak lengkap.
2. Sebelum `up`, validasi koneksi/nama target dan cocokkan `SHOW CREATE TABLE
   users`, enum penuh, default/nullability/charset/collation, serta hitungan
   role. Gagal secara eksplisit jika ada drift.
3. `ALTER` hanya menambahkan `Admin`, `Kepala Divisi`, dan `Pegawai` di ujung
   enum. Pertahankan ketujuh literal lama, urutan existing, default
   `Customer`, engine/collation, index/FK dan row data. Tidak ada UPDATE akun.
4. Uji migration Laravel final di salinan MariaDB yang diverifikasi, bukan hanya
   uji SQL manual atau SQLite. Bandingkan metadata kolom role, jumlah tiap
   role, table/column/index/FK counts, dan row count setiap tabel.
5. Sebelum `down`, hitung akun pada ketiga nilai target. Jika satu atau lebih
   ditemukan, rollback melempar error sebelum DDL; tidak menghapus/memetakan
   akun. Hanya setelah kosong dapat enum lama dipulihkan dengan atribut penuh.
6. Perluasan enum tidak mengubah validasi, dropdown, grant, role account, atau
   alur Customer. Kode kompatibilitas dan transisi akun harus melalui rencana
   serta persetujuan tersendiri.
7. Hanya setelah QA, tes regresi, backup/restore teruji dan persetujuan eksplisit
   dapat ada rencana deployment produksi terpisah. Tahap ini tidak mencakup
   penerapan tersebut.

### Tes yang wajib disiapkan

**Permission dan route**

- Matriks Feature role × route × HTTP method untuk keempat role target,
  tujuh role legacy, guest dan akun nonaktif. Uji status respons dan pastikan
  tidak ada mutasi saat akses ditolak.
- Admin: izin hanya untuk modul/aksi yang disetujui; larang pengelolaan role
  sendiri, peningkatan privilege, Super Admin assignment, serta aksi CRUD/
  approval yang belum disetujui.
- Pegawai: lihat/cari arsip sesuai policy; create loan request atas dirinya;
  hanya membaca permintaan dan status milik sendiri; tidak dapat melihat
  request pengguna lain, menyetujui, mengelola arsip, master, atau pengguna.
- Verifikator: scan dan review duplikasi tetap berfungsi; listing umum,
  persetujuan, review Customer dan admin tetap ditolak kecuali ada grant legacy
  terverifikasi yang memang berlaku.

**Scope divisi dan self-approval**

- Kepala Divisi: list/search/detail/lampiran/QR/dashboard/laporan/ekspor hanya
  divisi sendiri; uji beda kapitalisasi/spasi yang diizinkan kebijakan.
- Tolak divisi kosong pada akun dan arsip, arsip tanpa divisi, rahasia, serta
  divisi lain; pastikan response tidak membocorkan keberadaan record jika
  kebijakan memilih 404.
- Approval pinjaman list dan aksi harus sama-sama scoped; uji approve dan reject
  lintas divisi tanpa perubahan status.
- Pengusul tidak boleh approve/reject permintaan pinjaman sendiri.
- Retensi: pengusul tidak boleh review usul sendiri; uji scope pada list,
  review, completion dan berita acara; tes keputusan self-execution setelah
  kebijakan ditetapkan.
- Customer review: pemilik submission/access-request tidak dapat meninjau
  permintaannya sendiri setelah Admin memperoleh izin reviewer.

**Regresi eksternal dan fitur lama**

- Customer dan Pengguna Umum tetap dapat melihat arsip publik, portal, dokumen,
  revisi, request dan riwayatnya sendiri serta file/notifikasi terkait.
- Uji bahwa role Pegawai tidak otomatis memperoleh seluruh portal Customer.
- Uji peminjaman publik, status pending/approval, duplikasi request, transaksi
  atomik, notifikasi dan pemilik `user_id`.
- Uji QR, retensi, verifikasi dokumen/duplikasi, kategori/lokasi, laporan,
  impor/ekspor, riwayat, pengguna dan proteksi Super Admin aktif terakhir.
- Uji Blade navigation/dropdown terhadap validasi server; UI bukan satu-satunya
  lapisan kontrol.
- Chat hanya dapat dites setelah fitur dan kebijakannya dibuat; untuk saat ini
  catat sebagai fitur tidak tersedia, bukan tes pass.

**Skema**

- Jalankan `up` dan `down` migration Laravel pada salinan MariaDB 10.4.32.
- Uji `down` saat role target dipakai: harus gagal sebelum DDL dan enum/data
  tidak berubah; setelah fixture uji dibersihkan, rollback harus mengembalikan
  schema tepat.
- Bandingkan schema dan row counts penuh sebelum/sesudah. SQLite in-memory
  hanya bukti perilaku aplikasi, bukan bukti kompatibilitas MariaDB.

### File yang berpotensi berubah dan prasyarat

Perkiraan permukaan implementasi, belum merupakan perubahan yang disetujui:

- Migration baru: `database/migrations/*role*` untuk enum aditif dan guard
  rollback.
- `app/Support/RolePermissions.php`, `app/Support/ArchiveAccess.php` untuk
  katalog statis/bridge dan scope.
- `app/Http/Controllers/UserController.php`,
  `resources/views/users/index.blade.php`, `routes/web.php`, serta
  `resources/views/layouts/app.blade.php` untuk validasi, dropdown, enforcement
  dan navigasi.
- Controller yang harus ditelusuri/diperbarui sesuai keputusan: `ArchiveController`,
  `DashboardController`, `LoanController`, `LoanRequestController`,
  `RetentionController`, `QrController`, `CustomerPortalController`,
  `CustomerReviewController`, `DuplicateReviewController`, `CategoryController`,
  `LocationController`, `ReportController`, `ImportExportController`,
  `ActivityHistoryController`, `NotificationController`, dan reminder Super
  Admin. Tidak semua controller pasti perlu berubah.
- `tests/Feature/ArchiveManagementTest.php` dan tes permission/migration baru.
- Tidak ditemukan fitur chat/API route saat audit; chat akan memerlukan rancangan
  route, controller, penyimpanan/model, UI, dan permission terpisah.

Sebelum kode atau migration dibuat, diperlukan persetujuan eksplisit atas:

1. Operasi Admin yang masih `Perlu keputusan`: create/update/delete arsip;
   pemrosesan vs approval pinjaman; semua operasi retensi; scope report dan
   export; jenis/baris data impor; delete/nonaktif master; user visibility.
2. Apakah Admin dapat melihat/mengelola pengguna non-privilege. Kebijakan saat
   ini melarang eskalasi sendiri tetapi tidak menyetujui hak pengelolaan akun.
3. Apakah Pegawai mendapat QR scan/cetak atau permission operasional lain selain
   lihat/search sesuai akses dan loan request/history sendiri.
4. Apakah dashboard operasional Pegawai/Admin diperlukan dan data apa yang
   boleh ditampilkan.
5. Scope divisi kanonis, perilaku divisi kosong/alias, aturan Arsip tanpa divisi,
   akses laporan/dashboard/retensi dan seluruh aksi Kepala Divisi.
6. Keputusan self-approval untuk pinjaman, retensi, dan Customer review; temuan
   bahwa guard pinjaman dan guard Customer review belum ada harus diperbaiki
   sebelum grant approver/reviewer terkait diaktifkan.
7. Apakah pelaksana retensi yang sama dengan pengusul/approver boleh mengeksekusi
   disposisi yang telah disetujui.
8. Resolusi Customer → Pegawai tetap belum disetujui: desain pemisahan
   `customer.portal` dari izin pemohon Pegawai dan kepemilikan data lama.
9. Apakah chat internal/grup divisi termasuk scope produk; bila iya, policy
   membership, baca/kirim, moderasi, audit, dan retensi pesan.
10. Persetujuan strategi migration enum, uji pada salinan MariaDB Laravel,
    backup/restore, rollback guard, serta proses persetujuan pemetaan akun
    individual.

**Keputusan implementasi:** belum cukup jelas untuk menetapkan seluruh operasi
granular atau mengaktifkan grant. Tahap 5B berhenti pada temuan dan rancangan
ini. Tidak ada grant baru diaktifkan, tidak ada perubahan akun, dan tidak ada
migration dijalankan; tunggu persetujuan atas butir prasyarat di atas.

## Addendum Tahap 5C — Audit dan pengamanan alur persetujuan

Status: audit alur dan perbaikan backend terarah. Tidak ada perubahan schema,
enum, akun produksi, grant Admin/Pegawai, atau migration.

### Hasil audit alur status/persetujuan

| Alur dan route | Pengaju/pemilik | Reviewer/penentu | Self-review/approval | Scope dan ownership | Penolakan |
|---|---|---|---|---|---|
| Permintaan pinjaman Customer/Pengguna Umum; `POST /arsip/{archive}/permintaan-peminjaman` | Customer/Pengguna Umum dengan arsip Publik berstatus tersedia. Data `loan_requests.user_id` adalah pemilik; halaman riwayat memfilter ID login. | Role yang memiliki `loans.approve` (Super Admin/Pimpinan lama/Kepala Divisi sesuai katalog). | Sekarang dicegah di `LoanRequestController::decide`: reviewer dengan ID sama dengan `loan_requests.user_id` tidak boleh setujui atau tolak. | Persetujuan mengambil row terkunci, memeriksa `ArchiveAccess::canView`; Kepala Divisi hanya record sesuai divisinya. | 403 sebelum mutasi; status tetap pending, tidak membuat loan, tidak mengubah status arsip atau mengirim notifikasi. |
| Persetujuan/penolakan pinjaman; `PATCH /persetujuan/peminjaman/{id}` | Requester yang tersimpan di `loan_requests.user_id`. | `LOANS_APPROVE`; daftar memakai join arsip dan `ArchiveAccess::scope`, aksi juga memeriksa `canView`. | Self-approval kini ditolak untuk keputusan setuju maupun tolak. Admin belum diberi grant. | Record divisi lain/arsip rahasia/tanpa divisi tidak dapat diloloskan untuk Kepala Divisi melalui list maupun aksi. | 403 untuk self atau di luar scope; transaksi rollback/tidak menyentuh data. |
| Review dokumen Customer; `PATCH /verifikasi-customer/pengajuan/{id}` | Pemilik submission pada `customer_submissions.user_id`. | `CUSTOMER_REVIEW` (saat ini Super Admin; rencana Admin tidak otomatis grant). | Kini reviewer tidak dapat menyetujui, menolak, atau meminta revisi atas submission miliknya sendiri. | Customer mengakses submission/file sendiri. Reviewer dengan permission dapat meninjau file untuk tugas review; akses reviewer ini bukan akses akun Customer. | 403 sebelum perubahan status, arsip, file, atau notifikasi. |
| Review permintaan pemeriksaan/akses Customer; `PATCH /verifikasi-customer/permintaan-akses/{id}` | Pemilik request di `customer_requests.user_id`. | `CUSTOMER_REVIEW`. | Kini reviewer tidak dapat memproses request miliknya sendiri. | Pemohon melihat permintaan/riwayat milik sendiri; review memproses arsip Publik saja bila disetujui. | 403; status, reviewer, dan notifikasi tetap tidak berubah. |
| Usulan penyusutan; `POST /retensi/usulan` | Pengusul dicatat pada `disposition_requests.requested_by`. Pengusul harus dapat melihat arsip, arsip jatuh tempo, dan tidak sedang dipinjam/nonaktif. | Review dilakukan role `RETENTION_REVIEW`. | Reviewer tidak dapat menyetujui/menolak usulannya sendiri (guard sudah ada). Kebijakan Tahap 5C juga melarang pengusul mencatat pelaksanaan usulannya sendiri. | Daftar menggunakan `ArchiveAccess::scope`; create/review/complete/berita acara memeriksa record archive dengan `canView`. Kepala Divisi dibatasi divisi. | 403 untuk self atau di luar scope; tidak mengubah status usulan/arsip/berita acara. |
| Pelaksanaan usulan retensi; `PATCH /retensi/usulan/{id}/selesai` | Requester tetap `requested_by`. | Hanya role dengan `RETENTION_REVIEW` dapat melaksanakan usulan yang telah disetujui. | Kini `requested_by` tidak boleh menjadi pelaksana. Berbeda ID reviewer sebelumnya tetap dapat melaksanakan setelah status Disetujui. | Record usulan dikunci dan arsip diperiksa dengan `ArchiveAccess::canView`; completion di luar divisi ditolak. | 403 untuk pengusul atau out-of-scope; status tetap Disetujui dan arsip belum dinonaktifkan. |
| Verifikasi kandidat duplikasi; GET `/verifikasi-duplikasi`, PATCH `/verifikasi-duplikasi/{id}` | Tidak ada pengaju manusia. Kandidat dibuat secara otomatis dari pasangan arsip; pemanggilan halaman index dapat menyimpan pasangan candidate. | `DUPLICATES_REVIEW` (Super Admin dan Verifikator legacy). | Self-approval berbasis pengaju tidak berlaku karena tabel tidak mencatat pemohon; reviewer tidak boleh mengubah pasangan arsip di luar hak lihatnya. | List dan penemuan kandidat menerapkan scope untuk kedua arsip; aksi memeriksa `canView` pada keduanya. | 403 untuk pasangan di luar scope. Catat bahwa GET index memiliki efek samping pembuatan kandidat dan sebaiknya ditinjau sebagai pekerjaan terpisah. |
| Reset password admin; `POST /pengguna/reset-password` dengan `request_id` | Pemilik reset di `password_reset_requests.user_id`. | `USERS_MANAGE` (sekarang Super Admin) menerbitkan tautan. | Kini pemilik permintaan tidak dapat menerbitkan link reset untuk permintaannya sendiri. Jalur input `user_id` langsung sudah menolak target akun sendiri. | Request terkunci; target harus akun aktif. Link hash, sekali pakai, dan token lama dicabut pada reset. | 403; request tetap pending dan tidak membuat token/notifikasi. |
| Penyelesaian reset lewat token; `POST /reset-password/{token}` | Pemilik token mengubah password sendiri memakai token satu kali. | Bukan persetujuan oleh operator; pemilik menyelesaikan reset yang telah diterbitkan. | Bukan self-approval; penggunaan sendiri adalah tujuan alur. | Token di-hash, status/expiry dicek, hanya akun aktif; notifikasi/riwayat reset terkait pemiliknya. | Token invalid/kedaluwarsa/tidak aktif ditolak dan tidak mengubah password. |
| Peminjaman operasional/kembali; `POST /pinjaman`, `PATCH /pinjaman/{id}/kembali` | Operator operasional membuat/mencatat pengembalian, bukan permintaan persetujuan Customer. | `LOANS_MANAGE`; bukan workflow approval. | Self-approval tidak relevan; proses tetap memvalidasi status transaksi dan akses arsip. | `ArchiveAccess::canView` memeriksa arsip; return mengunci transaksi dan arsip. | 403/409 tanpa mutasi bila akses/status tidak cocok. |

Tidak ditemukan route approval arsip terpisah: persetujuan submission Customer
yang berhasil dapat membuat arsip baru, dan kini dilindungi guard kepemilikan
submission. Status verifikasi duplikasi bukan request yang diajukan pengguna;
pasangannya dihasilkan sistem.

### Aturan backend yang sekarang ditegakkan

- Pemohon loan tidak dapat menyetujui ataupun menolak loan request miliknya
  sendiri.
- Pengusul disposisi retensi tidak dapat mereview maupun melaksanakan
  usulannya sendiri. Pelaksanaan oleh approver berbeda tetap dapat dilakukan
  setelah status disetujui.
- Pemilik Customer submission/access request tidak dapat melakukan review
  statusnya sendiri, termasuk perubahan menjadi Disetujui, Ditolak, atau Perlu
  Perbaikan.
- Pemilik permintaan reset tidak dapat menerbitkan tautan reset untuk request
  miliknya sendiri. Pemohon yang memakai token sah untuk mereset passwordnya
  sendiri tetap diperbolehkan; itu bukan approval.
- Kepala Divisi tetap disaring oleh `ArchiveAccess` pada daftar dan diperiksa
  pada record sebelum tindakan. Arsip tanpa divisi tidak cocok dengan divisi
  pengguna dan ditolak; klasifikasi Dokumen Rahasia tetap ditolak.
- Portal, permintaan, lampiran dan history Customer tetap mengikat data ke
  `user_id`. Notification feed/list/read juga memfilter `user_id`. Pengguna
  lain tidak mendapat file submission privat lewat endpoint customer; reviewer
  hanya dapat mengakses file sesuai permission review yang sudah ada.
- Verifikator tetap memiliki fungsi legacy `qr.scan` dan `duplicates.review`.
  Tidak ada grant Admin/Pegawai yang ditambahkan.

### File yang diubah pada Tahap 5C

- `app/Http/Controllers/LoanRequestController.php`: menolak self-approve dan
  self-reject loan request.
- `app/Http/Controllers/RetentionController.php`: menolak self-completion
  disposisi; guard self-review existing dipertahankan.
- `app/Http/Controllers/CustomerReviewController.php`: menolak self-review
  submission dan access request.
- `app/Http/Controllers/UserController.php`: menolak penerbitan reset link
  melalui request milik operator sendiri.
- `tests/Feature/ArchiveManagementTest.php`: tambah regresi untuk keputusan
  loan sendiri, review submission/access request sendiri, penerbitan reset
  sendiri, dan self-completion retensi. Tes scope Kepala Divisi, portal/history,
  private file, notifikasi, verifikator dan alur valid yang ada juga dijalankan.
- `docs/role-permission-decision-addendum.md`: addendum audit ini.

### Validasi

Suite fokus yang dijalankan pada database test Laravel:

```text
php artisan test tests\Feature\ArchiveManagementTest.php
58 passed (457 assertions)
```

Suite lengkap yang dijalankan setelah suite fokus:

```text
php artisan test
60 passed (460 assertions)
```

Pemeriksaan problem file pada empat controller dan file test tidak menemukan
error. Tes scope Kepala Divisi yang sudah ada tetap lulus untuk list/detail
arsip serta list/aksi approval pinjaman lintas divisi. Tes Customer yang sudah
ada tetap lulus untuk portal publik, history pemilik, private submission file,
review valid, serta notification. Tes skema ini memakai database testing
Laravel (SQLite in-memory); tidak membuktikan kompatibilitas DDL MariaDB.
Tidak ada query tulis atau migration yang dijalankan pada database aktif.

### Keputusan/risiko yang masih tersisa

1. Aturan retensi Tahap 5C memilih pemisahan tugas ketat: pengusul tidak
   melaksanakan usulannya sendiri meskipun reviewer lain telah menyetujui.
   Pemilik bisnis perlu mengesahkan kebijakan operasional ini sebelum workflow
   retensi baru diberikan ke Admin/Pegawai.
2. Self-approval guard loan melindungi record lama/role berubah sekalipun
   pengajuan baru melalui UI hanya diperuntukkan bagi Customer/Pengguna Umum.
   Setelah Pegawai memperoleh `loans.request`, scope arsip yang boleh diminta
   serta kebijakan apakah Admin boleh mengajukan untuk dirinya masih perlu
   keputusan.
3. `CustomerReviewController` saat ini hanya memberi akses reviewer kepada
   Super Admin. Guard self-review sudah dipasang secara defensif, tetapi Admin
   belum diberi grant; sebelum grant masa depan, definisikan pemisahan
   reviewer/pemohon dan scope review Customer.
4. Kebijakan lintas divisi telah memiliki helper dan tes terarah, tetapi divisi
   adalah teks bebas. Daftar divisi kanonis/alias dan perilaku beda ejaan perlu
   persetujuan; jangan menyatakan semua variasi data divisi telah terverifikasi.
5. Duplicate review tidak memiliki pengaju manusia, tetapi route GET index
   membuat kandidat. Pemisahan discovery dari tindakan baca adalah perbaikan
   desain terpisah dan belum dilakukan pada Tahap 5C.
6. Transisi Customer menjadi Pegawai tetap belum disetujui/diterapkan. Customer
   dan Pengguna Umum masih terpisah; Verifikator tetap berfungsi. Tidak ada
   perubahan enum, migration, atau data.

Tahap 5C berhenti sebelum migration atau perubahan database produksi. Perubahan
permission Admin/Pegawai tetap menunggu keputusan bisnis dan QA terpisah.

## Addendum Tahap 5D — audit scope divisi, retensi, dan kandidat duplikasi

Tahap ini hanya audit dan dokumentasi. Tidak ada kode, schema, akun, atau
kandidat duplikasi yang diubah; tidak ada migration yang dijalankan. Pemeriksaan
data dilakukan dengan query SELECT/metadata saja pada koneksi `arsip_bakti`
(MariaDB 10.4.32). Tes aplikasi dijalankan terpisah dengan konfigurasi
PHPUnit `sqlite` dan database `:memory:`.

### A. Scope divisi berbasis teks

#### Kondisi schema dan data saat audit

| Tabel | Kolom | Definisi aktual | Nilai pada snapshot audit |
|---|---|---|---|
| `users` | `division` | `varchar(150) NULL` | 3 baris, seluruhnya `NULL` |
| `arsip` | `division` | `varchar(150) NULL` | 5 baris, seluruhnya `NULL` |

Pada saat pemeriksaan read-only tidak ditemukan nilai divisi non-NULL pada
database tersebut, sehingga tidak ada variasi kapitalisasi, whitespace,
alias, atau nama kanonis aktual yang dapat dihitung dari snapshot ini. Ini
bukan bukti bahwa semua environment atau data yang dimasukkan kemudian bebas
variasi. Tes fixture yang menggunakan nilai seperti `Keuangan` dan `keuangan`
adalah bukti perilaku tes, bukan inventaris nilai database produksi.

#### Titik pembentukan dan pembacaan nilai

- `ArchiveController::formData()` menyusun dropdown dari nilai `users.division`
  distinct yang tidak NULL dan tidak sama dengan string kosong. Nilainya tidak
  dipangkas atau dikanonisasi saat dropdown dibentuk; divisi user saat ini
  dibandingkan dengan daftar secara strict sebelum ditambahkan. Karena sumber
  daftar adalah akun, ini bukan katalog divisi kanonis.
- `ArchiveController::validatedArchiveData()` menerima string sampai 150
  karakter untuk arsip `Internal Divisi`. Untuk pengguna tanpa izin khusus,
  nilai input diganti dengan divisi akun; alur ini tidak mengubah teks menjadi
  ID divisi kanonis. Arsip klasifikasi lain disimpan dengan division `NULL`.
- `RolePermissions::matchesDivision()` membandingkan kedua nilai setelah
  `trim` dan lowercase Unicode. Helper query `scopeToDivision()` menggunakan
  `LOWER(TRIM(arsip.division))`, menolak user tanpa divisi, dan mengecualikan
  klasifikasi `Dokumen Rahasia`.
- `ArchiveAccess::canView()` memakai `matchesDivision()` untuk record-level
  check. Namun jalur scope untuk role legacy yang bukan Kepala Divisi
  membandingkan kolom divisi langsung dengan divisi user yang di-trim, bukan
  memakai `LOWER(TRIM())` pada kedua sisi. Dengan demikian kesetaraan antara
  scope daftar legacy dan pengecekan record tidak boleh diasumsikan untuk
  perbedaan leading whitespace atau kolasi; scope Kepala Divisi target memakai
  helper normalisasi tersebut.
- Nilai divisi juga dapat berasal dari alur impor arsip dan submission/review
  Customer. Belum ada katalog alias atau aturan normalisasi tunggal yang
  ditegakkan di semua jalur input. Nilai dari sumber-sumber ini perlu dicakup
  ketika audit implementasi transisi dilakukan.

#### Permukaan scope dan batas klaim

`ArchiveAccess` digunakan dalam daftar/detail arsip, dashboard, kedua sisi
pasangan verifikasi duplikasi, loan/approval, QR, dan alur daftar/record
retensi. Aksi terkait arsip melakukan pemeriksaan record-level sesuai controller
yang diaudit; scope Kepala Divisi mengembalikan query kosong bila divisinya
NULL/kosong dan tidak cocok dengan arsip tanpa divisi. Akses `Dokumen Rahasia`
juga dikecualikan dari scope Kepala Divisi.

Controller laporan dan ekspor terpisah dari helper scope arsip tersebut.
Karena itu, jika permission laporan atau ekspor kelak diberikan kepada role
division-scoped, hasilnya tidak boleh dianggap otomatis terbatas per divisi;
query, cetak, unduh, dan variasi format harus mendapat tes scope backend
tersendiri. Hal yang sama berlaku untuk lampiran dan endpoint akses langsung:
keamanan tidak boleh disimpulkan hanya dari daftar atau tombol UI. Tidak ada
grant baru yang diberikan pada Tahap 5D.

#### Risiko dan transisi yang direkomendasikan (belum diterapkan)

Perbedaan ejaan/alias dapat menyebabkan arsip tidak ditemukan, akses daftar
berbeda dari detail, atau pencarian/ekspor menghasilkan cakupan yang berbeda.
Arsip `Internal Divisi` dengan division kosong/orphan tidak semestinya
diberikan ke Kepala Divisi melalui fallback.

Sebelum merancang relasi divisi:

1. Jalankan inventaris read-only pada setiap environment untuk nilai mentah,
   nilai trim, nilai case-folded, jumlah per varian, NULL/kosong, serta nilai
   yang tidak punya pasangan user/divisi.
2. Minta pemilik bisnis menyetujui daftar kanonis dan tabel alias; jangan
   menyatukan nama yang hanya tampak mirip tanpa persetujuan.
3. Cocokkan kandidat mapping di staging/salinan terisolasi dan laporkan
   ketidakcocokan/orphan tanpa menulis ke data aktif.
4. Rancang backfill aditif yang dapat diverifikasi, kemudian pilih dual-read
   atau cutover eksplisit. Pertahankan teks legacy sampai hasil, jumlah baris,
   dan mapping dapat direkonsiliasi serta pemulihan diuji.
5. Tambahkan tes untuk scope daftar, detail, lampiran, pencarian, QR,
   persetujuan, laporan, ekspor, alias, whitespace, beda kapitalisasi, user
   tanpa divisi, dan arsip tanpa divisi sebelum perubahan akses diaktifkan.

Tidak ada migration relasi divisi yang dibuat atau dijalankan pada tahap ini.

### B. Retensi — alur dan keputusan yang masih perlu disahkan

| Tahap | Perilaku implementasi sekarang | Pemeriksaan keamanan |
|---|---|---|
| Pengusulan (`POST /retensi/usulan`) | Permission `RETENTION_PROPOSE`; arsip harus aktif, punya jadwal dan jatuh tempo, bukan sedang dipinjam, serta dapat dilihat pengusul. | Kepala Divisi dibatasi oleh akses arsip; pilihan arsip tidak memberi jalan untuk mengusulkan arsip di luar scope. |
| Peninjauan (`PATCH .../review`) | Permission `RETENTION_REVIEW`; usulan dapat diputuskan sesuai validasi status. | Pengusul tidak boleh menyetujui atau menolak usulannya sendiri; reviewer harus dapat melihat arsip. |
| Pelaksanaan (`PATCH .../selesai`) | Hanya usulan yang telah disetujui yang dapat ditandai selesai; arsip dikunci saat transaksi. | Pengusul tidak boleh melaksanakan usulannya sendiri; pelaksana harus dapat melihat arsip. |
| Jadwal dan berita acara | Jadwal memerlukan permission jadwal; berita acara melakukan pemeriksaan akses ke arsip terkait. | Data arsip tetap harus berada dalam scope peminta. |

Role legacy saat ini memiliki pembagian statis: Petugas Gudang dan Admin
Unit/Petugas Arsip dapat mengusulkan; Pimpinan/Approver dapat meninjau;
Kepala Divisi dapat meninjau dengan scope division; Super Admin memiliki
akses global sesuai helper permission. Grant role Admin/Pegawai masih kosong.
Ini adalah deskripsi implementasi, bukan persetujuan untuk memindahkan grant.

Sebelum grant baru dipertimbangkan, pemilik bisnis perlu menetapkan:

- role mana yang boleh mengusulkan, menyetujui/menolak, dan melaksanakan;
- apakah reviewer Kepala Divisi boleh menyetujui hanya arsip divisinya dan
  bagaimana usulan lintas divisi/arsip tanpa divisi ditangani;
- apakah pemisahan tugas pengusul–reviewer–pelaksana harus selalu melibatkan
  dua orang atau tiga orang berbeda;
- siapa yang mengelola jadwal retensi, pengecualian, serta perubahan keputusan;
- penerima, waktu, dan isi notifikasi untuk pengusulan, keputusan, dan
  pelaksanaan;
- audit trail serta bukti yang diwajibkan untuk tiap tindakan.

Aturan self-review dan self-completion yang ada dipertahankan. Workflow dan
grant retensi tidak diubah di Tahap 5D.

### C. Verifikasi duplikasi — efek samping GET

GET `/verifikasi-duplikasi` memanggil `discoverCandidates($user)` sebelum
membaca daftar. Discovery memasangkan arsip aktif dengan `a.id < b.id`,
mencari nomor berkas yang dinormalisasi atau nama+kategori+tahun yang sama,
menerapkan scope akses pada kedua arsip, lalu menjalankan
`insertOrIgnore` ke `duplicate_reviews`. Jadi membuka atau memuat ulang
halaman bukan operasi baca murni: pasangan kandidat baru dapat ditulis ke
database oleh permintaan GET. Audit Tahap 5D tidak memanggil route ini dan
tidak membuat atau mengubah kandidat.

Schema memiliki unique key `uq_duplicate_pair` untuk
`(archive_id, candidate_id)`. Untuk pasangan yang sama, batas unik dan
`insertOrIgnore` mencegah insert row kembar pada pengulangan normal. Itu tidak
menjamin metadata selalu mutakhir: insert yang diabaikan tidak memperbarui
`match_reason`, status, atau timestamp; kandidat yang sudah ada dapat tetap
stale ketika data arsip berubah atau pasangan tidak lagi cocok. Kandidat juga
tidak otomatis dihapus saat kondisi sumber hilang.

Discovery membatasi hasil sampai 500 pasangan tanpa urutan eksplisit. Jika
terdapat lebih banyak pasangan yang cocok, subset yang diproses tidak
ditentukan secara stabil dan pasangan lain dapat tertunda/tidak terjangkau
oleh pemuatan tersebut. Daftar yang ditampilkan juga dibatasi 300 record,
sehingga tidak menjamin seluruh kandidat tersaji sekaligus. Perilaku ini
berisiko pada data besar dan menyulitkan pemantauan kemajuan.

Rekomendasi untuk pekerjaan implementasi berikutnya, bukan perubahan tahap ini:

1. Pisahkan index GET yang read-only dari aksi discovery yang eksplisit.
2. Jalankan discovery melalui endpoint berotorisasi atau job terjadwal dengan
   batch/pagination deterministik, progress yang dapat dipantau, dan lock atau
   strategi concurrency yang tepat.
3. Definisikan kebijakan refresh `match_reason`, penanganan kandidat stale,
   pasangan yang sudah diputuskan, serta audit log sebelum memperbarui atau
   menutup kandidat lama.
4. Pertahankan unique pair sebagai pertahanan idempotensi, tetapi uji juga
   pemanggilan bersamaan, retry, batas batch, dan kegagalan sebagian.

Tidak ada grant verifier yang diubah dan fungsi legacy Verifikator tetap
dipertahankan.

### D. Rencana tes regresi

Tes berikut perlu ditambahkan/diperluas sebelum implementasi scope-relasi,
perubahan workflow, atau pemisahan discovery:

| Area | Kasus minimum | Hasil yang diharapkan |
|---|---|---|
| Normalisasi divisi | nilai sama, beda kapitalisasi, leading/trailing spaces, alias yang disetujui, nilai NULL/kosong | perilaku eksplisit dan seragam untuk daftar serta detail |
| Akses divisi | Kepala Divisi pada divisinya, divisi lain, arsip tanpa divisi, user tanpa divisi, `Dokumen Rahasia` | hanya arsip yang sesuai scope dapat dibaca/diubah/disetujui |
| Permukaan data | daftar, detail, lampiran, pencarian, QR, persetujuan, retensi, laporan, cetak, ekspor | tidak ada kebocoran lintas divisi pada URL langsung atau file unduhan |
| Retensi | pengusul meninjau/menjalankan sendiri; reviewer lintas divisi; arsip tanpa divisi; status belum disetujui; alur valid antar pengguna berbeda | kasus terlarang ditolak tanpa mutasi; alur yang disahkan berhasil dan tercatat |
| Discovery duplikasi | index GET tanpa tulis setelah refactor; discovery eksplisit membuat kandidat; retry/panggilan paralel; metadata berubah; >500 pasangan | GET tetap baca murni; discovery idempotent, deterministik, dapat dilanjutkan, dan tidak mengganti keputusan yang sudah ada tanpa kebijakan |

### E. Validasi dan status tahap

Tes terarah yang dijalankan:

```text
php artisan test tests\Feature\ArchiveManagementTest.php --filter="head_of_division|internal_division_archive|retention_schedule_reminders|duplicate_candidates"
4 passed (58 assertions)
```

Tes menggunakan SQLite in-memory sesuai `phpunit.xml`; hasilnya memvalidasi
perilaku pada fixture tes saja, bukan kolasi, DDL, atau konkurensi MariaDB.
Metadata MariaDB dibaca tanpa perubahan: kedua kolom division nullable
`varchar(150)`, semua nilai pada snapshot yang diaudit NULL, dan unique key
pasangan duplikasi tersedia. Tidak ada migration, perubahan database, atau
perubahan kandidat.

File yang diperbarui pada Tahap 5D:

- `docs/role-permission-decision-addendum.md` — menambahkan temuan scope
  divisi, alur retensi, efek samping duplicate discovery, risiko, rencana
  transisi, dan rencana tes.

Belum dilakukan: normalisasi atau relasi divisi, perubahan retensi, refactor
route GET duplikasi, refresh/pembersihan kandidat, grant permission baru,
migration, dan penerapan database produksi. Tahap 5D berhenti pada audit dan
dokumentasi; keputusan bisnis dan persetujuan terpisah tetap diperlukan.

Audit implementasi mendalam berikutnya dicatat terpisah dalam
[`role-permission-stage-5e-audit.md`](./role-permission-stage-5e-audit.md).

Perbaikan dan validasi scope laporan, ekspor arsip, serta riwayat Tahap 5F
dicatat dalam
[`role-permission-stage-5f-scope-validation.md`](./role-permission-stage-5f-scope-validation.md).

Pemisahan GET daftar duplikasi dari POST discovery, desain batch dan hasil tes
Tahap 5G dicatat dalam
[`role-permission-stage-5g-duplicate-discovery.md`](./role-permission-stage-5g-duplicate-discovery.md).

Audit kewenangan retensi Tahap 5H, termasuk bukti self-review/
self-execution, fakta bahwa reviewer dapat menjadi pelaksana, matriks role,
dan keputusan workflow yang belum disetujui, dicatat dalam
[`role-permission-stage-5h-retention-policy.md`](./role-permission-stage-5h-retention-policy.md).
Belum ada perubahan kode, grant, akun, konfigurasi, atau database. Keputusan
tentang pemisahan reviewer/pelaksana, approval terpisah, pembatalan, expiry,
pengajuan ulang, audit trail, dan notifikasi tetap tertunda.

Pemeriksaan prasyarat dan tinjauan kode Tahap 5I dicatat dalam
[`role-permission-stage-5i-mariadb-validation.md`](./role-permission-stage-5i-mariadb-validation.md).
Validasi MariaDB belum dijalankan karena konfigurasi aktif menunjuk ke
database produksi `arsip_bakti` dan database testing MariaDB terpisah belum
tersedia/terkonfirmasi. Tidak ada tes, query, migration, atau operasi discovery
yang dijalankan terhadap database tersebut. Tahap 6 belum siap sebelum
lingkungan MariaDB test terisolasi disediakan dan skenario validasi lulus.

Audit dan rancangan lingkungan MariaDB test Tahap 5J dicatat dalam
[`role-permission-stage-5j-test-environment.md`](./role-permission-stage-5j-test-environment.md).
Nama `arsip_bakti_test` hanya usulan dan belum dibuat/diverifikasi. Belum ada
konfigurasi atau script test yang ditambahkan; persetujuan DBA atas instance,
akun minimum, schema sintetis, guard fail-closed dan secret environment
diperlukan sebelum lingkungan dibuat atau koneksi pengujian dibuka.

Checklist dan spesifikasi teknis untuk tinjauan DBA Tahap 5K dicatat dalam
[`role-permission-stage-5k-dba-readiness.md`](./role-permission-stage-5k-dba-readiness.md).
Dokumen tersebut merinci rancangan konfigurasi PHPUnit/guard, risiko
`DB_URL`/environment/cache/Composer, schema dan fixture sintetis, serta cara
DBA membuktikan akun test tidak memiliki grant ke `arsip_bakti`. Tidak ada
database, akun, DDL, konfigurasi atau test yang dibuat/dijalankan; seluruh
persetujuan DBA masih diperlukan.

Pembaruan audit statis menambahkan urutan efek hook `post-autoload-dump` dan
bootstrap `package:discover`, serta mencatat bahwa seluruh 67 test pada
`ArchiveManagementTest` saat ini memanggil setup yang membuat 18 tabel.
Suite tersebut belum kompatibel langsung dengan schema MariaDB persisten
tanpa DDL; desain pemisahan test, fixture DML-only, dan isolasi/reset masih
memerlukan persetujuan dan implementasi. Tidak ada Composer, Artisan, tes,
koneksi, atau operasi database yang dijalankan untuk pembaruan ini.

Desain harness SQLite dan MariaDB terpisah Tahap 5L dicatat dalam
[`role-permission-stage-5l-test-harness-design.md`](./role-permission-stage-5l-test-harness-design.md).
Dokumen menginventarisasi 67 test `ArchiveManagementTest`, menetapkan bahwa
hanya test form login yang badan test-nya tidak membutuhkan database (namun
tetap terkena DDL dari `setUp()` saat ini), serta merancang lifecycle fixture
DML-only, guard fail-closed, dan matriks validasi lintas driver. Pada akhir
penyusunan desain itu, guard dan suite MariaDB belum dibuat; status implementasi
terkini dicatat pada laporan konsolidasi di bawah.

Pembaruan konsolidasi Tahap 5 dan kesiapan Tahap 6 tersedia di
[`role-permission-stage-5-completion-and-stage-6-readiness.md`](./role-permission-stage-5-completion-and-stage-6-readiness.md).
Preflight dan profile PHPUnit MariaDB terpisah kini diimplementasikan; suite
SQLite lulus 76 test/532 assertions, termasuk 7 test unit preflight/13
assertions. MariaDB profile berhenti sebelum bootstrap aplikasi bila
attestation/target test tidak tersedia. Suite MariaDB penuh dan migrasi role
tetap belum dijalankan; Tahap 6 belum siap sampai prasyarat DBA dan keputusan
bisnis kritis diselesaikan.
