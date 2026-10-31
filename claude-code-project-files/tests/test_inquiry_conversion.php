<?php
/**
 * The advertised room offerings live in room_types (one source for the website, the inquiry
 * forms and the admin pages), and an inquiry can be turned into a boarder account: the inquiry
 * fills in Add Boarder, the admin submits, and the inquiry records which account it became.
 */
require __DIR__ . '/lib.php';

$admin = seededClient('admin');
$staff = seededClient('staff');
$anon = new Client('anon');

section('Room types are stored, not hard-coded');
$types = q('SELECT * FROM room_types ORDER BY sort_order');
$byCode = array_column($types, null, 'code');
check(count($types) === 3, 'the three offerings are seeded', (string) count($types));
check(($byCode['solo']['name'] ?? '') === 'Solo Executive Room' && (float) $byCode['solo']['price'] === 3500.0 && $byCode['solo']['price_unit'] === 'room', 'Solo Executive Room is ₱3,500 per room');
check(($byCode['twin']['name'] ?? '') === 'Twin Sharing Scholar Suite' && (float) $byCode['twin']['price'] === 2200.0 && $byCode['twin']['price_unit'] === 'bed' && (int) $byCode['twin']['is_popular'] === 1, 'Twin Sharing Scholar Suite is ₱2,200 per bed and the popular one');
check(($byCode['quad']['name'] ?? '') === 'Quad Bedspace Sanctuary' && (float) $byCode['quad']['price'] === 1600.0 && $byCode['quad']['price_unit'] === 'bed', 'Quad Bedspace Sanctuary is ₱1,600 per bed');
$soloId = (int) $byCode['solo']['id'];
$twinId = (int) $byCode['twin']['id'];

$landing = $anon->get('/');
check($landing->has('Solo Executive Room') && $landing->has('₱3,500') && $landing->has('/ bed / month'), 'the public page shows the stored offerings');

$rooms = $admin->get('/admin/rooms');
check($rooms->has('Room Types') && $rooms->has('Twin Sharing Scholar Suite') && $rooms->has('₱2,200 / bed / month'), 'Rooms & Beds lists the offerings with price and unit');

section('Editing an offering');
$twin = $byCode['twin'];
$editFields = ['name' => $twin['name'], 'capacity_label' => $twin['capacity_label'], 'price' => '2350', 'price_unit' => 'bed',
    'badge' => $twin['badge'], 'is_popular' => '1', 'features' => $twin['features']];
$admin->post("/admin/room-types/{$twinId}/update", $editFields);
check((float) q1('SELECT price FROM room_types WHERE id = ?', [$twinId]) === 2350.0, 'the admin changes the twin price');
check($anon->get('/')->has('₱2,350'), 'the public page shows the new price straight away');
$admin->post("/admin/room-types/{$twinId}/update", ['price' => '-5'] + $editFields);
check((float) q1('SELECT price FROM room_types WHERE id = ?', [$twinId]) === 2350.0, 'a negative price is refused');
$admin->post("/admin/room-types/{$twinId}/update", ['name' => 'Solo Executive Room'] + $editFields);
check(q1('SELECT name FROM room_types WHERE id = ?', [$twinId]) === 'Twin Sharing Scholar Suite', 'two types cannot share a name');
check($staff->post("/admin/room-types/{$twinId}/update", ['price' => '1'] + $editFields)->code === 403, 'staff cannot change prices');
$admin->post("/admin/room-types/{$twinId}/update", ['price' => '1'] + $editFields, false);
check((float) q1('SELECT price FROM room_types WHERE id = ?', [$twinId]) === 2350.0, 'a request without a CSRF token changes nothing');

// Re-running the migration's seed never duplicates a type and never overwrites the admin's edit.
$sql = file_get_contents(__DIR__ . '/../database/migrations/0040_room_types_and_inquiry_conversion.sql');
preg_match('/INSERT IGNORE INTO room_types.*?;\s*$/ms', $sql, $m);
db()->exec($m[0]);
check((int) q1('SELECT COUNT(*) FROM room_types') === 3 && (float) q1('SELECT price FROM room_types WHERE id = ?', [$twinId]) === 2350.0, 're-seeding is idempotent and keeps edited prices');
$admin->post("/admin/room-types/{$twinId}/update", ['price' => '2200'] + $editFields);

section('Physical rooms point at a type');
$room = makeRoom($admin, 1, 3500, 1);
$admin->post("/admin/rooms/{$room['id']}/update", ['room_number' => $room['number'], 'floor' => '1', 'capacity' => 1, 'base_price' => 3500, 'room_type_id' => $soloId]);
check((int) q1('SELECT room_type_id FROM rooms WHERE id = ?', [$room['id']]) === $soloId, 'a room is linked to Solo Executive Room');
$admin->post("/admin/rooms/{$room['id']}/update", ['room_number' => $room['number'], 'floor' => '1', 'capacity' => 1, 'base_price' => 3500, 'room_type_id' => '999999']);
check((int) q1('SELECT room_type_id FROM rooms WHERE id = ?', [$room['id']]) === $soloId, 'an unknown type is refused');
check((int) q1('SELECT COUNT(*) FROM beds WHERE room_id = ?', [$room['id']]) === 1, 'linking a type creates no beds');

