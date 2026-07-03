SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

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
'[ИСПОЛНИТЕЛЬ] предлагает предоставить платный доступ к одному кабинету лидера SWPro. Один оплачиваемый тариф предоставляет доступ одному лидеру и его внутренней команде консультантов в пределах возможностей выбранного тарифа.

Стоимость: [СТОИМОСТЬ] рублей в месяц. Оплата на первом этапе подтверждается администратором вручную. Срок доступа указывается в кабинете лидера.

После окончания оплаченного периода доступ к рабочим функциям может быть ограничен, при этом данные не удаляются в течение установленного оператором срока. Условия возврата, порядок оплаты и реквизиты сторон необходимо заполнить до публикации оферты.',
1, 1);

INSERT INTO settings (setting_key, setting_value, description) VALUES
('legal_operator_name', '[ОПЕРАТОР]', 'Наименование или ФИО оператора персональных данных'),
('legal_operator_inn', '[ИНН]', 'ИНН оператора'),
('legal_operator_address', '[АДРЕС]', 'Адрес оператора'),
('legal_operator_email', '[EMAIL]', 'Email для обращений по персональным данным'),
('leader_monthly_price', NULL, 'Фиксированная ежемесячная стоимость одного места лидера'),
('automation_timezone', 'Europe/Moscow', 'Часовой пояс автоматических сообщений');

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
('Базовый тест самочувствия', 'Короткий тест для подбора направлений поддержки.', NULL, 0, 10);

INSERT INTO test_questions (test_id, question_text, question_type, is_required, sort_order) VALUES
((SELECT id FROM tests WHERE title = 'Базовый тест самочувствия'), 'Что сейчас беспокоит больше всего?', 'single_choice', 1, 10),
((SELECT id FROM tests WHERE title = 'Базовый тест самочувствия'), 'Как вы оцениваете уровень энергии?', 'scale', 1, 20);

INSERT INTO test_answers (question_id, answer_text, score, tag_id, category_id, sort_order) VALUES
((SELECT id FROM test_questions WHERE question_text = 'Что сейчас беспокоит больше всего?'), 'Частая усталость', 5, (SELECT id FROM tags WHERE slug = 'fatigue'), (SELECT id FROM product_categories WHERE slug = 'energy'), 10),
((SELECT id FROM test_questions WHERE question_text = 'Что сейчас беспокоит больше всего?'), 'Сложно высыпаться', 5, (SELECT id FROM tags WHERE slug = 'sleep'), (SELECT id FROM product_categories WHERE slug = 'sleep'), 20),
((SELECT id FROM test_questions WHERE question_text = 'Что сейчас беспокоит больше всего?'), 'Хочу поддержать иммунитет', 5, (SELECT id FROM tags WHERE slug = 'immune-support'), (SELECT id FROM product_categories WHERE slug = 'immunity'), 30);

INSERT INTO tags (title, slug, description) VALUES
('Кожа и красота', 'skin-beauty', 'Ответы, связанные с состоянием кожи, волос и внешнего вида.'),
('Питание', 'nutrition', 'Ответы, связанные с рационом, регулярностью питания и микронутриентами.'),
('Стресс', 'stress', 'Ответы, связанные с напряжением, восстановлением и эмоциональной нагрузкой.');

INSERT INTO tests (title, description, category_id, is_active, sort_order) VALUES
('Энергия и усталость', 'Помогает понять, где чаще всего проседает ресурс: сон, питание, нагрузка или восстановление.', (SELECT id FROM product_categories WHERE slug = 'energy'), 0, 20),
('Сон и восстановление', 'Короткий опрос о вечернем режиме, качестве сна и ощущении восстановления утром.', (SELECT id FROM product_categories WHERE slug = 'sleep'), 0, 30),
('Красота кожи и волос', 'Оценка базовых факторов, которые могут влиять на внешний вид кожи, волос и общее ощущение красоты.', (SELECT id FROM product_categories WHERE slug = 'skin'), 0, 40),
('Питание и микронутриенты', 'Опрос о регулярности питания, разнообразии рациона и возможной потребности в дополнительной поддержке.', (SELECT id FROM product_categories WHERE slug = 'vitamins'), 0, 50),
('Иммунитет и стресс', 'Помогает оценить сезонную нагрузку, стресс и привычки восстановления.', (SELECT id FROM product_categories WHERE slug = 'immunity'), 0, 60);

