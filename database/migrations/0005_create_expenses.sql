CREATE TABLE expenses (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id      INT UNSIGNED NOT NULL,
    category      VARCHAR(100) NOT NULL,
    amount        DECIMAL(10,2) NOT NULL,
    description   VARCHAR(255) NULL,
    receipt_path  VARCHAR(255) NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_expenses_staff FOREIGN KEY (staff_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
