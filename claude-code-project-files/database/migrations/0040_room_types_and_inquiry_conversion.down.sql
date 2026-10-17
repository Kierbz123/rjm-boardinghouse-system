ALTER TABLE inquiries DROP FOREIGN KEY IF EXISTS fk_inquiries_converted_user;
ALTER TABLE inquiries DROP INDEX IF EXISTS uniq_inquiries_converted_user;
ALTER TABLE inquiries DROP COLUMN IF EXISTS converted_at, DROP COLUMN IF EXISTS converted_user_id;

ALTER TABLE rooms DROP FOREIGN KEY IF EXISTS fk_rooms_room_type;
ALTER TABLE rooms DROP COLUMN IF EXISTS room_type_id;

DROP TABLE IF EXISTS room_types;
