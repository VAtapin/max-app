SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE content_posts
  ADD COLUMN IF NOT EXISTS source_content_post_id BIGINT UNSIGNED NULL AFTER created_by,
  ADD INDEX IF NOT EXISTS idx_content_source_content_post_id (source_content_post_id),
  ADD UNIQUE INDEX IF NOT EXISTS uq_content_owner_source_clone (owner_type, owner_id, source_content_post_id);

SET @content_source_fk_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'content_posts'
    AND CONSTRAINT_NAME = 'fk_content_source_post'
);
SET @content_source_fk_sql := IF(
  @content_source_fk_exists = 0,
  'ALTER TABLE content_posts
     ADD CONSTRAINT fk_content_source_post
     FOREIGN KEY (source_content_post_id) REFERENCES content_posts(id)
     ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE content_source_fk_stmt FROM @content_source_fk_sql;
EXECUTE content_source_fk_stmt;
DEALLOCATE PREPARE content_source_fk_stmt;
