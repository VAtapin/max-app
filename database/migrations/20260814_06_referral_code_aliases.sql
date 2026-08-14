CREATE TABLE IF NOT EXISTS referral_code_aliases (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type ENUM('reseller', 'manager') NOT NULL,
  owner_id BIGINT UNSIGNED NOT NULL,
  referral_code VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_referral_code_alias (referral_code),
  INDEX idx_referral_alias_owner (owner_type, owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS trg_resellers_referral_code_unique_insert;
DROP TRIGGER IF EXISTS trg_resellers_referral_code_unique_update;
DROP TRIGGER IF EXISTS trg_managers_referral_code_unique_insert;
DROP TRIGGER IF EXISTS trg_managers_referral_code_unique_update;
DROP TRIGGER IF EXISTS trg_referral_alias_unique_insert;
DROP TRIGGER IF EXISTS trg_referral_alias_unique_update;

DELIMITER //

CREATE TRIGGER trg_resellers_referral_code_unique_insert
BEFORE INSERT ON resellers
FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM managers WHERE referral_code = NEW.referral_code)
     OR EXISTS (SELECT 1 FROM referral_code_aliases WHERE referral_code = NEW.referral_code) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Referral code is already reserved';
  END IF;
END//

CREATE TRIGGER trg_resellers_referral_code_unique_update
BEFORE UPDATE ON resellers
FOR EACH ROW
BEGIN
  IF NOT (NEW.referral_code <=> OLD.referral_code)
     AND (EXISTS (SELECT 1 FROM managers WHERE referral_code = NEW.referral_code)
          OR EXISTS (SELECT 1 FROM referral_code_aliases WHERE referral_code = NEW.referral_code)) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Referral code is already reserved';
  END IF;
END//

CREATE TRIGGER trg_managers_referral_code_unique_insert
BEFORE INSERT ON managers
FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM resellers WHERE referral_code = NEW.referral_code)
     OR EXISTS (SELECT 1 FROM referral_code_aliases WHERE referral_code = NEW.referral_code) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Referral code is already reserved';
  END IF;
END//

CREATE TRIGGER trg_managers_referral_code_unique_update
BEFORE UPDATE ON managers
FOR EACH ROW
BEGIN
  IF NOT (NEW.referral_code <=> OLD.referral_code)
     AND (EXISTS (SELECT 1 FROM resellers WHERE referral_code = NEW.referral_code)
          OR EXISTS (SELECT 1 FROM referral_code_aliases WHERE referral_code = NEW.referral_code)) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Referral code is already reserved';
  END IF;
END//

CREATE TRIGGER trg_referral_alias_unique_insert
BEFORE INSERT ON referral_code_aliases
FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM resellers WHERE referral_code = NEW.referral_code)
     OR EXISTS (SELECT 1 FROM managers WHERE referral_code = NEW.referral_code) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Referral alias conflicts with a current code';
  END IF;
END//

CREATE TRIGGER trg_referral_alias_unique_update
BEFORE UPDATE ON referral_code_aliases
FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM resellers WHERE referral_code = NEW.referral_code)
     OR EXISTS (SELECT 1 FROM managers WHERE referral_code = NEW.referral_code) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Referral alias conflicts with a current code';
  END IF;
END//

DELIMITER ;
