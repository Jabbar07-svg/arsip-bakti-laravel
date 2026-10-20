CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(150) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `username` varchar(80) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Super Admin','Petugas Gudang','Admin Unit/Petugas Arsip','Verifikator','Pimpinan/Approver','Customer','Pengguna Umum') NOT NULL DEFAULT 'Customer',
  `division` varchar(150) DEFAULT NULL,
  `status` enum('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kategori_arsip` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nama_kategori` varchar(150) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `status` enum('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kategori_nama` (`nama_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lokasi` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gudang` varchar(100) NOT NULL,
  `rak` varchar(100) NOT NULL,
  `lemari` varchar(100) NOT NULL,
  `box` varchar(100) NOT NULL,
  `capacity_boxes` int(10) unsigned DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `status` enum('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lokasi` (`gudang`,`rak`,`lemari`,`box`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `arsip` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nomor_berkas` varchar(150) NOT NULL,
  `nama_berkas` varchar(255) NOT NULL,
  `kode_arsip` varchar(150) NOT NULL,
  `kategori_id` int(10) unsigned NOT NULL,
  `tahun` year(4) NOT NULL,
  `bulan` tinyint(3) unsigned NOT NULL,
  `tanggal_masuk` date NOT NULL,
  `lokasi_id` int(10) unsigned NOT NULL,
  `classification` varchar(32) NOT NULL DEFAULT 'Internal',
  `division` varchar(150) DEFAULT NULL,
  `physical_condition` varchar(40) NOT NULL DEFAULT 'Baik',
  `status` enum('Tersimpan','Dipinjam','Diproses','Hilang','Nonaktif') NOT NULL DEFAULT 'Tersimpan',
  `qr_code` varchar(60) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_arsip_qr` (`qr_code`),
  KEY `idx_nomor_berkas` (`nomor_berkas`),
  KEY `idx_kode_arsip` (`kode_arsip`),
  KEY `idx_arsip_tahun_bulan` (`tahun`,`bulan`),
  KEY `idx_arsip_status` (`status`),
  KEY `fk_arsip_kategori` (`kategori_id`),
  KEY `fk_arsip_lokasi` (`lokasi_id`),
  KEY `fk_arsip_user` (`created_by`),
  CONSTRAINT `fk_arsip_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_arsip` (`id`),
  CONSTRAINT `fk_arsip_lokasi` FOREIGN KEY (`lokasi_id`) REFERENCES `lokasi` (`id`),
  CONSTRAINT `fk_arsip_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `peminjaman` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `arsip_id` int(10) unsigned NOT NULL,
  `nama_peminjam` varchar(150) NOT NULL,
  `unit` varchar(150) DEFAULT NULL,
  `tanggal_pinjam` date NOT NULL,
  `tanggal_rencana_kembali` date DEFAULT NULL,
  `tanggal_dikembalikan` date DEFAULT NULL,
  `condition_return` varchar(40) DEFAULT NULL,
  `keperluan` text DEFAULT NULL,
  `status` enum('Dipinjam','Dikembalikan') NOT NULL DEFAULT 'Dipinjam',
  `created_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_peminjaman_arsip` (`arsip_id`),
  KEY `idx_peminjaman_status` (`status`),
  KEY `fk_peminjaman_user` (`created_by`),
  CONSTRAINT `fk_peminjaman_arsip` FOREIGN KEY (`arsip_id`) REFERENCES `arsip` (`id`),
  CONSTRAINT `fk_peminjaman_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `loan_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `arsip_id` int(10) unsigned NOT NULL,
  `purpose` text NOT NULL,
  `requested_due_date` date DEFAULT NULL,
  `status` enum('Menunggu Persetujuan','Disetujui','Ditolak','Dibatalkan') NOT NULL DEFAULT 'Menunggu Persetujuan',
  `reviewer_note` text DEFAULT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `loan_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_loan_request_status` (`status`,`created_at`),
  KEY `idx_loan_request_user` (`user_id`),
  KEY `fk_loan_request_archive` (`arsip_id`),
  KEY `fk_loan_request_reviewer` (`reviewed_by`),
  KEY `fk_loan_request_loan` (`loan_id`),
  CONSTRAINT `fk_loan_request_archive` FOREIGN KEY (`arsip_id`) REFERENCES `arsip` (`id`),
  CONSTRAINT `fk_loan_request_loan` FOREIGN KEY (`loan_id`) REFERENCES `peminjaman` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_loan_request_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_loan_request_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `arsip_files` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `arsip_id` int(10) unsigned NOT NULL,
  `nama_file` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `tipe_file` varchar(100) NOT NULL,
  `uploaded_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_files_arsip` (`arsip_id`),
  KEY `fk_files_user` (`uploaded_by`),
  CONSTRAINT `fk_files_arsip` FOREIGN KEY (`arsip_id`) REFERENCES `arsip` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_files_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_arsip_access` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `arsip_id` int(10) unsigned NOT NULL,
  `granted_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_archive_access` (`user_id`,`arsip_id`),
  KEY `idx_customer_access_user` (`user_id`),
  KEY `fk_customer_access_archive` (`arsip_id`),
  KEY `fk_customer_access_granted_by` (`granted_by`),
  CONSTRAINT `fk_customer_access_archive` FOREIGN KEY (`arsip_id`) REFERENCES `arsip` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_customer_access_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_customer_access_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `requested_code` varchar(180) NOT NULL,
  `purpose` text NOT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'Menunggu Verifikasi',
  `reviewer_note` text DEFAULT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer_requests_user` (`user_id`),
  KEY `idx_customer_requests_status` (`status`),
  KEY `fk_customer_request_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_customer_request_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_customer_request_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_submissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `arsip_id` int(10) unsigned DEFAULT NULL,
  `nomor_dokumen` varchar(180) NOT NULL,
  `nama_dokumen` varchar(255) NOT NULL,
  `kategori_id` int(10) unsigned DEFAULT NULL,
  `tanggal_dokumen` date NOT NULL,
  `keterangan` text DEFAULT NULL,
  `nama_file` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(10) unsigned NOT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'Menunggu Verifikasi',
  `reviewer_note` text DEFAULT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer_submissions_user` (`user_id`),
  KEY `idx_customer_submissions_status` (`status`),
  KEY `fk_customer_submission_category` (`kategori_id`),
  KEY `fk_customer_submission_reviewer` (`reviewed_by`),
  KEY `fk_customer_submission_archive` (`arsip_id`),
  CONSTRAINT `fk_customer_submission_archive` FOREIGN KEY (`arsip_id`) REFERENCES `arsip` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_customer_submission_category` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_arsip` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_customer_submission_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_customer_submission_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `disposition_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `arsip_id` int(10) unsigned NOT NULL,
  `action` enum('Musnahkan','Permanen','Serah ke ANRI') NOT NULL,
  `reason` text NOT NULL,
  `status` enum('Menunggu Persetujuan','Disetujui','Ditolak','Dilaksanakan') NOT NULL DEFAULT 'Menunggu Persetujuan',
  `requested_by` int(10) unsigned NOT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewer_note` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `minutes_number` varchar(100) DEFAULT NULL,
  `minutes_note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_disposition_status` (`status`,`created_at`),
  KEY `idx_disposition_archive` (`arsip_id`),
  KEY `fk_disposition_requester` (`requested_by`),
  KEY `fk_disposition_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_disposition_archive` FOREIGN KEY (`arsip_id`) REFERENCES `arsip` (`id`),
  CONSTRAINT `fk_disposition_requester` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_disposition_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `duplicate_reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `archive_id` int(10) unsigned NOT NULL,
  `candidate_id` int(10) unsigned NOT NULL,
  `match_reason` varchar(100) NOT NULL,
  `status` enum('Menunggu Verifikasi','Duplikat','Bukan Duplikat') NOT NULL DEFAULT 'Menunggu Verifikasi',
  `reviewer_note` text DEFAULT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_duplicate_pair` (`archive_id`,`candidate_id`),
  KEY `idx_duplicate_status` (`status`,`created_at`),
  KEY `fk_duplicate_candidate` (`candidate_id`),
  KEY `fk_duplicate_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_duplicate_archive` FOREIGN KEY (`archive_id`) REFERENCES `arsip` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_duplicate_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `arsip` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_duplicate_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jadwal_retensi` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kategori_id` int(10) unsigned NOT NULL,
  `active_months` smallint(5) unsigned NOT NULL,
  `inactive_months` smallint(5) unsigned NOT NULL,
  `final_action` enum('Musnahkan','Permanen','Serah ke ANRI') NOT NULL,
  `alert_days` smallint(5) unsigned NOT NULL DEFAULT 90,
  `updated_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_retention_category` (`kategori_id`),
  KEY `fk_retention_user` (`updated_by`),
  CONSTRAINT `fk_retention_category` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_arsip` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_retention_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'info',
  `url` varchar(255) NOT NULL DEFAULT './',
  `data` text DEFAULT NULL,
  `is_sent` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`,`created_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'Menunggu Verifikasi',
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `token_hash` char(64) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_password_reset_user` (`user_id`),
  KEY `idx_password_reset_status` (`status`),
  KEY `fk_password_reset_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_password_reset_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qr_batches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tahun` year(4) NOT NULL,
  `bulan` tinyint(3) unsigned NOT NULL,
  `jumlah_qr` int(10) unsigned NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qr_batch` (`tahun`,`bulan`),
  KEY `fk_qr_batch_user` (`created_by`),
  CONSTRAINT `fk_qr_batch_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_remember_token_hash` (`token_hash`),
  KEY `idx_remember_tokens_user` (`user_id`),
  CONSTRAINT `fk_remember_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `retention_reminders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `arsip_id` int(10) unsigned NOT NULL,
  `reminder_type` enum('Mendekati Retensi','Retensi Berakhir') NOT NULL,
  `due_date` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_retention_reminder` (`arsip_id`,`reminder_type`,`due_date`),
  CONSTRAINT `fk_retention_reminder_archive` FOREIGN KEY (`arsip_id`) REFERENCES `arsip` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `riwayat_arsip` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `arsip_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `aktivitas` varchar(150) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_riwayat_arsip` (`arsip_id`),
  KEY `idx_riwayat_user` (`user_id`),
  KEY `idx_riwayat_tanggal` (`created_at`),
  CONSTRAINT `fk_riwayat_arsip` FOREIGN KEY (`arsip_id`) REFERENCES `arsip` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_riwayat_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