section('Inquiry fills in Add Boarder');
$tag = uniq();
$email = "conv{$tag}@t.test";
q("INSERT INTO inquiries (name, phone, email, room_type, source) VALUES (?, '+639676355474', ?, 'Solo Executive Room', 'website')", ["Kierby {$tag}", $email]);
$inqId = (int) q1('SELECT id FROM inquiries WHERE email = ?', [$email]);

$detail = $admin->get("/admin/inquiries/{$inqId}");
check($detail->has("/admin/boarders?inquiry={$inqId}") && $detail->has('Convert to Boarder'), 'the inquiry page offers Convert to Boarder');
check($detail->has('₱3,500 / month'), 'the inquiry page shows the requested room\'s price');
check((bool) preg_match('#href="/admin/inquiry-center"\s+class="sidebar-nav-item mobile-nav-link active"#', $detail->body), 'the sidebar highlights Inquiry Center on an inquiry');

$form = $admin->get("/admin/boarders?inquiry={$inqId}");
check($form->code === 200 && $form->has('value="Kierby ' . $tag . '"') && $form->has('value="' . $email . '"') && $form->has('value="+639676355474"'), 'name, email and phone are filled in');
check($form->has('name="inquiry_id" value="' . $inqId . '"'), 'the form carries the inquiry id');
check(!$form->has('id="add_boarder_room"') && !$form->has('id="add_boarder_bed"'), 'Add Boarder no longer asks for a room or bed');
check((bool) preg_match('#id="assign-room-type".*?<option value="' . $soloId . '"\s+selected>Solo Executive Room \(\d+ vacant\)#s', $form->body), 'Assign to Bed opens on the requested room type');
check((bool) preg_match('#id="assign-bed".*?<option value="' . $room['beds'][0] . '" data-room-type="' . $soloId . '">#s', $form->body), 'each vacant bed carries its room type for the filter');
check((bool) preg_match('#id="rooms-assign-type".*?<option value="' . $soloId . '">#s', $admin->get('/admin/rooms')->body), 'Rooms & Beds has the same Room Type filter');
check(!$form->has('name="password" value='), 'no password is filled in');
check((int) q1('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) === 0, 'opening the form creates nothing');
check(!$admin->get('/admin/boarders')->has("Kierby {$tag}"), 'an ordinary visit to Boarders is not prefilled');
check($staff->get("/admin/boarders?inquiry={$inqId}")->code === 403, 'staff cannot open it');
check($admin->get('/admin/boarders?inquiry=999999')->has('Inquiry #999999 was not found.'), 'an unknown inquiry is explained, not prefilled');

q("UPDATE rooms SET room_type_id = NULL WHERE room_type_id = ?", [$twinId]);
q("INSERT INTO inquiries (name, phone, room_type, source) VALUES (?, '09170000000', 'Twin Sharing Scholar Suite', 'website')", ["Twin {$tag}"]);
$twinInq = (int) q1('SELECT id FROM inquiries WHERE name = ?', ["Twin {$tag}"]);
check($admin->get("/admin/boarders?inquiry={$twinInq}")->has('No Twin Sharing Scholar Suite has a vacant bed'), 'a type with no vacant room is explained');

section('Submitting creates one boarder and closes the inquiry');
$r = $admin->post('/admin/boarders', ['name' => "Kierby {$tag}", 'email' => $email, 'contact_number' => '+639676355474', 'password' => 'short', 'room_id' => $room['id'], 'bed_id' => $room['beds'][0], 'inquiry_id' => $inqId]);
check((int) q1('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) === 0, 'a weak password creates nothing');
check(($r->loc ?? '') === "/admin/boarders?inquiry={$inqId}", 'a failed submit returns to the same inquiry', (string) $r->loc);
check(str_contains($admin->get($r->loc)->body, 'value="Kierby ' . $tag . '"'), 'what was typed is kept');

$admin->post('/admin/boarders', ['name' => "Kierby {$tag}", 'email' => $email, 'contact_number' => '+639676355474', 'password' => 'Tmp-convert-heron-dune', 'room_id' => $room['id'], 'bed_id' => $room['beds'][0], 'inquiry_id' => $inqId]);
$userId = (int) q1('SELECT id FROM users WHERE email = ?', [$email]);
check($userId > 0, 'the boarder account is created');
$inq = q('SELECT converted_user_id, status FROM inquiries WHERE id = ?', [$inqId])[0];
check((int) $inq['converted_user_id'] === $userId && $inq['status'] === 'closed', 'the inquiry records the account and is closed');
check(q1('SELECT contact_number FROM boarder_profiles WHERE user_id = ?', [$userId]) === '639676355474', 'the phone becomes the contact number, digits only');
check((int) q1('SELECT current_boarder_id FROM beds WHERE id = ?', [$room['beds'][0]]) === $userId, 'the chosen bed is assigned');

$admin->post('/admin/boarders', ['name' => "Kierby {$tag}", 'email' => "again{$tag}@t.test", 'password' => 'Tmp-convert-heron-dune', 'inquiry_id' => $inqId]);
check((int) q1('SELECT COUNT(*) FROM users WHERE email = ?', ["again{$tag}@t.test"]) === 0, 'the same inquiry cannot become a second account');
check($admin->get("/admin/boarders?inquiry={$inqId}")->has('already registered'), 'reopening the link says it is already registered');
check($admin->get("/admin/inquiries/{$inqId}")->has('Registered as a boarder') && !$admin->get("/admin/inquiries/{$inqId}")->has('Convert to Boarder'), 'the inquiry page links to the boarder instead');

section('The inquirer already has an account');
$resident = makeResident($admin);
q("INSERT INTO inquiries (name, phone, email, room_type, source) VALUES ('Returning', '09170000001', ?, 'Solo Executive Room', 'website')", [$resident['email']]);
$retId = (int) q1('SELECT id FROM inquiries WHERE name = ? ORDER BY id DESC LIMIT 1', ['Returning']);
$page = $admin->get("/admin/boarders?inquiry={$retId}");
check($page->has('already has an account') && $page->has("/admin/inquiries/{$retId}/link-boarder"), 'the form warns before a password is typed and offers to link');
check($staff->post("/admin/inquiries/{$retId}/link-boarder")->code === 403, 'staff cannot link it');
$admin->post("/admin/inquiries/{$retId}/link-boarder", [], false);
check(q1('SELECT converted_user_id FROM inquiries WHERE id = ?', [$retId]) === null, 'linking without a CSRF token changes nothing');
$admin->post("/admin/inquiries/{$retId}/link-boarder");
check((int) q1('SELECT converted_user_id FROM inquiries WHERE id = ?', [$retId]) === $resident['id'] && q1('SELECT status FROM inquiries WHERE id = ?', [$retId]) === 'closed', 'the inquiry is linked to the existing boarder and closed');
check((int) q1('SELECT COUNT(*) FROM users WHERE email = ?', [$resident['email']]) === 1, 'no duplicate account is made');
$admin->post("/admin/inquiries/{$inqId}/link-boarder");
check((int) q1('SELECT converted_user_id FROM inquiries WHERE id = ?', [$inqId]) === $userId, 'an already-converted inquiry cannot be relinked');

section('Adding a boarder without an inquiry still works');
$plain = 'plain' . uniq() . '@t.test';
$admin->post('/admin/boarders', ['name' => 'Plain Boarder', 'email' => $plain, 'password' => 'Tmp-convert-heron-dune']);
check((int) q1('SELECT COUNT(*) FROM users WHERE email = ?', [$plain]) === 1, 'a boarder is created as before');
$plainId = (int) q1('SELECT id FROM users WHERE email = ?', [$plain]);
$after = $admin->get('/admin/boarders');
check((bool) preg_match('#<option value="' . $plainId . '"\s+selected>#', $after->body) && $after->has('Assign a bed with Assign to Bed.'), 'the new boarder is preselected in Assign to Bed');
check(!(bool) preg_match('#<option value="' . $plainId . '"\s+selected>#', $admin->get('/admin/boarders')->body), 'the preselection is used once');

section('A converted inquiry carries its room type into Assign to Bed');
$tag2 = uniq();
q("INSERT INTO inquiries (name, phone, email, room_type, source) VALUES (?, '09170000002', ?, 'Solo Executive Room', 'website')", ["Solo {$tag2}", "solo{$tag2}@t.test"]);
$soloInq = (int) q1('SELECT id FROM inquiries WHERE email = ?', ["solo{$tag2}@t.test"]);
$admin->post('/admin/boarders', ['name' => "Solo {$tag2}", 'email' => "solo{$tag2}@t.test", 'password' => 'Tmp-convert-heron-dune', 'inquiry_id' => $soloInq]);
$soloUser = (int) q1('SELECT id FROM users WHERE email = ?', ["solo{$tag2}@t.test"]);
$after = $admin->get('/admin/boarders')->body;
check($soloUser > 0 && (bool) preg_match('#<option value="' . $soloUser . '"\s+selected>#', $after)
    && (bool) preg_match('#id="assign-room-type".*?<option value="' . $soloId . '"\s+selected>#s', $after), 'the new boarder and their requested room type are preselected');
check(q1('SELECT bed_id FROM boarder_profiles WHERE user_id = ?', [$soloUser]) === null, 'no bed is given until the admin assigns one');
