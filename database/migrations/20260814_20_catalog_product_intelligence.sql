SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE products
  ADD COLUMN IF NOT EXISTS catalog_sku VARCHAR(40) NULL AFTER source_product_id,
  ADD COLUMN IF NOT EXISTS catalog_source VARCHAR(190) NULL AFTER catalog_sku,
  ADD COLUMN IF NOT EXISTS catalog_page SMALLINT UNSIGNED NULL AFTER catalog_source,
  ADD COLUMN IF NOT EXISTS product_kind ENUM('supplement','food','cosmetic','fragrance','personal_care','other') NOT NULL DEFAULT 'other' AFTER catalog_page,
  ADD COLUMN IF NOT EXISTS safety_review_status ENUM('not_required','catalog_only','verified') NOT NULL DEFAULT 'catalog_only' AFTER product_kind,
  ADD COLUMN IF NOT EXISTS image_review_status ENUM('missing','candidate','approved','rejected') NOT NULL DEFAULT 'missing' AFTER safety_review_status,
  ADD COLUMN IF NOT EXISTS recommendation_notice TEXT NULL AFTER image_review_status,
  ADD INDEX IF NOT EXISTS idx_products_catalog_sku (catalog_sku),
  ADD INDEX IF NOT EXISTS idx_products_catalog_source (catalog_source, catalog_page),
  ADD INDEX IF NOT EXISTS idx_products_kind_review (product_kind, safety_review_status, ai_enabled, content_status);

