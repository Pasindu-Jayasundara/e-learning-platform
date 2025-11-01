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
