-- Database Schema untuk Sistem Manajemen Gereja

CREATE DATABASE IF NOT EXISTS gereja_db;
USE gereja_db;

-- 1. Table users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    username VARCHAR(50) UNIQUE,
    phone VARCHAR(20) NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('MAJELIS_GEREJA', 'BENDAHARA', 'JEMAAT', 'SEKRETARIS') NOT NULL DEFAULT 'JEMAAT',
    force_password_change BOOLEAN DEFAULT FALSE,
    last_login DATETIME NULL,
    active_session_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 1.5 Security Questions for Password Reset
CREATE TABLE IF NOT EXISTS security_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_text VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS user_security_answers (
  user_id INT NOT NULL,
  question_id INT NOT NULL,
  answer VARCHAR(255) NOT NULL,
  PRIMARY KEY (user_id, question_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES security_questions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS password_reset_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  status ENUM('PENDING', 'APPROVED', 'REJECTED') DEFAULT 'PENDING',
  requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  approved_by INT NULL,
  approved_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO security_questions (question_text) VALUES 
('Ayat Alkitab Favorit anda (namakitab:pasal:ayat) (*tanpa spasi)'),
('Pastor / Pendeta / Penghkhotbah / Gembala Favorit Anda'),
('Tokoh Alkitab yang anda Kagumi selain selain Yesus, Murid Yesus, dan Nabi-nabi.');

-- 2. Table jemaat_profiles
CREATE TABLE IF NOT EXISTS jemaat_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    no_anggota VARCHAR(50) UNIQUE,
    nama_lengkap VARCHAR(150),
    email VARCHAR(100) NULL,
    foto_profil VARCHAR(255) NULL,
    jenis_kelamin ENUM('L', 'P'),
    tempat_lahir VARCHAR(100),
    tanggal_lahir DATE,
    alamat_lengkap TEXT,
    no_hp VARCHAR(20),
    golongan_darah VARCHAR(5),
    pekerjaan VARCHAR(100),
    pendidikan VARCHAR(100),
    status_keanggotaan ENUM('TETAP', 'TIDAK_TETAP') DEFAULT 'TETAP',
    tahun_masuk YEAR,
    status_baptis BOOLEAN DEFAULT FALSE,
    tanggal_baptis DATE NULL,
    status_sidi BOOLEAN DEFAULT FALSE,
    tanggal_sidi DATE NULL,
    status_perkawinan ENUM('LAJANG', 'MENIKAH', 'JANDA', 'DUDA') DEFAULT 'LAJANG',
    tanggal_nikah DATE NULL,
    tempat_nikah VARCHAR(150) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. Table uang_masuk
CREATE TABLE IF NOT EXISTS uang_masuk (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL, -- Bisa NULL jika anonim/diinput manual tanpa user jemaat
    amount DECIMAL(15, 2) NOT NULL,
    kategori ENUM('Perpuluhan', 'Persembahan', 'Donasi', 'Kolekte', 'Pembangunan', 'DiakoniaSosial', 'PemasukanLain') NOT NULL,
    description TEXT,
    proof_of_transfer VARCHAR(255) NULL,
    date DATE NOT NULL,
    status ENUM('PENDING', 'VERIFIED', 'REJECTED') DEFAULT 'VERIFIED',
    verified_by INT NULL,
    input_by INT NULL,
    receipt_token VARCHAR(50) UNIQUE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (input_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 4. Table uang_keluar
CREATE TABLE IF NOT EXISTS uang_keluar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    amount DECIMAL(15, 2) NOT NULL,
    kategori_utama ENUM('Biaya_Pembangunan', 'Biaya_Sosial', 'Biaya_Khusus', 'Biaya_Umum') NOT NULL,
    sub_kategori VARCHAR(100),
    description TEXT,
    date DATE NOT NULL,
    status ENUM('PENDING', 'VERIFIED', 'REJECTED') DEFAULT 'VERIFIED',
    verified_by INT NULL,
    alasan_penolakan TEXT NULL,
    is_request BOOLEAN DEFAULT FALSE,
    requested_by INT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 5. Table laporan_keuangan
CREATE TABLE IF NOT EXISTS laporan_keuangan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    periode_bulan INT NOT NULL,
    periode_tahun INT NOT NULL,
    total_masuk DECIMAL(15, 2) NOT NULL,
    total_keluar DECIMAL(15, 2) NOT NULL,
    saldo_akhir DECIMAL(15, 2) NOT NULL,
    status ENUM('DRAFT', 'SUBMITTED', 'VERIFIED', 'REJECTED') DEFAULT 'DRAFT',
    created_by INT NULL,
    verified_by INT NULL,
    rejection_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_periode (periode_bulan, periode_tahun),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 6. Table notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Default Admin Users with Secure Standard Credentials (Reset to password123)
-- Majelis Gereja:   Username: Admin Ketua      | Email: ketua@gereja.local      | Password: password123
-- Bendahara:      Username: Admin Bendahara  | Email: bendahara@gereja.local  | Password: password123
-- Sekretaris:     Username: Admin Sekretaris  | Email: sekretaris@gereja.local | Password: password123
INSERT INTO users (name, email, password, role) VALUES 
('Johannes Hutagalung', 'ketua@gereja.local', '$2y$10$GHbnRD1ZBt1lQoy8dGffZOnDES4wLQhEqKq4DRL8bpWB4EDi4NEU2', 'MAJELIS_GEREJA'),
('Maria Panjaitan', 'bendahara@gereja.local', '$2y$10$GHbnRD1ZBt1lQoy8dGffZOnDES4wLQhEqKq4DRL8bpWB4EDi4NEU2', 'BENDAHARA'),
('Lukas Simanjuntak', 'sekretaris@gereja.local', '$2y$10$GHbnRD1ZBt1lQoy8dGffZOnDES4wLQhEqKq4DRL8bpWB4EDi4NEU2', 'SEKRETARIS');

