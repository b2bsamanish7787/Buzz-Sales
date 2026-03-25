-- Buzznation Client Requirement Portal Database Schema
CREATE DATABASE IF NOT EXISTS buzz_sales_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE buzz_sales_db;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','sales','design','operations') NOT NULL,
  full_name VARCHAR(100),
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sales_user_id INT NOT NULL,
  project_name VARCHAR(200) NOT NULL,
  client_name VARCHAR(200) NOT NULL,
  status ENUM('pending','approved','rejected','on_hold','ongoing','design_complete','ops_review','sales_review','change_requested','completed','closed') DEFAULT 'pending',
  current_stage VARCHAR(50) DEFAULT 'sales',
  closing_comments TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (sales_user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS project_details (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  requirement_type SET('Exhibition','Event','Branding'),
  contact_person VARCHAR(200),
  contact_email VARCHAR(200),
  contact_phone VARCHAR(50),
  client_country VARCHAR(100),
  event_name VARCHAR(200),
  event_date VARCHAR(100),
  venue VARCHAR(200),
  city VARCHAR(100),
  country VARCHAR(100),
  booth_size VARCHAR(100),
  budget VARCHAR(100),
  stand_type VARCHAR(100),
  design_style VARCHAR(200),
  colors VARCHAR(200),
  products_to_display TEXT,
  special_requirements TEXT,
  additional_notes TEXT,
  consent_agreed TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS file_uploads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  original_name VARCHAR(255),
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_size BIGINT,
  file_type VARCHAR(100),
  upload_type ENUM('requirement','design','change_request') DEFAULT 'requirement',
  change_request_id INT NULL,
  uploaded_by INT,
  uploaded_by_role VARCHAR(20),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS design_reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  action ENUM('approved','rejected','on_hold'),
  deadline_days INT,
  hold_duration INT,
  hold_reason TEXT,
  rejection_reason TEXT,
  remarks TEXT,
  reviewed_by INT,
  reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id)
);

CREATE TABLE IF NOT EXISTS cost_tracking (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  cost_usd DECIMAL(10,2),
  remarks TEXT,
  reviewed_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id)
);

CREATE TABLE IF NOT EXISTS change_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  sales_user_id INT,
  description TEXT NOT NULL,
  notes TEXT,
  status ENUM('pending','approved','rejected','on_hold','implemented') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id)
);

CREATE TABLE IF NOT EXISTS activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(500) NOT NULL,
  project_id INT,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notification_emails (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role ENUM('admin','sales','design','operations') NOT NULL,
  email VARCHAR(100) NOT NULL,
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  target_role VARCHAR(20),
  project_id INT,
  message TEXT NOT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin user (password: Admin@123)
INSERT IGNORE INTO users (username, email, password_hash, role, full_name, status)
VALUES ('admin', 'admin@buzznation.com', '$2y$10$F20JH45nfwt27/6hBcxFb.9UIKbQRDVccBaI8AKNXp9Kb/15udBHC', 'admin', 'System Administrator', 'active');

-- Sample sales user (password: Sales@123)
INSERT IGNORE INTO users (username, email, password_hash, role, full_name, status)
VALUES ('sales1', 'sales1@buzznation.com', '$2y$10$EAuEVZdijk.lgF08TxRc8.jFd8ylD2TbIACIujYzebjF.osLXP6yq', 'sales', 'Sales User One', 'active');

-- Sample design user (password: Design@123)
INSERT IGNORE INTO users (username, email, password_hash, role, full_name, status)
VALUES ('design1', 'design1@buzznation.com', '$2y$10$jEl6uxg0XREkUlDUHG0aauVItNR6sEmryWVg3Iwa0v8PaooiTw22C', 'design', 'Design User One', 'active');

-- Sample operations user (password: Ops@1234)
INSERT IGNORE INTO users (username, email, password_hash, role, full_name, status)
VALUES ('ops1', 'ops1@buzznation.com', '$2y$10$YzII3xukSlXfw2nd/Vudxe7YZ/7sNoMdIk1qX0DEl6UDlpT6gzdQa', 'operations', 'Operations User One', 'active');

-- Default notification emails
INSERT IGNORE INTO notification_emails (role, email, active) VALUES
('design', 'design@buzznation.com', 1),
('operations', 'ops@buzznation.com', 1),
('sales', 'sales@buzznation.com', 1),
('admin', 'admin@buzznation.com', 1);

-- -----------------------------------------------------------------------
-- Fix password hashes for pre-existing installations.
-- If the rows already exist (INSERT IGNORE skipped them) but have the
-- wrong hash, the UPDATE below corrects them without touching any data
-- that was changed after initial setup.
-- -----------------------------------------------------------------------
UPDATE users SET password_hash = '$2y$10$F20JH45nfwt27/6hBcxFb.9UIKbQRDVccBaI8AKNXp9Kb/15udBHC'
    WHERE username = 'admin'  AND password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

UPDATE users SET password_hash = '$2y$10$EAuEVZdijk.lgF08TxRc8.jFd8ylD2TbIACIujYzebjF.osLXP6yq'
    WHERE username = 'sales1' AND password_hash = '$2y$10$TKh8H1.PfunDiE19Cs9x8eR9hh0JT2JHV/q5VXU7fZVLEkPmYhUy.';

UPDATE users SET password_hash = '$2y$10$jEl6uxg0XREkUlDUHG0aauVItNR6sEmryWVg3Iwa0v8PaooiTw22C'
    WHERE username = 'design1' AND password_hash = '$2y$10$6bYcSR0EHI5JTGcFjJUiZuQzBMXJZ8qLfDqVQ6Hm0NQvVu.vCxP6';

UPDATE users SET password_hash = '$2y$10$YzII3xukSlXfw2nd/Vudxe7YZ/7sNoMdIk1qX0DEl6UDlpT6gzdQa'
    WHERE username = 'ops1'    AND password_hash = '$2y$10$rIJ19ADjiu6S4rNQA0n3ROhGkVNxSjcM5LUgRF2U5tFp1nq7V8zBq';
