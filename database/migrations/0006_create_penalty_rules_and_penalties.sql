CREATE TABLE penalty_rules (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(150) NOT NULL,
    condition_type VARCHAR(100) NOT NULL COMMENT 'e.g. late_per_day, flat_damage',
    amount         DECIMAL(10,2) NOT NULL,
    active         TINYINT(1) NOT NULL DEFAULT 1,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE penalties (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    boarder_id  INT UNSIGNED NOT NULL,
    rule_id     INT UNSIGNED NOT NULL,
    amount      DECIMAL(10,2) NOT NULL,
    reason      VARCHAR(255) NULL,
    applied_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_penalties_boarder FOREIGN KEY (boarder_id) REFERENCES users(id),
    CONSTRAINT fk_penalties_rule    FOREIGN KEY (rule_id) REFERENCES penalty_rules(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
