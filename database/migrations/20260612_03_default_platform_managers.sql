INSERT INTO resellers (name, email, phone, referral_code, is_active)
SELECT 'Demo Reseller', 'demo-reseller@example.com', NULL, 'DEMO-RESELLER', 1
WHERE NOT EXISTS (SELECT 1 FROM resellers WHERE referral_code = 'DEMO-RESELLER');

INSERT INTO managers (reseller_id, name, email, phone, referral_code, is_active)
SELECT r.id, 'Default Manager', 'default-manager@example.com', NULL, 'SWPRO-START', 1
FROM resellers r
WHERE r.referral_code = 'DEMO-RESELLER'
  AND NOT EXISTS (SELECT 1 FROM managers WHERE referral_code = 'SWPRO-START');

CREATE TABLE default_platform_managers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  platform ENUM('telegram', 'VK', 'OK', 'MAX', 'web') NOT NULL,
  manager_id BIGINT UNSIGNED NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_default_platform_manager (platform),
  INDEX idx_default_platform_manager_id (manager_id),
  CONSTRAINT fk_default_platform_manager
    FOREIGN KEY (manager_id) REFERENCES managers(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO default_platform_managers (platform, manager_id, is_active)
SELECT p.platform, m.id, 1
FROM managers m
JOIN (
  SELECT 'telegram' AS platform
  UNION SELECT 'VK'
  UNION SELECT 'OK'
  UNION SELECT 'MAX'
  UNION SELECT 'web'
) p
WHERE m.referral_code = 'SWPRO-START'
ON DUPLICATE KEY UPDATE manager_id = VALUES(manager_id);