INSERT INTO test_questions (test_id, question_text, question_type, is_required, sort_order) VALUES
((SELECT id FROM tests WHERE title = 'Энергия и усталость'), 'Когда вы чаще всего чувствуете спад энергии?', 'single_choice', 1, 10),
((SELECT id FROM tests WHERE title = 'Энергия и усталость'), 'Как обычно выглядит ваш завтрак или первый приём пищи?', 'single_choice', 1, 20),
((SELECT id FROM tests WHERE title = 'Энергия и усталость'), 'Что лучше всего описывает ваш день?', 'single_choice', 1, 30),
((SELECT id FROM tests WHERE title = 'Сон и восстановление'), 'Как быстро вы обычно засыпаете?', 'single_choice', 1, 10),
((SELECT id FROM tests WHERE title = 'Сон и восстановление'), 'Как вы чувствуете себя утром?', 'single_choice', 1, 20),
((SELECT id FROM tests WHERE title = 'Сон и восстановление'), 'Что чаще всего мешает вечернему режиму?', 'single_choice', 1, 30),
((SELECT id FROM tests WHERE title = 'Красота кожи и волос'), 'Что сейчас больше всего беспокоит во внешнем виде?', 'single_choice', 1, 10),
((SELECT id FROM tests WHERE title = 'Красота кожи и волос'), 'Как часто в рационе есть белок, овощи и полезные жиры?', 'single_choice', 1, 20),
((SELECT id FROM tests WHERE title = 'Красота кожи и волос'), 'Как кожа реагирует на стресс или недосып?', 'single_choice', 1, 30),
((SELECT id FROM tests WHERE title = 'Питание и микронутриенты'), 'Насколько разнообразен ваш рацион в течение недели?', 'single_choice', 1, 10),
((SELECT id FROM tests WHERE title = 'Питание и микронутриенты'), 'Как часто вы пропускаете приёмы пищи?', 'single_choice', 1, 20),
((SELECT id FROM tests WHERE title = 'Питание и микронутриенты'), 'Что вы хотите улучшить в питании в первую очередь?', 'single_choice', 1, 30),
((SELECT id FROM tests WHERE title = 'Иммунитет и стресс'), 'Как часто вы чувствуете, что организм работает на пределе?', 'single_choice', 1, 10),
((SELECT id FROM tests WHERE title = 'Иммунитет и стресс'), 'Как вы восстанавливаетесь после напряжённой недели?', 'single_choice', 1, 20),
((SELECT id FROM tests WHERE title = 'Иммунитет и стресс'), 'Что чаще всего проседает в сезон нагрузки?', 'single_choice', 1, 30);

