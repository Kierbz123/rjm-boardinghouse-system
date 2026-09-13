ALTER TABLE notifications ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0 AFTER is_read;
CREATE INDEX idx_notifications_user_pinned ON notifications (user_id, is_pinned);
