UPDATE subscription_plans
SET ai_text_enabled = 1
WHERE is_active = 1
  AND ai_text_enabled = 0;
