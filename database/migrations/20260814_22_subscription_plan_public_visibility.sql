ALTER TABLE subscription_plans
  ADD COLUMN IF NOT EXISTS is_public TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active;

UPDATE subscription_plans
SET is_public = 0
WHERE owner_type = 'superadmin'
  AND LOWER(title) LIKE '%внутренн%';
