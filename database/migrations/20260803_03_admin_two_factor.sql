ALTER TABLE admin_users
  ADD COLUMN IF NOT EXISTS two_factor_required TINYINT(1) NOT NULL DEFAULT 0 AFTER referral_code,
  ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER two_factor_required,
  ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(64) NULL AFTER two_factor_enabled,
  ADD COLUMN IF NOT EXISTS two_factor_confirmed_at DATETIME NULL AFTER two_factor_secret;
