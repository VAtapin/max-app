ALTER TABLE end_users
  MODIFY COLUMN platform ENUM('telegram', 'vk', 'VK', 'ok', 'OK', 'max', 'MAX', 'web') NOT NULL;
UPDATE end_users SET platform = 'VK' WHERE platform = 'vk';
UPDATE end_users SET platform = 'OK' WHERE platform = 'ok';
UPDATE end_users SET platform = 'MAX' WHERE platform = 'max';
ALTER TABLE end_users
  MODIFY COLUMN platform ENUM('telegram', 'VK', 'OK', 'MAX', 'web') NOT NULL;

ALTER TABLE platform_accounts
  MODIFY COLUMN platform ENUM('telegram', 'vk', 'VK', 'ok', 'OK', 'max', 'MAX', 'web') NOT NULL;
UPDATE platform_accounts SET platform = 'VK' WHERE platform = 'vk';
UPDATE platform_accounts SET platform = 'OK' WHERE platform = 'ok';
UPDATE platform_accounts SET platform = 'MAX' WHERE platform = 'max';
ALTER TABLE platform_accounts
  MODIFY COLUMN platform ENUM('telegram', 'VK', 'OK', 'MAX', 'web') NOT NULL;

ALTER TABLE referral_links
  MODIFY COLUMN platform ENUM('telegram', 'vk', 'VK', 'ok', 'OK', 'max', 'MAX', 'web') NOT NULL;
UPDATE referral_links SET platform = 'VK' WHERE platform = 'vk';
UPDATE referral_links SET platform = 'OK' WHERE platform = 'ok';
UPDATE referral_links SET platform = 'MAX' WHERE platform = 'max';
ALTER TABLE referral_links
  MODIFY COLUMN platform ENUM('telegram', 'VK', 'OK', 'MAX', 'web') NOT NULL;

ALTER TABLE leads
  MODIFY COLUMN source_platform ENUM('telegram', 'vk', 'VK', 'ok', 'OK', 'max', 'MAX', 'web') NOT NULL;
UPDATE leads SET source_platform = 'VK' WHERE source_platform = 'vk';
UPDATE leads SET source_platform = 'OK' WHERE source_platform = 'ok';
UPDATE leads SET source_platform = 'MAX' WHERE source_platform = 'max';
ALTER TABLE leads
  MODIFY COLUMN source_platform ENUM('telegram', 'VK', 'OK', 'MAX', 'web') NOT NULL;

ALTER TABLE lead_responses
  MODIFY COLUMN platform ENUM('telegram', 'vk', 'VK', 'ok', 'OK', 'max', 'MAX', 'web') NOT NULL;
UPDATE lead_responses SET platform = 'VK' WHERE platform = 'vk';
UPDATE lead_responses SET platform = 'OK' WHERE platform = 'ok';
UPDATE lead_responses SET platform = 'MAX' WHERE platform = 'max';
ALTER TABLE lead_responses
  MODIFY COLUMN platform ENUM('telegram', 'VK', 'OK', 'MAX', 'web') NOT NULL;

ALTER TABLE broadcasts
  MODIFY COLUMN platform ENUM('all', 'telegram', 'vk', 'VK', 'ok', 'OK', 'max', 'MAX') NOT NULL DEFAULT 'all';
UPDATE broadcasts SET platform = 'VK' WHERE platform = 'vk';
UPDATE broadcasts SET platform = 'OK' WHERE platform = 'ok';
UPDATE broadcasts SET platform = 'MAX' WHERE platform = 'max';
ALTER TABLE broadcasts
  MODIFY COLUMN platform ENUM('all', 'telegram', 'VK', 'OK', 'MAX') NOT NULL DEFAULT 'all';

ALTER TABLE messaging_integrations
  MODIFY COLUMN platform ENUM('vk', 'VK', 'ok', 'OK', 'telegram', 'max', 'MAX') NOT NULL;
UPDATE messaging_integrations SET platform = 'VK' WHERE platform = 'vk';
UPDATE messaging_integrations SET platform = 'OK' WHERE platform = 'ok';
UPDATE messaging_integrations SET platform = 'MAX' WHERE platform = 'max';
ALTER TABLE messaging_integrations
  MODIFY COLUMN platform ENUM('VK', 'OK', 'telegram', 'MAX') NOT NULL;
