SET @sql := IF(
    (SELECT COUNT(*)
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'site_templates'
       AND COLUMN_NAME = 'owner_type') = 0,
    'ALTER TABLE site_templates ADD COLUMN owner_type ENUM(''reseller'',''manager'') NULL AFTER description',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*)
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'site_templates'
       AND COLUMN_NAME = 'owner_id') = 0,
    'ALTER TABLE site_templates ADD COLUMN owner_id BIGINT UNSIGNED NULL AFTER owner_type',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*)
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'site_templates'
       AND COLUMN_NAME = 'source_template_id') = 0,
    'ALTER TABLE site_templates ADD COLUMN source_template_id BIGINT UNSIGNED NULL AFTER owner_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*)
     FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'site_templates'
       AND INDEX_NAME = 'idx_site_templates_owner') = 0,
    'ALTER TABLE site_templates ADD INDEX idx_site_templates_owner (owner_type, owner_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*)
     FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'site_templates'
       AND INDEX_NAME = 'idx_site_templates_source') = 0,
    'ALTER TABLE site_templates ADD INDEX idx_site_templates_source (source_template_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
