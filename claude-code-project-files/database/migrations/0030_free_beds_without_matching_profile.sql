-- The other direction from 0029: a bed marked as held by a resident whose own profile
-- does not point at that bed. Nobody is actually recorded as living there, the bed could
-- not be deleted or reassigned, and that resident could not be given another bed
-- (one bed per resident). Free it. The live data had no such row; this is for copies
-- that do, found by the security review of 0029.
UPDATE beds b
LEFT JOIN boarder_profiles bp ON bp.user_id = b.current_boarder_id AND bp.bed_id = b.id
SET b.current_boarder_id = NULL, b.status = 'vacant'
WHERE b.current_boarder_id IS NOT NULL AND bp.user_id IS NULL;
