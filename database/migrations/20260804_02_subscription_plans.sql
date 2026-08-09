CREATE TABLE IF NOT EXISTS subscription_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(100) NOT NULL UNIQUE,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  billing_basis ENUM('direct','branch') NOT NULL DEFAULT 'branch',
  billing_mode ENUM('prepaid','actual') NOT NULL DEFAULT 'prepaid',
  direct_leader_limit INT UNSIGNED NULL,
  branch_leader_limit INT UNSIGNED NULL,
  direct_consultant_limit INT UNSIGNED NULL,
  branch_consultant_limit INT UNSIGNED NULL,
  per_child_consultant_limit INT UNSIGNED NULL,
  price_per_leader DECIMAL(10,2) NULL,
  price_per_consultant DECIMAL(10,2) NULL,
  fixed_monthly_price DECIMAL(10,2) NULL,
  payment_terms TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_subscription_plans_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE resellers
  ADD COLUMN IF NOT EXISTS subscription_plan_id BIGINT UNSIGNED NULL AFTER parent_reseller_id,
  ADD INDEX IF NOT EXISTS idx_resellers_subscription_plan_id (subscription_plan_id);

ALTER TABLE leader_subscriptions
  ADD COLUMN IF NOT EXISTS subscription_plan_id BIGINT UNSIGNED NULL AFTER reseller_id,
  ADD INDEX IF NOT EXISTS idx_leader_subscriptions_plan (subscription_plan_id);

INSERT INTO subscription_plans (
  slug, title, description, billing_mode, billing_basis,
  direct_leader_limit, branch_leader_limit,
  direct_consultant_limit, branch_consultant_limit, per_child_consultant_limit,
  price_per_leader, price_per_consultant, fixed_monthly_price, payment_terms, sort_order, is_active
) VALUES
  (
    'starter',
    'Старт',
    'Для небольшого лидера: несколько дочерних лидеров и базовая команда консультантов.',
    'prepaid',
    'branch',
    5, 20, 50, 150, 50,
    300.00, 300.00, NULL,
    'Оплата подтверждается администратором вручную. Онлайн-касса на первом этапе не подключена.',
    10, 1
  ),
  (
    'team',
    'Команда',
    'Основной тариф для активной команды лидера.',
    'prepaid',
    'branch',
    20, 100, 100, 1000, 200,
    300.00, 300.00, NULL,
    'Оплата подтверждается администратором вручную. Онлайн-касса на первом этапе не подключена.',
    20, 1
  ),
  (
    'network',
    'Лидерская сеть',
    'Для большой многоуровневой структуры с несколькими лидерами внутри ветки.',
    'prepaid',
    'branch',
    100, 500, 300, 5000, 500,
    300.00, 300.00, NULL,
    'Оплата подтверждается администратором вручную. Онлайн-касса на первом этапе не подключена.',
    30, 1
  )
ON DUPLICATE KEY UPDATE
  title = VALUES(title),
  description = VALUES(description),
  payment_terms = VALUES(payment_terms),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active);
