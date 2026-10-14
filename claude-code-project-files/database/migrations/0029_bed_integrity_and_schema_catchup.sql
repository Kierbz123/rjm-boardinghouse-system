-- Found by the October 2026 feature audit.

-- 1. The beds table is the record of who holds a bed (Bed::assign locks and checks it).
--    A profile that points at a bed held by someone else, or by nobody, is a stale
--    pointer: clear it. The room stays, so the resident's rent is unchanged. The live
--    data had one such row (two profiles on Room 1 / Bed A).
UPDATE boarder_profiles bp
JOIN beds b ON b.id = bp.bed_id
SET bp.bed_id = NULL
WHERE b.current_boarder_id IS NULL OR b.current_boarder_id <> bp.user_id;

-- 2. From now on the database refuses the double booking itself:
--    one profile per bed, and one bed per resident.
ALTER TABLE boarder_profiles ADD UNIQUE INDEX IF NOT EXISTS uq_boarder_profiles_bed (bed_id);
ALTER TABLE beds ADD UNIQUE INDEX IF NOT EXISTS uq_beds_current_boarder (current_boarder_id);

-- 3. Two indexes the live database already has, added by hand before migrations were
--    tracked. A freshly built database now gets them too.
CREATE INDEX IF NOT EXISTS idx_incidents_created_at ON incidents (created_at);
CREATE INDEX IF NOT EXISTS idx_maintenance_created_at ON maintenance_requests (created_at);

-- 4. "Who waived this penalty" was the one user reference without a foreign key.
ALTER TABLE penalties
    ADD CONSTRAINT fk_penalties_waived_by FOREIGN KEY IF NOT EXISTS (waived_by) REFERENCES users (id) ON DELETE SET NULL;
