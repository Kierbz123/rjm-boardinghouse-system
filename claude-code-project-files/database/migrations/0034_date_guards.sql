-- @delimiter //
-- WP-3: key/value settings (opening date for now), an audit trail, and database triggers that
-- refuse absurd move-in / move-out dates even from raw SQL.
CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(64) NOT NULL PRIMARY KEY,
    setting_value TEXT NULL,
    updated_by    INT UNSIGNED NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
//
-- Placeholder: the real opening date was not supplied. Change it under Settings (or here) when known.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('property_opening_date', '2026-01-01')
//
CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id    INT UNSIGNED NULL COMMENT 'NULL = the system itself',
    action      VARCHAR(60) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id   BIGINT UNSIGNED NULL,
    details     TEXT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_action_time (action, created_at),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
//
CREATE TRIGGER trg_boarder_profiles_dates_ins BEFORE INSERT ON boarder_profiles FOR EACH ROW
BEGIN
    DECLARE opening DATE;
    SET opening = COALESCE((SELECT STR_TO_DATE(setting_value, '%Y-%m-%d') FROM settings WHERE setting_key = 'property_opening_date'), '2000-01-01');
    IF NEW.move_in_date IS NOT NULL AND (NEW.move_in_date < opening OR NEW.move_in_date > DATE_ADD(CURDATE(), INTERVAL 12 MONTH)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'move_in_date is outside the allowed range';
    END IF;
    IF NEW.move_out_date IS NOT NULL AND ((NEW.move_in_date IS NOT NULL AND NEW.move_out_date < NEW.move_in_date) OR NEW.move_out_date > DATE_ADD(CURDATE(), INTERVAL 5 YEAR)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'move_out_date is outside the allowed range';
    END IF;
END
//
CREATE TRIGGER trg_boarder_profiles_dates_upd BEFORE UPDATE ON boarder_profiles FOR EACH ROW
BEGIN
    DECLARE opening DATE;
    -- Only when a date actually changes: an old row with an old date can still have its notes edited.
    IF NOT (NEW.move_in_date <=> OLD.move_in_date) OR NOT (NEW.move_out_date <=> OLD.move_out_date) THEN
        SET opening = COALESCE((SELECT STR_TO_DATE(setting_value, '%Y-%m-%d') FROM settings WHERE setting_key = 'property_opening_date'), '2000-01-01');
        IF NEW.move_in_date IS NOT NULL AND NOT (NEW.move_in_date <=> OLD.move_in_date)
           AND (NEW.move_in_date < opening OR NEW.move_in_date > DATE_ADD(CURDATE(), INTERVAL 12 MONTH)) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'move_in_date is outside the allowed range';
        END IF;
        IF NEW.move_out_date IS NOT NULL AND ((NEW.move_in_date IS NOT NULL AND NEW.move_out_date < NEW.move_in_date)
           OR (NOT (NEW.move_out_date <=> OLD.move_out_date) AND NEW.move_out_date > DATE_ADD(CURDATE(), INTERVAL 5 YEAR))) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'move_out_date is outside the allowed range';
        END IF;
    END IF;
END
//
