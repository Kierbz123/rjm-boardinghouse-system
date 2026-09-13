ALTER TABLE incidents ADD COLUMN resolved_by INT UNSIGNED NULL AFTER resolution_notes;
ALTER TABLE incidents ADD COLUMN resolved_at TIMESTAMP NULL AFTER resolved_by;
ALTER TABLE incidents ADD CONSTRAINT fk_incidents_resolved_by FOREIGN KEY (resolved_by) REFERENCES users(id);
