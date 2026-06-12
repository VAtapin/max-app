ALTER TABLE end_users
  ADD COLUMN merged_into_user_id BIGINT UNSIGNED NULL AFTER status,
  ADD INDEX idx_end_users_merged_into_user_id (merged_into_user_id),
  ADD CONSTRAINT fk_end_users_merged_into
    FOREIGN KEY (merged_into_user_id) REFERENCES end_users(id)
    ON DELETE SET NULL ON UPDATE CASCADE;
