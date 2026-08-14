ALTER TABLE ai_avatars
  MODIFY COLUMN owner_type ENUM('superadmin','reseller','manager') NOT NULL;