INSERT INTO test_answers (question_id, answer_text, score, tag_id, category_id, sort_order) VALUES
((SELECT id FROM test_questions WHERE question_text = 'Когда вы чаще всего чувствуете спад энергии?'), 'Почти сразу утром', 4, (SELECT id FROM tags WHERE slug = 'fatigue'), (SELECT id FROM product_categories WHERE slug = 'energy'), 10),
((SELECT id FROM test_questions WHERE question_text = 'Когда вы чаще всего чувствуете спад энергии?'), 'После обеда', 3, (SELECT id FROM tags WHERE slug = 'nutrition'), (SELECT id FROM product_categories WHERE slug = 'energy'), 20),
((SELECT id FROM test_questions WHERE question_text = 'Когда вы чаще всего чувствуете спад энергии?'), 'К вечеру', 2, (SELECT id FROM tags WHERE slug = 'stress'), (SELECT id FROM product_categories WHERE slug = 'energy'), 30),
((SELECT id FROM test_questions WHERE question_text = 'Когда вы чаще всего чувствуете спад энергии?'), 'Редко, энергии обычно хватает', 0, NULL, (SELECT id FROM product_categories WHERE slug = 'energy'), 40),
((SELECT id FROM test_questions WHERE question_text = 'Как обычно выглядит ваш завтрак или первый приём пищи?'), 'Часто пропускаю', 4, (SELECT id FROM tags WHERE slug = 'nutrition'), (SELECT id FROM product_categories WHERE slug = 'vitamins'), 10),
((SELECT id FROM test_questions WHERE question_text = 'Как обычно выглядит ваш завтрак или первый приём пищи?'), 'Кофе и что-то быстрое', 3, (SELECT id FROM tags WHERE slug = 'nutrition'), (SELECT id FROM product_categories WHERE slug = 'energy'), 20),
((SELECT id FROM test_questions WHERE question_text = 'Как обычно выглядит ваш завтрак или первый приём пищи?'), 'Есть белок и нормальная еда', 1, NULL, (SELECT id FROM product_categories WHERE slug = 'energy'), 30),
((SELECT id FROM test_questions WHERE question_text = 'Как обычно выглядит ваш завтрак или первый приём пищи?'), 'Питаюсь регулярно и разнообразно', 0, NULL, (SELECT id FROM product_categories WHERE slug = 'energy'), 40),
((SELECT id FROM test_questions WHERE question_text = 'Что лучше всего описывает ваш день?'), 'Много стресса и мало пауз', 4, (SELECT id FROM tags WHERE slug = 'stress'), (SELECT id FROM product_categories WHERE slug = 'energy'), 10),
((SELECT id FROM test_questions WHERE question_text = 'Что лучше всего описывает ваш день?'), 'Много сидячей работы', 3, (SELECT id FROM tags WHERE slug = 'fatigue'), (SELECT id FROM product_categories WHERE slug = 'energy'), 20),
((SELECT id FROM test_questions WHERE question_text = 'Что лучше всего описывает ваш день?'), 'Активность есть, но восстановление слабое', 2, (SELECT id FROM tags WHERE slug = 'sleep'), (SELECT id FROM product_categories WHERE slug = 'sleep'), 30),
((SELECT id FROM test_questions WHERE question_text = 'Что лучше всего описывает ваш день?'), 'Режим в целом стабильный', 0, NULL, (SELECT id FROM product_categories WHERE slug = 'energy'), 40),

((SELECT id FROM test_questions WHERE question_text = 'Как быстро вы обычно засыпаете?'), 'Долго не могу уснуть', 4, (SELECT id FROM tags WHERE slug = 'sleep'), (SELECT id FROM product_categories WHERE slug = 'sleep'), 10),
((SELECT id FROM test_questions WHERE question_text = 'Как быстро вы обычно засыпаете?'), 'По-разному, зависит от дня', 2, (SELECT id FROM tags WHERE slug = 'stress'), (SELECT id FROM product_categories WHERE slug = 'sleep'), 20),
((SELECT id FROM test_questions WHERE question_text = 'Как быстро вы обычно засыпаете?'), 'Обычно быстро', 0, NULL, (SELECT id FROM product_categories WHERE slug = 'sleep'), 30),
((SELECT id FROM test_questions WHERE question_text = 'Как вы чувствуете себя утром?'), 'Просыпаюсь разбитым', 4, (SELECT id FROM tags WHERE slug = 'sleep'), (SELECT id FROM product_categories WHERE slug = 'sleep'), 10),
((SELECT id FROM test_questions WHERE question_text = 'Как вы чувствуете себя утром?'), 'Нужно много времени, чтобы включиться', 3, (SELECT id FROM tags WHERE slug = 'fatigue'), (SELECT id FROM product_categories WHERE slug = 'energy'), 20),
((SELECT id FROM test_questions WHERE question_text = 'Как вы чувствуете себя утром?'), 'В целом нормально', 1, NULL, (SELECT id FROM product_categories WHERE slug = 'sleep'), 30),
((SELECT id FROM test_questions WHERE question_text = 'Что чаще всего мешает вечернему режиму?'), 'Телефон, новости, работа до позднего вечера', 4, (SELECT id FROM tags WHERE slug = 'stress'), (SELECT id FROM product_categories WHERE slug = 'sleep'), 10),
((SELECT id FROM test_questions WHERE question_text = 'Что чаще всего мешает вечернему режиму?'), 'Поздняя еда или нерегулярный график', 3, (SELECT id FROM tags WHERE slug = 'nutrition'), (SELECT id FROM product_categories WHERE slug = 'digestion'), 20),
((SELECT id FROM test_questions WHERE question_text = 'Что чаще всего мешает вечернему режиму?'), 'Тревожные мысли', 3, (SELECT id FROM tags WHERE slug = 'stress'), (SELECT id FROM product_categories WHERE slug = 'sleep'), 30),
((SELECT id FROM test_questions WHERE question_text = 'Что чаще всего мешает вечернему режиму?'), 'Особых проблем нет', 0, NULL, (SELECT id FROM product_categories WHERE slug = 'sleep'), 40),

