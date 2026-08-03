ALTER TABLE messaging_integrations
  ADD COLUMN IF NOT EXISTS callback_confirmation_code VARCHAR(190) NULL AFTER access_token,
  ADD COLUMN IF NOT EXISTS callback_secret VARCHAR(190) NULL AFTER callback_confirmation_code,
  ADD COLUMN IF NOT EXISTS callback_last_event_at DATETIME NULL AFTER callback_secret,
  ADD COLUMN IF NOT EXISTS callback_last_error TEXT NULL AFTER callback_last_event_at;

ALTER TABLE platform_accounts
  ADD COLUMN IF NOT EXISTS messages_allowed TINYINT(1) NULL AFTER display_name,
  ADD COLUMN IF NOT EXISTS messages_allowed_at DATETIME NULL AFTER messages_allowed,
  ADD COLUMN IF NOT EXISTS messages_denied_at DATETIME NULL AFTER messages_allowed_at,
  ADD COLUMN IF NOT EXISTS last_inbound_message_at DATETIME NULL AFTER messages_denied_at;

ALTER TABLE leads
  ADD COLUMN IF NOT EXISTS source_message_id VARCHAR(190) NULL AFTER source_platform,
  ADD COLUMN IF NOT EXISTS attachments_json JSON NULL AFTER message,
  ADD INDEX IF NOT EXISTS idx_leads_source_message (source_platform, source_message_id);

CREATE TABLE IF NOT EXISTS social_callback_events (
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

DELETE FROM help_faq_sections
WHERE title IN (
  'Как подключить VK-сообщество',
  'Входящие сообщения VK'
);

INSERT INTO help_faq_sections (title, body, items_json, is_featured, is_active, sort_order) VALUES
('Как подключить VK-сообщество', 'Для VK нужен ключ доступа сообщества и Callback API. Ключ доступа отправляет ответы клиентам, а Callback API принимает входящие сообщения из лички сообщества.', JSON_ARRAY(
  'В VK откройте сообщество: Управление -> Сообщения. Включите сообщения сообщества, возможности ботов и кнопку Начать.',
  'В Управление -> Работа с API -> Ключи доступа создайте ключ с правами Сообщения сообщества и Управление сообществом.',
  'В Callback API укажите адрес https://swpro.ru/api/vk_callback.php и версию API 5.199.',
  'В Типах событий отметьте Входящее сообщение, Разрешение на получение и Запрет на получение. Дополнительно можно включить Исходящее сообщение, Действие с сообщением и Прочитанность сообщений.',
  'В SWPro откройте Подключения, выберите VK, укажите ID сообщества, ключ доступа, строку подтверждения и секретный ключ Callback API.',
  'После сохранения подключения нажмите Подтвердить в VK. Сервер должен вернуть строку подтверждения.'
), 0, 1, 110),
('Входящие сообщения VK', 'Если клиент пишет прямо в личные сообщения VK-сообщества, SWPro создает обращение и уведомляет консультанта в Telegram. Ответ консультанта возвращается клиенту в VK от имени сообщества.', JSON_ARRAY(
  'Текст сообщения сохраняется в обращении.',
  'Фото, видео, голосовые, документы и ссылки приходят как вложения внутри события Входящее сообщение и сохраняются в обращении как список вложений.',
  'Если VK повторно пришлет одно и то же событие, SWPro не создаст дубль обращения.',
  'Если клиент запретил сообщения от сообщества, SWPro запомнит это и покажет ошибку при попытке отправить ответ.'
), 0, 1, 115);
