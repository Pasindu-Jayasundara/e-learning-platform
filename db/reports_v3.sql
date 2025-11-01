-- Add course_id to reports table to allow easier filtering
ALTER TABLE `reports`
  ADD COLUMN `course_id` INT UNSIGNED NULL DEFAULT NULL,
  ADD INDEX (`course_id`);
