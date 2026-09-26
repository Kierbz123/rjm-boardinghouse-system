ALTER TABLE boarder_profiles
    ADD COLUMN IF NOT EXISTS contact_number VARCHAR(50) NULL AFTER bed_id,
    ADD COLUMN IF NOT EXISTS emergency_contact_number VARCHAR(50) NULL AFTER contact_number;
