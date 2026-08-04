SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE end_users
  ADD COLUMN gender ENUM('female', 'male', 'prefer_not_to_say') NULL AFTER last_name,
  ADD COLUMN birth_date DATE NULL AFTER gender,
  ADD COLUMN age_years TINYINT UNSIGNED NULL AFTER birth_date,
  ADD COLUMN city VARCHAR(190) NULL AFTER age_years,
  ADD COLUMN timezone VARCHAR(100) NOT NULL DEFAULT 'Europe/Moscow' AFTER city,
  ADD COLUMN client_stage ENUM(
    'new',
    'profile_completed',
    'test_started',
    'test_completed',
    'consultation_requested',
    'in_progress',
    'client',
    'partner',
    'inactive',
    'unsubscribed'
  ) NOT NULL DEFAULT 'new' AFTER referral_code_used,
  ADD COLUMN stage_updated_at DATETIME NULL AFTER client_stage,
  ADD COLUMN onboarding_completed_at DATETIME NULL AFTER stage_updated_at,
  ADD COLUMN notifications_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER onboarding_completed_at,
  ADD INDEX idx_end_users_stage (client_stage),
  ADD INDEX idx_end_users_activity (last_activity_at);

ALTER TABLE consultant_profiles
  ADD COLUMN welcome_text TEXT NULL AFTER short_description,
  ADD COLUMN welcome_image_path VARCHAR(255) NULL AFTER welcome_text,
  ADD COLUMN welcome_video_url VARCHAR(255) NULL AFTER welcome_image_path,
  ADD COLUMN cashback_title VARCHAR(190) NULL AFTER welcome_video_url,
  ADD COLUMN cashback_text MEDIUMTEXT NULL AFTER cashback_title,
  ADD COLUMN cashback_image_path VARCHAR(255) NULL AFTER cashback_text,
  ADD COLUMN cashback_url VARCHAR(500) NULL AFTER cashback_image_path,
  ADD COLUMN cooperation_title VARCHAR(190) NULL AFTER cashback_url,
  ADD COLUMN cooperation_text MEDIUMTEXT NULL AFTER cooperation_title,
  ADD COLUMN cooperation_image_path VARCHAR(255) NULL AFTER cooperation_text,
  ADD COLUMN cooperation_video_url VARCHAR(255) NULL AFTER cooperation_image_path;

ALTER TABLE profile_blocks
  MODIFY COLUMN block_type ENUM(
    'hero',
    'video',
    'about',
    'tests',
    'products',
    'materials',
    'reviews',
    'contacts',
    'cashback',
    'cooperation'
  ) NOT NULL;

ALTER TABLE content_posts
  ADD COLUMN section_type ENUM(
    'general',
    'story',
    'result',
    'promotion',
    'giveaway',
    'program',
    'marathon'
  ) NOT NULL DEFAULT 'general' AFTER content_type,
  ADD INDEX idx_content_section_type (section_type);

ALTER TABLE test_questions
  ADD COLUMN gender_scope ENUM('all', 'female', 'male') NOT NULL DEFAULT 'all' AFTER question_type,
  ADD INDEX idx_test_questions_gender_scope (gender_scope);

UPDATE test_questions
SET gender_scope = 'female'
WHERE question_text IN (
  'Для женщин: проблемы с менструальным циклом',
  'Для женщин: период менопаузы, «приливы»'
);

ALTER TABLE user_test_sessions
  ADD COLUMN last_answered_at DATETIME NULL AFTER started_at,
  ADD INDEX idx_user_test_sessions_reminders (completed_at, last_answered_at);

ALTER TABLE broadcasts
  MODIFY COLUMN message_text TEXT NULL,
  ADD COLUMN audience_type ENUM('clients', 'consultants') NOT NULL DEFAULT 'clients' AFTER message_text,
  ADD COLUMN video_path VARCHAR(255) NULL AFTER image_path;

