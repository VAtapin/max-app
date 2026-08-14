ALTER TABLE ai_content_drafts
  ADD COLUMN provider VARCHAR(50) NOT NULL DEFAULT 'swpro' AFTER content,
  ADD COLUMN model VARCHAR(100) NULL AFTER provider,
  ADD COLUMN input_tokens INT UNSIGNED NULL AFTER model,
  ADD COLUMN output_tokens INT UNSIGNED NULL AFTER input_tokens,
  ADD COLUMN end_user_id BIGINT UNSIGNED NULL AFTER owner_id,
  ADD INDEX idx_ai_content_client (end_user_id);

ALTER TABLE ai_voice_jobs
  ADD COLUMN model VARCHAR(100) NULL AFTER provider;

ALTER TABLE ai_usage_events
  MODIFY COLUMN event_type ENUM('text','studio','voice','video','personal_video','realtime') NOT NULL;

INSERT INTO settings (setting_key, setting_value, description) VALUES
  ('ai.studio_external_enabled', '0', 'Разрешение передавать в OpenAI тему, утверждённый источник и выбранный контекст персонализации без контактов и идентификаторов'),
  ('ai.voice_external_enabled', '0', 'Явное разрешение отправлять проверенный голосовой сценарий внешнему TTS-провайдеру'),
  ('ai.studio_model', 'gpt-5-mini', 'Модель OpenAI для черновиков AI-студии'),
  ('ai.openai_tts_model', 'gpt-4o-mini-tts', 'Модель OpenAI для синтеза речи'),
  ('ai.openai_voice', 'coral', 'Стандартный голос OpenAI'),
  ('ai.openai_voice_instructions', 'Говори по-русски тепло, естественно и спокойно. Не спеши и делай смысловые паузы.', 'Инструкция для стандартного голоса OpenAI')
ON DUPLICATE KEY UPDATE description = VALUES(description);

UPDATE help_faq_sections
SET body = 'AI-студия создаёт общие и персональные черновики по утверждённым материалам. При выборе клиента OpenAI может получить имя, пол, возраст или дату рождения, город и содержание последнего чек-апа. Контакты, точный адрес, внутренние и платформенные ID, логины и токены не передаются.',
    items_json = JSON_ARRAY(
      'Черновик всегда проверяет человек и переводит в статус «Проверено».',
      'Голос создаётся только после отдельного подтверждения отправки проверенного сценария в OpenAI.',
      'Для видео нужен HEYGEN_API_KEY или TAVUS_API_KEY и внешний ID аватара.',
      'Готовые MP3 и MP4 хранятся закрыто и доступны владельцу рабочего места.'
    )
WHERE title = 'AI-студия материалов';