((SELECT id FROM test_questions WHERE question_text = 'Что сейчас больше всего беспокоит во внешнем виде?'), 'Сухость, тусклость кожи', 4, (SELECT id FROM tags WHERE slug = 'skin-beauty'), (SELECT id FROM product_categories WHERE slug = 'skin'), 10),
((SELECT id FROM test_questions WHERE question_text = 'Что сейчас больше всего беспокоит во внешнем виде?'), 'Волосы и ногти стали слабее', 4, (SELECT id FROM tags WHERE slug = 'skin-beauty'), (SELECT id FROM product_categories WHERE slug = 'vitamins'), 20),
((SELECT id FROM test_questions WHERE question_text = 'Что сейчас больше всего беспокоит во внешнем виде?'), 'Отёчность или усталый вид', 3, (SELECT id FROM tags WHERE slug = 'fatigue'), (SELECT id FROM product_categories WHERE slug = 'skin'), 30),
((SELECT id FROM test_questions WHERE question_text = 'Что сейчас больше всего беспокоит во внешнем виде?'), 'Хочу профилактически поддержать уход', 1, NULL, (SELECT id FROM product_categories WHERE slug = 'skin'), 40),
((SELECT id FROM test_questions WHERE question_text = 'Как часто в рационе есть белок, овощи и полезные жиры?'), 'Редко, питание хаотичное', 4, (SELECT id FROM tags WHERE slug = 'nutrition'), (SELECT id FROM product_categories WHERE slug = 'vitamins'), 10),
((SELECT id FROM test_questions WHERE question_text = 'Как часто в рационе есть белок, овощи и полезные жиры?'), 'Иногда, но не каждый день', 3, (SELECT id FROM tags WHERE slug = 'nutrition'), (SELECT id FROM product_categories WHERE slug = 'skin'), 20),
((SELECT id FROM test_questions WHERE question_text = 'Как часто в рационе есть белок, овощи и полезные жиры?'), 'Почти каждый день', 1, NULL, (SELECT id FROM product_categories WHERE slug = 'skin'), 30),
((SELECT id FROM test_questions WHERE question_text = 'Как кожа реагирует на стресс или недосып?'), 'Сразу появляются высыпания или раздражение', 4, (SELECT id FROM tags WHERE slug = 'stress'), (SELECT id FROM product_categories WHERE slug = 'skin'), 10),
((SELECT id FROM test_questions WHERE question_text = 'Как кожа реагирует на стресс или недосып?'), 'Становится тусклой и чувствительной', 3, (SELECT id FROM tags WHERE slug = 'skin-beauty'), (SELECT id FROM product_categories WHERE slug = 'skin'), 20),
((SELECT id FROM test_questions WHERE question_text = 'Как кожа реагирует на стресс или недосып?'), 'Почти не реагирует', 0, NULL, (SELECT id FROM product_categories WHERE slug = 'skin'), 30),

