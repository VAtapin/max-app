ALTER TABLE resellers
  ADD COLUMN IF NOT EXISTS legal_name VARCHAR(190) NULL AFTER billing_comment,
  ADD COLUMN IF NOT EXISTS legal_status VARCHAR(100) NULL AFTER legal_name,
  ADD COLUMN IF NOT EXISTS legal_inn VARCHAR(20) NULL AFTER legal_status,
  ADD COLUMN IF NOT EXISTS legal_address VARCHAR(500) NULL AFTER legal_inn,
  ADD COLUMN IF NOT EXISTS legal_email VARCHAR(190) NULL AFTER legal_address,
  ADD COLUMN IF NOT EXISTS legal_phone VARCHAR(50) NULL AFTER legal_email;

UPDATE resellers
SET legal_name = COALESCE(NULLIF(legal_name, ''), NULLIF(billing_name, ''), name),
    legal_inn = COALESCE(NULLIF(legal_inn, ''), billing_inn),
    legal_email = COALESCE(NULLIF(legal_email, ''), NULLIF(billing_email, ''), email),
    legal_phone = COALESCE(NULLIF(legal_phone, ''), phone);

ALTER TABLE legal_documents
  MODIFY COLUMN document_type ENUM(
    'privacy_policy',
    'leader_privacy_policy',
    'personal_data_consent',
    'health_data_consent',
    'marketing_consent',
    'user_agreement',
    'leader_offer'
  ) NOT NULL;

ALTER TABLE user_consents
  ADD COLUMN IF NOT EXISTS operator_reseller_id BIGINT UNSIGNED NULL AFTER document_version,
  ADD COLUMN IF NOT EXISTS document_snapshot MEDIUMTEXT NULL AFTER operator_reseller_id,
  ADD COLUMN IF NOT EXISTS document_hash CHAR(64) NULL AFTER document_snapshot,
  ADD INDEX IF NOT EXISTS idx_user_consents_operator (operator_reseller_id);
