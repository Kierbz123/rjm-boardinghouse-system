CREATE TABLE sos_alerts (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    boarder_id       INT UNSIGNED NOT NULL,
    room_id          INT UNSIGNED NULL,
    status           ENUM('active','acknowledged','resolved') NOT NULL DEFAULT 'active',
    acknowledged_by  INT UNSIGNED NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at      TIMESTAMP NULL,
    INDEX idx_sos_status (status),
    CONSTRAINT fk_sos_boarder FOREIGN KEY (boarder_id) REFERENCES users(id),
    CONSTRAINT fk_sos_room    FOREIGN KEY (room_id) REFERENCES rooms(id),
    CONSTRAINT fk_sos_ack     FOREIGN KEY (acknowledged_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE incidents (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reported_by       INT UNSIGNED NOT NULL,
    type              VARCHAR(100) NOT NULL,
    description       TEXT NOT NULL,
    resolved          TINYINT(1) NOT NULL DEFAULT 0,
    resolution_notes  TEXT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_incidents_reporter FOREIGN KEY (reported_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
