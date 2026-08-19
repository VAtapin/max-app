-- The permission granted in VK and the user's choice to receive SWPro
-- messages are different states. Keep both so that a local opt-out never
-- pretends to revoke the permission in VK itself.
ALTER TABLE vk_message_permissions
  ADD COLUMN IF NOT EXISTS delivery_enabled TINYINT(1) NOT NULL DEFAULT 1
  AFTER status;

UPDATE vk_message_permissions
SET delivery_enabled = CASE WHEN status = 'allowed' THEN 1 ELSE 0 END
WHERE delivery_enabled = 0 AND status = 'allowed';
