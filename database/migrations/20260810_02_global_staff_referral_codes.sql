DROP TRIGGER IF EXISTS trg_resellers_referral_code_unique_insert;
DROP TRIGGER IF EXISTS trg_resellers_referral_code_unique_update;
DROP TRIGGER IF EXISTS trg_managers_referral_code_unique_insert;
DROP TRIGGER IF EXISTS trg_managers_referral_code_unique_update;

DELIMITER //

CREATE TRIGGER trg_resellers_referral_code_unique_insert
BEFORE INSERT ON resellers
FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM managers WHERE referral_code = NEW.referral_code) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Referral code is already used by a manager';
  END IF;
END//

CREATE TRIGGER trg_resellers_referral_code_unique_update
BEFORE UPDATE ON resellers
FOR EACH ROW
BEGIN
  IF NOT (NEW.referral_code <=> OLD.referral_code)
     AND EXISTS (SELECT 1 FROM managers WHERE referral_code = NEW.referral_code) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Referral code is already used by a manager';
  END IF;
END//

CREATE TRIGGER trg_managers_referral_code_unique_insert
BEFORE INSERT ON managers
FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM resellers WHERE referral_code = NEW.referral_code) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Referral code is already used by a reseller';
  END IF;
END//

CREATE TRIGGER trg_managers_referral_code_unique_update
BEFORE UPDATE ON managers
FOR EACH ROW
BEGIN
  IF NOT (NEW.referral_code <=> OLD.referral_code)
     AND EXISTS (SELECT 1 FROM resellers WHERE referral_code = NEW.referral_code) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Referral code is already used by a reseller';
  END IF;
END//

DELIMITER ;