((SELECT id FROM test_questions WHERE question_text = 'Насколько разнообразен ваш рацион в течение недели?'), 'Часто одни и те же продукты', 4, (SELECT id FROM tags WHERE slug = 'nutrition'), (SELECT id FROM product_categories WHERE slug = 'vitamins'), 10),
((SELECT id FROM test_questions WHERE question_text = 'Насколько разнообразен ваш рацион в течение недели?'), 'Разнообразие есть, но не хватает системы', 2, (SELECT id FROM tags WHERE slug = 'nutrition'), (SELECT id FROM product_categories WHERE slug = 'vitamins'), 20),
((SELECT id FROM test_questions WHERE question_text = 'Насколько разнообразен ваш рацион в течение недели?'), 'Стараюсь держать баланс', 1, NULL, (SELECT id FROM product_categories WHERE slug = 'vitamins'), 30),
((SELECT id FROM test_questions WHERE question_text = 'Как часто вы пропускаете приёмы пищи?'), 'Почти каждый день', 4, (SELECT id FROM tags WHERE slug = 'nutrition'), (SELECT id FROM product_categories WHERE slug = 'energy'), 10),
((SELECT id FROM test_questions WHERE question_text = 'Как часто вы пропускаете приёмы пищи?'), 'Несколько раз в неделю', 3, (SELECT id FROM tags WHERE slug = 'nutrition'), (SELECT id FROM product_categories WHERE slug = 'vitamins'), 20),
((SELECT id FROM test_questions WHERE question_text = 'Как часто вы пропускаете приёмы пищи?'), 'Редко', 0, NULL, (SELECT id FROM product_categories WHERE slug = 'vitamins'), 30),
((SELECT id FROM test_questions WHERE question_text = 'Что вы хотите улучшить в питании в первую очередь?'), 'Больше энергии и меньше тяги к сладкому', 4, (SELECT id FROM tags WHERE slug = 'fatigue'), (SELECT id FROM product_categories WHERE slug = 'energy'), 10),
((SELECT id FROM test_questions WHERE question_text = 'Что вы хотите улучшить в питании в первую очередь?'), 'Поддержать кожу, волосы, ногти', 3, (SELECT id FROM tags WHERE slug = 'skin-beauty'), (SELECT id FROM product_categories WHERE slug = 'skin'), 20),
((SELECT id FROM test_questions WHERE question_text = 'Что вы хотите улучшить в питании в первую очередь?'), 'Собрать базовый комплекс витаминов', 3, (SELECT id FROM tags WHERE slug = 'nutrition'), (SELECT id FROM product_categories WHERE slug = 'vitamins'), 30),

((SELECT id FROM test_questions WHERE question_text = 'Как часто вы чувствуете, что организм работает на пределе?'), 'Почти постоянно', 4, (SELECT id FROM tags WHERE slug = 'stress'), (SELECT id FROM product_categories WHERE slug = 'immunity'), 10),
((SELECT id FROM test_questions WHERE question_text = 'Как часто вы чувствуете, что организм работает на пределе?'), 'В периоды нагрузки', 3, (SELECT id FROM tags WHERE slug = 'immune-support'), (SELECT id FROM product_categories WHERE slug = 'immunity'), 20),
((SELECT id FROM test_questions WHERE question_text = 'Как часто вы чувствуете, что организм работает на пределе?'), 'Редко', 0, NULL, (SELECT id FROM product_categories WHERE slug = 'immunity'), 30),
((SELECT id FROM test_questions WHERE question_text = 'Как вы восстанавливаетесь после напряжённой недели?'), 'Плохо, усталость накапливается', 4, (SELECT id FROM tags WHERE slug = 'fatigue'), (SELECT id FROM product_categories WHERE slug = 'energy'), 10),
((SELECT id FROM test_questions WHERE question_text = 'Как вы восстанавливаетесь после напряжённой недели?'), 'Помогает сон, но его не хватает', 3, (SELECT id FROM tags WHERE slug = 'sleep'), (SELECT id FROM product_categories WHERE slug = 'sleep'), 20),
((SELECT id FROM test_questions WHERE question_text = 'Как вы восстанавливаетесь после напряжённой недели?'), 'Есть свои ритуалы восстановления', 1, NULL, (SELECT id FROM product_categories WHERE slug = 'immunity'), 30),
((SELECT id FROM test_questions WHERE question_text = 'Что чаще всего проседает в сезон нагрузки?'), 'Чаще простужаюсь или дольше восстанавливаюсь', 4, (SELECT id FROM tags WHERE slug = 'immune-support'), (SELECT id FROM product_categories WHERE slug = 'immunity'), 10),
((SELECT id FROM test_questions WHERE question_text = 'Что чаще всего проседает в сезон нагрузки?'), 'Энергия и настроение', 3, (SELECT id FROM tags WHERE slug = 'fatigue'), (SELECT id FROM product_categories WHERE slug = 'energy'), 20),
((SELECT id FROM test_questions WHERE question_text = 'Что чаще всего проседает в сезон нагрузки?'), 'Сон и спокойствие', 3, (SELECT id FROM tags WHERE slug = 'sleep'), (SELECT id FROM product_categories WHERE slug = 'sleep'), 30);

