INSERT INTO settings (setting_key, setting_value, description) VALUES
  ('ai.smart_routing_enabled', '1', 'Автоматически выбирать экономичную или усиленную модель по сложности вопроса'),
  ('ai.complexity_threshold', '4', 'Минимальный локальный балл для использования усиленной модели'),
  ('ai.standard_max_output_tokens', '700', 'Максимум токенов обычного текстового ответа'),
  ('ai.complex_max_output_tokens', '1100', 'Максимум токенов сложного текстового ответа')
ON DUPLICATE KEY UPDATE description = VALUES(description);
