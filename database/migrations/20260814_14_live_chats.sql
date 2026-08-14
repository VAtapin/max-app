CREATE TABLE IF NOT EXISTS chat_threads (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  thread_type ENUM('client','team') NOT NULL,
  end_user_id BIGINT UNSIGNED NULL,
  root_reseller_id BIGINT UNSIGNED NULL,
  title VARCHAR(190) NULL,
  last_message_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_chat_client (end_user_id),
  UNIQUE KEY uq_chat_team (root_reseller_id),
  INDEX idx_chat_threads_activity (thread_type, last_message_at, id),
  CONSTRAINT fk_chat_thread_user FOREIGN KEY (end_user_id) REFERENCES end_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_chat_thread_root FOREIGN KEY (root_reseller_id) REFERENCES resellers(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  thread_id BIGINT UNSIGNED NOT NULL,
  sender_type ENUM('admin','client','system') NOT NULL,
  sender_admin_user_id BIGINT UNSIGNED NULL,
  sender_end_user_id BIGINT UNSIGNED NULL,
  channel ENUM('internal','web','telegram','VK','OK','MAX') NOT NULL DEFAULT 'internal',
  message_text MEDIUMTEXT NOT NULL,
  attachments_json JSON NULL,
  status ENUM('pending','sent','delivered','read','failed') NOT NULL DEFAULT 'sent',
  error_text TEXT NULL,
  dedupe_key VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  delivered_at DATETIME NULL,
  read_at DATETIME NULL,
  UNIQUE KEY uq_chat_message_dedupe (dedupe_key),
  INDEX idx_chat_message_thread (thread_id, id),
  INDEX idx_chat_message_admin (sender_admin_user_id, created_at),
  INDEX idx_chat_message_client (sender_end_user_id, created_at),
  CONSTRAINT fk_chat_message_thread FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_chat_message_admin FOREIGN KEY (sender_admin_user_id) REFERENCES admin_users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_chat_message_client FOREIGN KEY (sender_end_user_id) REFERENCES end_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_reads (
  thread_id BIGINT UNSIGNED NOT NULL,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  last_message_id BIGINT UNSIGNED NULL,
  read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (thread_id, admin_user_id),
  INDEX idx_chat_reads_admin (admin_user_id, read_at),
  CONSTRAINT fk_chat_read_thread FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_chat_read_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_chat_read_message FOREIGN KEY (last_message_id) REFERENCES chat_messages(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
