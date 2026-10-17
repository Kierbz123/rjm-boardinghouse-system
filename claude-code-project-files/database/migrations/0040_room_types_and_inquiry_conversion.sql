-- The three advertised offerings ("Choose how you live") lived only in LandingController,
-- so the admin pages could not see them and a room could not say which offering it is.
-- room_types is now the one source: the website, the inquiry forms and the admin pages read it.
-- A physical room may point at one type; beds and occupancy stay on rooms/beds as before.
CREATE TABLE IF NOT EXISTS room_types (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code           VARCHAR(20)  NOT NULL UNIQUE,
    name           VARCHAR(100) NOT NULL UNIQUE,
    capacity       INT UNSIGNED NOT NULL,
    capacity_label VARCHAR(60)  NOT NULL,
    price          DECIMAL(10,2) NOT NULL,
    price_unit     ENUM('room','bed') NOT NULL COMMENT 'room = whole room per month, bed = per bed per month',
    badge          VARCHAR(40)  NULL,
    is_popular     TINYINT(1)   NOT NULL DEFAULT 0,
    features       TEXT         NOT NULL COMMENT 'one feature per line',
    sort_order     INT          NOT NULL DEFAULT 0,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_room_types_capacity CHECK (capacity BETWEEN 1 AND 50),
    CONSTRAINT chk_room_types_price CHECK (price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- INSERT IGNORE on the unique code: re-running never duplicates and never overwrites an admin's edits.
INSERT IGNORE INTO room_types (code, name, capacity, capacity_label, price, price_unit, badge, is_popular, features, sort_order) VALUES
('solo', 'Solo Executive Room', 1, '1 Resident (Private)', 3500.00, 'room', 'Maximum Privacy', 0,
 'Private furnished room with solid hardwood door\nDedicated ergonomic study desk & reading lamp\nIndividual steel locker & wardrobe cabinet\nDedicated electric submeter & private ceiling fan\nFiber Wi-Fi & quiet study floor allocation', 1),
('twin', 'Twin Sharing Scholar Suite', 2, '2 Residents (Shared)', 2200.00, 'bed', 'Most Popular', 1,
 'Spacious 2-bed layout with dual study stations\nTwo separate secure steel storage lockers\nWide cross-ventilation windows with blackout curtains\nHigh-speed Wi-Fi & power charging hubs\nSplit electricity submetering per occupant', 2),
('quad', 'Quad Bedspace Sanctuary', 4, '4 Residents (Bunk Bed)', 1600.00, 'bed', 'Best Value', 0,
 'Heavy-duty mahogany double-deck bunks with privacy curtains\nIndividual LED night lamp & dual USB wall sockets\nAssigned lockable heavy-duty storage compartment\nComplimentary purified drinking water station\nFull access to shared study hall & pantry station', 3);

-- Existing rooms are left untyped: their capacity/price do not say which offering they are,
-- so the admin picks the type on Rooms & Beds rather than the migration guessing.
ALTER TABLE rooms ADD COLUMN IF NOT EXISTS room_type_id INT UNSIGNED NULL AFTER floor;
ALTER TABLE rooms ADD CONSTRAINT fk_rooms_room_type FOREIGN KEY IF NOT EXISTS (room_type_id) REFERENCES room_types (id) ON DELETE SET NULL;

-- Which boarder account an inquiry became. UNIQUE: one inquiry can only ever become one account.
ALTER TABLE inquiries ADD COLUMN IF NOT EXISTS converted_user_id INT UNSIGNED NULL AFTER status;
ALTER TABLE inquiries ADD COLUMN IF NOT EXISTS converted_at TIMESTAMP NULL DEFAULT NULL AFTER converted_user_id;
ALTER TABLE inquiries ADD UNIQUE KEY IF NOT EXISTS uniq_inquiries_converted_user (converted_user_id);
ALTER TABLE inquiries ADD CONSTRAINT fk_inquiries_converted_user FOREIGN KEY IF NOT EXISTS (converted_user_id) REFERENCES users (id) ON DELETE SET NULL;
