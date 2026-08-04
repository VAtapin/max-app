ALTER TABLE resellers
  ADD COLUMN IF NOT EXISTS parent_reseller_id BIGINT UNSIGNED NULL AFTER id,
  ADD COLUMN IF NOT EXISTS direct_leader_limit INT UNSIGNED NULL AFTER manager_limit,
  ADD COLUMN IF NOT EXISTS branch_leader_limit INT UNSIGNED NULL AFTER direct_leader_limit,
  ADD COLUMN IF NOT EXISTS direct_manager_limit INT UNSIGNED NULL AFTER branch_leader_limit,
  ADD COLUMN IF NOT EXISTS branch_manager_limit INT UNSIGNED NULL AFTER direct_manager_limit,
  ADD COLUMN IF NOT EXISTS per_child_manager_limit INT UNSIGNED NULL AFTER branch_manager_limit,
  ADD COLUMN IF NOT EXISTS price_per_leader DECIMAL(10,2) NULL AFTER per_child_manager_limit,
  ADD COLUMN IF NOT EXISTS price_per_consultant DECIMAL(10,2) NULL AFTER price_per_leader,
  ADD INDEX IF NOT EXISTS idx_resellers_parent_reseller_id (parent_reseller_id);

SET @resellers_parent_fk_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'resellers'
    AND CONSTRAINT_NAME = 'fk_resellers_parent'
);
SET @resellers_parent_fk_sql := IF(
  @resellers_parent_fk_exists = 0,
  'ALTER TABLE resellers
     ADD CONSTRAINT fk_resellers_parent
     FOREIGN KEY (parent_reseller_id) REFERENCES resellers(id)
     ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE resellers_parent_fk_stmt FROM @resellers_parent_fk_sql;
EXECUTE resellers_parent_fk_stmt;
DEALLOCATE PREPARE resellers_parent_fk_stmt;

UPDATE resellers
SET direct_manager_limit = manager_limit
WHERE direct_manager_limit IS NULL
  AND manager_limit IS NOT NULL;

UPDATE resellers
SET branch_manager_limit = manager_limit
WHERE branch_manager_limit IS NULL
  AND manager_limit IS NOT NULL;

CREATE TABLE IF NOT EXISTS site_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(100) NOT NULL UNIQUE,
  title VARCHAR(190) NOT NULL,
  description VARCHAR(500) NULL,
  profile_json JSON NOT NULL,
  blocks_json JSON NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_site_templates_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_templates (slug, title, description, profile_json, blocks_json, is_active, sort_order)
VALUES
(
  'soft-expert',
  'Мягкий эксперт',
  'Спокойная экспертная страница для консультанта по здоровью и красоте.',
  JSON_OBJECT(
    'title', 'Ваш персональный консультант',
    'subtitle', '{{role_label}} по здоровью и красоте',
    'short_description', 'Помогаю разобраться с самочувствием, пройти чек-ап и подготовить понятный маршрут поддержки организма.',
    'welcome_text', 'Добро пожаловать! Здесь можно пройти чек-ап организма, получить результат и отправить его мне для персонального разбора.',
    'cashback_title', 'Кэшбэк и подарки',
    'cashback_text', 'Оформите карту клиента по персональной ссылке консультанта, чтобы получать выгодные условия, подарки и информацию об актуальных предложениях.',
    'cooperation_title', 'Возможность сотрудничества',
    'cooperation_text', 'Если вам интересна тема wellness, дополнительный доход или своя команда, напишите консультанту. Он расскажет варианты участия простым языком.',
    'bio', 'Здравствуйте! Меня зовут {{name}}. Я помогаю клиентам бережно разобраться с запросами по здоровью, красоте и энергии.',
    'specialization', 'Чек-ап организма, подбор поддерживающих программ, сопровождение клиентов, полезные материалы и акции.',
    'experience_text', 'На странице можно пройти тест, изучить материалы и отправить результат консультанту для разбора.',
    'certificates_text', 'Персональный маршрут поддержки лучше обсудить с консультантом.',
    'theme_key', 'classic'
  ),
  JSON_ARRAY(
    JSON_OBJECT('block_type', 'hero', 'title', 'Добро пожаловать', 'is_enabled', 1, 'sort_order', 10),
    JSON_OBJECT('block_type', 'cashback', 'title', 'Кэшбэк и подарки', 'is_enabled', 1, 'sort_order', 40),
    JSON_OBJECT('block_type', 'cooperation', 'title', 'Возможность сотрудничества', 'is_enabled', 1, 'sort_order', 50),
    JSON_OBJECT('block_type', 'tests', 'title', 'Чек-ап организма', 'is_enabled', 1, 'sort_order', 60),
    JSON_OBJECT('block_type', 'materials', 'title', 'Полезные материалы', 'is_enabled', 1, 'sort_order', 70),
    JSON_OBJECT('block_type', 'contacts', 'title', 'Связаться с консультантом', 'is_enabled', 1, 'sort_order', 90)
  ),
  1,
  10
),
(
  'promo-benefit',
  'Акции и выгода',
  'Страница с акцентом на кэшбэк, подарки, программы и быстрый контакт.',
  JSON_OBJECT(
    'title', 'Кэшбэк, подарки и персональный чек-ап',
    'subtitle', '{{role_label}} SWPro',
    'short_description', 'Пройдите бесплатный чек-ап, узнайте, на что стоит обратить внимание, и получите персональный разбор от консультанта.',
    'welcome_text', 'Начните с чек-апа организма. После результата можно сразу отправить заявку консультанту и обсудить подходящую программу.',
    'cashback_title', 'Карта клиента: кэшбэк и подарки',
    'cashback_text', 'Карта клиента помогает покупать выгоднее, получать подарки и узнавать о специальных предложениях. Консультант подскажет, как оформить ее по персональной ссылке.',
    'cooperation_title', 'Сотрудничество и команда',
    'cooperation_text', 'Можно пользоваться продуктами для себя, рекомендовать знакомым или развивать свою команду. Узнайте формат, который подойдет именно вам.',
    'bio', '{{name}} помогает клиентам быстро разобраться, с чего начать: чек-ап, кэшбэк, программы, акции и поддержка.',
    'specialization', 'Кэшбэк, подарки, стартовые программы, марафоны и клиентское сопровождение.',
    'experience_text', 'Собраны основные разделы, чтобы клиенту было удобно перейти от интереса к разговору с консультантом.',
    'certificates_text', 'Финальный подбор программы лучше делать после личного разговора с консультантом.',
    'theme_key', 'berry'
  ),
  JSON_ARRAY(
    JSON_OBJECT('block_type', 'hero', 'title', 'Персональный старт', 'is_enabled', 1, 'sort_order', 10),
    JSON_OBJECT('block_type', 'tests', 'title', 'Бесплатный чек-ап', 'is_enabled', 1, 'sort_order', 20),
    JSON_OBJECT('block_type', 'cashback', 'title', 'Кэшбэк и подарки', 'is_enabled', 1, 'sort_order', 30),
    JSON_OBJECT('block_type', 'materials', 'title', 'Акции и материалы', 'is_enabled', 1, 'sort_order', 40),
    JSON_OBJECT('block_type', 'cooperation', 'title', 'Сотрудничество', 'is_enabled', 1, 'sort_order', 50),
    JSON_OBJECT('block_type', 'contacts', 'title', 'Написать консультанту', 'is_enabled', 1, 'sort_order', 60)
  ),
  1,
  20
),
(
  'team-growth',
  'Команда и рост',
  'Страница для лидера или консультанта, который делает акцент на команде.',
  JSON_OBJECT(
    'title', 'Команда, клиенты и возможности',
    'subtitle', '{{role_label}} SWPro',
    'short_description', 'Здесь можно пройти чек-ап, узнать о клиентских преимуществах и обсудить сотрудничество в команде.',
    'welcome_text', 'SWPro помогает вести клиентов, делиться материалами и развивать команду. Начните с чек-апа или напишите консультанту.',
    'cashback_title', 'Преимущества клиента',
    'cashback_text', 'Клиенты получают понятный старт: чек-ап, полезные материалы, информацию о кэшбэке, подарках и акциях.',
    'cooperation_title', 'Развитие в команде',
    'cooperation_text', 'Если вам интересна своя клиентская база, рассылки, мини-сайт и команда консультантов, обсудите с лидером доступный формат сотрудничества.',
    'bio', '{{name}} развивает направление SWPro и помогает людям входить в систему без сложностей: от первого чек-апа до работы с клиентами.',
    'specialization', 'Команда, сопровождение консультантов, клиентские программы, материалы и рассылки.',
    'experience_text', 'Этот шаблон подходит лидеру, который хочет показать и клиентские, и партнерские возможности.',
    'certificates_text', 'Перед началом работы консультант помогает разобраться с форматом и следующими шагами.',
    'theme_key', 'ocean'
  ),
  JSON_ARRAY(
    JSON_OBJECT('block_type', 'hero', 'title', 'SWPro и команда', 'is_enabled', 1, 'sort_order', 10),
    JSON_OBJECT('block_type', 'about', 'title', 'О направлении', 'is_enabled', 1, 'sort_order', 20),
    JSON_OBJECT('block_type', 'tests', 'title', 'Чек-ап для клиента', 'is_enabled', 1, 'sort_order', 30),
    JSON_OBJECT('block_type', 'cooperation', 'title', 'Возможность сотрудничества', 'is_enabled', 1, 'sort_order', 40),
    JSON_OBJECT('block_type', 'cashback', 'title', 'Кэшбэк и подарки', 'is_enabled', 1, 'sort_order', 50),
    JSON_OBJECT('block_type', 'materials', 'title', 'Материалы команды', 'is_enabled', 1, 'sort_order', 60),
    JSON_OBJECT('block_type', 'contacts', 'title', 'Связаться', 'is_enabled', 1, 'sort_order', 70)
  ),
  1,
  30
)
ON DUPLICATE KEY UPDATE
  title = VALUES(title),
  description = VALUES(description),
  profile_json = VALUES(profile_json),
  blocks_json = VALUES(blocks_json),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

ALTER TABLE consultant_profiles
  ADD COLUMN IF NOT EXISTS source_profile_id BIGINT UNSIGNED NULL AFTER owner_id,
  ADD COLUMN IF NOT EXISTS template_id BIGINT UNSIGNED NULL AFTER owner_id,
  ADD COLUMN IF NOT EXISTS template_applied_at DATETIME NULL AFTER template_id,
  ADD COLUMN IF NOT EXISTS template_customized_at DATETIME NULL AFTER template_applied_at,
  ADD INDEX IF NOT EXISTS idx_consultant_profiles_source_profile_id (source_profile_id),
  ADD INDEX IF NOT EXISTS idx_consultant_profiles_template_id (template_id);

SET @consultant_profiles_source_fk_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'consultant_profiles'
    AND CONSTRAINT_NAME = 'fk_consultant_profiles_source_profile'
);
SET @consultant_profiles_source_fk_sql := IF(
  @consultant_profiles_source_fk_exists = 0,
  'ALTER TABLE consultant_profiles
     ADD CONSTRAINT fk_consultant_profiles_source_profile
     FOREIGN KEY (source_profile_id) REFERENCES consultant_profiles(id)
     ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE consultant_profiles_source_fk_stmt FROM @consultant_profiles_source_fk_sql;
EXECUTE consultant_profiles_source_fk_stmt;
DEALLOCATE PREPARE consultant_profiles_source_fk_stmt;

SET @consultant_profiles_template_fk_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'consultant_profiles'
    AND CONSTRAINT_NAME = 'fk_consultant_profiles_template'
);
SET @consultant_profiles_template_fk_sql := IF(
  @consultant_profiles_template_fk_exists = 0,
  'ALTER TABLE consultant_profiles
     ADD CONSTRAINT fk_consultant_profiles_template
     FOREIGN KEY (template_id) REFERENCES site_templates(id)
     ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE consultant_profiles_template_fk_stmt FROM @consultant_profiles_template_fk_sql;
EXECUTE consultant_profiles_template_fk_stmt;
DEALLOCATE PREPARE consultant_profiles_template_fk_stmt;

ALTER TABLE broadcasts
  MODIFY COLUMN target_type ENUM('all', 'reseller', 'manager', 'segment', 'own_clients', 'branch_clients', 'direct_consultants', 'branch_consultants', 'direct_leaders', 'branch_leaders', 'whole_branch') NOT NULL DEFAULT 'all',
  ADD COLUMN IF NOT EXISTS segment_stage VARCHAR(50) NULL AFTER target_manager_id,
  ADD COLUMN IF NOT EXISTS segment_checkup VARCHAR(50) NULL AFTER segment_stage,
  ADD COLUMN IF NOT EXISTS segment_activity VARCHAR(50) NULL AFTER segment_checkup;

ALTER TABLE leader_subscriptions
  ADD COLUMN IF NOT EXISTS leader_limit INT UNSIGNED NULL AFTER consultant_limit,
  ADD COLUMN IF NOT EXISTS price_per_leader DECIMAL(10,2) NULL AFTER price_per_consultant,
  ADD COLUMN IF NOT EXISTS leader_amount_due DECIMAL(10,2) NULL AFTER amount_due,
  ADD COLUMN IF NOT EXISTS billing_basis ENUM('direct','branch') NOT NULL DEFAULT 'branch' AFTER leader_amount_due,
  ADD COLUMN IF NOT EXISTS direct_leader_limit INT UNSIGNED NULL AFTER billing_basis,
  ADD COLUMN IF NOT EXISTS branch_leader_limit INT UNSIGNED NULL AFTER direct_leader_limit,
  ADD COLUMN IF NOT EXISTS direct_consultant_limit INT UNSIGNED NULL AFTER branch_leader_limit,
  ADD COLUMN IF NOT EXISTS branch_consultant_limit INT UNSIGNED NULL AFTER direct_consultant_limit,
  ADD COLUMN IF NOT EXISTS per_child_consultant_limit INT UNSIGNED NULL AFTER branch_consultant_limit;

UPDATE leader_subscriptions
SET branch_consultant_limit = consultant_limit
WHERE branch_consultant_limit IS NULL
  AND consultant_limit IS NOT NULL;

INSERT INTO settings (setting_key, setting_value, description)
VALUES
  ('leader_price_per_leader', '500.00', 'Базовая ежемесячная стоимость одного дочернего лидера'),
  ('leader_price_per_consultant', '300.00', 'Базовая ежемесячная стоимость одного консультанта')
ON DUPLICATE KEY UPDATE
  setting_value = VALUES(setting_value),
  description = VALUES(description);