ALTER TABLE broadcast_logs
  MODIFY COLUMN end_user_id BIGINT UNSIGNED NULL,
  ADD COLUMN manager_id BIGINT UNSIGNED NULL AFTER end_user_id,
  ADD INDEX idx_broadcast_logs_manager_id (manager_id),
  ADD CONSTRAINT fk_broadcast_logs_manager
    FOREIGN KEY (manager_id) REFERENCES managers(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

CREATE TABLE legal_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_type ENUM(
    'privacy_policy',
    'personal_data_consent',
    'health_data_consent',
    'marketing_consent',
    'user_agreement',
    'leader_offer'
  ) NOT NULL,
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
  document_type ENUM(
    'personal_data_consent',
    'health_data_consent',
    'marketing_consent',
    'user_agreement'
  ) NOT NULL,
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
  manager_id BIGINT UNSIGNED NOT NULL,
  end_user_id BIGINT UNSIGNED NOT NULL,
  notification_type VARCHAR(50) NOT NULL,
  event_key VARCHAR(190) NOT NULL,
  title VARCHAR(190) NOT NULL,
  message_text TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  delivery_status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
  delivery_error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  UNIQUE KEY uq_consultant_notification_event (manager_id, event_key),
  INDEX idx_consultant_notifications_manager (manager_id, is_read, created_at),
  CONSTRAINT fk_consultant_notifications_manager
    FOREIGN KEY (manager_id) REFERENCES managers(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_consultant_notifications_user
    FOREIGN KEY (end_user_id) REFERENCES end_users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leader_subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reseller_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending', 'active', 'expired', 'suspended') NOT NULL DEFAULT 'pending',
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  monthly_price DECIMAL(10,2) NULL,
  payment_note VARCHAR(500) NULL,
  activated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_leader_subscriptions_reseller (reseller_id, status, ends_at),
  CONSTRAINT fk_leader_subscriptions_reseller
    FOREIGN KEY (reseller_id) REFERENCES resellers(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_leader_subscriptions_admin
    FOREIGN KEY (activated_by) REFERENCES admin_users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO legal_documents (document_type, title, version, body, is_required, is_active) VALUES
('privacy_policy', 'Политика обработки персональных данных', '2026-07-03',
'Оператор: [УКАЖИТЕ НАИМЕНОВАНИЕ ИЛИ ФИО ОПЕРАТОРА], ИНН [ИНН], адрес: [АДРЕС], email: [EMAIL].

Настоящая политика определяет порядок обработки персональных данных в сервисе SWPro. Обрабатываются идентификаторы платформы, имя, фамилия, пол, возраст или дата рождения, город, контактные данные, сведения об активности, обращения, ответы и результаты чек-апа.

Цели обработки: регистрация пользователя, закрепление за выбранным консультантом, проведение чек-апа, показ результата, организация связи с консультантом, ведение истории обращений, обеспечение безопасности сервиса и, при наличии отдельного согласия, направление информационных и рекламных сообщений.

Данные обрабатываются с использованием автоматизированных средств и могут быть доступны оператору, закреплённому консультанту и лидеру его команды только в объёме, необходимом для работы сервиса. Данные не публикуются и не передаются иным лицам без законного основания.

Пользователь вправе запросить сведения об обработке, исправление, блокирование или удаление данных, отозвать согласие и отказаться от рассылок, направив обращение на [EMAIL].

Срок хранения определяется целями обработки и требованиями законодательства. После достижения целей или отзыва согласия данные удаляются либо обезличиваются, если их дальнейшее хранение не требуется по закону.

Политика действует с даты публикации. Актуальная версия размещается в сервисе SWPro.',
1, 1),
('personal_data_consent', 'Согласие на обработку персональных данных', '2026-07-03',
'Я свободно, своей волей и в своём интересе даю [ОПЕРАТОРУ] согласие на обработку моих персональных данных: идентификатора платформы, имени, фамилии, пола, возраста или даты рождения, города, контактных данных, сведений об активности и обращениях.

Цели обработки: предоставление функций SWPro, закрепление за консультантом, связь с консультантом, ведение клиентской истории и обеспечение работы сервиса.

Разрешённые действия: сбор, запись, систематизация, накопление, хранение, уточнение, использование, предоставление закреплённому консультанту и лидеру его команды, блокирование, удаление и уничтожение.

Согласие действует до достижения целей обработки или его отзыва. Отзыв можно направить на [EMAIL] либо выполнить через команду или кнопку отказа в сервисе.',
1, 1),
('health_data_consent', 'Согласие на обработку ответов чек-апа', '2026-07-03',
'Я отдельно и явно соглашаюсь на обработку моих ответов на вопросы чек-апа и сформированных на их основе информационных результатов, которые могут относиться к сведениям о состоянии здоровья.

Цель обработки: проведение выбранного мной чек-апа, сохранение результата и передача результата закреплённому консультанту для последующего обсуждения.

Я понимаю, что чек-ап не является медицинской диагностикой, а результат не заменяет консультацию врача. Согласие действует до его отзыва или удаления моей учётной записи.',
1, 1),
('marketing_consent', 'Согласие на информационные и рекламные сообщения', '2026-07-03',
'Я соглашаюсь получать через выбранную платформу сообщения SWPro и моего консультанта: полезные материалы, новости, информацию об акциях, подарках, программах и возможности сотрудничества.

Согласие является добровольным и не влияет на доступ к чек-апу. Я могу отказаться от рассылок в любой момент через кнопку или команду в сервисе либо по адресу [EMAIL].',
0, 1),
('user_agreement', 'Пользовательское соглашение SWPro', '2026-07-03',
'SWPro предоставляет информационный сервис для прохождения чек-апов и связи с независимым консультантом. Сервис не оказывает медицинские услуги, не устанавливает диагнозы и не назначает лечение.

Пользователь обязуется указывать достоверные данные, не передавать доступ третьим лицам и не использовать сервис противоправно. Результаты носят ознакомительный характер. Перед применением продукции следует изучить официальную информацию производителя и при необходимости проконсультироваться со специалистом.

Оператор вправе изменять функциональность и настоящее соглашение, публикуя новую версию в сервисе.',
1, 1),
('leader_offer', 'Оферта на доступ к кабинету лидера', '2026-07-03',
'[ИСПОЛНИТЕЛЬ] предлагает предоставить платный доступ к кабинету лидера SWPro. Стоимость доступа рассчитывается по выбранной подписке и фактическому количеству активных лидеров и консультантов в выбранном периоде.

Стоимость: [СТОИМОСТЬ] рублей в месяц по фактическому начислению. Оплата на первом этапе подтверждается администратором вручную. Срок доступа указывается в кабинете лидера.

После окончания оплаченного периода доступ к рабочим функциям может быть ограничен, при этом данные не удаляются в течение установленного оператором срока. Условия возврата, порядок оплаты и реквизиты сторон необходимо заполнить до публикации оферты.',
1, 1);

INSERT INTO settings (setting_key, setting_value, description) VALUES
('legal_operator_name', '[ОПЕРАТОР]', 'Наименование или ФИО оператора персональных данных'),
('legal_operator_inn', '[ИНН]', 'ИНН оператора'),
('legal_operator_address', '[АДРЕС]', 'Адрес оператора'),
('legal_operator_email', '[EMAIL]', 'Email для обращений по персональным данным'),
('leader_monthly_price', NULL, 'Устаревшее поле совместимости: текущая цена считается по лимиту консультантов'),
('automation_timezone', 'Europe/Moscow', 'Часовой пояс автоматических сообщений')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO profile_blocks (profile_id, block_type, title, is_enabled, sort_order)
SELECT id, 'cashback', 'Кэшбэк и подарки', 1, 40 FROM consultant_profiles
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO profile_blocks (profile_id, block_type, title, is_enabled, sort_order)
SELECT id, 'cooperation', 'Возможность сотрудничества', 1, 50 FROM consultant_profiles
ON DUPLICATE KEY UPDATE title = VALUES(title);

UPDATE profile_blocks SET is_enabled = 0 WHERE block_type = 'products';

UPDATE tests
SET is_active = CASE WHEN title = 'Диагностика организма' THEN 1 ELSE 0 END;

UPDATE content_posts
SET section_type = 'program'
WHERE title = 'Персональная программа с консультантом';

INSERT IGNORE INTO profile_tests (profile_id, test_id, sort_order)
SELECT cp.id, t.id, 10
FROM consultant_profiles cp
INNER JOIN tests t ON t.title = 'Диагностика организма' AND t.is_active = 1;
