CREATE DATABASE IF NOT EXISTS `daruratcheck` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `daruratcheck`;

-- --------------------------------------------------------
-- Table: admins
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: users (Requesters)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `whatsapp` VARCHAR(20) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `location` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: requests
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_code` VARCHAR(20) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `need_type` ENUM('Makanan', 'Sembako', 'Susu atau kebutuhan bayi', 'Kebutuhan lansia', 'Kebutuhan kesehatan', 'Kebutuhan pendidikan', 'Lainnya') NOT NULL,
  `description` TEXT NOT NULL,
  `people_count` INT NOT NULL DEFAULT 1,
  `supporting_document` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('BARU', 'SEDANG DIVERIFIKASI', 'TERVERIFIKASI', 'DISETUJUI', 'BANTUAN DISALURKAN', 'SELESAI', 'DITOLAK') NOT NULL DEFAULT 'BARU',
  `risk_score` INT NOT NULL DEFAULT 0,
  
  -- Safety Indicators (Each reduces risk by 20 points, default: 0 except where calculated)
  `safe_new_wa` TINYINT(1) NOT NULL DEFAULT 0,
  `safe_consistent_location` TINYINT(1) NOT NULL DEFAULT 0,
  `safe_comm_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `safe_valid_evidence` TINYINT(1) NOT NULL DEFAULT 0,
  `safe_no_suspicious_hist` TINYINT(1) NOT NULL DEFAULT 0,
  
  -- Risk Indicators (Each increases risk by 20 points, default: 0)
  `risk_multi_request` TINYINT(1) NOT NULL DEFAULT 0,
  `risk_data_changes` TINYINT(1) NOT NULL DEFAULT 0,
  `risk_inconsistent_location` TINYINT(1) NOT NULL DEFAULT 0,
  `risk_refuse_verification` TINYINT(1) NOT NULL DEFAULT 0,
  `risk_repeated_pattern` TINYINT(1) NOT NULL DEFAULT 0,
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: verifications
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `verifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_id` INT NOT NULL,
  `admin_id` INT NOT NULL,
  `method` ENUM('Telepon', 'WhatsApp', 'Video call', 'Pengecekan dokumen', 'Pengecekan lapangan', 'Informasi dari relawan', 'Lainnya') NOT NULL,
  `result` ENUM('Valid', 'Perlu pemeriksaan lanjutan', 'Tidak valid') NOT NULL,
  `notes` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: assistance
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `assistance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_id` INT NOT NULL,
  `admin_id` INT NOT NULL,
  `aid_type` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `value` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `distribution_date` DATE NOT NULL,
  `distribution_method` ENUM('Barang', 'Pembayaran langsung ke toko', 'Pengantaran', 'Lainnya') NOT NULL,
  `volunteer_name` VARCHAR(100) NOT NULL,
  `distribution_proof` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: official_contacts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `official_contacts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NOT NULL,
  `community_name` VARCHAR(100) NOT NULL,
  `admin_name` VARCHAR(100) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `whatsapp` VARCHAR(20) NOT NULL UNIQUE,
  `bank_account` VARCHAR(100) DEFAULT NULL,
  `instagram` VARCHAR(100) DEFAULT NULL,
  `tiktok` VARCHAR(100) DEFAULT NULL,
  `website` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('Aktif', 'Tidak aktif', 'Perlu diperbarui') NOT NULL DEFAULT 'Aktif',
  `verification_date` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: audit_logs
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT DEFAULT NULL,
  `activity` VARCHAR(255) NOT NULL,
  `data_id` VARCHAR(50) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Seed Data
-- --------------------------------------------------------

-- Seeding Default Admin: username 'admin', password 'Admin123!'
INSERT INTO `admins` (`id`, `username`, `password`, `name`) VALUES
(1, 'admin', '$2y$10$qJ36s5ip/97ap0zl//a0lOHxu5r/y0/Nfeijx/k61zEbXI4nzSKSK', 'Admin Nasi Darurat');

-- Seeding Dummy Requesters (users)
INSERT INTO `users` (`id`, `whatsapp`, `name`, `location`) VALUES
(1, '081234567890', 'Andi Santoso', 'Tangerang'),
(2, '089876543210', 'Siti Rahma', 'Jakarta Selatan'),
(3, '085512345678', 'Budi Hartono', 'Bekasi'),
(4, '081122334455', 'Lani Wijaya', 'Depok');

-- Seeding Dummy Requests
-- Andi Santoso (Sembako, Baru) - Risk Score 60/100 (Unverified, new WA, location inconsistent, etc.)
INSERT INTO `requests` (`id`, `request_code`, `user_id`, `need_type`, `description`, `people_count`, `supporting_document`, `status`, `risk_score`, `safe_new_wa`, `safe_consistent_location`, `safe_comm_verified`, `safe_valid_evidence`, `safe_no_suspicious_hist`, `risk_multi_request`, `risk_data_changes`, `risk_inconsistent_location`, `risk_refuse_verification`, `risk_repeated_pattern`, `created_at`) VALUES
(1, 'ND-20260815-0043', 1, 'Sembako', 'Membutuhkan sembako untuk kebutuhan keluarga selama beberapa hari.', 4, NULL, 'BARU', 60, 1, 0, 0, 0, 1, 0, 0, 1, 0, 0, '2026-08-15 18:20:00');

-- Siti Rahma (Makanan, Selesai) - Risk Score 0/100 (Fully verified, safe)
INSERT INTO `requests` (`id`, `request_code`, `user_id`, `need_type`, `description`, `people_count`, `supporting_document`, `status`, `risk_score`, `safe_new_wa`, `safe_consistent_location`, `safe_comm_verified`, `safe_valid_evidence`, `safe_no_suspicious_hist`, `risk_multi_request`, `risk_data_changes`, `risk_inconsistent_location`, `risk_refuse_verification`, `risk_repeated_pattern`, `created_at`) VALUES
(2, 'ND-20260816-0001', 2, 'Makanan', 'Keluarga isolasi mandiri butuh makanan siap saji harian.', 3, NULL, 'SELESAI', 0, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-08-16 08:30:00');

-- Budi Hartono (Susu atau kebutuhan bayi, Sedang Diverifikasi) - Risk Score 40/100 (In progress)
INSERT INTO `requests` (`id`, `request_code`, `user_id`, `need_type`, `description`, `people_count`, `supporting_document`, `status`, `risk_score`, `safe_new_wa`, `safe_consistent_location`, `safe_comm_verified`, `safe_valid_evidence`, `safe_no_suspicious_hist`, `risk_multi_request`, `risk_data_changes`, `risk_inconsistent_location`, `risk_refuse_verification`, `risk_repeated_pattern`, `created_at`) VALUES
(3, 'ND-20260816-0002', 3, 'Susu atau kebutuhan bayi', 'Butuh susu formula SGM Eksplor 1+ untuk anak usia 1.5 tahun.', 1, NULL, 'SEDANG DIVERIFIKASI', 40, 1, 1, 0, 0, 1, 0, 0, 0, 0, 0, '2026-08-16 09:15:00');

-- Lani Wijaya (Kebutuhan lansia, Ditolak) - Risk Score 80/100 (High Risk, Refused verification, location inconsistent)
INSERT INTO `requests` (`id`, `request_code`, `user_id`, `need_type`, `description`, `people_count`, `supporting_document`, `status`, `risk_score`, `safe_new_wa`, `safe_consistent_location`, `safe_comm_verified`, `safe_valid_evidence`, `safe_no_suspicious_hist`, `risk_multi_request`, `risk_data_changes`, `risk_inconsistent_location`, `risk_refuse_verification`, `risk_repeated_pattern`, `created_at`) VALUES
(4, 'ND-20260816-0003', 4, 'Kebutuhan lansia', 'Membutuhkan popok dewasa ukuran L untuk kakek yang sakit.', 1, NULL, 'DITOLAK', 80, 0, 0, 0, 0, 1, 0, 0, 1, 1, 1, '2026-08-16 10:00:00');

-- Seeding Dummy Verifications
INSERT INTO `verifications` (`id`, `request_id`, `admin_id`, `method`, `result`, `notes`, `created_at`) VALUES
(1, 2, 1, 'Video call', 'Valid', 'Pemohon menunjukkan kondisi rumah dan anak-anaknya. Berkas KTP sesuai.', '2026-08-16 09:00:00'),
(2, 3, 1, 'WhatsApp', 'Perlu pemeriksaan lanjutan', 'Foto KK sudah dikirim, tapi lokasi koordinat GPS belum dikirim.', '2026-08-16 10:15:00'),
(3, 4, 1, 'Telepon', 'Tidak valid', 'Pemohon menolak video call dan memberikan lokasi palsu saat dicocokkan dengan nomor WA.', '2026-08-16 11:00:00');

-- Seeding Dummy Assistance (Completed for Siti Rahma)
INSERT INTO `assistance` (`id`, `request_id`, `admin_id`, `aid_type`, `description`, `value`, `distribution_date`, `distribution_method`, `volunteer_name`, `distribution_proof`, `created_at`) VALUES
(1, 2, 1, 'Makanan Siap Saji', 'Nasi kotak 3 porsi untuk makan siang dan malam selama 3 hari.', 150000.00, '2026-08-16', 'Pengantaran', 'Rian Relawan', NULL, '2026-08-16 12:00:00');

-- Seeding Dummy Official Contacts
INSERT INTO `official_contacts` (`id`, `admin_id`, `community_name`, `admin_name`, `city`, `whatsapp`, `bank_account`, `instagram`, `tiktok`, `website`, `status`, `verification_date`, `created_at`) VALUES
(1, 1, 'Nasi Darurat Tangerang', 'Rian Relawan', 'Tangerang', '081234567899', 'BCA 123456789 a/n Nasi Darurat', '@nasidarurat_tng', '@nasidarurat_tng', 'https://nasidarurat-tng.org', 'Aktif', '2026-08-01', '2026-08-01 10:00:00'),
(2, 1, 'Nasi Darurat Jakarta', 'Siska Relawan', 'Jakarta Selatan', '089876543211', 'Mandiri 987654321 a/n Nasi Darurat Jkt', '@nasidarurat_jkt', NULL, NULL, 'Aktif', '2026-08-05', '2026-08-05 11:00:00');

-- Seeding Dummy Audit Logs
INSERT INTO `audit_logs` (`id`, `admin_id`, `activity`, `data_id`, `ip_address`, `created_at`) VALUES
(1, 1, 'Admin Login', 'admin', '127.0.0.1', '2026-08-16 08:00:00'),
(2, 1, 'Memulai Verifikasi Permintaan', 'ND-20260816-0001', '127.0.0.1', '2026-08-16 08:35:00'),
(3, 1, 'Menyetujui Permintaan Bantuan', 'ND-20260816-0001', '127.0.0.1', '2026-08-16 09:00:00'),
(4, 1, 'Mencatat Penyaluran Bantuan', 'ND-20260816-0001', '127.0.0.1', '2026-08-16 12:00:00'),
(5, 1, 'Menolak Permintaan Bantuan', 'ND-20260816-0003', '127.0.0.1', '2026-08-16 11:00:00');
