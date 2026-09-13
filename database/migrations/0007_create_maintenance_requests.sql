CREATE TABLE maintenance_requests (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    boarder_id         INT UNSIGNED NOT NULL,
    room_id            INT UNSIGNED NULL,
    category           ENUM('electrical','plumbing','structural','appliance','other') NOT NULL,
    description        TEXT NOT NULL,
    media_path         VARCHAR(255) NULL,
    severity_score     DECIMAL(5,2) NULL,
    priority_tier      ENUM('critical','high','medium','low') NULL,
    scoring_pending    TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'true if Python service was unreachable at submit time',
    status             ENUM('open','in_progress','resolved') NOT NULL DEFAULT 'open',
    assigned_staff_id  INT UNSIGNED NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at        TIMESTAMP NULL,
    INDEX idx_maintenance_priority_status (priority_tier, status),
    CONSTRAINT fk_mr_boarder FOREIGN KEY (boarder_id) REFERENCES users(id),
    CONSTRAINT fk_mr_room    FOREIGN KEY (room_id) REFERENCES rooms(id),
    CONSTRAINT fk_mr_staff   FOREIGN KEY (assigned_staff_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
