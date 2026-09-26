CREATE TABLE qr_login_challenges (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token               CHAR(64) NOT NULL UNIQUE,
    creator_session_id  VARCHAR(128) NOT NULL,
    user_id             INT UNSIGNED NULL,
    status              ENUM('pending','approved','consumed') NOT NULL DEFAULT 'pending',
    expires_at          DATETIME NOT NULL,
    consumed_at         DATETIME NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_qr_login_expires (expires_at),
    CONSTRAINT fk_qr_login_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
