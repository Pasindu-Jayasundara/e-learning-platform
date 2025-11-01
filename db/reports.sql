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
