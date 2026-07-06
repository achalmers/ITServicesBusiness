-- ============================================================
-- NexaTech Solutions — Database Schema
-- MySQL 8.x (SiteGround compatible)
-- ============================================================

-- NOTE: On SiteGround, the database is already created via Site Tools > MySQL
-- Databases (with its own account-prefixed name). Your DB user only has
-- privileges on that specific database, so CREATE DATABASE / USE statements
-- here would fail with "Access denied". Select your actual database in
-- phpMyAdmin's left sidebar first, then run the Import from there.

-- ============================================================
-- CUSTOMERS TABLE
-- ============================================================
CREATE TABLE customers (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  first_name    VARCHAR(100) NOT NULL,
  last_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(255) NOT NULL UNIQUE,
  phone         VARCHAR(20),
  company       VARCHAR(200),
  password_hash VARCHAR(255) NOT NULL,
  plan          ENUM('starter','growth','enterprise','none') DEFAULT 'none',
  status        ENUM('active','inactive','pending') DEFAULT 'pending',
  notes         TEXT,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email  (email),
  INDEX idx_status (status),
  INDEX idx_plan   (plan)
);

-- ============================================================
-- ADMIN USERS TABLE
-- ============================================================
CREATE TABLE admin_users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(100) NOT NULL UNIQUE,
  email         VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','technician') DEFAULT 'technician',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_username (username),
  INDEX idx_email    (email)
);

-- ============================================================
-- SUPPORT TICKETS TABLE
-- ============================================================
CREATE TABLE tickets (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  subject     VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  category    ENUM('network','cloud','security','hardware','software','backup','remote','consulting','other') DEFAULT 'other',
  priority    ENUM('low','medium','high','critical') DEFAULT 'medium',
  status      ENUM('open','in_progress','waiting','resolved','closed') DEFAULT 'open',
  assigned_to INT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_to) REFERENCES admin_users(id) ON DELETE SET NULL,
  INDEX idx_customer (customer_id),
  INDEX idx_status   (status),
  INDEX idx_priority (priority),
  INDEX idx_assigned (assigned_to)
);

-- ============================================================
-- TICKET COMMENTS TABLE
-- ============================================================
CREATE TABLE ticket_comments (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id   INT NOT NULL,
  author_type ENUM('customer','admin') NOT NULL,
  author_id   INT NOT NULL,
  comment     TEXT NOT NULL,
  is_internal TINYINT(1) DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  INDEX idx_ticket (ticket_id)
);

-- ============================================================
-- CONTACT FORM SUBMISSIONS TABLE
-- ============================================================
CREATE TABLE contact_submissions (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  name             VARCHAR(200) NOT NULL,
  company          VARCHAR(200),
  email            VARCHAR(255) NOT NULL,
  phone            VARCHAR(20),
  service_interest VARCHAR(100),
  message          TEXT NOT NULL,
  status           ENUM('new','contacted','converted','closed') DEFAULT 'new',
  ip_address       VARCHAR(45),
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_email  (email)
);

-- ============================================================
-- TICKET ATTACHMENTS TABLE (for file uploads)
-- ============================================================
CREATE TABLE ticket_attachments (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id   INT NOT NULL,
  filename    VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  file_size   INT NOT NULL,
  mime_type   VARCHAR(100),
  uploaded_by_type ENUM('customer','admin') NOT NULL,
  uploaded_by_id   INT NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  INDEX idx_ticket (ticket_id)
);

-- ============================================================
-- DEFAULT ADMIN USER
-- Password: ChangeMe123! — MUST be changed after setup
-- Run: php -r "echo password_hash('ChangeMe123!', PASSWORD_BCRYPT, ['cost'=>12]);"
-- and update the password_hash value below.
-- ============================================================
INSERT INTO admin_users (username, email, password_hash, role) VALUES
('alex', 'alex.l.chalmers@gmail.com', '$2y$12$placeholder_change_on_setup_REPLACE_THIS_HASH', 'admin');
