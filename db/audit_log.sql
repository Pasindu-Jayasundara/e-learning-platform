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
