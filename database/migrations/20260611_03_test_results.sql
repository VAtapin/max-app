CREATE TABLE IF NOT EXISTS test_results (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  min_score INT NOT NULL DEFAULT 0,
  max_score INT NOT NULL DEFAULT 0,
  summary_text TEXT NULL,
  advice_text TEXT NULL,
  product_id BIGINT UNSIGNED NULL,
  category_id BIGINT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_test_results_test_id (test_id),
  INDEX idx_test_results_score (test_id, min_score, max_score),
  CONSTRAINT fk_test_results_test
    FOREIGN KEY (test_id) REFERENCES tests(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_test_results_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_test_results_category
    FOREIGN KEY (category_id) REFERENCES product_categories(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
