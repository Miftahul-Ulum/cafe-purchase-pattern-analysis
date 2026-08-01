-- ============================================================
-- Skema Database Sistem Analisis Pola Minat Beli - Story Cafe
-- Database: analisis_apriori
-- Catatan: tabel berikut otomatis dibuat oleh analisis_full.php
--          saat aplikasi pertama kali diakses.
-- ============================================================

CREATE DATABASE IF NOT EXISTS analisis_apriori
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE analisis_apriori;

-- Daftar menu (otomatis terisi saat import data)
CREATE TABLE IF NOT EXISTS menu (
    id_menu   INT AUTO_INCREMENT PRIMARY KEY,
    nama_menu VARCHAR(255) NOT NULL UNIQUE,
    harga     INT DEFAULT 0,
    gambar    VARCHAR(255) DEFAULT '',
    kategori  VARCHAR(20) DEFAULT '' -- 'makanan' | 'minuman'
) ENGINE=InnoDB;

-- Header transaksi
CREATE TABLE IF NOT EXISTS transaksi (
    id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
    tanggal      DATE,
    jam          INT DEFAULT 0,      -- jam 0-23 (untuk analisis jam ramai/sepi)
    total        INT DEFAULT 0
) ENGINE=InnoDB;

-- Detail item per transaksi
CREATE TABLE IF NOT EXISTS detail_transaksi (
    id_detail     INT AUTO_INCREMENT PRIMARY KEY,
    id_transaksi  INT NOT NULL,
    id_menu       INT NOT NULL,
    jumlah        INT DEFAULT 1,
    subtotal      INT DEFAULT 0,
    FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi) ON DELETE CASCADE,
    FOREIGN KEY (id_menu) REFERENCES menu(id_menu) ON DELETE CASCADE
) ENGINE=InnoDB;
