SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE ai_voice_jobs
  ADD COLUMN draft_id BIGINT UNSIGNED NULL AFTER conversation_id,
  ADD COLUMN sent_at DATETIME NULL AFTER completed_at,
  ADD COLUMN delivery_channel VARCHAR(20) NULL AFTER sent_at,
  ADD COLUMN delivery_error TEXT NULL AFTER delivery_channel,
  ADD COLUMN chat_message_id BIGINT UNSIGNED NULL AFTER delivery_error,
  ADD INDEX idx_ai_voice_draft (draft_id),
  ADD INDEX idx_ai_voice_client_ready (end_user_id, status, created_at),
  ADD CONSTRAINT fk_ai_voice_draft FOREIGN KEY (draft_id) REFERENCES ai_content_drafts(id) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT fk_ai_voice_chat_message FOREIGN KEY (chat_message_id) REFERENCES chat_messages(id) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE IF NOT EXISTS ai_voice_delivery_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voice_job_id BIGINT UNSIGNED NOT NULL,
  end_user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_accessed_at DATETIME NULL,
  revoked_at DATETIME NULL,
  UNIQUE KEY uq_ai_voice_delivery_token (token_hash),
  INDEX idx_ai_voice_delivery_job (voice_job_id, revoked_at),
  CONSTRAINT fk_ai_voice_delivery_job FOREIGN KEY (voice_job_id) REFERENCES ai_voice_jobs(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ai_voice_delivery_user FOREIGN KEY (end_user_id) REFERENCES end_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE help_faq_sections
SET body = 'Голосовое сообщение создаётся для выбранного клиента: выберите готовый повод или напишите свой, проверьте персональный текст, создайте голос и нажмите «Отправить клиенту». Сообщение появится в живом чате и уйдёт в доступный канал клиента.',
    items_json = JSON_ARRAY(
      'Telegram получает OGG как обычное голосовое сообщение; старые MP3 отправляются как аудио.',
      'VK получает голосовое вложение, а при недоступности загрузки — защищённую ссылку.',
      'В Web-профиле голос появляется прямо в чате клиента с кнопкой воспроизведения.',
      'Скачивание файла остаётся дополнительной возможностью. Автоматическая отправка без проверки лидером не выполняется.'
    ),
    keywords = CONCAT_WS(', ', NULLIF(keywords, ''), 'голос, голосовое приветствие, отправить аудио, OGG, MP3')
WHERE title = 'AI-студия материалов';