INSERT INTO test_results (test_id, title, min_score, max_score, summary_text, advice_text, category_id, sort_order) VALUES
((SELECT id FROM tests WHERE title = 'Энергия и усталость'), 'Ресурс в целом стабильный', 0, 3, 'Сейчас нет ярко выраженного сигнала по энергии.', 'Поддерживайте режим сна, воды, регулярное питание и мягкую активность.', (SELECT id FROM product_categories WHERE slug = 'energy'), 10),
((SELECT id FROM tests WHERE title = 'Энергия и усталость'), 'Нужна мягкая поддержка энергии', 4, 8, 'Есть признаки нерегулярного восстановления или питания.', 'Обсудите с консультантом базовую поддержку энергии, режима и микронутриентов.', (SELECT id FROM product_categories WHERE slug = 'energy'), 20),
((SELECT id FROM tests WHERE title = 'Энергия и усталость'), 'Высокая нагрузка на ресурс', 9, 12, 'Ответы показывают, что организм часто работает в режиме дефицита восстановления.', 'Начните с режима сна, питания и консультации по поддержке энергии.', (SELECT id FROM product_categories WHERE slug = 'energy'), 30),
((SELECT id FROM tests WHERE title = 'Сон и восстановление'), 'Сон выглядит стабильным', 0, 3, 'Серьёзных сигналов по восстановлению сейчас немного.', 'Сохраняйте вечерний режим и регулярность сна.', (SELECT id FROM product_categories WHERE slug = 'sleep'), 10),
((SELECT id FROM tests WHERE title = 'Сон и восстановление'), 'Восстановление требует внимания', 4, 8, 'Есть факторы, которые могут мешать качественному отдыху.', 'Обсудите вечерние привычки, стресс и мягкую поддержку сна.', (SELECT id FROM product_categories WHERE slug = 'sleep'), 20),
((SELECT id FROM tests WHERE title = 'Сон и восстановление'), 'Сон может быть ключевой точкой', 9, 12, 'Ответы показывают выраженный запрос на восстановление.', 'Начните с вечернего режима и консультации по поддержке сна.', (SELECT id FROM product_categories WHERE slug = 'sleep'), 30),
((SELECT id FROM tests WHERE title = 'Красота кожи и волос'), 'Профилактический уход', 0, 3, 'Сейчас запрос скорее поддерживающий.', 'Поддерживайте регулярный уход, воду, белок и базовый рацион.', (SELECT id FROM product_categories WHERE slug = 'skin'), 10),
((SELECT id FROM tests WHERE title = 'Красота кожи и волос'), 'Коже нужна системная поддержка', 4, 8, 'Есть связь между внешним видом, питанием и восстановлением.', 'Обсудите уход, питание и поддержку кожи изнутри.', (SELECT id FROM product_categories WHERE slug = 'skin'), 20),
((SELECT id FROM tests WHERE title = 'Красота кожи и волос'), 'Красота зависит от общего ресурса', 9, 12, 'Ответы показывают, что внешний вид может страдать из-за стресса, питания или сна.', 'Начните с комплексного подхода: рацион, сон, уход и консультация.', (SELECT id FROM product_categories WHERE slug = 'skin'), 30),
((SELECT id FROM tests WHERE title = 'Питание и микронутриенты'), 'Рацион в целом устойчивый', 0, 3, 'Базовые привычки выглядят достаточно стабильными.', 'Можно обсудить профилактическую поддержку и сезонные задачи.', (SELECT id FROM product_categories WHERE slug = 'vitamins'), 10),
((SELECT id FROM tests WHERE title = 'Питание и микронутриенты'), 'Есть точки для улучшения рациона', 4, 8, 'Есть признаки нерегулярности или недостатка разнообразия.', 'С консультантом можно собрать понятный план питания и базовой поддержки.', (SELECT id FROM product_categories WHERE slug = 'vitamins'), 20),
((SELECT id FROM tests WHERE title = 'Питание и микронутриенты'), 'Питание сильно влияет на запрос', 9, 12, 'Ответы показывают, что питание может быть центральной причиной самочувствия.', 'Начните с регулярности питания, воды и подбора микронутриентной поддержки.', (SELECT id FROM product_categories WHERE slug = 'vitamins'), 30),
((SELECT id FROM tests WHERE title = 'Иммунитет и стресс'), 'Сезонная поддержка', 0, 3, 'Сейчас достаточно профилактического подхода.', 'Сохраняйте сон, активность и базовые привычки восстановления.', (SELECT id FROM product_categories WHERE slug = 'immunity'), 10),
((SELECT id FROM tests WHERE title = 'Иммунитет и стресс'), 'Нагрузка заметна', 4, 8, 'Стресс и восстановление могут влиять на устойчивость организма.', 'Обсудите с консультантом поддержку иммунитета и восстановительные привычки.', (SELECT id FROM product_categories WHERE slug = 'immunity'), 20),
((SELECT id FROM tests WHERE title = 'Иммунитет и стресс'), 'Высокая сезонная нагрузка', 9, 12, 'Ответы показывают выраженную нагрузку на ресурс и восстановление.', 'Сфокусируйтесь на сне, снижении стресса и поддержке иммунитета.', (SELECT id FROM product_categories WHERE slug = 'immunity'), 30);

