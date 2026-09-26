CREATE TABLE boarder_profiles (
    user_id           INT UNSIGNED PRIMARY KEY,
    room_id           INT UNSIGNED NULL,
    bed_id            INT UNSIGNED NULL,
    move_in_date      DATE NULL,
    move_out_date     DATE NULL,
    status            ENUM('pending','active','on_notice','moved_out') NOT NULL DEFAULT 'pending',
    status_updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bp_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_bp_room FOREIGN KEY (room_id) REFERENCES rooms(id),
    CONSTRAINT fk_bp_bed  FOREIGN KEY (bed_id) REFERENCES beds(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Feature 10 (Status Life System) requires status changes to be logged: who/when/why.
CREATE TABLE boarder_status_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    boarder_id  INT UNSIGNED NOT NULL,
    old_status  VARCHAR(50) NULL,
    new_status  VARCHAR(50) NOT NULL,
    changed_by  INT UNSIGNED NULL COMMENT 'NULL = automatic/system-triggered change',
    reason      VARCHAR(255) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bsl_boarder FOREIGN KEY (boarder_id) REFERENCES users(id),
    CONSTRAINT fk_bsl_actor   FOREIGN KEY (changed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
