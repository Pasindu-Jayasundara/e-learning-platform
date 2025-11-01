-- Migration: add payment_intent and refund id columns for better Stripe reconciliation
ALTER TABLE `payments`
  ADD COLUMN `stripe_payment_intent_id` VARCHAR(255) NULL AFTER `stripe_charge_id`,
  ADD COLUMN `stripe_refund_id` VARCHAR(255) NULL AFTER `stripe_payment_intent_id`;

ALTER TABLE `payments` ADD KEY (`stripe_payment_intent_id`);
ALTER TABLE `payments` ADD KEY (`stripe_refund_id`);

-- Note: run this migration against your MySQL database to enable storing intent/refund ids.