INSERT INTO help_faq_sections (title, body, items_json, is_featured, sort_order) VALUES
('Как пользоваться SWPro', 'SWPro помогает консультанту вести клиентов из Telegram, VK, OK и персонального мини-сайта. Клиент проходит чек-ап и при необходимости обращается к закреплённому консультанту.', NULL, 1, 10),
('Главная идея', 'В центре системы находятся бесплатный чек-ап и личная связь клиента с консультантом.', JSON_ARRAY(
  'Заполните раздел «Моя страница».',
  'Добавьте приветствие, фото или видео, контакты, ссылку на карту клиента и описание сотрудничества.',
  'Разместите историю, результаты, акции, розыгрыши, программы и марафоны.'
), 0, 20),
('Обращения', 'Обращение появляется, когда клиент просит связаться с консультантом.', JSON_ARRAY(
  'Откройте раздел «Обращения».',
  'Выберите заявку и отправьте ответ.',
  'К ответу можно прикрепить текст, материал, тест и файлы.',
  'Для VK и OK ответ показывается внутри Mini App как новый ответ.'
), 0, 30),
('Чек-ап', 'Многошкальный чек-ап показывает клиенту информационную карту по системам организма и передаёт результат консультанту.', JSON_ARRAY(
  'Редактируйте вопросы и варианты ответов в разделе «Тесты».',
  'Для матричного теста отмечайте шкалы, к которым относится ответ.',
  'Настройте четыре диапазона результата для каждой шкалы.',
  'После прохождения клиент сможет запросить персональный разбор.'
), 0, 40),
('Материалы мини-сайта', 'Материалы формируют необязательную персональную страницу консультанта.', JSON_ARRAY(
  'Загружайте изображения и файлы через форму материала.',
  'Выберите раздел: история, результат, акция, розыгрыш, программа или марафон.',
  'Новый файл заменяет текущий.',
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

INSERT INTO settings (setting_key, setting_value, description) VALUES
('medical_disclaimer', 'Информация носит ознакомительный характер и не является медицинской рекомендацией. При вопросах о здоровье проконсультируйтесь со специалистом.', 'Дисклеймер для интерфейса и результатов чек-апа.'),
('project_name', 'SWPro Assistant', 'Название проекта.');
