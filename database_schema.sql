-- =====================================================================
-- SISTEM RENTAL PS GAME - DATABASE SCHEMA
-- Database: MariaDB / MySQL
-- Jalankan file ini terlebih dahulu sebelum menjalankan aplikasi
-- =====================================================================

CREATE DATABASE IF NOT EXISTS db_rental_ps CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_rental_ps;

-- Tabel Pengguna (Admin/Operator Rental)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabel Unit PS (Data Utama Aset Konsol)
CREATE TABLE IF NOT EXISTS unit_ps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_unit VARCHAR(10) NOT NULL UNIQUE,
    nama_unit VARCHAR(50) NOT NULL,
    tipe_konsol ENUM('PS3', 'PS4', 'PS5') NOT NULL DEFAULT 'PS4',
    harga_per_jam DECIMAL(10,2) NOT NULL,
    status ENUM('Tersedia', 'Disewa', 'Maintenance') NOT NULL DEFAULT 'Tersedia',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabel Rental (Core System - Transaksi Sewa per Unit)
CREATE TABLE IF NOT EXISTS rental (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    nama_penyewa VARCHAR(100) NOT NULL,
    no_hp VARCHAR(20) NULL,
    waktu_mulai DATETIME NOT NULL,
    waktu_selesai DATETIME NULL,
    total_jam DECIMAL(6,2) NULL,
    total_biaya DECIMAL(12,2) NULL,
    status ENUM('Berlangsung', 'Selesai') NOT NULL DEFAULT 'Berlangsung',
    catatan VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_unit FOREIGN KEY (unit_id) REFERENCES unit_ps(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Akun admin default (username: admin, password: admin123)
-- PENTING: hash di bawah HARUS di-generate ulang di komputer Anda sendiri karena
-- hash password_hash() bersifat unik per-server/per-generate. Jalankan:
--   php -r "echo password_hash('admin123', PASSWORD_DEFAULT) . PHP_EOL;"
-- lalu ganti nilai di bawah ini dengan hasilnya sebelum import, ATAU
-- import dulu lalu jalankan file reset_password.php yang disertakan.
INSERT INTO users (username, password, nama_lengkap) VALUES
('admin', '$2y$10$PLACEHOLDER_JALANKAN_reset_password.php_UNTUK_SET_PASSWORD', 'Administrator Rental');

-- Contoh data unit PS
INSERT INTO unit_ps (kode_unit, nama_unit, tipe_konsol, harga_per_jam, status) VALUES
('PS-01', 'Bilik 1', 'PS4', 5000, 'Tersedia'),
('PS-02', 'Bilik 2', 'PS4', 5000, 'Tersedia'),
('PS-03', 'Bilik 3', 'PS5', 8000, 'Tersedia'),
('PS-04', 'Bilik VIP', 'PS5', 10000, 'Tersedia');
