ALTER TABLE profile_cashback_cards
  ADD COLUMN IF NOT EXISTS button_text VARCHAR(190) NOT NULL DEFAULT 'Оформить карту клиента' AFTER card_url;

UPDATE profile_cashback_cards
SET button_text = 'Оформить карту клиента'
WHERE button_text IS NULL OR TRIM(button_text) = '';
