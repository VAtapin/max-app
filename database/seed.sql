USE health_sales_system;

INSERT INTO admin_users (
  role,
  name,
  email,
  password_hash,
  referral_code,
  is_active
) VALUES (
  'superadmin',
  'Test Super Admin',
  'admin@example.com',
  '$2a$10$y1Svo.9fgTrNKzSKxZ33xexfo93P46e55WXT65YwOf4iiZDfG9u8G',
  'ADMIN001',
  1
);

INSERT INTO product_categories (title, slug, description, sort_order) VALUES
('Иммунитет', 'immunity', 'Продукты для поддержки иммунитета.', 10),
('Энергия', 'energy', 'Продукты для поддержки энергии и тонуса.', 20),
('Сон', 'sleep', 'Продукты для поддержки режима сна.', 30),
('Вес', 'weight', 'Продукты для поддержки контроля веса.', 40),
('Пищеварение', 'digestion', 'Продукты для поддержки пищеварения.', 50),
('Кожа', 'skin', 'Продукты и косметика для ухода за кожей.', 60),
('Витамины', 'vitamins', 'Витаминные комплексы и добавки.', 70);

INSERT INTO tags (title, slug, description) VALUES
('Усталость', 'fatigue', 'Ответы, связанные с усталостью и снижением энергии.'),
('Сон', 'sleep', 'Ответы, связанные с качеством сна.'),
('Иммунная поддержка', 'immune-support', 'Ответы, связанные с сезонной поддержкой организма.');

INSERT INTO products (
  category_id,
  title,
  slug,
  short_description,
  full_description,
  usage_text,
  warning_text,
  is_active,
  sort_order
) VALUES
((SELECT id FROM product_categories WHERE slug = 'energy'), 'Энергия комплекс', 'energy-complex', 'Комплекс для поддержки энергии.', 'Может использоваться как часть здорового образа жизни для поддержки тонуса.', 'Согласно инструкции производителя.', 'Информация носит ознакомительный характер и не является медицинской рекомендацией.', 1, 10),
((SELECT id FROM product_categories WHERE slug = 'sleep'), 'Сон баланс', 'sleep-balance', 'Продукт для поддержки режима сна.', 'Может способствовать поддержанию спокойного вечернего режима.', 'Согласно инструкции производителя.', 'Перед применением продуктов проконсультируйтесь со специалистом.', 1, 20);

INSERT INTO tests (title, description, category_id, is_active, sort_order) VALUES
('Базовый тест самочувствия', 'Короткий тест для подбора направлений поддержки.', NULL, 1, 10);

INSERT INTO test_questions (test_id, question_text, question_type, is_required, sort_order) VALUES
((SELECT id FROM tests WHERE title = 'Базовый тест самочувствия'), 'Что сейчас беспокоит больше всего?', 'single_choice', 1, 10),
((SELECT id FROM tests WHERE title = 'Базовый тест самочувствия'), 'Как вы оцениваете уровень энергии?', 'scale', 1, 20);

INSERT INTO test_answers (question_id, answer_text, score, tag_id, category_id, sort_order) VALUES
((SELECT id FROM test_questions WHERE question_text = 'Что сейчас беспокоит больше всего?'), 'Частая усталость', 5, (SELECT id FROM tags WHERE slug = 'fatigue'), (SELECT id FROM product_categories WHERE slug = 'energy'), 10),
((SELECT id FROM test_questions WHERE question_text = 'Что сейчас беспокоит больше всего?'), 'Сложно высыпаться', 5, (SELECT id FROM tags WHERE slug = 'sleep'), (SELECT id FROM product_categories WHERE slug = 'sleep'), 20),
((SELECT id FROM test_questions WHERE question_text = 'Что сейчас беспокоит больше всего?'), 'Хочу поддержать иммунитет', 5, (SELECT id FROM tags WHERE slug = 'immune-support'), (SELECT id FROM product_categories WHERE slug = 'immunity'), 30);

INSERT INTO settings (setting_key, setting_value, description) VALUES
('medical_disclaimer', 'Информация носит ознакомительный характер и не является медицинской рекомендацией. Перед применением продуктов проконсультируйтесь со специалистом.', 'Дисклеймер для интерфейса и рекомендаций.'),
('project_name', 'Health Sales Support', 'Название проекта.');
