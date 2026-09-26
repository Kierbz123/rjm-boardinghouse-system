ALTER TABLE maintenance_requests ADD COLUMN resolved_by INT UNSIGNED NULL AFTER resolved_at;
ALTER TABLE maintenance_requests ADD CONSTRAINT fk_mr_resolved_by FOREIGN KEY (resolved_by) REFERENCES users(id);
