USE health_sales_system;

CREATE TABLE IF NOT EXISTS help_faq_sections (
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

DELETE FROM help_faq_sections
WHERE title IN (
  'Как пользоваться SWPro',
  'Главная идея',
  'Заявки',
  'Тесты',
  'Продукты и материалы',
  'Платформы клиента',
  'Реферальные ссылки'
);

INSERT INTO help_faq_sections (title, body, items_json, is_featured, sort_order) VALUES
('Как пользоваться SWPro', 'SWPro помогает консультанту вести клиентов из Telegram, VK, OK, MAX и публичной страницы. Это не интернет-магазин: клиент не покупает напрямую, а оставляет заявку, проходит тесты и получает персональные рекомендации.', NULL, 1, 10),
('Главная идея', 'Клиент приходит не в каталог, а к своему консультанту. Поэтому в центре системы находится страница менеджера или реселлера.', JSON_ARRAY(
  'Заполните раздел «Моя страница».',
  'Добавьте фото, короткое описание, видеообращение и контакты.',
  'Выберите продукты, тесты и материалы, которые хотите показывать клиентам.'
), 0, 20),
('Заявки', 'Заявка появляется, когда клиент хочет связаться с консультантом или запросить информацию по продукту.', JSON_ARRAY(
  'Откройте раздел «Заявки».',
  'Выберите заявку и отправьте ответ.',
  'К ответу можно прикрепить текст, материал, тест и файлы.',
  'Для VK и OK ответ показывается внутри Mini App как новый ответ.'
), 0, 30),
('Тесты', 'Тесты нужны не ради тестов, а чтобы понять запрос клиента и предложить направление консультации.', JSON_ARRAY(
  'Создайте тест, вопросы и варианты ответов.',
  'Укажите баллы для вариантов.',
  'Настройте результаты по диапазонам баллов.',
  'После прохождения клиент увидит мягкую рекомендацию и сможет задать вопрос.'
), 0, 40),
('Продукты и материалы', 'Продукты и материалы являются витриной консультанта. Их можно показывать на публичной странице и в Mini App.', JSON_ARRAY(
  'Загружайте изображения и файлы через форму продукта или материала.',
  'Новый файл заменяет текущий.',
  'Чтобы убрать файл из карточки, отметьте «Удалить текущий файл» и сохраните.',
  'Видео можно указывать ссылкой, например YouTube.'
), 0, 50),
('Платформы клиента', 'Один клиент может использовать несколько платформ, но объединение аккаунтов должно быть осознанным.', JSON_ARRAY(
  'Автоматического объединения VK, OK, Telegram и MAX нет.',
  'Клиент может подключить другую платформу в профиле Mini App.',
  'Администратор может объединить пользователей вручную.',
  'После объединения заявки и ответы доступны одному человеку на подключённых платформах.'
), 0, 60),
('Реферальные ссылки', 'Менеджер привлекает клиентов через свою реферальную ссылку. Первичная привязка сохраняется постоянно.', JSON_ARRAY(
  'Если клиент уже закреплён за менеджером, новая ссылка не меняет привязку.',
  'Если клиент пришёл впервые, он попадает в команду указанного менеджера.',
  'Менеджер или реселлер не должен регистрироваться как клиент по своей рабочей ссылке.'
), 0, 70);

INSERT INTO product_categories (title, slug, description, sort_order)
SELECT 'Кожа', 'skin', 'Продукты и косметика для ухода за кожей.', 60
WHERE NOT EXISTS (SELECT 1 FROM product_categories WHERE slug = 'skin');

INSERT INTO product_categories (title, slug, description, sort_order)
SELECT 'Витамины', 'vitamins', 'Витаминные комплексы и добавки.', 70
WHERE NOT EXISTS (SELECT 1 FROM product_categories WHERE slug = 'vitamins');

INSERT INTO tags (title, slug, description)
SELECT 'Кожа и красота', 'skin-beauty', 'Ответы, связанные с состоянием кожи, волос и внешнего вида.'
WHERE NOT EXISTS (SELECT 1 FROM tags WHERE slug = 'skin-beauty');

INSERT INTO tags (title, slug, description)
SELECT 'Питание', 'nutrition', 'Ответы, связанные с рационом, регулярностью питания и микронутриентами.'
WHERE NOT EXISTS (SELECT 1 FROM tags WHERE slug = 'nutrition');

INSERT INTO tags (title, slug, description)
SELECT 'Стресс', 'stress', 'Ответы, связанные с напряжением, восстановлением и эмоциональной нагрузкой.'
WHERE NOT EXISTS (SELECT 1 FROM tags WHERE slug = 'stress');

INSERT INTO tests (title, description, category_id, is_active, sort_order)
SELECT 'Энергия и усталость', 'Помогает понять, где чаще всего проседает ресурс: сон, питание, нагрузка или восстановление.', (SELECT id FROM product_categories WHERE slug = 'energy'), 1, 20
WHERE NOT EXISTS (SELECT 1 FROM tests WHERE title = 'Энергия и усталость');

INSERT INTO tests (title, description, category_id, is_active, sort_order)
SELECT 'Сон и восстановление', 'Короткий опрос о вечернем режиме, качестве сна и ощущении восстановления утром.', (SELECT id FROM product_categories WHERE slug = 'sleep'), 1, 30
WHERE NOT EXISTS (SELECT 1 FROM tests WHERE title = 'Сон и восстановление');

INSERT INTO tests (title, description, category_id, is_active, sort_order)
SELECT 'Красота кожи и волос', 'Оценка базовых факторов, которые могут влиять на внешний вид кожи, волос и общее ощущение красоты.', (SELECT id FROM product_categories WHERE slug = 'skin'), 1, 40
WHERE NOT EXISTS (SELECT 1 FROM tests WHERE title = 'Красота кожи и волос');

INSERT INTO tests (title, description, category_id, is_active, sort_order)
SELECT 'Питание и микронутриенты', 'Опрос о регулярности питания, разнообразии рациона и возможной потребности в дополнительной поддержке.', (SELECT id FROM product_categories WHERE slug = 'vitamins'), 1, 50
WHERE NOT EXISTS (SELECT 1 FROM tests WHERE title = 'Питание и микронутриенты');

INSERT INTO tests (title, description, category_id, is_active, sort_order)
SELECT 'Иммунитет и стресс', 'Помогает оценить сезонную нагрузку, стресс и привычки восстановления.', (SELECT id FROM product_categories WHERE slug = 'immunity'), 1, 60
WHERE NOT EXISTS (SELECT 1 FROM tests WHERE title = 'Иммунитет и стресс');

INSERT INTO test_questions (test_id, question_text, question_type, is_required, sort_order)
SELECT t.id, q.question_text, 'single_choice', 1, q.sort_order
FROM tests t
JOIN (
  SELECT 'Энергия и усталость' test_title, 'Когда вы чаще всего чувствуете спад энергии?' question_text, 10 sort_order UNION ALL
  SELECT 'Энергия и усталость', 'Как обычно выглядит ваш завтрак или первый приём пищи?', 20 UNION ALL
  SELECT 'Энергия и усталость', 'Что лучше всего описывает ваш день?', 30 UNION ALL
  SELECT 'Сон и восстановление', 'Как быстро вы обычно засыпаете?', 10 UNION ALL
  SELECT 'Сон и восстановление', 'Как вы чувствуете себя утром?', 20 UNION ALL
  SELECT 'Сон и восстановление', 'Что чаще всего мешает вечернему режиму?', 30 UNION ALL
  SELECT 'Красота кожи и волос', 'Что сейчас больше всего беспокоит во внешнем виде?', 10 UNION ALL
  SELECT 'Красота кожи и волос', 'Как часто в рационе есть белок, овощи и полезные жиры?', 20 UNION ALL
  SELECT 'Красота кожи и волос', 'Как кожа реагирует на стресс или недосып?', 30 UNION ALL
  SELECT 'Питание и микронутриенты', 'Насколько разнообразен ваш рацион в течение недели?', 10 UNION ALL
  SELECT 'Питание и микронутриенты', 'Как часто вы пропускаете приёмы пищи?', 20 UNION ALL
  SELECT 'Питание и микронутриенты', 'Что вы хотите улучшить в питании в первую очередь?', 30 UNION ALL
  SELECT 'Иммунитет и стресс', 'Как часто вы чувствуете, что организм работает на пределе?', 10 UNION ALL
  SELECT 'Иммунитет и стресс', 'Как вы восстанавливаетесь после напряжённой недели?', 20 UNION ALL
  SELECT 'Иммунитет и стресс', 'Что чаще всего проседает в сезон нагрузки?', 30
) q ON q.test_title = t.title
WHERE NOT EXISTS (
  SELECT 1 FROM test_questions existing
  WHERE existing.test_id = t.id AND existing.question_text = q.question_text
);

INSERT INTO test_answers (question_id, answer_text, score, tag_id, category_id, sort_order)
SELECT tq.id, a.answer_text, a.score, tg.id, pc.id, a.sort_order
FROM test_questions tq
JOIN (
  SELECT 'Когда вы чаще всего чувствуете спад энергии?' question_text, 'Почти сразу утром' answer_text, 4 score, 'fatigue' tag_slug, 'energy' category_slug, 10 sort_order UNION ALL
  SELECT 'Когда вы чаще всего чувствуете спад энергии?', 'После обеда', 3, 'nutrition', 'energy', 20 UNION ALL
  SELECT 'Когда вы чаще всего чувствуете спад энергии?', 'К вечеру', 2, 'stress', 'energy', 30 UNION ALL
  SELECT 'Когда вы чаще всего чувствуете спад энергии?', 'Редко, энергии обычно хватает', 0, NULL, 'energy', 40 UNION ALL
  SELECT 'Как обычно выглядит ваш завтрак или первый приём пищи?', 'Часто пропускаю', 4, 'nutrition', 'vitamins', 10 UNION ALL
  SELECT 'Как обычно выглядит ваш завтрак или первый приём пищи?', 'Кофе и что-то быстрое', 3, 'nutrition', 'energy', 20 UNION ALL
  SELECT 'Как обычно выглядит ваш завтрак или первый приём пищи?', 'Есть белок и нормальная еда', 1, NULL, 'energy', 30 UNION ALL
  SELECT 'Как обычно выглядит ваш завтрак или первый приём пищи?', 'Питаюсь регулярно и разнообразно', 0, NULL, 'energy', 40 UNION ALL
  SELECT 'Что лучше всего описывает ваш день?', 'Много стресса и мало пауз', 4, 'stress', 'energy', 10 UNION ALL
  SELECT 'Что лучше всего описывает ваш день?', 'Много сидячей работы', 3, 'fatigue', 'energy', 20 UNION ALL
  SELECT 'Что лучше всего описывает ваш день?', 'Активность есть, но восстановление слабое', 2, 'sleep', 'sleep', 30 UNION ALL
  SELECT 'Что лучше всего описывает ваш день?', 'Режим в целом стабильный', 0, NULL, 'energy', 40 UNION ALL
  SELECT 'Как быстро вы обычно засыпаете?', 'Долго не могу уснуть', 4, 'sleep', 'sleep', 10 UNION ALL
  SELECT 'Как быстро вы обычно засыпаете?', 'По-разному, зависит от дня', 2, 'stress', 'sleep', 20 UNION ALL
  SELECT 'Как быстро вы обычно засыпаете?', 'Обычно быстро', 0, NULL, 'sleep', 30 UNION ALL
  SELECT 'Как вы чувствуете себя утром?', 'Просыпаюсь разбитым', 4, 'sleep', 'sleep', 10 UNION ALL
  SELECT 'Как вы чувствуете себя утром?', 'Нужно много времени, чтобы включиться', 3, 'fatigue', 'energy', 20 UNION ALL
  SELECT 'Как вы чувствуете себя утром?', 'В целом нормально', 1, NULL, 'sleep', 30 UNION ALL
  SELECT 'Что чаще всего мешает вечернему режиму?', 'Телефон, новости, работа до позднего вечера', 4, 'stress', 'sleep', 10 UNION ALL
  SELECT 'Что чаще всего мешает вечернему режиму?', 'Поздняя еда или нерегулярный график', 3, 'nutrition', 'digestion', 20 UNION ALL
  SELECT 'Что чаще всего мешает вечернему режиму?', 'Тревожные мысли', 3, 'stress', 'sleep', 30 UNION ALL
  SELECT 'Что чаще всего мешает вечернему режиму?', 'Особых проблем нет', 0, NULL, 'sleep', 40 UNION ALL
  SELECT 'Что сейчас больше всего беспокоит во внешнем виде?', 'Сухость, тусклость кожи', 4, 'skin-beauty', 'skin', 10 UNION ALL
  SELECT 'Что сейчас больше всего беспокоит во внешнем виде?', 'Волосы и ногти стали слабее', 4, 'skin-beauty', 'vitamins', 20 UNION ALL
  SELECT 'Что сейчас больше всего беспокоит во внешнем виде?', 'Отёчность или усталый вид', 3, 'fatigue', 'skin', 30 UNION ALL
  SELECT 'Что сейчас больше всего беспокоит во внешнем виде?', 'Хочу профилактически поддержать уход', 1, NULL, 'skin', 40 UNION ALL
  SELECT 'Как часто в рационе есть белок, овощи и полезные жиры?', 'Редко, питание хаотичное', 4, 'nutrition', 'vitamins', 10 UNION ALL
  SELECT 'Как часто в рационе есть белок, овощи и полезные жиры?', 'Иногда, но не каждый день', 3, 'nutrition', 'skin', 20 UNION ALL
  SELECT 'Как часто в рационе есть белок, овощи и полезные жиры?', 'Почти каждый день', 1, NULL, 'skin', 30 UNION ALL
  SELECT 'Как кожа реагирует на стресс или недосып?', 'Сразу появляются высыпания или раздражение', 4, 'stress', 'skin', 10 UNION ALL
  SELECT 'Как кожа реагирует на стресс или недосып?', 'Становится тусклой и чувствительной', 3, 'skin-beauty', 'skin', 20 UNION ALL
  SELECT 'Как кожа реагирует на стресс или недосып?', 'Почти не реагирует', 0, NULL, 'skin', 30 UNION ALL
  SELECT 'Насколько разнообразен ваш рацион в течение недели?', 'Часто одни и те же продукты', 4, 'nutrition', 'vitamins', 10 UNION ALL
  SELECT 'Насколько разнообразен ваш рацион в течение недели?', 'Разнообразие есть, но не хватает системы', 2, 'nutrition', 'vitamins', 20 UNION ALL
  SELECT 'Насколько разнообразен ваш рацион в течение недели?', 'Стараюсь держать баланс', 1, NULL, 'vitamins', 30 UNION ALL
  SELECT 'Как часто вы пропускаете приёмы пищи?', 'Почти каждый день', 4, 'nutrition', 'energy', 10 UNION ALL
  SELECT 'Как часто вы пропускаете приёмы пищи?', 'Несколько раз в неделю', 3, 'nutrition', 'vitamins', 20 UNION ALL
  SELECT 'Как часто вы пропускаете приёмы пищи?', 'Редко', 0, NULL, 'vitamins', 30 UNION ALL
  SELECT 'Что вы хотите улучшить в питании в первую очередь?', 'Больше энергии и меньше тяги к сладкому', 4, 'fatigue', 'energy', 10 UNION ALL
  SELECT 'Что вы хотите улучшить в питании в первую очередь?', 'Поддержать кожу, волосы, ногти', 3, 'skin-beauty', 'skin', 20 UNION ALL
  SELECT 'Что вы хотите улучшить в питании в первую очередь?', 'Собрать базовый комплекс витаминов', 3, 'nutrition', 'vitamins', 30 UNION ALL
  SELECT 'Как часто вы чувствуете, что организм работает на пределе?', 'Почти постоянно', 4, 'stress', 'immunity', 10 UNION ALL
  SELECT 'Как часто вы чувствуете, что организм работает на пределе?', 'В периоды нагрузки', 3, 'immune-support', 'immunity', 20 UNION ALL
  SELECT 'Как часто вы чувствуете, что организм работает на пределе?', 'Редко', 0, NULL, 'immunity', 30 UNION ALL
  SELECT 'Как вы восстанавливаетесь после напряжённой недели?', 'Плохо, усталость накапливается', 4, 'fatigue', 'energy', 10 UNION ALL
  SELECT 'Как вы восстанавливаетесь после напряжённой недели?', 'Помогает сон, но его не хватает', 3, 'sleep', 'sleep', 20 UNION ALL
  SELECT 'Как вы восстанавливаетесь после напряжённой недели?', 'Есть свои ритуалы восстановления', 1, NULL, 'immunity', 30 UNION ALL
  SELECT 'Что чаще всего проседает в сезон нагрузки?', 'Чаще простужаюсь или дольше восстанавливаюсь', 4, 'immune-support', 'immunity', 10 UNION ALL
  SELECT 'Что чаще всего проседает в сезон нагрузки?', 'Энергия и настроение', 3, 'fatigue', 'energy', 20 UNION ALL
  SELECT 'Что чаще всего проседает в сезон нагрузки?', 'Сон и спокойствие', 3, 'sleep', 'sleep', 30
) a ON a.question_text = tq.question_text
LEFT JOIN tags tg ON tg.slug = a.tag_slug
LEFT JOIN product_categories pc ON pc.slug = a.category_slug
WHERE NOT EXISTS (
  SELECT 1 FROM test_answers existing
  WHERE existing.question_id = tq.id AND existing.answer_text = a.answer_text
);

INSERT INTO test_results (test_id, title, min_score, max_score, summary_text, advice_text, category_id, sort_order)
SELECT t.id, r.title, r.min_score, r.max_score, r.summary_text, r.advice_text, pc.id, r.sort_order
FROM tests t
JOIN (
  SELECT 'Энергия и усталость' test_title, 'Ресурс в целом стабильный' title, 0 min_score, 3 max_score, 'Сейчас нет ярко выраженного сигнала по энергии.' summary_text, 'Поддерживайте режим сна, воды, регулярное питание и мягкую активность.' advice_text, 'energy' category_slug, 10 sort_order UNION ALL
  SELECT 'Энергия и усталость', 'Нужна мягкая поддержка энергии', 4, 8, 'Есть признаки нерегулярного восстановления или питания.', 'Обсудите с консультантом базовую поддержку энергии, режима и микронутриентов.', 'energy', 20 UNION ALL
  SELECT 'Энергия и усталость', 'Высокая нагрузка на ресурс', 9, 12, 'Ответы показывают, что организм часто работает в режиме дефицита восстановления.', 'Начните с режима сна, питания и консультации по поддержке энергии.', 'energy', 30 UNION ALL
  SELECT 'Сон и восстановление', 'Сон выглядит стабильным', 0, 3, 'Серьёзных сигналов по восстановлению сейчас немного.', 'Сохраняйте вечерний режим и регулярность сна.', 'sleep', 10 UNION ALL
  SELECT 'Сон и восстановление', 'Восстановление требует внимания', 4, 8, 'Есть факторы, которые могут мешать качественному отдыху.', 'Обсудите вечерние привычки, стресс и мягкую поддержку сна.', 'sleep', 20 UNION ALL
  SELECT 'Сон и восстановление', 'Сон может быть ключевой точкой', 9, 12, 'Ответы показывают выраженный запрос на восстановление.', 'Начните с вечернего режима и консультации по поддержке сна.', 'sleep', 30 UNION ALL
  SELECT 'Красота кожи и волос', 'Профилактический уход', 0, 3, 'Сейчас запрос скорее поддерживающий.', 'Поддерживайте регулярный уход, воду, белок и базовый рацион.', 'skin', 10 UNION ALL
  SELECT 'Красота кожи и волос', 'Коже нужна системная поддержка', 4, 8, 'Есть связь между внешним видом, питанием и восстановлением.', 'Обсудите уход, питание и поддержку кожи изнутри.', 'skin', 20 UNION ALL
  SELECT 'Красота кожи и волос', 'Красота зависит от общего ресурса', 9, 12, 'Ответы показывают, что внешний вид может страдать из-за стресса, питания или сна.', 'Начните с комплексного подхода: рацион, сон, уход и консультация.', 'skin', 30 UNION ALL
  SELECT 'Питание и микронутриенты', 'Рацион в целом устойчивый', 0, 3, 'Базовые привычки выглядят достаточно стабильными.', 'Можно обсудить профилактическую поддержку и сезонные задачи.', 'vitamins', 10 UNION ALL
  SELECT 'Питание и микронутриенты', 'Есть точки для улучшения рациона', 4, 8, 'Есть признаки нерегулярности или недостатка разнообразия.', 'С консультантом можно собрать понятный план питания и базовой поддержки.', 'vitamins', 20 UNION ALL
  SELECT 'Питание и микронутриенты', 'Питание сильно влияет на запрос', 9, 12, 'Ответы показывают, что питание может быть центральной причиной самочувствия.', 'Начните с регулярности питания, воды и подбора микронутриентной поддержки.', 'vitamins', 30 UNION ALL
  SELECT 'Иммунитет и стресс', 'Сезонная поддержка', 0, 3, 'Сейчас достаточно профилактического подхода.', 'Сохраняйте сон, активность и базовые привычки восстановления.', 'immunity', 10 UNION ALL
  SELECT 'Иммунитет и стресс', 'Нагрузка заметна', 4, 8, 'Стресс и восстановление могут влиять на устойчивость организма.', 'Обсудите с консультантом поддержку иммунитета и восстановительные привычки.', 'immunity', 20 UNION ALL
  SELECT 'Иммунитет и стресс', 'Высокая сезонная нагрузка', 9, 12, 'Ответы показывают выраженную нагрузку на ресурс и восстановление.', 'Сфокусируйтесь на сне, снижении стресса и поддержке иммунитета.', 'immunity', 30
) r ON r.test_title = t.title
LEFT JOIN product_categories pc ON pc.slug = r.category_slug
WHERE NOT EXISTS (
  SELECT 1 FROM test_results existing
  WHERE existing.test_id = t.id
    AND existing.title = r.title
    AND existing.min_score = r.min_score
    AND existing.max_score = r.max_score
);
