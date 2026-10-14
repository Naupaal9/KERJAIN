-- Kerjain! — Skema database
-- Import lewat phpMyAdmin (tab Import) atau: mysql -u root < schema.sql

CREATE DATABASE IF NOT EXISTS kerjain
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE kerjain;

DROP TABLE IF EXISTS applications;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- USERS ---------------------------------------------------------------
CREATE TABLE users (
  user_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(120) NOT NULL UNIQUE,
  password      VARCHAR(255) NOT NULL,              -- hasil password_hash()
  role          ENUM('mahasiswa','dosen','admin') NOT NULL DEFAULT 'mahasiswa',
  nim_nip       VARCHAR(30)  DEFAULT NULL,
  prodi         VARCHAR(100) DEFAULT NULL,
  phone         VARCHAR(20)  DEFAULT NULL,
  profile_photo VARCHAR(255) DEFAULT NULL,          -- nama file di assets/img/
  bio           TEXT         DEFAULT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- CATEGORIES ----------------------------------------------------------
CREATE TABLE categories (
  category_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(80) NOT NULL UNIQUE,
  description   VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

-- PROJECTS ------------------------------------------------------------
CREATE TABLE projects (
  project_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id    INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  title       VARCHAR(150) NOT NULL,
  description TEXT         NOT NULL,
  budget      DECIMAL(12,0) NOT NULL DEFAULT 0,     -- rupiah, tanpa desimal
  deadline    DATE         DEFAULT NULL,
  status      ENUM('open','in_progress','done','closed') NOT NULL DEFAULT 'open',
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_projects_owner
    FOREIGN KEY (owner_id) REFERENCES users(user_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_projects_category
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_projects_status (status),
  INDEX idx_projects_category (category_id)
) ENGINE=InnoDB;

-- APPLICATIONS --------------------------------------------------------
CREATE TABLE applications (
  application_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id     INT UNSIGNED NOT NULL,
  applicant_id   INT UNSIGNED NOT NULL,
  cover_letter   TEXT         NOT NULL,
  proposed_fee   DECIMAL(12,0) NOT NULL DEFAULT 0,
  status         ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  applied_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_applications_project
    FOREIGN KEY (project_id) REFERENCES projects(project_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_applications_applicant
    FOREIGN KEY (applicant_id) REFERENCES users(user_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uq_one_application (project_id, applicant_id)  -- 1 user melamar 1x per project
) ENGINE=InnoDB;

-- Data awal kategori (user demo & project contoh dibuat oleh database/seed.php)
INSERT INTO categories (category_name, description) VALUES
  ('Desain Grafis',      'Poster, logo, feed & konten sosial media'),
  ('UI/UX',              'Wireframe, prototype Figma, desain antarmuka'),
  ('Web Development',    'Website, database, fitur backend'),
  ('Video & Animasi',    'Editing video, motion graphic, animasi'),
  ('Penulisan',          'Copywriting, artikel, proposal, laporan'),
  ('Sosial Media',       'Pengelolaan akun dan konten'),
  ('Penelitian & Data',  'Olah data, survei, bantu riset');
