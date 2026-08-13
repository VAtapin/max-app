SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS schema_migrations;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS social_callback_events;
DROP TABLE IF EXISTS leader_subscriptions;
DROP TABLE IF EXISTS consultant_notifications;
DROP TABLE IF EXISTS automation_logs;
DROP TABLE IF EXISTS user_notifications;
DROP TABLE IF EXISTS client_stage_history;
DROP TABLE IF EXISTS user_consents;
DROP TABLE IF EXISTS legal_documents;
DROP TABLE IF EXISTS broadcast_logs;
DROP TABLE IF EXISTS broadcasts;
DROP TABLE IF EXISTS lead_responses;
DROP TABLE IF EXISTS leads;
DROP TABLE IF EXISTS profile_materials;
DROP TABLE IF EXISTS content_posts;
DROP TABLE IF EXISTS recommendations;
DROP TABLE IF EXISTS user_test_scale_scores;
DROP TABLE IF EXISTS user_test_answers;
DROP TABLE IF EXISTS user_test_sessions;
DROP TABLE IF EXISTS test_scale_results;
DROP TABLE IF EXISTS test_answer_scale_scores;
DROP TABLE IF EXISTS test_scales;
DROP TABLE IF EXISTS test_results;
DROP TABLE IF EXISTS test_answers;
DROP TABLE IF EXISTS test_questions;
DROP TABLE IF EXISTS profile_tests;
DROP TABLE IF EXISTS tests;
DROP TABLE IF EXISTS product_tags;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS profile_products;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS product_categories;
DROP TABLE IF EXISTS admin_web_accounts;
DROP TABLE IF EXISTS platform_accounts;
DROP TABLE IF EXISTS referral_links;
DROP TABLE IF EXISTS end_users;
DROP TABLE IF EXISTS admin_users;
DROP TABLE IF EXISTS profile_reviews;
DROP TABLE IF EXISTS profile_cashback_cards;
DROP TABLE IF EXISTS profile_blocks;
DROP TABLE IF EXISTS consultant_profiles;
DROP TABLE IF EXISTS site_templates;
DROP TABLE IF EXISTS default_platform_managers;
DROP TABLE IF EXISTS managers;
DROP TABLE IF EXISTS resellers;
DROP TABLE IF EXISTS help_faq_sections;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS subscription_plans;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE subscription_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type ENUM('superadmin','reseller') NOT NULL DEFAULT 'superadmin',
  owner_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  slug VARCHAR(100) NOT NULL,
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
  UNIQUE KEY uq_subscription_plans_owner_slug (owner_type, owner_id, slug),
  INDEX idx_subscription_plans_owner (owner_type, owner_id),
  INDEX idx_subscription_plans_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE resellers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_end_user_id BIGINT UNSIGNED NULL,
  parent_reseller_id BIGINT UNSIGNED NULL,
  subscription_plan_id BIGINT UNSIGNED NULL,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(50) NULL,
  billing_name VARCHAR(190) NULL,
  billing_inn VARCHAR(20) NULL,
  billing_email VARCHAR(190) NULL,
  billing_comment VARCHAR(500) NULL,
  referral_code VARCHAR(64) NOT NULL UNIQUE,
  manager_limit INT UNSIGNED NULL,
  direct_leader_limit INT UNSIGNED NULL,
  branch_leader_limit INT UNSIGNED NULL,
  direct_manager_limit INT UNSIGNED NULL,
  branch_manager_limit INT UNSIGNED NULL,
  per_child_manager_limit INT UNSIGNED NULL,
  price_per_leader DECIMAL(10,2) NULL,
  price_per_consultant DECIMAL(10,2) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_resellers_referral_code (referral_code),
  INDEX idx_resellers_parent_reseller_id (parent_reseller_id),
  INDEX idx_resellers_subscription_plan_id (subscription_plan_id),
  INDEX idx_resellers_source_end_user_id (source_end_user_id),
  CONSTRAINT fk_resellers_parent
    FOREIGN KEY (parent_reseller_id) REFERENCES resellers(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE managers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_end_user_id BIGINT UNSIGNED NULL,
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
  INDEX idx_managers_source_end_user_id (source_end_user_id),
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
  two_factor_required TINYINT(1) NOT NULL DEFAULT 0,
  two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
  two_factor_secret VARCHAR(64) NULL,
  two_factor_confirmed_at DATETIME NULL,
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
  platform ENUM('telegram', 'VK', 'OK', 'MAX', 'web') NOT NULL,
  platform_user_id VARCHAR(100) NOT NULL,
  username VARCHAR(190) NULL,
  first_name VARCHAR(190) NULL,
  last_name VARCHAR(190) NULL,
  gender ENUM('female', 'male', 'prefer_not_to_say') NULL,
  birth_date DATE NULL,
  age_years TINYINT UNSIGNED NULL,
  city VARCHAR(190) NULL,
  timezone VARCHAR(100) NOT NULL DEFAULT 'Europe/Moscow',
  phone VARCHAR(50) NULL,
  email VARCHAR(190) NULL,
  referral_code_used VARCHAR(64) NULL,
  client_stage ENUM('new', 'profile_completed', 'test_started', 'test_completed', 'consultation_requested', 'in_progress', 'client', 'partner', 'inactive', 'unsubscribed') NOT NULL DEFAULT 'new',
  stage_updated_at DATETIME NULL,
  onboarding_completed_at DATETIME NULL,
  referral_registered_at DATETIME NULL,
  notifications_enabled TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('active', 'blocked', 'unsubscribed') NOT NULL DEFAULT 'active',
  merged_into_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_activity_at DATETIME NULL,
  UNIQUE KEY uq_end_users_platform_account (platform, platform_user_id),
  INDEX idx_end_users_reseller_id (reseller_id),
  INDEX idx_end_users_manager_id (manager_id),
  INDEX idx_end_users_merged_into_user_id (merged_into_user_id),
  INDEX idx_end_users_referral_code (referral_code_used),
  INDEX idx_end_users_platform (platform),
  INDEX idx_end_users_stage (client_stage),
  INDEX idx_end_users_activity (last_activity_at),
  INDEX idx_end_users_referral_registered_at (referral_registered_at),
  CONSTRAINT fk_end_users_reseller
    FOREIGN KEY (reseller_id) REFERENCES resellers(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_end_users_manager
    FOREIGN KEY (manager_id) REFERENCES managers(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_end_users_merged_into
    FOREIGN KEY (merged_into_user_id) REFERENCES end_users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(100) NOT NULL UNIQUE,
  title VARCHAR(190) NOT NULL,
  description VARCHAR(500) NULL,
  owner_type ENUM('reseller','manager') NULL,
  owner_id BIGINT UNSIGNED NULL,
  source_template_id BIGINT UNSIGNED NULL,
  profile_json JSON NOT NULL,
  blocks_json JSON NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_site_templates_active (is_active, sort_order),
  INDEX idx_site_templates_owner (owner_type, owner_id),
  INDEX idx_site_templates_source (source_template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE consultant_profiles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type ENUM('reseller', 'manager') NOT NULL,
  owner_id BIGINT UNSIGNED NOT NULL,
  source_profile_id BIGINT UNSIGNED NULL,
  template_id BIGINT UNSIGNED NULL,
  template_applied_at DATETIME NULL,
  template_customized_at DATETIME NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  display_name VARCHAR(190) NOT NULL,
  title VARCHAR(190) NULL,
  subtitle VARCHAR(255) NULL,
  short_description TEXT NULL,
  welcome_text TEXT NULL,
  welcome_image_path VARCHAR(255) NULL,
  welcome_video_url VARCHAR(255) NULL,
  cashback_title VARCHAR(190) NULL,
  cashback_text MEDIUMTEXT NULL,
  cashback_image_path VARCHAR(255) NULL,
  cashback_url VARCHAR(500) NULL,
  cooperation_title VARCHAR(190) NULL,
  cooperation_text MEDIUMTEXT NULL,
  cooperation_image_path VARCHAR(255) NULL,
  cooperation_video_url VARCHAR(255) NULL,
  bio MEDIUMTEXT NULL,
  specialization TEXT NULL,
  experience_text TEXT NULL,
  achievements_text TEXT NULL,
  certificates_text TEXT NULL,
  photo_path VARCHAR(255) NULL,
  banner_path VARCHAR(255) NULL,
  video_url VARCHAR(255) NULL,
  phone VARCHAR(50) NULL,
  email VARCHAR(190) NULL,
  telegram_url VARCHAR(255) NULL,
  whatsapp_url VARCHAR(255) NULL,
  vk_url VARCHAR(255) NULL,
  ok_url VARCHAR(255) NULL,
  theme_key VARCHAR(50) NOT NULL DEFAULT 'classic',
  is_public TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_consultant_profile_owner (owner_type, owner_id),
  INDEX idx_consultant_profiles_slug (slug),
  INDEX idx_consultant_profiles_owner (owner_type, owner_id),
  INDEX idx_consultant_profiles_source_profile_id (source_profile_id),
  INDEX idx_consultant_profiles_template_id (template_id),
  CONSTRAINT fk_consultant_profiles_source_profile
    FOREIGN KEY (source_profile_id) REFERENCES consultant_profiles(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_consultant_profiles_template
    FOREIGN KEY (template_id) REFERENCES site_templates(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profile_blocks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  profile_id BIGINT UNSIGNED NOT NULL,
  block_type ENUM('hero', 'video', 'about', 'tests', 'products', 'materials', 'reviews', 'contacts', 'cashback', 'cooperation') NOT NULL,
  title VARCHAR(190) NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  settings_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_profile_block_type (profile_id, block_type),
  INDEX idx_profile_blocks_profile_id (profile_id),
  CONSTRAINT fk_profile_blocks_profile
    FOREIGN KEY (profile_id) REFERENCES consultant_profiles(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profile_reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  profile_id BIGINT UNSIGNED NOT NULL,
  client_name VARCHAR(190) NOT NULL,
  client_photo_path VARCHAR(255) NULL,
  review_text TEXT NOT NULL,
  rating TINYINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_profile_reviews_profile_id (profile_id),
  CONSTRAINT fk_profile_reviews_profile
    FOREIGN KEY (profile_id) REFERENCES consultant_profiles(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profile_cashback_cards (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  profile_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(190) NULL,
  description MEDIUMTEXT NULL,
  image_path VARCHAR(255) NULL,
  card_url VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_profile_cashback_cards_profile (profile_id, sort_order, id),
  CONSTRAINT fk_profile_cashback_cards_profile
    FOREIGN KEY (profile_id) REFERENCES consultant_profiles(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platform_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  end_user_id BIGINT UNSIGNED NOT NULL,
  platform ENUM('telegram', 'VK', 'OK', 'MAX', 'web') NOT NULL,
  platform_user_id VARCHAR(100) NOT NULL,
  username VARCHAR(190) NULL,
  first_name VARCHAR(190) NULL,
  last_name VARCHAR(190) NULL,
  display_name VARCHAR(255) NULL,
  messages_allowed TINYINT(1) NULL,
  messages_allowed_at DATETIME NULL,
  messages_denied_at DATETIME NULL,
  last_inbound_message_at DATETIME NULL,
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
  platform ENUM('telegram', 'VK', 'OK', 'MAX', 'web') NOT NULL,
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
  owner_type ENUM('superadmin', 'reseller', 'manager') NULL,
  owner_id BIGINT UNSIGNED NULL,
  source_category_id BIGINT UNSIGNED NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_product_categories_source_category_id (source_category_id),
  UNIQUE KEY uq_product_categories_owner_source_clone (owner_type, owner_id, source_category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_web_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  web_user_id VARCHAR(100) NOT NULL,
  last_seen_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admin_web_accounts_web_user_id (web_user_id),
  INDEX idx_admin_web_accounts_admin_user_id (admin_user_id),
  INDEX idx_admin_web_accounts_active (revoked_at, last_seen_at),
  CONSTRAINT fk_admin_web_accounts_admin_user
    FOREIGN KEY (admin_user_id) REFERENCES admin_users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NULL,
  owner_type ENUM('superadmin', 'reseller', 'manager') NULL,
  owner_id BIGINT UNSIGNED NULL,
  source_product_id BIGINT UNSIGNED NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
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
  INDEX idx_products_source_product_id (source_product_id),
  UNIQUE KEY uq_products_owner_source_clone (owner_type, owner_id, source_product_id),
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

CREATE TABLE profile_products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  profile_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_profile_product (profile_id, product_id),
  INDEX idx_profile_products_profile_id (profile_id),
  INDEX idx_profile_products_product_id (product_id),
  CONSTRAINT fk_profile_products_profile
    FOREIGN KEY (profile_id) REFERENCES consultant_profiles(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_profile_products_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  category_id BIGINT UNSIGNED NULL,
  scoring_type ENUM('single', 'multiscale') NOT NULL DEFAULT 'single',
  emoji VARCHAR(16) NULL,
  intro_text TEXT NULL,
  intro_image_path VARCHAR(255) NULL,
  intro_video_url VARCHAR(255) NULL,
  owner_type ENUM('superadmin', 'reseller', 'manager') NULL,
  owner_id BIGINT UNSIGNED NULL,
  source_test_id BIGINT UNSIGNED NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tests_category_id (category_id),
  INDEX idx_tests_source_test_id (source_test_id),
  UNIQUE KEY uq_tests_owner_source_clone (owner_type, owner_id, source_test_id),
  CONSTRAINT fk_tests_category
    FOREIGN KEY (category_id) REFERENCES product_categories(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profile_tests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  profile_id BIGINT UNSIGNED NOT NULL,
  test_id BIGINT UNSIGNED NOT NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_profile_test (profile_id, test_id),
  INDEX idx_profile_tests_profile_id (profile_id),
  INDEX idx_profile_tests_test_id (test_id),
  CONSTRAINT fk_profile_tests_profile
    FOREIGN KEY (profile_id) REFERENCES consultant_profiles(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_profile_tests_test
    FOREIGN KEY (test_id) REFERENCES tests(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE test_questions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_id BIGINT UNSIGNED NOT NULL,
  question_text TEXT NOT NULL,
  question_type ENUM('single_choice', 'multiple_choice', 'scale', 'text') NOT NULL,
  gender_scope ENUM('all', 'female', 'male') NOT NULL DEFAULT 'all',
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_test_questions_test_id (test_id),
  INDEX idx_test_questions_gender_scope (gender_scope),
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

CREATE TABLE test_scales (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_id BIGINT UNSIGNED NOT NULL,
  slug VARCHAR(100) NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_test_scale_slug (test_id, slug),
  INDEX idx_test_scales_test_id (test_id),
  CONSTRAINT fk_test_scales_test
    FOREIGN KEY (test_id) REFERENCES tests(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE test_answer_scale_scores (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  answer_id BIGINT UNSIGNED NOT NULL,
  scale_id BIGINT UNSIGNED NOT NULL,
  score INT NOT NULL DEFAULT 1,
  UNIQUE KEY uq_answer_scale_score (answer_id, scale_id),
  INDEX idx_test_answer_scale_answer_id (answer_id),
  INDEX idx_test_answer_scale_scale_id (scale_id),
  CONSTRAINT fk_test_answer_scale_answer
    FOREIGN KEY (answer_id) REFERENCES test_answers(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_test_answer_scale_scale
    FOREIGN KEY (scale_id) REFERENCES test_scales(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE test_scale_results (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scale_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  min_score INT NOT NULL DEFAULT 0,
  max_score INT NOT NULL DEFAULT 0,
  severity ENUM('excellent', 'good', 'risk', 'critical') NOT NULL DEFAULT 'good',
  summary_text TEXT NULL,
  advice_text TEXT NULL,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_test_scale_results_scale_id (scale_id),
  INDEX idx_test_scale_results_score (scale_id, min_score, max_score),
  CONSTRAINT fk_test_scale_results_scale
    FOREIGN KEY (scale_id) REFERENCES test_scales(id)
    ON DELETE CASCADE ON UPDATE CASCADE
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
  last_answered_at DATETIME NULL,
  completed_at DATETIME NULL,
  total_score INT NOT NULL DEFAULT 0,
  result_summary TEXT NULL,
  is_preview TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_user_test_sessions_end_user_id (end_user_id),
  INDEX idx_user_test_sessions_test_id (test_id),
  INDEX idx_user_test_sessions_reminders (completed_at, last_answered_at),
  INDEX idx_user_test_sessions_preview (is_preview, completed_at),
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

CREATE TABLE user_test_scale_scores (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  scale_id BIGINT UNSIGNED NOT NULL,
  score INT NOT NULL DEFAULT 0,
  result_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_test_scale_score (session_id, scale_id),
  INDEX idx_user_test_scale_session_id (session_id),
  INDEX idx_user_test_scale_scale_id (scale_id),
  INDEX idx_user_test_scale_result_id (result_id),
  CONSTRAINT fk_user_test_scale_session
    FOREIGN KEY (session_id) REFERENCES user_test_sessions(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_user_test_scale_scale
    FOREIGN KEY (scale_id) REFERENCES test_scales(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_user_test_scale_result
    FOREIGN KEY (result_id) REFERENCES test_scale_results(id)
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
  section_type ENUM('general', 'story', 'result', 'promotion', 'giveaway', 'program', 'marathon') NOT NULL DEFAULT 'general',
  title VARCHAR(190) NOT NULL,
  short_text TEXT NULL,
  full_text MEDIUMTEXT NULL,
  image_path VARCHAR(255) NULL,
  attachment_path VARCHAR(255) NULL,
  video_url VARCHAR(255) NULL,
  button_text VARCHAR(100) NULL,
  button_url VARCHAR(255) NULL,
  category_id BIGINT UNSIGNED NULL,
  owner_type ENUM('superadmin', 'reseller', 'manager') NULL,
  owner_id BIGINT UNSIGNED NULL,
  status ENUM('draft', 'published', 'hidden') NOT NULL DEFAULT 'draft',
  publish_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  source_content_post_id BIGINT UNSIGNED NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_content_category_id (category_id),
  INDEX idx_content_created_by (created_by),
  INDEX idx_content_section_type (section_type),
  INDEX idx_content_source_content_post_id (source_content_post_id),
  UNIQUE KEY uq_content_owner_source_clone (owner_type, owner_id, source_content_post_id),
  CONSTRAINT fk_content_category
    FOREIGN KEY (category_id) REFERENCES product_categories(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_content_admin
    FOREIGN KEY (created_by) REFERENCES admin_users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_content_source_post
    FOREIGN KEY (source_content_post_id) REFERENCES content_posts(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profile_materials (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  profile_id BIGINT UNSIGNED NOT NULL,
  content_post_id BIGINT UNSIGNED NOT NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_profile_material (profile_id, content_post_id),
  INDEX idx_profile_materials_profile_id (profile_id),
  INDEX idx_profile_materials_content_post_id (content_post_id),
  CONSTRAINT fk_profile_materials_profile
    FOREIGN KEY (profile_id) REFERENCES consultant_profiles(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_profile_materials_content
    FOREIGN KEY (content_post_id) REFERENCES content_posts(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leads (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  end_user_id BIGINT UNSIGNED NOT NULL,
  manager_id BIGINT UNSIGNED NULL,
  reseller_id BIGINT UNSIGNED NULL,
  product_id BIGINT UNSIGNED NULL,
  request_type VARCHAR(50) NOT NULL DEFAULT 'consultation',
  source_platform ENUM('telegram', 'VK', 'OK', 'MAX', 'web') NOT NULL,
  source_message_id VARCHAR(190) NULL,
  message TEXT NULL,
  attachments_json JSON NULL,
  status ENUM('new', 'contacted', 'interested', 'closed', 'lost') NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_leads_end_user_id (end_user_id),
  INDEX idx_leads_manager_id (manager_id),
  INDEX idx_leads_reseller_id (reseller_id),
  INDEX idx_leads_product_id (product_id),
  INDEX idx_leads_status (status),
  INDEX idx_leads_source_platform (source_platform),
  INDEX idx_leads_source_message (source_platform, source_message_id),
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
  response_source ENUM('admin', 'telegram') NOT NULL DEFAULT 'admin',
  telegram_chat_id VARCHAR(100) NULL,
  telegram_message_id BIGINT UNSIGNED NULL,
  content_post_id BIGINT UNSIGNED NULL,
  test_id BIGINT UNSIGNED NULL,
  platform ENUM('telegram', 'VK', 'OK', 'MAX', 'web') NOT NULL,
  message_text TEXT NULL,
  attachment_path TEXT NULL,
  external_url VARCHAR(255) NULL,
  status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
  error_message TEXT NULL,
  sent_at DATETIME NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_lead_response_telegram_message (telegram_chat_id, telegram_message_id),
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

CREATE TABLE messaging_integrations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type ENUM('reseller', 'manager') NOT NULL,
  owner_id BIGINT UNSIGNED NOT NULL,
  platform ENUM('VK', 'OK', 'telegram', 'MAX') NOT NULL,
  title VARCHAR(190) NOT NULL,
  external_id VARCHAR(190) NULL,
  access_token TEXT NULL,
  callback_confirmation_code VARCHAR(190) NULL,
  callback_secret VARCHAR(190) NULL,
  callback_last_event_at DATETIME NULL,
  callback_last_error TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_messaging_integrations_owner (owner_type, owner_id),
  INDEX idx_messaging_integrations_platform (platform, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE social_callback_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  platform ENUM('VK', 'OK') NOT NULL,
  external_id VARCHAR(190) NOT NULL,
  event_id VARCHAR(190) NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  payload_json MEDIUMTEXT NULL,
  processed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_social_callback_event (platform, external_id, event_id),
  INDEX idx_social_callback_events_platform (platform, external_id, created_at),
  INDEX idx_social_callback_events_type (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE broadcasts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type ENUM('superadmin', 'reseller', 'manager') NULL,
  owner_id BIGINT UNSIGNED NULL,
  source_broadcast_id BIGINT UNSIGNED NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  title VARCHAR(190) NOT NULL,
  message_text TEXT NULL,
  audience_type ENUM('clients', 'consultants') NOT NULL DEFAULT 'clients',
  image_path VARCHAR(255) NULL,
  video_path VARCHAR(255) NULL,
  button_text VARCHAR(100) NULL,
  button_url VARCHAR(255) NULL,
  target_type ENUM('all', 'reseller', 'manager', 'segment', 'own_clients', 'branch_clients', 'direct_consultants', 'branch_consultants', 'direct_leaders', 'branch_leaders', 'whole_branch') NOT NULL DEFAULT 'all',
  target_reseller_id BIGINT UNSIGNED NULL,
  target_manager_id BIGINT UNSIGNED NULL,
  segment_stage VARCHAR(50) NULL,
  segment_checkup VARCHAR(50) NULL,
  segment_activity VARCHAR(50) NULL,
  platform ENUM('all', 'telegram', 'VK', 'OK', 'MAX') NOT NULL DEFAULT 'all',
  schedule_type ENUM('once', 'daily', 'weekly', 'monthly') NOT NULL DEFAULT 'once',
  scheduled_at DATETIME NULL,
  status ENUM('draft', 'scheduled', 'sent', 'cancelled') NOT NULL DEFAULT 'draft',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_broadcasts_owner (owner_type, owner_id),
  INDEX idx_broadcasts_source_broadcast_id (source_broadcast_id),
  UNIQUE KEY uq_broadcasts_owner_source_clone (owner_type, owner_id, source_broadcast_id),
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
  end_user_id BIGINT UNSIGNED NULL,
  manager_id BIGINT UNSIGNED NULL,
  platform ENUM('telegram', 'VK', 'OK', 'MAX', 'web') NOT NULL,
  status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
  error_message TEXT NULL,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_broadcast_logs_broadcast_id (broadcast_id),
  INDEX idx_broadcast_logs_end_user_id (end_user_id),
  INDEX idx_broadcast_logs_manager_id (manager_id),
  INDEX idx_broadcast_logs_platform (platform),
  CONSTRAINT fk_broadcast_logs_broadcast
    FOREIGN KEY (broadcast_id) REFERENCES broadcasts(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_broadcast_logs_user
    FOREIGN KEY (end_user_id) REFERENCES end_users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_broadcast_logs_manager
    FOREIGN KEY (manager_id) REFERENCES managers(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE legal_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_type ENUM('privacy_policy', 'personal_data_consent', 'health_data_consent', 'marketing_consent', 'user_agreement', 'leader_offer') NOT NULL,
  title VARCHAR(255) NOT NULL,
  version VARCHAR(50) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_legal_document_version (document_type, version),
  INDEX idx_legal_document_active (document_type, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_consents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  end_user_id BIGINT UNSIGNED NOT NULL,
  document_type ENUM('personal_data_consent', 'health_data_consent', 'marketing_consent', 'user_agreement') NOT NULL,
  document_version VARCHAR(50) NOT NULL,
  platform ENUM('telegram', 'VK', 'OK', 'MAX', 'web') NOT NULL,
  granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_consents_user_type (end_user_id, document_type, revoked_at),
  CONSTRAINT fk_user_consents_user
    FOREIGN KEY (end_user_id) REFERENCES end_users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE client_stage_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  end_user_id BIGINT UNSIGNED NOT NULL,
  previous_stage VARCHAR(50) NULL,
  new_stage VARCHAR(50) NOT NULL,
  source ENUM('system', 'client', 'consultant', 'leader', 'admin') NOT NULL DEFAULT 'system',
  actor_id BIGINT UNSIGNED NULL,
  note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_stage_history_user (end_user_id, created_at),
  CONSTRAINT fk_stage_history_user
    FOREIGN KEY (end_user_id) REFERENCES end_users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  end_user_id BIGINT UNSIGNED NOT NULL,
  notification_type VARCHAR(50) NOT NULL,
  title VARCHAR(190) NOT NULL,
  message_text TEXT NOT NULL,
  image_path VARCHAR(255) NULL,
  video_path VARCHAR(255) NULL,
  action_text VARCHAR(100) NULL,
  action_url VARCHAR(500) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  INDEX idx_user_notifications_user (end_user_id, is_read, created_at),
  CONSTRAINT fk_user_notifications_user
    FOREIGN KEY (end_user_id) REFERENCES end_users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE automation_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  end_user_id BIGINT UNSIGNED NOT NULL,
  automation_type VARCHAR(50) NOT NULL,
  context_key VARCHAR(190) NOT NULL,
  platform ENUM('telegram', 'VK', 'OK', 'MAX', 'web') NOT NULL,
  status ENUM('sent', 'queued', 'skipped', 'failed') NOT NULL,
  error_message TEXT NULL,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_automation_event (end_user_id, automation_type, context_key, platform),
  INDEX idx_automation_logs_status (status, created_at),
  CONSTRAINT fk_automation_logs_user
    FOREIGN KEY (end_user_id) REFERENCES end_users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE consultant_notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  manager_id BIGINT UNSIGNED NULL,
  reseller_id BIGINT UNSIGNED NULL,
  end_user_id BIGINT UNSIGNED NOT NULL,
  lead_id BIGINT UNSIGNED NULL,
  notification_type VARCHAR(50) NOT NULL,
  source_platform ENUM('telegram', 'VK', 'OK', 'MAX', 'web') NULL,
  event_key VARCHAR(190) NOT NULL,
  title VARCHAR(190) NOT NULL,
  message_text TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  delivery_status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
  delivery_error TEXT NULL,
  telegram_chat_id VARCHAR(100) NULL,
  telegram_message_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  UNIQUE KEY uq_consultant_notification_event (manager_id, event_key),
  UNIQUE KEY uq_consultant_notification_reseller_event (reseller_id, event_key),
  UNIQUE KEY uq_consultant_notification_telegram_message (telegram_chat_id, telegram_message_id),
  INDEX idx_consultant_notifications_manager (manager_id, is_read, created_at),
  INDEX idx_consultant_notifications_reseller (reseller_id, is_read, created_at),
  INDEX idx_consultant_notifications_lead (lead_id),
  CONSTRAINT fk_consultant_notifications_manager
    FOREIGN KEY (manager_id) REFERENCES managers(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_consultant_notifications_reseller
    FOREIGN KEY (reseller_id) REFERENCES resellers(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_consultant_notifications_user
    FOREIGN KEY (end_user_id) REFERENCES end_users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_consultant_notifications_lead
    FOREIGN KEY (lead_id) REFERENCES leads(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leader_subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reseller_id BIGINT UNSIGNED NOT NULL,
  subscription_plan_id BIGINT UNSIGNED NULL,
  consultant_limit INT UNSIGNED NULL,
  leader_limit INT UNSIGNED NULL,
  price_per_consultant DECIMAL(10,2) NULL,
  price_per_leader DECIMAL(10,2) NULL,
  amount_due DECIMAL(10,2) NULL,
  leader_amount_due DECIMAL(10,2) NULL,
  billing_basis ENUM('direct','branch') NOT NULL DEFAULT 'branch',
  billing_mode ENUM('prepaid','actual') NOT NULL DEFAULT 'prepaid',
  direct_leader_limit INT UNSIGNED NULL,
  branch_leader_limit INT UNSIGNED NULL,
  direct_consultant_limit INT UNSIGNED NULL,
  branch_consultant_limit INT UNSIGNED NULL,
  per_child_consultant_limit INT UNSIGNED NULL,
  status ENUM('pending', 'active', 'expired', 'suspended') NOT NULL DEFAULT 'pending',
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  monthly_price DECIMAL(10,2) NULL,
  paid_at DATETIME NULL,
  invoice_number VARCHAR(100) NULL,
  payment_method VARCHAR(100) NULL,
  payment_note VARCHAR(500) NULL,
  activated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_leader_subscriptions_reseller (reseller_id, status, ends_at),
  INDEX idx_leader_subscriptions_plan (subscription_plan_id),
  CONSTRAINT fk_leader_subscriptions_reseller
    FOREIGN KEY (reseller_id) REFERENCES resellers(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_leader_subscriptions_admin
    FOREIGN KEY (activated_by) REFERENCES admin_users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE help_faq_sections (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  body TEXT NOT NULL,
  items_json JSON NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_help_faq_active (is_active, sort_order),
  INDEX idx_help_faq_featured (is_featured, is_active)
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

CREATE TABLE schema_migrations (
  migration VARCHAR(255) NOT NULL PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER //

CREATE TRIGGER trg_resellers_referral_code_unique_insert
BEFORE INSERT ON resellers
FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM managers WHERE referral_code = NEW.referral_code) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Referral code is already used by a manager';
  END IF;
END//

CREATE TRIGGER trg_resellers_referral_code_unique_update
BEFORE UPDATE ON resellers
FOR EACH ROW
BEGIN
  IF NOT (NEW.referral_code <=> OLD.referral_code)
     AND EXISTS (SELECT 1 FROM managers WHERE referral_code = NEW.referral_code) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Referral code is already used by a manager';
  END IF;
END//

CREATE TRIGGER trg_managers_referral_code_unique_insert
BEFORE INSERT ON managers
FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM resellers WHERE referral_code = NEW.referral_code) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Referral code is already used by a reseller';
  END IF;
END//

CREATE TRIGGER trg_managers_referral_code_unique_update
BEFORE UPDATE ON managers
FOR EACH ROW
BEGIN
  IF NOT (NEW.referral_code <=> OLD.referral_code)
     AND EXISTS (SELECT 1 FROM resellers WHERE referral_code = NEW.referral_code) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Referral code is already used by a reseller';
  END IF;
END//

DELIMITER ;
