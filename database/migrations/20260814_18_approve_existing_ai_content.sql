SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Existing check-up texts are the approved client-facing source used to build
-- result cards. Later edits in the constructor still reset a record to draft.
UPDATE test_scale_results scale_result
INNER JOIN test_scales scale_row ON scale_row.id = scale_result.scale_id
INNER JOIN tests test_row ON test_row.id = scale_row.test_id
SET scale_result.source_urls = COALESCE(
        NULLIF(TRIM(scale_result.source_urls), ''),
        CONCAT_WS(
            CHAR(10),
            CONCAT('Внутренний первоисточник SWPro: тест «', test_row.title, '», раздел «', scale_row.title, '», результат «', scale_result.title, '» (', scale_result.min_score, '–', scale_result.max_score, ' баллов).'),
            IF(NULLIF(TRIM(scale_result.summary_text), '') IS NULL, NULL, CONCAT('Краткий итог: ', TRIM(scale_result.summary_text))),
            IF(NULLIF(TRIM(scale_result.advice_text), '') IS NULL, NULL, CONCAT('Текст карточки результата: ', TRIM(scale_result.advice_text)))
        )
    ),
    scale_result.ai_enabled = 1,
    scale_result.content_status = 'approved',
    scale_result.reviewed_at = NOW(),
    scale_result.next_review_at = COALESCE(scale_result.next_review_at, DATE_ADD(CURRENT_DATE, INTERVAL 1 YEAR));

UPDATE test_results test_result
INNER JOIN tests test_row ON test_row.id = test_result.test_id
SET test_result.source_urls = COALESCE(
        NULLIF(TRIM(test_result.source_urls), ''),
        CONCAT_WS(
            CHAR(10),
            CONCAT('Внутренний первоисточник SWPro: тест «', test_row.title, '», общий результат «', test_result.title, '» (', test_result.min_score, '–', test_result.max_score, ' баллов).'),
            IF(NULLIF(TRIM(test_result.summary_text), '') IS NULL, NULL, CONCAT('Краткий итог: ', TRIM(test_result.summary_text))),
            IF(NULLIF(TRIM(test_result.advice_text), '') IS NULL, NULL, CONCAT('Текст карточки результата: ', TRIM(test_result.advice_text)))
        )
    ),
    test_result.ai_enabled = 1,
    test_result.content_status = 'approved',
    test_result.reviewed_at = NOW(),
    test_result.next_review_at = COALESCE(test_result.next_review_at, DATE_ADD(CURRENT_DATE, INTERVAL 1 YEAR));

-- Preserve manually entered sources. Otherwise the approved product card is
-- recorded as the current internal source, together with its attached document.
UPDATE products product
SET product.source_urls = COALESCE(
        NULLIF(TRIM(product.source_urls), ''),
        CONCAT_WS(
            CHAR(10),
            CONCAT('Внутренний первоисточник SWPro: утверждённая карточка продукта «', product.title, '».'),
            IF(NULLIF(TRIM(product.document_path), '') IS NULL, NULL, CONCAT('Документ: ', TRIM(product.document_path))),
            IF(NULLIF(TRIM(product.full_description), '') IS NULL, NULL, CONCAT('Утверждённое описание: ', TRIM(product.full_description)))
        )
    ),
    product.ai_enabled = 1,
    product.content_status = 'approved',
    product.reviewed_at = NOW(),
    product.next_review_at = COALESCE(product.next_review_at, DATE_ADD(CURRENT_DATE, INTERVAL 1 YEAR))
WHERE product.is_deleted = 0;

-- Migration 16 could not create these links while products were still in
-- review. Complete the existing constructor mappings after approval.
INSERT INTO ai_recommendation_rules
  (test_result_id, target_type, target_id, rule_type, rationale, priority, is_active, is_approved, approved_at)
SELECT test_result.id, 'product', test_result.product_id, 'include',
       'Продукт явно выбран для этого диапазона в конструкторе теста.', 100, 1, 1, NOW()
FROM test_results test_result
INNER JOIN products product
        ON product.id = test_result.product_id
       AND product.is_active = 1
       AND product.is_deleted = 0
       AND product.ai_enabled = 1
       AND product.content_status = 'approved'
WHERE test_result.product_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM ai_recommendation_rules rule_row
      WHERE rule_row.test_result_id = test_result.id
        AND rule_row.target_type = 'product'
        AND rule_row.target_id = test_result.product_id
  );
