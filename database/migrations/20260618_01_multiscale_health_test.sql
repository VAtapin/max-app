CREATE TABLE IF NOT EXISTS test_scales (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_id BIGINT UNSIGNED NOT NULL,
  slug VARCHAR(100) NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_test_scale_slug (test_id, slug),
  INDEX idx_test_scales_test_id (test_id),
  CONSTRAINT fk_test_scales_test
    FOREIGN KEY (test_id) REFERENCES tests(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_answer_scale_scores (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  answer_id BIGINT UNSIGNED NOT NULL,
  scale_id BIGINT UNSIGNED NOT NULL,
  score INT NOT NULL DEFAULT 1,
  UNIQUE KEY uq_answer_scale_score (answer_id, scale_id),
  INDEX idx_test_answer_scale_answer_id (answer_id),
  INDEX idx_test_answer_scale_scale_id (scale_id),
  CONSTRAINT fk_test_answer_scale_answer
    FOREIGN KEY (answer_id) REFERENCES test_answers(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_test_answer_scale_scale
    FOREIGN KEY (scale_id) REFERENCES test_scales(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_scale_results (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scale_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  min_score INT NOT NULL DEFAULT 0,
  max_score INT NOT NULL DEFAULT 0,
  severity ENUM('excellent', 'good', 'risk', 'critical') NOT NULL DEFAULT 'good',
  summary_text TEXT NULL,
  advice_text TEXT NULL,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_test_scale_results_scale_id (scale_id),
  INDEX idx_test_scale_results_score (scale_id, min_score, max_score),
  CONSTRAINT fk_test_scale_results_scale
    FOREIGN KEY (scale_id) REFERENCES test_scales(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_test_scale_scores (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  scale_id BIGINT UNSIGNED NOT NULL,
  score INT NOT NULL DEFAULT 0,
  result_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_test_scale_score (session_id, scale_id),
  INDEX idx_user_test_scale_session_id (session_id),
  INDEX idx_user_test_scale_scale_id (scale_id),
  INDEX idx_user_test_scale_result_id (result_id),
  CONSTRAINT fk_user_test_scale_session
    FOREIGN KEY (session_id) REFERENCES user_test_sessions(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_user_test_scale_scale
    FOREIGN KEY (scale_id) REFERENCES test_scales(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_user_test_scale_result
    FOREIGN KEY (result_id) REFERENCES test_scale_results(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tests (title, description, owner_type, owner_id, is_active, sort_order)
SELECT
  'Диагностика организма',
  'Большой экспресс-тест по 10 системам организма. Отметьте симптомы и привычки, которые вам подходят: система покажет направления, которым стоит уделить внимание, и поможет подготовить персональный разбор с консультантом Siberian Wellness.',
  NULL,
  NULL,
  1,
  5
WHERE NOT EXISTS (SELECT 1 FROM tests WHERE title = 'Диагностика организма');

SET @health_test_id := (SELECT id FROM tests WHERE title = 'Диагностика организма' ORDER BY id LIMIT 1);

CREATE TEMPORARY TABLE IF NOT EXISTS tmp_health_scales (
  source_col INT NOT NULL PRIMARY KEY,
  slug VARCHAR(100) NOT NULL,
  title VARCHAR(190) NOT NULL,
  sort_order INT NOT NULL
) ENGINE=Memory;

DELETE FROM tmp_health_scales;
INSERT INTO tmp_health_scales (source_col, slug, title, sort_order) VALUES
(3, 'digestion', 'Переваривание и усвоение пищи', 10),
(4, 'gastrointestinal', 'Желудочно-кишечный тракт', 20),
(5, 'cardiovascular', 'Сердечно-сосудистая система', 30),
(6, 'nervous', 'Нервная система', 40),
(7, 'immune', 'Иммунная система', 50),
(8, 'respiratory', 'Дыхательная система', 60),
(9, 'urogenital', 'Мочеполовая система', 70),
(10, 'endocrine', 'Эндокринная система', 80),
(11, 'musculoskeletal', 'Опорно-двигательная система', 90),
(12, 'skin', 'Кожа', 100);

INSERT INTO test_scales (test_id, slug, title, description, sort_order)
SELECT @health_test_id, slug, title, 'Оценка по ответам теста диагностики организма.', sort_order
FROM tmp_health_scales s
WHERE @health_test_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM test_scales ts WHERE ts.test_id = @health_test_id AND ts.slug = s.slug
  );

CREATE TEMPORARY TABLE IF NOT EXISTS tmp_health_questions (
  sort_order INT NOT NULL PRIMARY KEY,
  question_text VARCHAR(500) NOT NULL,
  scale_cols VARCHAR(60) NOT NULL
) ENGINE=Memory;

DELETE FROM tmp_health_questions;
INSERT INTO tmp_health_questions (sort_order, question_text, scale_cols) VALUES
(1, 'Недостаток энергии, упадок сил', '3,5,6,7,10'),
(2, 'Любые заболевания более 2 раз в год', '7,12'),
(3, 'Неприятный запах тела или изо рта', '3,4,8,9'),
(4, 'Плохое переваривание некоторых продуктов (чувство тяжести)', '3,7'),
(5, 'Употребление мяса красного цвета более 2 раз в неделю', '4,5,7,8'),
(6, 'Для женщин: проблемы с менструальным циклом', '4,9,10'),
(7, 'Использование антибиотиков (лекарств) более 2 раз в год', '4,7'),
(8, 'Употребление алкоголя (пива) более 1 раза в неделю', '6,10'),
(9, 'Частые перепады настроения', '6,10'),
(10, 'Аллергия любого типа', '3,7,8,12'),
(11, 'Темные круги или отёчность под глазами', '5,6,9,12'),
(12, 'Курение (в т.ч. пассивное)', '5,6,8,12'),
(13, 'Трудности с концентрацией внимания, плохое запоминание', '5,6,10'),
(14, 'Дискомфорт после еды (изжога, газообразование)', '3,7'),
(15, 'Нервная обстановка, частые стрессы', '5,6,7,10,12'),
(16, 'Дефекты кожи или неудовлетворительный цвет кожи лица', '3,4,9,10,11,12'),
(17, 'Потребление сладостей или полуфабрикатов (фастфудов)', '6,10'),
(18, 'Употребление любых молочных продуктов более 2 раз в неделю', '4,8'),
(19, 'Чувство апатии, депрессии (постоянно, либо время от времени)', '4,6,10'),
(20, 'Сон, не приносящий отдыха, или бессонница', '6,10,11'),
(21, 'Для женщин: период менопаузы, «приливы»', '6,10,11'),
(22, 'Проблемы с мочеиспусканием или заболевания мочевого пузыря', '9'),
(23, 'Чувствительная (истончённая) кожа', '12'),
(24, 'Выпадение волос сверх нормы или проблемы с кожей головы', '5,6,10,11'),
(25, 'Боли в суставах, хруст, отечность или онемение конечностей', '5,7,11'),
(26, 'Отклонение от нормального веса', '6,7,10,11'),
(27, 'Быстрая утомляемость', '5,8,11'),
(28, 'Нарушение режима питания (менее 3 раз в день)', '3,4,6,10,12'),
(29, 'Длительное восстановление после болезней', '4,5,7,10'),
(30, 'Нерегулярный стул (опорожнение кишечника менее 3 раз в день)', '3,4,6,12'),
(31, 'Плохой аппетит постоянно, либо время от времени', '3,6,10'),
(32, 'Истончённые и ломкие ногти (слоящиеся ногти)', '3,11'),
(33, 'Повреждённые волосы (сухие или ломкие) или тусклый цвет волос', '3,9'),
(34, 'Употребление жирной пищи более 2 раз в неделю', '3,4,5'),
(35, 'Недостаток клетчатки в рационе (салаты - менее 2 раз в день)', '4,5'),
(36, 'Мышечный дискомфорт (боли, судороги)', '5,6,11'),
(37, 'Проживание или работа в местах с неблагоприятной экологией', '7,8,12'),
(38, 'Дневная сонливость', '5,10'),
(39, 'Употребление в день более 2 чашек колы, кофе или черного чая', '6,10,11'),
(40, 'Чувствительность к химикатам, лекарствам или некоторой пище', '3,4,7'),
(41, 'Грибковые поражения', '3,4,7,9'),
(42, 'Слабость в мышцах или хрупкость костей', '3,11'),
(43, 'Чувство тревоги постоянно, либо время от времени', '3,6'),
(44, 'Повышенная раздражительность, вспыльчивость', '4,6,10'),
(45, 'Малоподвижный образ жизни, низкая физическая активность', '4,5,6,7,10,11'),
(46, 'Повышенное выделение мокроты (выделение слизи)', '4,8'),
(47, 'Большие поры на коже / повышенное потоотделение / угри', '12');

INSERT INTO test_questions (test_id, question_text, question_type, is_required, sort_order)
SELECT @health_test_id, q.question_text, 'single_choice', 1, q.sort_order
FROM tmp_health_questions q
WHERE @health_test_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM test_questions tq
    WHERE tq.test_id = @health_test_id AND tq.question_text = q.question_text
  );

INSERT INTO test_answers (question_id, answer_text, score, sort_order)
SELECT tq.id, 'Да', 1, 10
FROM test_questions tq
JOIN tmp_health_questions q ON q.question_text = tq.question_text
WHERE tq.test_id = @health_test_id
  AND NOT EXISTS (
    SELECT 1 FROM test_answers ta WHERE ta.question_id = tq.id AND ta.answer_text = 'Да'
  );

INSERT INTO test_answers (question_id, answer_text, score, sort_order)
SELECT tq.id, 'Нет', 0, 20
FROM test_questions tq
JOIN tmp_health_questions q ON q.question_text = tq.question_text
WHERE tq.test_id = @health_test_id
  AND NOT EXISTS (
    SELECT 1 FROM test_answers ta WHERE ta.question_id = tq.id AND ta.answer_text = 'Нет'
  );

INSERT IGNORE INTO test_answer_scale_scores (answer_id, scale_id, score)
SELECT ta.id, ts.id, 1
FROM tmp_health_questions q
JOIN test_questions tq ON tq.test_id = @health_test_id AND tq.question_text = q.question_text
JOIN test_answers ta ON ta.question_id = tq.id AND ta.answer_text = 'Да'
JOIN tmp_health_scales s ON FIND_IN_SET(s.source_col, q.scale_cols) > 0
JOIN test_scales ts ON ts.test_id = @health_test_id AND ts.slug = s.slug;

CREATE TEMPORARY TABLE IF NOT EXISTS tmp_health_scale_results (
  source_col INT NOT NULL,
  title VARCHAR(190) NOT NULL,
  min_score INT NOT NULL,
  max_score INT NOT NULL,
  severity VARCHAR(20) NOT NULL,
  sort_order INT NOT NULL
) ENGINE=Memory;

DELETE FROM tmp_health_scale_results;
INSERT INTO tmp_health_scale_results (source_col, title, min_score, max_score, severity, sort_order) VALUES
(3, 'Очень хорошо', 0, 2, 'excellent', 10),
(3, 'Хорошо', 3, 4, 'good', 20),
(3, 'Зона риска', 5, 9, 'risk', 30),
(3, 'Требует внимания специалиста', 10, 999, 'critical', 40),
(4, 'Очень хорошо', 0, 2, 'excellent', 10),
(4, 'Хорошо', 3, 4, 'good', 20),
(4, 'Зона риска', 5, 9, 'risk', 30),
(4, 'Требует внимания специалиста', 10, 999, 'critical', 40),
(5, 'Очень хорошо', 0, 2, 'excellent', 10),
(5, 'Хорошо', 3, 3, 'good', 20),
(5, 'Зона риска', 4, 7, 'risk', 30),
(5, 'Требует внимания специалиста', 8, 999, 'critical', 40),
(6, 'Очень хорошо', 0, 2, 'excellent', 10),
(6, 'Хорошо', 3, 5, 'good', 20),
(6, 'Зона риска', 6, 9, 'risk', 30),
(6, 'Требует внимания специалиста', 10, 999, 'critical', 40),
(7, 'Очень хорошо', 0, 2, 'excellent', 10),
(7, 'Хорошо', 3, 4, 'good', 20),
(7, 'Зона риска', 5, 7, 'risk', 30),
(7, 'Требует внимания специалиста', 8, 999, 'critical', 40),
(8, 'Очень хорошо', 0, 0, 'excellent', 10),
(8, 'Хорошо', 1, 3, 'good', 20),
(8, 'Зона риска', 4, 5, 'risk', 30),
(8, 'Требует внимания специалиста', 6, 999, 'critical', 40),
(9, 'Очень хорошо', 0, 0, 'excellent', 10),
(9, 'Хорошо', 1, 1, 'good', 20),
(9, 'Зона риска', 2, 4, 'risk', 30),
(9, 'Требует внимания специалиста', 5, 999, 'critical', 40),
(10, 'Очень хорошо', 0, 2, 'excellent', 10),
(10, 'Хорошо', 3, 5, 'good', 20),
(10, 'Зона риска', 6, 9, 'risk', 30),
(10, 'Требует внимания специалиста', 10, 999, 'critical', 40),
(11, 'Очень хорошо', 0, 1, 'excellent', 10),
(11, 'Хорошо', 2, 3, 'good', 20),
(11, 'Зона риска', 4, 8, 'risk', 30),
(11, 'Требует внимания специалиста', 9, 999, 'critical', 40),
(12, 'Очень хорошо', 0, 1, 'excellent', 10),
(12, 'Хорошо', 2, 3, 'good', 20),
(12, 'Зона риска', 4, 6, 'risk', 30),
(12, 'Требует внимания специалиста', 7, 999, 'critical', 40);

INSERT INTO test_scale_results (scale_id, title, min_score, max_score, severity, summary_text, advice_text, sort_order)
SELECT
  ts.id,
  r.title,
  r.min_score,
  r.max_score,
  r.severity,
  CASE r.severity
    WHEN 'excellent' THEN CONCAT('По направлению "', ts.title, '" сейчас мало отмеченных сигналов. Это хороший фон для профилактики и поддержания привычек.')
    WHEN 'good' THEN CONCAT('По направлению "', ts.title, '" есть отдельные сигналы. Их лучше не игнорировать, особенно если они повторяются регулярно.')
    WHEN 'risk' THEN CONCAT('По направлению "', ts.title, '" набралась зона риска. Результат стоит разобрать с консультантом и подобрать мягкую поддерживающую программу.')
    ELSE CONCAT('По направлению "', ts.title, '" много отмеченных сигналов. Это не диагноз, но повод не откладывать очную консультацию со специалистом и обсудить поддерживающие шаги с консультантом.')
  END,
  CASE r.severity
    WHEN 'critical' THEN 'Если симптомы выражены или сохраняются, обратитесь к врачу. Консультант поможет подготовить вопросы и подобрать информационные материалы без замены медицинской помощи.'
    WHEN 'risk' THEN 'Напишите консультанту: он поможет расставить приоритеты, объяснит результат и предложит персональный wellness-маршрут.'
    ELSE 'Сохраните результат и обсудите его с консультантом, чтобы выбрать профилактические материалы и продукты под ваши цели.'
  END,
  r.sort_order
FROM tmp_health_scale_results r
JOIN tmp_health_scales s ON s.source_col = r.source_col
JOIN test_scales ts ON ts.test_id = @health_test_id AND ts.slug = s.slug
WHERE NOT EXISTS (
  SELECT 1 FROM test_scale_results existing
  WHERE existing.scale_id = ts.id
    AND existing.min_score = r.min_score
    AND existing.max_score = r.max_score
);

INSERT INTO test_results (test_id, title, min_score, max_score, summary_text, advice_text, sort_order)
SELECT
  @health_test_id,
  'Диагностика пройдена',
  0,
  47,
  'Система рассчитала результат по 10 направлениям организма. В первую очередь обратите внимание на шкалы с повышенными баллами.',
  'Отправьте результат консультанту Siberian Wellness, чтобы получить персональный разбор и подобрать дальнейшие шаги.',
  10
WHERE @health_test_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM test_results WHERE test_id = @health_test_id AND title = 'Диагностика пройдена'
  );

INSERT INTO content_posts (content_type, title, short_text, full_text, status, publish_at)
SELECT 'article',
       'Как читать диагностику организма',
       'Короткая инструкция: результат теста показывает направления для внимания, а не медицинский диагноз.',
       'Диагностика организма помогает увидеть, какие темы чаще всего проявляются в ответах: энергия, пищеварение, кожа, иммунная система, нервная система и другие направления. Высокий балл не является диагнозом и не заменяет врача. Он показывает, что эту тему стоит разобрать внимательнее: образ жизни, питание, сон, стресс, привычки и текущие цели. Консультант поможет спокойно прочитать результат, выделить 1-2 приоритета и предложить материалы для первого шага.',
       'published',
       NOW()
WHERE NOT EXISTS (SELECT 1 FROM content_posts WHERE title = 'Как читать диагностику организма');

INSERT INTO content_posts (content_type, title, short_text, full_text, status, publish_at)
SELECT 'article',
       'Энергия, восстановление и ежедневный ресурс',
       'Материал о том, почему усталость часто связана не с одним фактором, а с режимом, питанием, стрессом и восстановлением.',
       'Если в ответах часто встречаются усталость, сонливость, трудности с концентрацией или долгое восстановление, важно смотреть на картину целиком. На ресурс влияет сон, питание, регулярность приёмов пищи, уровень стресса, физическая активность и привычки. Задача wellness-подхода — не обещать быстрый эффект, а выстроить понятную последовательность маленьких шагов. Консультант поможет выбрать безопасный старт и объяснит, какие продукты Siberian Wellness могут быть уместны как часть ежедневной поддержки.',
       'published',
       NOW()
WHERE NOT EXISTS (SELECT 1 FROM content_posts WHERE title = 'Энергия, восстановление и ежедневный ресурс');

INSERT INTO content_posts (content_type, title, short_text, full_text, status, publish_at)
SELECT 'article',
       'Пищеварение и комфорт после еды',
       'Заготовка материала о пищевых привычках, клетчатке, регулярности питания и внимании к реакции организма.',
       'Пищеварение часто первым реагирует на нерегулярное питание, избыток тяжёлой пищи, недостаток клетчатки, стресс и индивидуальную чувствительность к продуктам. Если тест показывает повышенный балл по этому направлению, полезно обсудить рацион, режим, воду, овощи, белок и привычки после еды. При стойких симптомах нужна консультация врача. В рамках wellness-разбора консультант может предложить образовательные материалы и продукты для мягкой поддержки ежедневного рациона.',
       'published',
       NOW()
WHERE NOT EXISTS (SELECT 1 FROM content_posts WHERE title = 'Пищеварение и комфорт после еды');

INSERT INTO content_posts (content_type, title, short_text, full_text, status, publish_at)
SELECT 'article',
       'Кожа, волосы и внешний вид как зеркало привычек',
       'Материал о связи внешнего вида с питанием, восстановлением, стрессом, уходом и регулярностью wellness-рутины.',
       'Кожа, волосы и ногти могут отражать разные факторы: питание, питьевой режим, сон, стресс, уход, сезонность и индивидуальные особенности. Тест не определяет причину, но помогает заметить, что тема внешнего вида важна для клиента. Хороший следующий шаг — обсудить с консультантом цели: кожа, волосы, энергия, комфорт, красота изнутри и снаружи. После этого можно подобрать материалы и продукты Siberian Wellness в рамках персональной программы.',
       'published',
       NOW()
WHERE NOT EXISTS (SELECT 1 FROM content_posts WHERE title = 'Кожа, волосы и внешний вид как зеркало привычек');

INSERT INTO content_posts (content_type, title, short_text, full_text, status, publish_at)
SELECT 'article',
       'Персональная программа с консультантом',
       'Заготовка материала о том, зачем после теста нужен живой разбор и как строится первый wellness-маршрут.',
       'После большого теста важно не хвататься за всё сразу. Персональная программа начинается с выбора главной цели: энергия, пищеварение, внешний вид, восстановление, привычки или профилактика. Консультант помогает прочитать результат, задать уточняющие вопросы и предложить первый понятный шаг. Если человеку интересен не только личный результат, но и возможность развиваться в команде Siberian Wellness, консультант также расскажет о формате работы, обучении и поддержке.',
       'published',
       NOW()
WHERE NOT EXISTS (SELECT 1 FROM content_posts WHERE title = 'Персональная программа с консультантом');
