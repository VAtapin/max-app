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
'[ИСПОЛНИТЕЛЬ] предлагает предоставить платный доступ к кабинету лидера SWPro. Стоимость доступа рассчитывается по выбранной подписке и фактическому количеству активных лидеров и консультантов в выбранном периоде.

Стоимость: [СТОИМОСТЬ] рублей в месяц по фактическому начислению. Оплата на первом этапе подтверждается администратором вручную. Срок доступа указывается в кабинете лидера.

После окончания оплаченного периода доступ к рабочим функциям может быть ограничен, при этом данные не удаляются в течение установленного оператором срока. Условия возврата, порядок оплаты и реквизиты сторон необходимо заполнить до публикации оферты.',
1, 1);

INSERT INTO settings (setting_key, setting_value, description) VALUES
('legal_operator_name', '[ОПЕРАТОР]', 'Наименование или ФИО оператора персональных данных'),
('legal_operator_inn', '[ИНН]', 'ИНН оператора'),
('legal_operator_address', '[АДРЕС]', 'Адрес оператора'),
('legal_operator_email', '[EMAIL]', 'Email для обращений по персональным данным'),
('leader_monthly_price', NULL, 'Устаревшее поле совместимости: текущая цена считается по подписке и фактической структуре'),
('leader_price_per_consultant', '300', 'Базовая ежемесячная стоимость одного консультанта в команде лидера'),
('leader_payment_terms', 'Оплата подтверждается администратором вручную. Онлайн-касса на первом этапе не подключена.', 'Короткая подсказка для бухгалтерской панели лидеров'),
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
('Как работает SWPro', 'SWPro ведет клиента от персональной реферальной ссылки до чек-апа, результата, заявки и живого контакта с консультантом. Система не заменяет консультанта: она помогает собрать данные, показать нужные материалы и не потерять обращение.', NULL, 1, 10),
('Роли и доступ', 'В системе есть четыре основные роли: супер-админ, лидер, консультант и клиент. Каждый видит только тот уровень, который относится к его работе.', JSON_ARRAY(
  'Супер-админ управляет всей системой, лидерами, тарифами, стартовым контентом и настройками.',
  'Лидер управляет своей командой консультантов и видит клиентов своей структуры.',
  'Консультант работает со своими клиентами, заявками, материалами, чек-апами и рассылками.',
  'Клиент проходит чек-ап, читает материалы и обращается к закрепленному консультанту.'
), 0, 20),
('Реферальные ссылки', 'Клиент закрепляется за консультантом или лидером только по персональной реферальной ссылке или по введенному реферальному коду. Автоматической привязки к случайному консультанту быть не должно.', JSON_ARRAY(
  'Если клиент пришел впервые по ссылке консультанта, он закрепляется за этим консультантом.',
  'Если клиент пришел по ссылке лидера, он попадает в структуру лидера без конкретного консультанта, пока его не назначат.',
  'Если клиент уже закреплен, новая ссылка не должна молча менять его консультанта.',
  'Реферальный код можно ввести вручную, если пользователь открыл сайт или Mini App без параметра ссылки.'
), 0, 30),
('Стартовый и личный контент', 'Категории продуктов, продукты, тесты, материалы, рассылки и блоки персонального сайта могут начинаться как общий стартовый контент. Когда лидер или консультант меняет общий объект, для него создается личная копия.', JSON_ARRAY(
  'Стартовый контент задает супер-админ.',
  'Лидер может настроить контент для своей команды, не меняя общий шаблон системы.',
  'Консультант может изменить, добавить или удалить контент только у себя.',
  'Удаление общего объекта лидером или консультантом скрывает его только у этого владельца.',
  'Правки консультанта имеют приоритет над командной версией лидера.'
), 0, 40),
('Чек-апы и результаты', 'Главный сценарий для клиента: пройти чек-ап организма, увидеть понятный результат и при необходимости запросить персональный разбор у консультанта.', JSON_ARRAY(
  'Многошкальный чек-ап считает результат по нескольким направлениям организма.',
  'Клиент видит прогресс прохождения и может продолжить незавершенный тест.',
  'Если тест уже пройден, клиенту показываются кнопки посмотреть результат или пройти заново.',
  'Результат доступен консультанту в разделе результатов чек-апов и в карточке клиента.',
  'Продукция не должна навязываться автоматически: основной следующий шаг - связь с консультантом.'
), 0, 50),
('Клиенты и платформы', 'Один человек может прийти из Telegram, VK, OK или обычного Web-входа. В карточке клиента видны подключенные платформы и анкета.', JSON_ARRAY(
  'При первом входе клиент должен заполнить обязательные данные: имя, пол и город; фамилия, возраст или дата рождения уточняются по возможности.',
  'Если платформа отдала настоящее имя и фамилию, система может подставить их в форму.',
  'Технические имена вроде Web User или VK User не должны считаться настоящими данными.',
  'Объединение похожих аккаунтов делается только после подтверждения пользователя или вручную администратором.',
  'Простой запуск бота без реферальной привязки не должен смешиваться с полноценными клиентами консультанта.'
), 0, 60),
('Обращения', 'Обращение появляется, когда клиент просит связаться с консультантом, задает вопрос или отправляет заявку после чек-апа.', JSON_ARRAY(
  'Консультант видит обращение в админке.',
  'Уведомление также может прийти консультанту в Telegram, если у него указан Telegram ID.',
  'Ответ из админки или ответом на Telegram-уведомление сохраняется в истории обращения.',
  'Для Telegram ответ уходит пользователю в Telegram.',
  'Для VK и OK ответ отправляется от имени подключенного сообщества, если клиент разрешил сообщения.'
), 0, 70),
('Рассылки', 'Рассылки нужны для поддержания связи со своей базой клиентов или командой. Они отправляются только в рамках доступной аудитории владельца.', JSON_ARRAY(
  'Консультант работает со своей базой клиентов.',
  'Лидер может делать рассылки по своей структуре.',
  'В рассылку можно добавлять текст, изображение, видео и кнопку-ссылку.',
  'Для Telegram используется бот.',
  'Для VK и OK нужна активная интеграция сообщества или группы и разрешение пользователя получать сообщения.'
), 0, 80),
('Мой мини-сайт', 'Мини-сайт консультанта или лидера - это персональная витрина, которую можно заполнять постепенно. Пустые блоки не обязательны.', JSON_ARRAY(
  'Заполните фото, короткое описание, историю, контакты и ссылки на мессенджеры.',
  'Добавляйте акции, розыгрыши, программы, марафоны, материалы и результаты.',
  'Выберите тесты, материалы и продукты, которые нужно показывать клиентам.',
  'Если вы редактируете стартовый материал, создается ваша личная версия.',
  'Публичная страница и Mini App должны показывать контент закрепленного консультанта.'
), 0, 90),
('Подключения VK и OK', 'Раздел Подключения нужен для отправки ответов и рассылок через сообщества VK и группы OK. Это не личная страница консультанта: сообщения уходят от имени сообщества или группы.', JSON_ARRAY(
  'Создавайте подключение на владельца: лидера или консультанта.',
  'Платформа: VK или OK.',
  'Название: понятное имя подключения, например Сообщество Марии VK.',
  'group_id: число из JSON в блоке подтверждения Callback API VK или ID группы OK.',
  'Ключ доступа: токен сообщества VK или токен Bot API группы OK.',
  'Если у консультанта нет своего подключения, система может использовать подключение лидера его команды.'
), 0, 100),
('Как подключить VK-сообщество', 'Для VK нужен ключ доступа сообщества и Callback API. Ключ доступа отправляет ответы клиентам, а Callback API принимает входящие сообщения из лички сообщества.', JSON_ARRAY(
  'В VK откройте свое сообщество и перейдите в Управление.',
  'В Управление -> Сообщения включите сообщения сообщества и задайте приветствие: Здравствуйте! Здесь можно пройти чек-ап и получить ответ консультанта. Нажмите «Начать» или откройте приложение SWPro.',
  'В Настройки для бота включите возможности ботов и кнопку Начать.',
  'Перейдите в Управление -> Дополнительно -> Работа с API -> Ключи доступа.',
  'Создайте ключ доступа с правами: управление сообществом, сообщения сообщества, фотографии, документы, истории и стена.',
  'В Callback API укажите адрес https://swpro.ru/api/vk_callback.php и версию API 5.199.',
  'Скопируйте из VK group_id и строку, которую должен вернуть сервер. Секретный ключ создается в SWPro автоматически: скопируйте его из подключения в VK.',
  'В Типах событий отметьте Входящее сообщение, Действие с сообщением, Разрешение на получение, Запрет на получение и Прочитанность сообщений.',
  'После сохранения подключения нажмите Подтвердить в VK. Сервер должен вернуть строку, которую показал VK.'
), 0, 110),
('Как подключить группу OK', 'Для OK используется Bot API группы. Группа должна разрешать сообщения, а пользователь должен разрешить отправку сообщений от группы.', JSON_ARRAY(
  'В OK откройте группу на desktop-версии и перейдите в настройки сообщений группы.',
  'Включите сообщения группы для гостей и участников или хотя бы для участников, если группа закрытая.',
  'В этом же разделе нажмите Получить или Сгенерировать ключ доступа.',
  'Скопируйте ID группы из адреса ok.ru/group/... и полученный access token.',
  'В SWPro откройте Подключения, выберите OK, укажите название, ID группы и ключ доступа, затем сохраните.',
  'Если отправка не проходит, проверьте, что пользователь разрешил сообщения от группы и токен не был отозван.'
), 0, 120);

