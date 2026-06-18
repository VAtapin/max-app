SET @tests_scoring_type_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tests'
    AND COLUMN_NAME = 'scoring_type'
);

SET @tests_scoring_type_sql := IF(
  @tests_scoring_type_exists = 0,
  'ALTER TABLE tests ADD COLUMN scoring_type ENUM(''single'', ''multiscale'') NOT NULL DEFAULT ''single'' AFTER category_id',
  'SELECT 1'
);

PREPARE tests_scoring_type_stmt FROM @tests_scoring_type_sql;
EXECUTE tests_scoring_type_stmt;
DEALLOCATE PREPARE tests_scoring_type_stmt;

UPDATE tests
SET scoring_type = 'multiscale'
WHERE title = 'Диагностика организма';
