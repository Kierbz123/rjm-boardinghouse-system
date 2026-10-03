-- Room inquiries were only ever stored as admin notifications, so dismissing the
-- notification deleted the lead. This is the durable record; notifications just point here.
CREATE TABLE IF NOT EXISTS inquiries (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(150) NOT NULL,
    phone         VARCHAR(50) NOT NULL,
    email         VARCHAR(150) NULL,
    room_type     VARCHAR(100) NULL,
    move_in_date  DATE NULL,
    message       TEXT NULL,
    source        ENUM('website','staff') NOT NULL DEFAULT 'website',
    submitted_by  INT UNSIGNED NULL COMMENT 'staff/admin who logged a walk-in or phone inquiry',
    ip_address    VARCHAR(45) NULL,
    status        ENUM('new','contacted','closed') NOT NULL DEFAULT 'new',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inquiries_created (created_at),
    INDEX idx_inquiries_ip_time (ip_address, created_at),
    CONSTRAINT fk_inquiries_submitter FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
