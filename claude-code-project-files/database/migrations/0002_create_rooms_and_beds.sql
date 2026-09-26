CREATE TABLE rooms (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(20) NOT NULL UNIQUE,
    floor       VARCHAR(20) NULL,
    capacity    INT UNSIGNED NOT NULL DEFAULT 1,
    base_price  DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE beds (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id             INT UNSIGNED NOT NULL,
    label               VARCHAR(20) NOT NULL,
    status              ENUM('vacant','occupied') NOT NULL DEFAULT 'vacant',
    current_boarder_id  INT UNSIGNED NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_room_bed_label (room_id, label),
    CONSTRAINT fk_beds_room    FOREIGN KEY (room_id) REFERENCES rooms(id),
    CONSTRAINT fk_beds_boarder FOREIGN KEY (current_boarder_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
