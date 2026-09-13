CREATE TABLE occupancy_snapshots (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    snapshot_date  DATE NOT NULL UNIQUE,
    total_beds     INT UNSIGNED NOT NULL,
    occupied_beds  INT UNSIGNED NOT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
