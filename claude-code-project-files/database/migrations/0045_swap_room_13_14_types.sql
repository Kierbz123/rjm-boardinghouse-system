-- Swap room types between Room 13 and Room 14:
-- Room 14 becomes Solo Executive Room (capacity 1, 1 bed).
-- Room 13 becomes Twin Sharing Scholar Suite (capacity 2, 2 beds).

UPDATE beds b
JOIN rooms r14 ON r14.id = b.room_id AND r14.room_number = '14'
JOIN rooms r13 ON r13.room_number = '13'
SET b.room_id = r13.id
WHERE b.label = 'Bed B';

UPDATE rooms r
JOIN room_types rt ON rt.code = 'solo'
SET r.room_type_id = rt.id,
    r.capacity = 1,
    r.base_price = rt.price
WHERE r.room_number = '14';

UPDATE rooms r
JOIN room_types rt ON rt.code = 'twin'
SET r.room_type_id = rt.id,
    r.capacity = 2,
    r.base_price = rt.price
WHERE r.room_number = '13';
