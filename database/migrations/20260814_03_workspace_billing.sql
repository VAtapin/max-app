ALTER TABLE subscription_plans
  ADD COLUMN IF NOT EXISTS payment_grace_days INT UNSIGNED NOT NULL DEFAULT 5 AFTER payment_terms;

CREATE TABLE IF NOT EXISTS subscription_period_discounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subscription_plan_id BIGINT UNSIGNED NOT NULL,
  months INT UNSIGNED NOT NULL,
  discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  badge_text VARCHAR(100) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_subscription_period_discount (subscription_plan_id, months),
  CONSTRAINT fk_subscription_period_discount_plan
    FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plans(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_methods (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  title VARCHAR(190) NOT NULL,
  method_type ENUM('gateway','manual') NOT NULL DEFAULT 'gateway',
  description TEXT NULL,
  instructions MEDIUMTEXT NULL,
  config_json MEDIUMTEXT NULL,
  is_test TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workspace_subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_type ENUM('reseller','manager') NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  root_reseller_id BIGINT UNSIGNED NOT NULL,
  subscription_plan_id BIGINT UNSIGNED NOT NULL,
  unit_type ENUM('base','leader','consultant') NOT NULL,
  billing_mode ENUM('prepaid','actual') NOT NULL,
  monthly_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  active_from DATE NOT NULL,
  inactive_at DATE NULL,
  paid_until DATE NULL,
  status ENUM('active','due','overdue','suspended') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_workspace_subscription_subject (subject_type, subject_id),
  INDEX idx_workspace_subscription_root (root_reseller_id, status),
  INDEX idx_workspace_subscription_plan (subscription_plan_id),
  CONSTRAINT fk_workspace_subscription_root
    FOREIGN KEY (root_reseller_id) REFERENCES resellers(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_workspace_subscription_plan
    FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plans(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_invoices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  workspace_subscription_id BIGINT UNSIGNED NOT NULL,
  root_reseller_id BIGINT UNSIGNED NOT NULL,
  invoice_number VARCHAR(100) NOT NULL UNIQUE,
  invoice_type ENUM('prepaid','actual','adjustment') NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  months INT UNSIGNED NOT NULL DEFAULT 1,
  base_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  amount_due DECIMAL(10,2) NOT NULL DEFAULT 0,
  amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
  due_at DATETIME NULL,
  status ENUM('pending','awaiting_confirmation','paid','overdue','canceled') NOT NULL DEFAULT 'pending',
  paid_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_billing_invoice_period (workspace_subscription_id, invoice_type, period_start, period_end),
  INDEX idx_billing_invoice_root (root_reseller_id, status, due_at),
  CONSTRAINT fk_billing_invoice_workspace
    FOREIGN KEY (workspace_subscription_id) REFERENCES workspace_subscriptions(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_billing_invoice_root
    FOREIGN KEY (root_reseller_id) REFERENCES resellers(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  billing_invoice_id BIGINT UNSIGNED NOT NULL,
  payment_method_id BIGINT UNSIGNED NULL,
  provider_payment_id VARCHAR(190) NULL,
  idempotency_key VARCHAR(100) NOT NULL UNIQUE,
  amount DECIMAL(10,2) NOT NULL,
  status ENUM('created','pending','succeeded','failed','canceled','refunded') NOT NULL DEFAULT 'created',
  payer_comment VARCHAR(500) NULL,
  receipt_path VARCHAR(255) NULL,
  provider_payload MEDIUMTEXT NULL,
  confirmed_by BIGINT UNSIGNED NULL,
  confirmed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_payment_transaction_invoice (billing_invoice_id, status),
  INDEX idx_payment_transaction_provider (provider_payment_id),
  CONSTRAINT fk_payment_transaction_invoice
    FOREIGN KEY (billing_invoice_id) REFERENCES billing_invoices(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_payment_transaction_method
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_payment_transaction_admin
    FOREIGN KEY (confirmed_by) REFERENCES admin_users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_webhook_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_method_code VARCHAR(50) NOT NULL,
  provider_event_id VARCHAR(190) NOT NULL,
  signature_valid TINYINT(1) NOT NULL DEFAULT 0,
  payload MEDIUMTEXT NULL,
  processed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payment_webhook_event (payment_method_code, provider_event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_adjustments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  workspace_subscription_id BIGINT UNSIGNED NOT NULL,
  adjustment_type ENUM('credit','debit','extend','writeoff') NOT NULL,
  amount DECIMAL(10,2) NULL,
  days INT NULL,
  note VARCHAR(500) NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_billing_adjustment_workspace
    FOREIGN KEY (workspace_subscription_id) REFERENCES workspace_subscriptions(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_billing_adjustment_admin
    FOREIGN KEY (created_by) REFERENCES admin_users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO payment_methods (code, title, method_type, description, is_test, is_active, sort_order) VALUES
('stripe', 'Stripe', 'gateway', 'Оплата банковской картой через Stripe.', 1, 0, 10),
('paypal', 'PayPal', 'gateway', 'Оплата через PayPal.', 1, 0, 20),
('yookassa', 'ЮKassa', 'gateway', 'Оплата через ЮKassa.', 1, 0, 30),
('cloudpayments', 'CloudPayments', 'gateway', 'Оплата через CloudPayments.', 1, 0, 40),
('bank_transfer', 'Перевод по реквизитам', 'manual', 'Банковский перевод или другая ручная оплата.', 0, 0, 50);

INSERT IGNORE INTO subscription_period_discounts
  (subscription_plan_id, months, discount_percent, badge_text, is_active, sort_order)
SELECT id, 1, 0, NULL, 1, 10 FROM subscription_plans;
INSERT IGNORE INTO subscription_period_discounts
  (subscription_plan_id, months, discount_percent, badge_text, is_active, sort_order)
SELECT id, 6, 2, 'Выгодно', 1, 20 FROM subscription_plans;
INSERT IGNORE INTO subscription_period_discounts
  (subscription_plan_id, months, discount_percent, badge_text, is_active, sort_order)
SELECT id, 12, 5, 'Максимальная выгода', 1, 30 FROM subscription_plans;
