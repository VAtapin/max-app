CREATE DATABASE IF NOT EXISTS health_sales_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE health_sales_system;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS broadcast_logs;
DROP TABLE IF EXISTS broadcasts;
DROP TABLE IF EXISTS lead_responses;
DROP TABLE IF EXISTS leads;
DROP TABLE IF EXISTS content_posts;
DROP TABLE IF EXISTS recommendations;
DROP TABLE IF EXISTS user_test_answers;
DROP TABLE IF EXISTS user_test_sessions;
DROP TABLE IF EXISTS test_results;
DROP TABLE IF EXISTS test_answers;
DROP TABLE IF EXISTS test_questions;
DROP TABLE IF EXISTS tests;
DROP TABLE IF EXISTS product_tags;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS product_categories;
DROP TABLE IF EXISTS platform_accounts;
DROP TABLE IF EXISTS referral_links;
DROP TABLE IF EXISTS end_users;
DROP TABLE IF EXISTS admin_users;
DROP TABLE IF EXISTS managers;
DROP TABLE IF EXISTS resellers;
DROP TABLE IF EXISTS settings;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE resellers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(50) NULL,
  referral_code VARCHAR(64) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_resellers_referral_code (referral_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE managers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reseller_id BIGINT UNSIGNED NULL,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(50) NULL,
  telegram_id VARCHAR(100) NULL,
  max_id VARCHAR(100) NULL,
  vk_id VARCHAR(100) NULL,
  referral_code VARCHAR(64) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_managers_reseller_id (reseller_id),
  INDEX idx_managers_referral_code (referral_code),
  CONSTRAINT fk_managers_reseller
    FOREIGN KEY (reseller_id) REFERENCES resellers(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role ENUM('superadmin', 'reseller', 'manager') NOT NULL,
  reseller_id BIGINT UNSIGNED NULL,
  manager_id BIGINT UNSIGNED NULL,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(50) NULL,
  telegram_id VARCHAR(100) NULL,
  max_id VARCHAR(100) NULL,
  vk_id VARCHAR(100) NULL,
  referral_code VARCHAR(64) NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_admin_role (role),
  INDEX idx_admin_reseller_id (reseller_id),
  INDEX idx_admin_manager_id (manager_id),
  INDEX idx_admin_referral_code (referral_code),
  CONSTRAINT fk_admin_reseller
    FOREIGN KEY (reseller_id) REFERENCES resellers(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_admin_manager
    FOREIGN KEY (manager_id) REFERENCES managers(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE end_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reseller_id BIGINT UNSIGNED NULL,
  manager_id BIGINT UNSIGNED NULL,
  platform ENUM('telegram', 'vk', 'max', 'web') NOT NULL,
  platform_user_id VARCHAR(100) NOT NULL,
  username VARCHAR(190) NULL,
  first_name VARCHAR(190) NULL,
  last_name VARCHAR(190) NULL,
  phone VARCHAR(50) NULL,
  email VARCHAR(190) NULL,
  referral_code_used VARCHAR(64) NULL,
  status ENUM('active', 'blocked', 'unsubscribed') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_activity_at DATETIME NULL,
  UNIQUE KEY uq_end_users_platform_account (platform, platform_user_id),
  INDEX idx_end_users_reseller_id (reseller_id),
  INDEX idx_end_users_manager_id (manager_id),
  INDEX idx_end_users_referral_code (referral_code_used),
  INDEX idx_end_users_platform (platform),
  CONSTRAINT fk_end_users_reseller
    FOREIGN KEY (reseller_id) REFERENCES resellers(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_end_users_manager
    FOREIGN KEY (manager_id) REFERENCES managers(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platform_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  end_user_id BIGINT UNSIGNED NOT NULL,
  platform ENUM('telegram', 'vk', 'max', 'web') NOT NULL,
  platform_user_id VARCHAR(100) NOT NULL,
  username VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_platform_account (platform, platform_user_id),
  INDEX idx_platform_accounts_end_user_id (end_user_id),
  INDEX idx_platform_accounts_platform (platform),
  CONSTRAINT fk_platform_accounts_user
    FOREIGN KEY (end_user_id) REFERENCES end_users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE referral_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type ENUM('reseller', 'manager') NOT NULL,
  owner_id BIGINT UNSIGNED NOT NULL,
  referral_code VARCHAR(64) NOT NULL,
  platform ENUM('telegram', 'vk', 'max', 'web') NOT NULL,
  clicks_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  registrations_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_referral_platform (referral_code, platform),
  INDEX idx_referral_code (referral_code),
  INDEX idx_referral_owner (owner_type, owner_id),
  INDEX idx_referral_platform (platform)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  short_description TEXT NULL,
  full_description MEDIUMTEXT NULL,
  composition TEXT NULL,
  usage_text TEXT NULL,
  warning_text TEXT NULL,
  contraindications TEXT NULL,
  image_path VARCHAR(255) NULL,
  document_path VARCHAR(255) NULL,
  video_url VARCHAR(255) NULL,
  price DECIMAL(10,2) NULL,
  purchase_url VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_products_category_id (category_id),
  CONSTRAINT fk_products_category
    FOREIGN KEY (category_id) REFERENCES product_categories(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tags (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  description TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_tags (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  tag_id BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_product_tag (product_id, tag_id),
  INDEX idx_product_tags_product_id (product_id),
  INDEX idx_product_tags_tag_id (tag_id),
  CONSTRAINT fk_product_tags_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_product_tags_tag
    FOREIGN KEY (tag_id) REFERENCES tags(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  category_id BIGINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tests_category_id (category_id),
  CONSTRAINT fk_tests_category
    FOREIGN KEY (category_id) REFERENCES product_categories(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE test_questions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_id BIGINT UNSIGNED NOT NULL,
  question_text TEXT NOT NULL,
  question_type ENUM('single_choice', 'multiple_choice', 'scale', 'text') NOT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_test_questions_test_id (test_id),
  CONSTRAINT fk_test_questions_test
    FOREIGN KEY (test_id) REFERENCES tests(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE test_answers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id BIGINT UNSIGNED NOT NULL,
  answer_text TEXT NOT NULL,
  score INT NOT NULL DEFAULT 0,
  tag_id BIGINT UNSIGNED NULL,
  category_id BIGINT UNSIGNED NULL,
  product_id BIGINT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 100,
  INDEX idx_test_answers_question_id (question_id),
  INDEX idx_test_answers_tag_id (tag_id),
  INDEX idx_test_answers_category_id (category_id),
  INDEX idx_test_answers_product_id (product_id),
  CONSTRAINT fk_test_answers_question
    FOREIGN KEY (question_id) REFERENCES test_questions(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_test_answers_tag
    FOREIGN KEY (tag_id) REFERENCES tags(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_test_answers_category
    FOREIGN KEY (category_id) REFERENCES product_categories(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_test_answers_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE test_results (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  min_score INT NOT NULL DEFAULT 0,
  max_score INT NOT NULL DEFAULT 0,
  summary_text TEXT NULL,
  advice_text TEXT NULL,
  product_id BIGINT UNSIGNED NULL,
  category_id BIGINT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_test_results_test_id (test_id),
  INDEX idx_test_results_score (test_id, min_score, max_score),
  CONSTRAINT fk_test_results_test
    FOREIGN KEY (test_id) REFERENCES tests(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_test_results_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_test_results_category
    FOREIGN KEY (category_id) REFERENCES product_categories(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_test_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  end_user_id BIGINT UNSIGNED NOT NULL,
  test_id BIGINT UNSIGNED NOT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  total_score INT NOT NULL DEFAULT 0,
  result_summary TEXT NULL,
  INDEX idx_user_test_sessions_end_user_id (end_user_id),
  INDEX idx_user_test_sessions_test_id (test_id),
  CONSTRAINT fk_user_test_sessions_user
    FOREIGN KEY (end_user_id) REFERENCES end_users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_user_test_sessions_test
    FOREIGN KEY (test_id) REFERENCES tests(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_test_answers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  question_id BIGINT UNSIGNED NOT NULL,
  answer_id BIGINT UNSIGNED NULL,
  text_answer TEXT NULL,
  score INT NOT NULL DEFAULT 0,
  INDEX idx_user_test_answers_session_id (session_id),
  INDEX idx_user_test_answers_question_id (question_id),
  INDEX idx_user_test_answers_answer_id (answer_id),
  CONSTRAINT fk_user_test_answers_session
    FOREIGN KEY (session_id) REFERENCES user_test_sessions(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_user_test_answers_question
    FOREIGN KEY (question_id) REFERENCES test_questions(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_user_test_answers_answer
    FOREIGN KEY (answer_id) REFERENCES test_answers(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recommendations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  end_user_id BIGINT UNSIGNED NOT NULL,
  test_session_id BIGINT UNSIGNED NULL,
  product_id BIGINT UNSIGNED NULL,
  category_id BIGINT UNSIGNED NULL,
  tag_id BIGINT UNSIGNED NULL,
  reason_text TEXT NULL,
  score INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_recommendations_end_user_id (end_user_id),
  INDEX idx_recommendations_session_id (test_session_id),
  INDEX idx_recommendations_product_id (product_id),
  INDEX idx_recommendations_category_id (category_id),
  INDEX idx_recommendations_tag_id (tag_id),
  CONSTRAINT fk_recommendations_user
    FOREIGN KEY (end_user_id) REFERENCES end_users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_recommendations_session
    FOREIGN KEY (test_session_id) REFERENCES user_test_sessions(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_recommendations_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_recommendations_category
    FOREIGN KEY (category_id) REFERENCES product_categories(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_recommendations_tag
    FOREIGN KEY (tag_id) REFERENCES tags(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE content_posts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  content_type ENUM('article', 'image', 'pdf', 'video', 'link') NOT NULL DEFAULT 'article',
  title VARCHAR(190) NOT NULL,
  short_text TEXT NULL,
  full_text MEDIUMTEXT NULL,
  image_path VARCHAR(255) NULL,
  attachment_path VARCHAR(255) NULL,
  video_url VARCHAR(255) NULL,
  button_text VARCHAR(100) NULL,
  button_url VARCHAR(255) NULL,
  category_id BIGINT UNSIGNED NULL,
  status ENUM('draft', 'published', 'hidden') NOT NULL DEFAULT 'draft',
  publish_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_content_category_id (category_id),
  INDEX idx_content_created_by (created_by),
  CONSTRAINT fk_content_category
    FOREIGN KEY (category_id) REFERENCES product_categories(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_content_admin
    FOREIGN KEY (created_by) REFERENCES admin_users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leads (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  end_user_id BIGINT UNSIGNED NOT NULL,
  manager_id BIGINT UNSIGNED NULL,
  reseller_id BIGINT UNSIGNED NULL,
  product_id BIGINT UNSIGNED NULL,
  source_platform ENUM('telegram', 'vk', 'max', 'web') NOT NULL,
  message TEXT NULL,
  status ENUM('new', 'contacted', 'interested', 'closed', 'lost') NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_leads_end_user_id (end_user_id),
  INDEX idx_leads_manager_id (manager_id),
  INDEX idx_leads_reseller_id (reseller_id),
  INDEX idx_leads_product_id (product_id),
  INDEX idx_leads_status (status),
  INDEX idx_leads_source_platform (source_platform),
  CONSTRAINT fk_leads_user
    FOREIGN KEY (end_user_id) REFERENCES end_users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_leads_manager
    FOREIGN KEY (manager_id) REFERENCES managers(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_leads_reseller
    FOREIGN KEY (reseller_id) REFERENCES resellers(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_leads_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lead_responses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id BIGINT UNSIGNED NOT NULL,
  admin_user_id BIGINT UNSIGNED NULL,
  content_post_id BIGINT UNSIGNED NULL,
  test_id BIGINT UNSIGNED NULL,
  platform ENUM('telegram', 'vk', 'max', 'web') NOT NULL,
  message_text TEXT NULL,
  attachment_path TEXT NULL,
  external_url VARCHAR(255) NULL,
  status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
  error_message TEXT NULL,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lead_responses_lead_id (lead_id),
  INDEX idx_lead_responses_admin_user_id (admin_user_id),
  INDEX idx_lead_responses_status (status),
  CONSTRAINT fk_lead_responses_lead
    FOREIGN KEY (lead_id) REFERENCES leads(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_lead_responses_admin
    FOREIGN KEY (admin_user_id) REFERENCES admin_users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_lead_responses_content
    FOREIGN KEY (content_post_id) REFERENCES content_posts(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_lead_responses_test
    FOREIGN KEY (test_id) REFERENCES tests(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE broadcasts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  message_text TEXT NOT NULL,
  image_path VARCHAR(255) NULL,
  button_text VARCHAR(100) NULL,
  button_url VARCHAR(255) NULL,
  target_type ENUM('all', 'reseller', 'manager', 'segment') NOT NULL DEFAULT 'all',
  target_reseller_id BIGINT UNSIGNED NULL,
  target_manager_id BIGINT UNSIGNED NULL,
  platform ENUM('all', 'telegram', 'vk', 'max') NOT NULL DEFAULT 'all',
  schedule_type ENUM('once', 'daily', 'weekly', 'monthly') NOT NULL DEFAULT 'once',
  scheduled_at DATETIME NULL,
  status ENUM('draft', 'scheduled', 'sent', 'cancelled') NOT NULL DEFAULT 'draft',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_broadcasts_target_reseller_id (target_reseller_id),
  INDEX idx_broadcasts_target_manager_id (target_manager_id),
  INDEX idx_broadcasts_platform (platform),
  INDEX idx_broadcasts_status (status),
  CONSTRAINT fk_broadcasts_reseller
    FOREIGN KEY (target_reseller_id) REFERENCES resellers(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_broadcasts_manager
    FOREIGN KEY (target_manager_id) REFERENCES managers(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_broadcasts_admin
    FOREIGN KEY (created_by) REFERENCES admin_users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE broadcast_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  broadcast_id BIGINT UNSIGNED NOT NULL,
  end_user_id BIGINT UNSIGNED NOT NULL,
  platform ENUM('telegram', 'vk', 'max', 'web') NOT NULL,
  status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
  error_message TEXT NULL,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_broadcast_logs_broadcast_id (broadcast_id),
  INDEX idx_broadcast_logs_end_user_id (end_user_id),
  INDEX idx_broadcast_logs_platform (platform),
  CONSTRAINT fk_broadcast_logs_broadcast
    FOREIGN KEY (broadcast_id) REFERENCES broadcasts(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_broadcast_logs_user
    FOREIGN KEY (end_user_id) REFERENCES end_users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_type VARCHAR(50) NULL,
  actor_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(100) NULL,
  entity_id BIGINT UNSIGNED NULL,
  details JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activity_actor (actor_type, actor_id),
  INDEX idx_activity_action (action),
  INDEX idx_activity_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(190) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  description TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
