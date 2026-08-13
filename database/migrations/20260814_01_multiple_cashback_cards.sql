CREATE TABLE IF NOT EXISTS profile_cashback_cards (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  profile_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(190) NULL,
  description MEDIUMTEXT NULL,
  image_path VARCHAR(255) NULL,
  card_url VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_profile_cashback_cards_profile (profile_id, sort_order, id),
  CONSTRAINT fk_profile_cashback_cards_profile
    FOREIGN KEY (profile_id) REFERENCES consultant_profiles(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO profile_cashback_cards
  (profile_id, title, description, image_path, card_url, sort_order)
SELECT
  cp.id,
  cp.cashback_title,
  cp.cashback_text,
  cp.cashback_image_path,
  cp.cashback_url,
  10
FROM consultant_profiles cp
WHERE (NULLIF(TRIM(cp.cashback_title), '') IS NOT NULL
    OR NULLIF(TRIM(cp.cashback_text), '') IS NOT NULL
    OR NULLIF(TRIM(cp.cashback_image_path), '') IS NOT NULL
    OR NULLIF(TRIM(cp.cashback_url), '') IS NOT NULL)
  AND NOT EXISTS (
    SELECT 1
    FROM profile_cashback_cards pcc
    WHERE pcc.profile_id = cp.id
  );
