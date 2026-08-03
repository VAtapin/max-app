ALTER TABLE resellers
  ADD COLUMN IF NOT EXISTS billing_name VARCHAR(190) NULL AFTER phone,
  ADD COLUMN IF NOT EXISTS billing_inn VARCHAR(20) NULL AFTER billing_name,
  ADD COLUMN IF NOT EXISTS billing_email VARCHAR(190) NULL AFTER billing_inn,
  ADD COLUMN IF NOT EXISTS billing_comment VARCHAR(500) NULL AFTER billing_email,
  ADD COLUMN IF NOT EXISTS manager_limit INT UNSIGNED NULL AFTER referral_code;

ALTER TABLE leader_subscriptions
  ADD COLUMN IF NOT EXISTS consultant_limit INT UNSIGNED NULL AFTER reseller_id,
  ADD COLUMN IF NOT EXISTS price_per_consultant DECIMAL(10,2) NULL AFTER consultant_limit,
  ADD COLUMN IF NOT EXISTS amount_due DECIMAL(10,2) NULL AFTER price_per_consultant,
  ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL AFTER monthly_price,
  ADD COLUMN IF NOT EXISTS invoice_number VARCHAR(100) NULL AFTER paid_at,
  ADD COLUMN IF NOT EXISTS payment_method VARCHAR(100) NULL AFTER invoice_number;

INSERT INTO settings (setting_key, setting_value, description) VALUES
('leader_price_per_consultant', '300', 'Базовая ежемесячная стоимость одного консультанта в команде лидера'),
('leader_payment_terms', 'Оплата подтверждается администратором вручную. Онлайн-касса на первом этапе не подключена.', 'Короткая подсказка для бухгалтерской панели лидеров')
ON DUPLICATE KEY UPDATE description = VALUES(description);

UPDATE settings
SET description = 'Устаревшее поле совместимости: текущая цена считается по лимиту консультантов'
WHERE setting_key = 'leader_monthly_price';