INSERT INTO subscription_plans (
  slug, title, description, billing_mode, billing_basis,
  direct_leader_limit, branch_leader_limit,
  direct_consultant_limit, branch_consultant_limit, per_child_consultant_limit,
  price_per_leader, price_per_consultant, fixed_monthly_price, payment_terms, sort_order, is_active
) VALUES
  (
    'starter',
    'Старт',
    'Для небольшого лидера: несколько дочерних лидеров и базовая команда консультантов.',
    'prepaid',
    'branch',
    5, 20, 50, 150, 50,
    300.00, 300.00, NULL,
    'Оплата подтверждается администратором вручную. Онлайн-касса на первом этапе не подключена.',
    10, 1
  ),
  (
    'team',
    'Команда',
    'Основной тариф для активной команды лидера.',
    'prepaid',
    'branch',
    20, 100, 100, 1000, 200,
    300.00, 300.00, NULL,
    'Оплата подтверждается администратором вручную. Онлайн-касса на первом этапе не подключена.',
    20, 1
  ),
  (
    'network',
    'Лидерская сеть',
    'Для большой многоуровневой структуры с несколькими лидерами внутри ветки.',
    'prepaid',
    'branch',
    100, 500, 300, 5000, 500,
    300.00, 300.00, NULL,
    'Оплата подтверждается администратором вручную. Онлайн-касса на первом этапе не подключена.',
    30, 1
  );

INSERT INTO subscription_period_discounts
  (subscription_plan_id, months, discount_percent, badge_text, is_active, sort_order)
SELECT id, 1, 0, NULL, 1, 10 FROM subscription_plans;
INSERT INTO subscription_period_discounts
  (subscription_plan_id, months, discount_percent, badge_text, is_active, sort_order)
SELECT id, 6, 2, 'Выгодно', 1, 20 FROM subscription_plans;
INSERT INTO subscription_period_discounts
  (subscription_plan_id, months, discount_percent, badge_text, is_active, sort_order)
SELECT id, 12, 5, 'Максимальная выгода', 1, 30 FROM subscription_plans;

INSERT INTO settings (setting_key, setting_value, description) VALUES
('medical_disclaimer', 'Информация носит ознакомительный характер и не является медицинской рекомендацией. При вопросах о здоровье проконсультируйтесь со специалистом.', 'Дисклеймер для интерфейса и результатов чек-апа.'),
('project_name', 'SWPro Assistant', 'Название проекта.');
