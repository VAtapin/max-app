SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE chat_messages
  MODIFY COLUMN sender_type ENUM('admin','client','system','ai') NOT NULL;

