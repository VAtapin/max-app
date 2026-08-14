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
('Как работает SWPro', 'SWPro объединяет персональные мини-сайты, информационные опросы, клиентскую базу, обращения, рассылки, команду и оплату рабочих мест. Платформа помогает вести клиента, но не заменяет личную работу специалиста.', JSON_ARRAY(
  'Опросы собирают субъективные ощущения и предпочтения и не являются медицинской диагностикой.',
  'Каждый пользователь видит только разрешённую ему область данных.',
  'Оператором персональных данных внутри SWPro является владелец платформы.'
), 1, 10),
('Роли и доступ', 'В системе работают суперадминистратор, лидеры, консультанты и клиенты. Права определяются ролью и положением в команде.', JSON_ARRAY(
  'Суперадминистратор управляет всей платформой.',
  'Лидер управляет своей веткой и видит только её участников и клиентов.',
  'Консультант работает только со своими клиентами и не видит чужих лидеров или консультантов.',
  'Клиент видит свой Mini App, материалы, результаты и обращения.'
), 0, 20),
('Первый вход и анкета', 'При первом входе клиент принимает обязательные документы и заполняет анкету. Телефон и email можно оставить пустыми.', JSON_ARRAY(
  'Обязательны имя, фамилия, пол, город, а также возраст или дата рождения.',
  'Обязательны согласие на обработку данных, пользовательское соглашение и согласие на обработку ответов опросов.',
  'Согласие на новости и акции добровольное и не ограничивает основные функции.'
), 0, 30),
('Web-клиенты и объединение аккаунтов', 'Обычный браузер сначала получает временный web-профиль. Его можно связать с VK, Telegram или другой подтверждённой платформой.', JSON_ARRAY(
  'Без принятого соглашения временный профиль удаляется через 3 дня.',
  'После соглашения на объединение с реальным аккаунтом даётся 5 дней.',
  'До завершения анкеты открытие другой реферальной ссылки может изменить выбранного специалиста.',
  'После объединения данные и история остаются у одного клиента.'
), 0, 40),
('Реферальные ссылки и коды', 'Коды лидеров, консультантов и администраторов уникальны во всей системе. Код можно безопасно изменить.', JSON_ARRAY(
  'Старая ссылка после смены кода продолжает работать через сохранённый псевдоним.',
  'Смена кода не удаляет клиентов, историю и платформенные связи.',
  'После подтверждения клиента новая ссылка не меняет его специалиста молча.',
  'При совпадении с существующим или старым кодом система не даст сохранить дубль.'
), 0, 50),
('Преобразование клиента в лидера или консультанта', 'Клиента можно связать с существующим рабочим аккаунтом либо создать из него новое рабочее место.', JSON_ARRAY(
  'Внутренние ID клиента, лидера и администратора могут отличаться — это разные таблицы.',
  'Связь хранится явно, поэтому платформы и история не теряются.',
  'Перед созданием проверьте найденные похожие рабочие аккаунты и используйте «Связать с существующим», если запись уже есть.'
), 0, 60),
('Мой мини-сайт', 'Мини-сайт — персональная страница лидера или консультанта. Пустые блоки можно не заполнять.', JSON_ARRAY(
  'Настройте фото, описание, контакты, материалы, продукты и опросы.',
  'В «Кэшбэк и подарки» можно добавить несколько карточек — они показываются одна под другой.',
  'У карточки настраиваются заголовок, описание, изображение, ссылка и текст кнопки.',
  'Текст кнопки по умолчанию: «Оформить карту клиента».'
), 0, 70),
('Общий, командный и личный контент', 'Контент наследуется по уровням: общий SWPro, командный лидера и личный консультанта.', JSON_ARRAY(
  'Изменение общего объекта создаёт версию владельца и не портит оригинал.',
  'Личная версия консультанта имеет приоритет над версией лидера.',
  'Скрытие у одного владельца не удаляет объект у остальных.'
), 0, 80),
('Опросы и результаты', 'Опросы SWPro описывают субъективные ощущения и предпочтения пользователя и формируют информационный результат.', JSON_ARRAY(
  'Опрос не является обследованием, диагнозом, медицинским заключением или назначением лечения.',
  'Прогресс сохраняется после каждого ответа.',
  'Клиент может продолжить, посмотреть результат или пройти опрос заново.',
  'Результат видят только закреплённый специалист и разрешённое руководство его команды.'
), 0, 90),
('Обращения', 'Обращение появляется после вопроса клиента, просьбы связаться или входящего сообщения подключённой платформы.', JSON_ARRAY(
  'Ответ из админки сохраняется в истории.',
  'Для VK и OK сообщение уходит от имени подключённого сообщества или группы.',
  'Повторное Callback-событие не должно создавать дубль обращения.'
), 0, 100),
('Рассылки и безопасность аудитории', 'Рассылка отправляется только разрешённой аудитории и только клиентам с действующим согласием на информационные и рекламные сообщения.', JSON_ARRAY(
  'Консультант отправляет только своим клиентам.',
  'Лидер работает только со своими клиентами, консультантами и лидерами ветки.',
  'Поддерживаются текст, изображение, MP4 и кнопка-ссылка.',
  'Чужие клиенты и сотрудники не должны появляться в списках выбора.'
), 0, 110),
('Разрешение сообщений VK', 'Согласие на рассылку и техническое разрешение VK — разные действия. Для отправки нужны оба.', JSON_ARRAY(
  'В VK Mini App открывается системное окно разрешения сообщений сообщества.',
  'В обычном браузере показывается web-виджет VK; при необходимости VK сначала предложит войти.',
  'Разрешение хранится для конкретного клиента и конкретной группы.',
  'При смене группы требуется новое разрешение.',
  'Если VK не подключён, пользователь может указать email.'
), 0, 120),
('Подключения VK и OK', 'Подключение определяет, от имени какого сообщества или группы отправляются ответы и рассылки.', JSON_ARRAY(
  'Сначала используется готовое подключение консультанта, затем его лидера.',
  'Если своих подключений нет, используется явно отмеченное стандартное сообщество VK.',
  'Стандартное сообщество должно быть активным и иметь проверенный Callback API.',
  'Один и тот же group_id нельзя добавлять повторно.'
), 0, 130),
('Как подключить VK-сообщество', 'Для VK нужен ключ доступа сообщества и Callback API. Ключ отправляет сообщения, Callback API принимает входящие события и подтверждает разрешения.', JSON_ARRAY(
  'Включите сообщения сообщества и возможности бота.',
  'Создайте ключ доступа с правами на сообщения сообщества.',
  'В Callback API укажите https://swpro.ru/api/vk_callback.php и версию API 5.199.',
  'Перенесите в SWPro group_id, строку подтверждения и созданный SWPro секретный ключ.',
  'Включите события: входящее сообщение, разрешение сообщений, запрет сообщений и необходимые события работы с сообщениями.',
  'После получения первого корректного события подключение считается проверенным.'
), 0, 140),
('Как подключить группу OK', 'Для OK используется Bot API группы. Укажите ID группы и действующий токен.', JSON_ARRAY(
  'Включите сообщения группы для нужной аудитории.',
  'Получите ключ Bot API в настройках сообщений группы.',
  'Пользователь должен разрешить группе отправлять ему сообщения.',
  'Если отправка не проходит, проверьте статус группы и не был ли отозван токен.'
), 0, 150),
('Подписки и рабочие места', 'Тариф и статистика ветки относятся к главному лидеру, но задолженность и доступ рассчитываются для каждого рабочего места отдельно.', JSON_ARRAY(
  'Главный лидер оплачивает базовую часть, каждый нижестоящий лидер и консультант — своё место.',
  'Лидер может создавать собственные тарифы для нижестоящей ветки в пределах лимитов своей подписки; глобальные тарифы он не изменяет.',
  'Предоплата продлевается от конца уже оплаченного периода.',
  'При оплате по факту счёт создаётся за прошедший календарный месяц; неполный месяц считается по дням.',
  'После срока оплаты блокируются клиентские функции только должника, но его админка и команда продолжают работать.',
  'Скидки за 6 и 12 месяцев настраиваются в тарифе.'
), 0, 160),
('Оплата', 'Кнопка оплаты находится в разделе подписки рабочего места. Суперадминистратор настраивает все способы в «Подписка → Методы оплаты».', JSON_ARRAY(
  'Можно включить Stripe, PayPal, ЮKassa, CloudPayments и перевод по реквизитам.',
  'Все активные методы показываются плательщику без автоматического выбора страны.',
  'Ручной перевод ожидает подтверждения администратора.',
  'Онлайн-оплата подтверждается webhook соответствующего сервиса.'
), 0, 170),
('Юридические документы', 'Внутри SWPro один оператор персональных данных — владелец платформы. Лидер и консультант получают ограниченный доступ, но не становятся операторами только из-за реферальной ссылки.', JSON_ARRAY(
  'Реквизиты оператора задаются в разделе «Реквизиты документов».',
  'Пустые служебные переменные не должны показываться посетителю.',
  'Ссылки на актуальные документы размещаются на главном сайте и мини-сайтах.',
  'При изменении версии обязательного документа согласие запрашивается повторно.'
), 0, 180),
('Двухфакторная защита', 'QR-код 2FA нужно добавлять в приложение с поддержкой TOTP, а не сканировать обычной камерой.', JSON_ARRAY(
  'Подойдут Яндекс ID, 2FAS, Aegis, Microsoft Authenticator и аналоги.',
  'После добавления сохраните аккаунт в приложении и подтвердите код.',
  'Если доступ потерян, суперадминистратор отключает 2FA внутри формы учётной записи.',
  'Быстрой кнопки отключения в общей таблице нет, чтобы избежать случайного действия.'
), 0, 190),
('Автоматические задачи', 'Планировщик отдельно запускает рассылки, автоматические напоминания, биллинг и очистку временных web-профилей.', JSON_ARRAY(
  'Рассылки запускаются каждые 5 минут.',
  'Автоматические сценарии — каждые 15 минут.',
  'Биллинг — ежедневно.',
  'Очистка временных web-клиентов — ежедневно.',
  'Повторный запуск миграций безопасен: уже применённые миграции не выполняются снова.'
), 0, 200);

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
