-- Migration: store Stripe session and charge IDs for payments
ALTER TABLE `payments`
  ADD COLUMN `stripe_session_id` VARCHAR(255) NULL AFTER `status`,
  ADD COLUMN `stripe_charge_id` VARCHAR(255) NULL AFTER `stripe_session_id`;

ALTER TABLE `payments` ADD KEY (`stripe_session_id`);
ALTER TABLE `payments` ADD KEY (`stripe_charge_id`);

-- Note: run this migration against your MySQL database to enable Stripe refund automation.
