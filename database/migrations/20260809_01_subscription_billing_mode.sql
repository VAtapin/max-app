ALTER TABLE subscription_plans
  ADD COLUMN IF NOT EXISTS billing_mode ENUM('prepaid','actual') NOT NULL DEFAULT 'prepaid' AFTER billing_basis;

ALTER TABLE leader_subscriptions
  ADD COLUMN IF NOT EXISTS billing_mode ENUM('prepaid','actual') NOT NULL DEFAULT 'prepaid' AFTER billing_basis;