CREATE TABLE IF NOT EXISTS product_variants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  sku VARCHAR(40) NOT NULL,
  title VARCHAR(190) NULL,
  volume_text VARCHAR(100) NULL,
  price DECIMAL(10,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'RUB',
  image_path VARCHAR(255) NULL,
  is_sample TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_product_variants_product_sku (product_id, sku),
  INDEX idx_product_variants_product (product_id, is_active, sort_order),
  CONSTRAINT fk_product_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_media (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NULL,
  media_type ENUM('product_packshot','document','video') NOT NULL DEFAULT 'product_packshot',
  file_path VARCHAR(255) NOT NULL,
  source_page SMALLINT UNSIGNED NULL,
  review_status ENUM('candidate','approved','rejected') NOT NULL DEFAULT 'candidate',
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_product_media_path (file_path),
  INDEX idx_product_media_product (product_id, review_status, is_primary),
  CONSTRAINT fk_product_media_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_product_media_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recommendation_signals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  description TEXT NULL,
  keywords_json JSON NULL,
  safety_level ENUM('general','caution','specialist') NOT NULL DEFAULT 'general',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_recommendation_signals_slug (slug),
  INDEX idx_recommendation_signals_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_signal_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  signal_id BIGINT UNSIGNED NOT NULL,
  match_type ENUM('supports','exclude') NOT NULL DEFAULT 'supports',
  weight SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  rationale TEXT NULL,
  is_approved TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_product_signal_link (product_id, signal_id, match_type),
  INDEX idx_product_signal_lookup (signal_id, match_type, is_approved, weight),
  CONSTRAINT fk_product_signal_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_product_signal_signal FOREIGN KEY (signal_id) REFERENCES recommendation_signals(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_result_signal_links (
  test_result_id BIGINT UNSIGNED NOT NULL,
  signal_id BIGINT UNSIGNED NOT NULL,
  weight SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (test_result_id, signal_id),
  CONSTRAINT fk_test_result_signal_result FOREIGN KEY (test_result_id) REFERENCES test_results(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_test_result_signal_signal FOREIGN KEY (signal_id) REFERENCES recommendation_signals(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scale_result_signal_links (
  scale_result_id BIGINT UNSIGNED NOT NULL,
  signal_id BIGINT UNSIGNED NOT NULL,
  weight SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (scale_result_id, signal_id),
  CONSTRAINT fk_scale_result_signal_result FOREIGN KEY (scale_result_id) REFERENCES test_scale_results(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_scale_result_signal_signal FOREIGN KEY (signal_id) REFERENCES recommendation_signals(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE leads
  ADD COLUMN IF NOT EXISTS product_variant_id BIGINT UNSIGNED NULL AFTER product_id,
  ADD COLUMN IF NOT EXISTS recommendation_id BIGINT UNSIGNED NULL AFTER product_variant_id,
  ADD COLUMN IF NOT EXISTS recommendation_context_json JSON NULL AFTER recommendation_id,
  ADD INDEX IF NOT EXISTS idx_leads_product_variant (product_variant_id),
  ADD INDEX IF NOT EXISTS idx_leads_recommendation (recommendation_id);

SET @add_lead_variant_fk = IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'leads' AND CONSTRAINT_NAME = 'fk_leads_product_variant') = 0,
  'ALTER TABLE leads ADD CONSTRAINT fk_leads_product_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE add_lead_variant_fk_stmt FROM @add_lead_variant_fk;
EXECUTE add_lead_variant_fk_stmt;
DEALLOCATE PREPARE add_lead_variant_fk_stmt;

SET @add_lead_recommendation_fk = IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'leads' AND CONSTRAINT_NAME = 'fk_leads_recommendation') = 0,
  'ALTER TABLE leads ADD CONSTRAINT fk_leads_recommendation FOREIGN KEY (recommendation_id) REFERENCES recommendations(id) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE add_lead_recommendation_fk_stmt FROM @add_lead_recommendation_fk;
EXECUTE add_lead_recommendation_fk_stmt;
DEALLOCATE PREPARE add_lead_recommendation_fk_stmt;

INSERT INTO product_categories (title, slug, description, sort_order, is_active) VALUES
  ('Здоровье и питание', 'health-nutrition', 'Нутрицевтики, витамины, минералы и функциональное питание.', 10, 1),
  ('Спортивное питание', 'sport-nutrition', 'Продукты для тренировок, выносливости и восстановления.', 20, 1),
  ('Уход за лицом', 'face-care', 'Очищение, основной и специальный уход за кожей лица.', 30, 1),
  ('Парфюмерия', 'fragrance', 'Парфюмерная вода, ароматы и наборы миниатюр.', 40, 1),
  ('Уход за волосами', 'hair-care', 'Средства для волос и кожи головы.', 50, 1),
  ('Уход за телом', 'body-care', 'Средства для тела, рук и ног.', 60, 1),
  ('Гигиена и полость рта', 'personal-hygiene', 'Интимный уход, дезодоранты и средства для полости рта.', 70, 1)
ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), sort_order = VALUES(sort_order), is_active = 1;

INSERT INTO recommendation_signals (title, slug, description, keywords_json, safety_level, sort_order) VALUES
  ('Энергия и усталость', 'energy-fatigue', 'Поддержка энергии, тонуса и восстановления при нагрузках.', '["энергия","усталость","тонус","работоспособность"]', 'general', 10),
  ('Стресс и сон', 'stress-sleep', 'Эмоциональное равновесие, расслабление и качество сна.', '["стресс","сон","тревожность","напряжение","расслабление"]', 'caution', 20),
  ('Иммунная поддержка', 'immunity', 'Сезонная и общая поддержка защитных функций организма.', '["иммунитет","простуда","сезонная нагрузка","защитные силы"]', 'caution', 30),
  ('Пищеварение и микрофлора', 'digestion-microbiome', 'Комфорт пищеварения, микробиом и регулярность.', '["пищеварение","кишечник","микрофлора","микробиом","желудок"]', 'caution', 40),
  ('Контроль веса и обмен веществ', 'weight-metabolism', 'Контроль веса, аппетита и метаболическая поддержка.', '["вес","метаболизм","аппетит","сахар","углеводный"]', 'specialist', 50),
  ('Сердце и сосуды', 'heart-vessels', 'Поддержка сердца, сосудов и кровообращения.', '["сердце","сосуды","кровообращение","холестерин","омега-3"]', 'specialist', 60),
  ('Мозг, концентрация и зрение', 'brain-vision', 'Когнитивная нагрузка, концентрация и поддержка зрения.', '["мозг","память","концентрация","зрение","лютеин"]', 'caution', 70),
  ('Кости и суставы', 'bones-joints', 'Поддержка костей, суставов, связок и подвижности.', '["кости","суставы","связки","кальций","хондроитин"]', 'caution', 80),
  ('Женское здоровье', 'women-health', 'Потребности женского организма и специальные периоды.', '["женское здоровье","цикл","менопауза","беременность","кормление"]', 'specialist', 90),
  ('Кожа, волосы и ногти', 'skin-hair-nails', 'Нутритивная и наружная поддержка кожи, волос и ногтей.', '["кожа","волосы","ногти","коллаген","красота"]', 'general', 100),
  ('Детские продукты', 'children', 'Продукты и уход, предназначенные для детей.', '["дети","детский","ребенок"]', 'specialist', 110),
  ('Спорт и восстановление', 'sport-recovery', 'Тренировки, выносливость, белок и восстановление.', '["спорт","тренировка","выносливость","протеин","восстановление"]', 'caution', 120),
  ('Состояние кожи лица', 'face-skin', 'Уход по типу кожи и косметической потребности.', '["сухость кожи","чувствительная кожа","акне","морщины","тон кожи"]', 'general', 130),
  ('Волосы и кожа головы', 'hair-scalp', 'Очищение, рост, восстановление волос и кожи головы.', '["кожа головы","рост волос","перхоть","ломкость волос"]', 'general', 140),
  ('Предпочтения по ароматам', 'fragrance-preferences', 'Подбор аромата по семейству, нотам и настроению.', '["аромат","парфюм","ноты","цветочный","древесный","цитрусовый"]', 'general', 150)
ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), keywords_json = VALUES(keywords_json), safety_level = VALUES(safety_level), is_active = 1;
