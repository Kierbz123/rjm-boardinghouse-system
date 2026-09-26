ALTER TABLE notifications ADD COLUMN entity_type VARCHAR(50) NULL AFTER action_url;
ALTER TABLE notifications ADD COLUMN entity_id INT UNSIGNED NULL AFTER entity_type;
ALTER TABLE notifications ADD INDEX idx_notifications_entity (entity_type, entity_id);