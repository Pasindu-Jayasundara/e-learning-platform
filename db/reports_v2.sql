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

-- Note: run this migration against your MySQL database to apply schema changes.
-- Migration to add handling metadata to reports
ALTER TABLE `reports`
  ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'open',
  ADD COLUMN `handled_by` INT UNSIGNED NULL DEFAULT NULL,
  ADD COLUMN `handled_at` TIMESTAMP NULL DEFAULT NULL;
