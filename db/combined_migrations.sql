-- Combined Migrations
-- Generated: 2025-10-31
-- This file combines the individual SQL migration files found in db/ into a single file
-- Run against your database in a transaction where appropriate. Review and split as needed for production.

SET FOREIGN_KEY_CHECKS=0;

-- >> File: schema.sql
-- (Base schema: users, courses, materials, enrollments, payments, certificates (legacy), announcements, forum tables)

-- Schema for e-learning platform
-- Create database separately (e.g., `CREATE DATABASE e_learning; USE e_learning;`)

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','instructor','student') NOT NULL DEFAULT 'student',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  price DECIMAL(10,2) DEFAULT 0.00,
  is_published TINYINT(1) DEFAULT 0,
  instructor_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE course_materials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  filename VARCHAR(255) NOT NULL,
  path VARCHAR(500) NOT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE enrollments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  course_id INT NOT NULL,
  progress INT DEFAULT 0,
  enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  course_id INT,
  amount DECIMAL(10,2) NOT NULL,
  status ENUM('pending','completed','refunded') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE certificates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  course_id INT NOT NULL,
  issued_at TIMESTAMP NULL,
  template VARCHAR(255),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  course_id INT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Forum tables: posts and replies (course-specific forums)
CREATE TABLE forum_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE forum_replies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  user_id INT NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- >> File: certificates.sql
-- (Certificate templates and certificates table with richer metadata)

-- Migration: certificates and certificate_templates
CREATE TABLE IF NOT EXISTS certificate_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  html_template TEXT NOT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (created_by)
);

CREATE TABLE IF NOT EXISTS certificates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  template_id INT DEFAULT NULL,
  user_id INT NOT NULL,
  course_id INT NOT NULL,
  status ENUM('requested','issued','revoked') NOT NULL DEFAULT 'requested',
  issued_by INT DEFAULT NULL,
  issued_at DATETIME DEFAULT NULL,
  revoked_by INT DEFAULT NULL,
  revoked_at DATETIME DEFAULT NULL,
  serial VARCHAR(128) DEFAULT NULL,
  metadata JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (template_id),
  INDEX (user_id),
  INDEX (course_id),
  INDEX (status)
);

-- >> File: audit_log.sql
-- (Audit log for moderator/admin actions)

-- Create audit_log table to record moderator actions
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) NULL,
  `entity_id` INT UNSIGNED NULL,
  `report_id` INT UNSIGNED NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY (`actor_id`)
);

-- >> File: messages.sql
-- (Private messaging table)

-- Migration: create messages table for private messaging
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `sender_id` INT NOT NULL,
  `recipient_id` INT NOT NULL,
  `subject` VARCHAR(255) DEFAULT NULL,
  `body` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_recipient` (`recipient_id`),
  KEY `idx_sender` (`sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- >> File: reports.sql
-- (Base reports table)

-- Add reports table for post/reply reporting
CREATE TABLE IF NOT EXISTS `reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reporter_id` INT UNSIGNED NOT NULL,
  `entity_type` VARCHAR(20) NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `reason` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY (`reporter_id`)
);

-- >> File: reports_v2.sql
-- (Add handling metadata to reports)

-- Migration: add status, course_id, claimed/handled fields to reports
ALTER TABLE `reports`
  ADD COLUMN `course_id` INT UNSIGNED NULL AFTER `entity_id`,
  ADD COLUMN `status` ENUM('open','claimed','dismissed','resolved') NOT NULL DEFAULT 'open' AFTER `course_id`,
  ADD COLUMN `claimed_by` INT UNSIGNED NULL AFTER `status`,
  ADD COLUMN `claimed_at` TIMESTAMP NULL DEFAULT NULL AFTER `claimed_by`,
  ADD COLUMN `handled_by` INT UNSIGNED NULL AFTER `claimed_at`,
  ADD COLUMN `handled_at` TIMESTAMP NULL DEFAULT NULL AFTER `handled_by`;

ALTER TABLE `reports` ADD KEY (`course_id`);
ALTER TABLE `reports` ADD KEY (`status`);

-- >> File: reports_v3.sql
-- (Add course_id to reports table - some migrations may be duplicates; included for completeness)

-- Add course_id to reports table to allow easier filtering
ALTER TABLE `reports`
  ADD COLUMN `course_id` INT UNSIGNED NULL DEFAULT NULL,
  ADD INDEX (`course_id`);

-- >> File: payments_v2.sql
-- (Store Stripe session and charge IDs)

-- Migration: store Stripe session and charge IDs for payments
ALTER TABLE `payments`
  ADD COLUMN `stripe_session_id` VARCHAR(255) NULL AFTER `status`,
  ADD COLUMN `stripe_charge_id` VARCHAR(255) NULL AFTER `stripe_session_id`;

ALTER TABLE `payments` ADD KEY (`stripe_session_id`);
ALTER TABLE `payments` ADD KEY (`stripe_charge_id`);

-- Note: run this migration against your MySQL database to enable Stripe refund automation.

-- >> File: payments_v3.sql
-- (Store payment intent and refund IDs for Stripe)

-- Migration: add payment_intent and refund id columns for better Stripe reconciliation
ALTER TABLE `payments`
  ADD COLUMN `stripe_payment_intent_id` VARCHAR(255) NULL AFTER `stripe_charge_id`,
  ADD COLUMN `stripe_refund_id` VARCHAR(255) NULL AFTER `stripe_payment_intent_id`;

ALTER TABLE `payments` ADD KEY (`stripe_payment_intent_id`);
ALTER TABLE `payments` ADD KEY (`stripe_refund_id`);

-- >> File: users_notify.sql
-- (Add notify_on_report flag to users)

-- Add notify_on_report flag to users so instructors can opt-out of notifications
ALTER TABLE `users`
  ADD COLUMN `notify_on_report` TINYINT(1) NOT NULL DEFAULT 1;

SET FOREIGN_KEY_CHECKS=1;

-- End of combined migrations
