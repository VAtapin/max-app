INSERT INTO settings (setting_key, setting_value, description)
VALUES
  ('ai.openai_voice', 'marin', 'Стандартный голос OpenAI'),
  ('ai.openai_voice_instructions', 'Говори по-русски как в личном голосовом сообщении знакомому человеку: тепло, живо и естественно. Избегай дикторской, рекламной и торжественной подачи. Используй разговорную интонацию, лёгкие изменения темпа и высоты голоса, короткие естественные паузы между мыслями. Не растягивай окончания и не делай одинаковые паузы после каждого предложения.', 'Инструкция для стандартного голоса OpenAI')
ON DUPLICATE KEY UPDATE
  setting_value = CASE
    WHEN setting_key = 'ai.openai_voice' AND setting_value = 'coral' THEN VALUES(setting_value)
    WHEN setting_key = 'ai.openai_voice_instructions' AND setting_value IN (
      'Говори по-русски тепло, естественно и спокойно.',
      'Говори по-русски тепло, естественно и спокойно. Не спеши и делай смысловые паузы.'
    ) THEN VALUES(setting_value)
    ELSE setting_value
  END,
  description = VALUES(description);
