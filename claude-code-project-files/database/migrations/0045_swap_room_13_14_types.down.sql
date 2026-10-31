-- Revert swap:
UPDATE beds b
JOIN rooms r13 ON r13.id = b.room_id AND r13.room_number = '13'
JOIN rooms r14 ON r14.room_number = '14'
SET b.room_id = r14.id
WHERE b.label = 'Bed B';

UPDATE rooms r
JOIN room_types rt ON rt.code = 'solo'
SET r.room_type_id = rt.id,
    r.capacity = 1,
    r.base_price = rt.price
WHERE r.room_number = '13';

UPDATE rooms r
JOIN room_types rt ON rt.code = 'twin'
SET r.room_type_id = rt.id,
    r.capacity = 2,
    r.base_price = rt.price
WHERE r.room_number = '14';
