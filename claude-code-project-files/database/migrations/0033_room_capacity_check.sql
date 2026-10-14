-- WP-2: a room holds between 1 and 50 beds; the database refuses anything else.
ALTER TABLE rooms ADD CONSTRAINT chk_rooms_capacity CHECK (capacity BETWEEN 1 AND 50);
