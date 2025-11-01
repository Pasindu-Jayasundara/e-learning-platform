-- Add notify_on_report flag to users so instructors can opt-out of notifications
ALTER TABLE `users`
  ADD COLUMN `notify_on_report` TINYINT(1) NOT NULL DEFAULT 1;
