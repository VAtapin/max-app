SET @tests_emoji_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tests' AND COLUMN_NAME = 'emoji'
);
SET @tests_emoji_sql := IF(
  @tests_emoji_exists = 0,
  'ALTER TABLE tests ADD COLUMN emoji VARCHAR(16) NULL AFTER scoring_type',
  'SELECT 1'
);
PREPARE tests_emoji_stmt FROM @tests_emoji_sql;
EXECUTE tests_emoji_stmt;
DEALLOCATE PREPARE tests_emoji_stmt;

SET @tests_intro_text_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tests' AND COLUMN_NAME = 'intro_text'
);
SET @tests_intro_text_sql := IF(
  @tests_intro_text_exists = 0,
  'ALTER TABLE tests ADD COLUMN intro_text TEXT NULL AFTER emoji',
  'SELECT 1'
);
PREPARE tests_intro_text_stmt FROM @tests_intro_text_sql;
EXECUTE tests_intro_text_stmt;
DEALLOCATE PREPARE tests_intro_text_stmt;

SET @tests_intro_image_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tests' AND COLUMN_NAME = 'intro_image_path'
);
SET @tests_intro_image_sql := IF(
  @tests_intro_image_exists = 0,
  'ALTER TABLE tests ADD COLUMN intro_image_path VARCHAR(255) NULL AFTER intro_text',
  'SELECT 1'
);
PREPARE tests_intro_image_stmt FROM @tests_intro_image_sql;
EXECUTE tests_intro_image_stmt;
DEALLOCATE PREPARE tests_intro_image_stmt;

SET @tests_intro_video_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tests' AND COLUMN_NAME = 'intro_video_url'
);
SET @tests_intro_video_sql := IF(
  @tests_intro_video_exists = 0,
  'ALTER TABLE tests ADD COLUMN intro_video_url VARCHAR(255) NULL AFTER intro_image_path',
  'SELECT 1'
);
PREPARE tests_intro_video_stmt FROM @tests_intro_video_sql;
EXECUTE tests_intro_video_stmt;
DEALLOCATE PREPARE tests_intro_video_stmt;

UPDATE tests
SET
  emoji = '🌿',
  intro_text = 'Ответьте на вопросы о самочувствии, привычках и сигналах организма. После прохождения вы увидите карту по 10 направлениям: где всё спокойно, а где стоит уделить больше внимания. Это не медицинский диагноз, а удобная wellness-диагностика для дальнейшего разбора с консультантом Siberian Wellness.',
  scoring_type = 'multiscale'
WHERE title = 'Диагностика организма';

UPDATE content_posts
SET
  short_text = 'Короткое видео/материал о том, как читать результат диагностики и что делать дальше.',
  button_text = 'Разобрать с консультантом'
WHERE title = 'Как читать диагностику организма';

UPDATE content_posts
SET
  short_text = 'Что влияет на ежедневный ресурс: сон, питание, стресс, движение и поддерживающие привычки.',
  button_text = 'Подобрать первый шаг'
WHERE title = 'Энергия, восстановление и ежедневный ресурс';

UPDATE content_posts
SET
  short_text = 'Пищеварение часто первым реагирует на режим, рацион, стресс и индивидуальные особенности.',
  button_text = 'Обсудить комфорт после еды'
WHERE title = 'Пищеварение и комфорт после еды';

UPDATE content_posts
SET
  short_text = 'Кожа, волосы и ногти как сигналы рутины: питание, сон, уход, стресс и восстановление.',
  button_text = 'Разобрать цель красоты'
WHERE title = 'Кожа, волосы и внешний вид как зеркало привычек';

UPDATE content_posts
SET
  short_text = 'Как строится персональный wellness-маршрут и как узнать о возможностях команды Siberian Wellness.',
  button_text = 'Узнать про программу'
WHERE title = 'Персональная программа с консультантом';
