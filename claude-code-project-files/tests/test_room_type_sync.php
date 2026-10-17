<?php
/**
 * A room linked to a room type takes its capacity and rent from that type, so the offering,
 * the room's settings, its beds and what residents are billed never disagree.
 */
require __DIR__ . '/lib.php';

$admin = seededClient('admin');
$types = array_column(q('SELECT * FROM room_types'), null, 'code');
$solo = $types['solo'];
$twin = $types['twin'];
$fieldsOf = fn (array $t) => ['name' => $t['name'], 'capacity_label' => $t['capacity_label'], 'capacity' => $t['capacity'],
    'price' => $t['price'], 'price_unit' => $t['price_unit'], 'badge' => $t['badge'], 'features' => $t['features']]
    + ($t['is_popular'] ? ['is_popular' => '1'] : []);
$roomOf = fn (int $id) => q('SELECT capacity, base_price, room_type_id FROM rooms WHERE id = ?', [$id])[0];

section('The type sets capacity and rent');
$room = makeRoom($admin, 4, 3500, 2);
$update = fn (array $extra) => $admin->post("/admin/rooms/{$room['id']}/update",
    $extra + ['room_number' => $room['number'], 'floor' => '1', 'capacity' => 4, 'base_price' => 3500]);

$r = $update(['room_type_id' => $solo['id']]);
check(flashHas($admin, $r, 'Solo Executive Room holds 1'), 'a room with 2 beds cannot become an exclusive (1-bed) room');
check($roomOf($room['id'])['room_type_id'] === null, 'and it stays unlinked');

$update(['room_type_id' => $twin['id'], 'capacity' => 9, 'base_price' => 1]);
$row = $roomOf($room['id']);
check((int) $row['room_type_id'] === (int) $twin['id'] && (int) $row['capacity'] === 2 && (float) $row['base_price'] === 2200.0,
    'linking to the twin type sets capacity 2 and ₱2,200 per bed, whatever was typed', json_encode($row));
check(flashHas($admin, $admin->post('/admin/beds', ['room_id' => $room['id'], 'label' => 'Bed Z']), 'holds 2'), 'no third bed fits a twin room');

$admin->post("/admin/beds/{$room['beds'][1]}/delete");
$update(['room_type_id' => $solo['id']]);
$row = $roomOf($room['id']);
check((int) $row['capacity'] === 1 && (float) $row['base_price'] === 3500.0, 'with one bed left it becomes a solo room: 1 bed, ₱3,500', json_encode($row));

$update(['room_type_id' => '', 'capacity' => 3, 'base_price' => 1800]);
$row = $roomOf($room['id']);
check($row['room_type_id'] === null && (int) $row['capacity'] === 3 && (float) $row['base_price'] === 1800.0, 'an untyped room keeps the capacity and rent typed by hand');

$number = 'S' . uniq();
$admin->post('/admin/rooms', ['room_number' => $number, 'capacity' => 7, 'base_price' => 5, 'room_type_id' => $twin['id']]);
$row = q('SELECT capacity, base_price FROM rooms WHERE room_number = ?', [$number])[0];
check((int) $row['capacity'] === 2 && (float) $row['base_price'] === 2200.0, 'Add Room with a type takes the type\'s capacity and rent');

section('Editing the type updates its rooms');
$twinRoom = makeRoom($admin, 2, 3000, 2);
$admin->post("/admin/rooms/{$twinRoom['id']}/update", ['room_number' => $twinRoom['number'], 'capacity' => 2, 'base_price' => 3000, 'room_type_id' => $twin['id']]);
$admin->post("/admin/room-types/{$twin['id']}/update", ['price' => '2400'] + $fieldsOf($twin));
check((float) $roomOf($twinRoom['id'])['base_price'] === 2400.0, 'a new per-bed price becomes every twin room\'s rent');

$admin->post("/admin/room-types/{$twin['id']}/update", ['capacity' => '1'] + $fieldsOf($twin));
check((int) q1('SELECT capacity FROM room_types WHERE id = ?', [$twin['id']]) === 2, 'the type cannot hold fewer than the beds its rooms already have');

$admin->post("/admin/room-types/{$twin['id']}/update", ['capacity' => '3', 'price' => '6000', 'price_unit' => 'room'] + $fieldsOf($twin));
$row = $roomOf($twinRoom['id']);
check((int) $row['capacity'] === 3 && (float) $row['base_price'] === 2000.0, 'a per-room price is split equally among the room\'s residents', json_encode($row));

section('The page shows one capacity');
$page = $admin->get('/admin/rooms');
check($page->has('2 of 3 beds') && $page->has('1 not set up yet'), 'Beds Directory shows beds set up against the room\'s capacity');
check($page->has('Exclusive: 1 bed per room'), 'the solo offering says it is exclusive');

$admin->post("/admin/room-types/{$twin['id']}/update", $fieldsOf($twin));
check((float) $roomOf($twinRoom['id'])['base_price'] === (float) $twin['price'], 'the twin type is restored for later tests');
