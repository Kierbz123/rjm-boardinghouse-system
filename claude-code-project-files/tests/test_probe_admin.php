<?php
/**
 * Audit probe 2 (14 Oct 2026): rooms, beds, residents, status/date handling, billing cycles,
 * archive/delete, password resets, staff accounts, electricity bills, occupancy figures.
 * Open defects are knownDefect() until their fix lands.
 */
require __DIR__ . '/lib.php';

$admin = seededClient('admin');
$staff = seededClient('staff');

section('Rooms and beds');
$number = 'R' . uniq();
$r = $admin->post('/admin/rooms', ['room_number' => $number, 'floor' => '2', 'capacity' => 2, 'base_price' => 3000]);
check(flashHas($admin, $r, "Room {$number} added"), 'create a room');
$roomId = (int) q1('SELECT id FROM rooms WHERE room_number = ?', [$number]);
check(flashHas($admin, $admin->post('/admin/rooms', ['room_number' => $number, 'capacity' => 2, 'base_price' => 3000]), 'already exists'), 'duplicate room number refused');
check(flashHas($admin, $admin->post('/admin/rooms', ['room_number' => 'Z' . uniq(), 'capacity' => 0, 'base_price' => 3000]), 'capacity must be greater'), 'capacity 0 refused');
check(flashHas($admin, $admin->post('/admin/rooms', ['room_number' => 'Z' . uniq(), 'capacity' => 2, 'base_price' => -5]), 'Monthly price'), 'negative price refused');
check(flashHas($admin, $admin->post('/admin/rooms', ['room_number' => 'Z' . uniq(), 'capacity' => 2, 'base_price' => 'abc']), 'Monthly price'), 'non-numeric price refused');
$huge = 'Z' . uniq();
$r = $admin->post('/admin/rooms', ['room_number' => $huge, 'capacity' => 99999999999, 'base_price' => 10]);
knownDefect('F-10', $r->code < 500 && !q1('SELECT COUNT(*) FROM rooms WHERE room_number = ?', [$huge]), 'capacity 99999999999 is refused cleanly', "HTTP {$r->code}");
$r = $admin->post('/admin/rooms/999999/update', ['room_number' => 'Q' . uniq(), 'capacity' => 2, 'base_price' => 100]);
knownDefect('F-27', !flashHas($admin, $r, 'updated'), 'updating a room that does not exist does not claim success');

$admin->post('/admin/beds', ['room_id' => $roomId, 'label' => 'Bed A', 'count' => 2]);
$beds = array_map('intval', array_column(q('SELECT id FROM beds WHERE room_id = ? ORDER BY label', [$roomId]), 'id'));
check(count($beds) === 2, 'two beds created from one label (A, B series)');
check(flashHas($admin, $admin->post('/admin/beds', ['room_id' => $roomId, 'label' => 'Bed C', 'count' => 1]), 'holds 2'), 'beds cannot exceed the room capacity');
check(flashHas($admin, $admin->post("/admin/rooms/{$roomId}/update", ['room_number' => $number, 'floor' => '2', 'capacity' => 1, 'base_price' => 3000]), 'capacity cannot be lower'), 'capacity below the bed count refused');
check(flashHas($admin, $admin->post("/admin/rooms/{$roomId}/delete"), 'still has beds'), 'a room with beds cannot be deleted');
check(flashHas($admin, $admin->post("/admin/beds/{$beds[1]}/update", ['label' => 'Bed A']), 'already has a bed'), 'renaming a bed to an existing label is refused');
check(flashHas($admin, $admin->post('/admin/beds/999999/delete'), 'not found'), 'deleting a missing bed says so');

section('Residents: create / validate / assign');
$email = 'one' . uniq() . '@t.test';
$mk = fn (array $o) => $admin->post('/admin/boarders', $o + ['name' => 'Audit One', 'email' => $email, 'password' => 'Passw0rd!x']);
check(flashHas($admin, $mk(['bed_id' => $beds[0], 'room_id' => $roomId]), 'Added Audit One'), 'create a resident on a bed');
$one = (int) q1('SELECT id FROM users WHERE email = ?', [$email]);
check(q1('SELECT status FROM beds WHERE id = ?', [$beds[0]]) === 'occupied' && (int) q1('SELECT bed_id FROM boarder_profiles WHERE user_id = ?', [$one]) === $beds[0], 'the bed becomes occupied and the profile points at it');
check(q1('SELECT status FROM boarder_profiles WHERE user_id = ?', [$one]) === 'pending', 'a new resident starts as pending');
check(flashHas($admin, $mk([]), 'already in use'), 'duplicate e-mail refused');
check(flashHas($admin, $mk(['email' => strtoupper($email)]), 'already in use'), 'duplicate e-mail in other letter case refused');
check(flashHas($admin, $mk(['email' => 'not-an-email']), 'valid email'), 'invalid e-mail refused');
check(flashHas($admin, $mk(['email' => 's' . uniq() . '@t.test', 'password' => '1234567']), '8 characters'), 'password under 8 refused');
$long = 'l' . uniq() . '@t.test';
$mk(['email' => $long, 'password' => str_repeat('p', 100) . 'A']);
$accepted = (bool) q1('SELECT COUNT(*) FROM users WHERE email = ?', [$long]);
$loginWithDifferentTail = $accepted ? (new Client('long'))->login($long, str_repeat('p', 100) . 'ZZZ')->loc : '';
knownDefect('F-07', !$accepted || $loginWithDifferentTail !== '/portal/dashboard', 'a password over 72 bytes is refused (or at least never truncated silently)', $accepted ? 'accepted, and a different tail still logs in' : '');
check(flashHas($admin, $mk(['email' => 'n' . uniq() . '@t.test', 'name' => str_repeat('n', 151)]), '150 characters'), 'name over 150 refused');
$xss = 'x' . uniq() . '@t.test';
$mk(['email' => $xss, 'name' => '<script>alert(1)</script>']);
$list = $admin->get('/admin/boarders', true);
check(!$list->has('<script>alert(1)</script>') && $list->has('&lt;script&gt;'), 'stored XSS in a name is escaped');
$occ = 'o' . uniq() . '@t.test';
check(flashHas($admin, $mk(['email' => $occ, 'bed_id' => $beds[0], 'room_id' => $roomId]), 'already occupied') && !q1('SELECT COUNT(*) FROM users WHERE email = ?', [$occ]), 'an occupied bed is refused and the half-made account is rolled back');

$two = makeResident($admin);
check(flashHas($admin, $admin->post('/admin/beds/assign', ['boarder_id' => $two['id'], 'bed_id' => $beds[0]]), 'already occupied'), 'assigning an occupied bed is refused');
$admin->post('/admin/beds/assign', ['boarder_id' => $two['id'], 'bed_id' => $beds[1]]);
check((int) q1('SELECT current_boarder_id FROM beds WHERE id = ?', [$beds[1]]) === $two['id'], 'assigning a free bed works');
check(flashHas($admin, $admin->post('/admin/beds/assign', ['boarder_id' => 999999, 'bed_id' => $beds[1]]), 'not found'), 'an unknown resident is refused');
check(flashHas($admin, $admin->post('/admin/beds/assign', ['boarder_id' => 1, 'bed_id' => $beds[1]]), 'not found'), 'an admin account id cannot be given a bed');
check((int) q1('SELECT COUNT(*) FROM beds WHERE status = "occupied"') === (int) q1('SELECT COUNT(DISTINCT current_boarder_id) FROM beds WHERE current_boarder_id IS NOT NULL'), 'no ghost or double-booked beds anywhere');

section('Status, move-in and move-out dates, billing cycles');
$info = fn (int $id, string $status, string $note = '') => $admin->post("/admin/boarders/{$id}/info", ['name' => 'Audit Two', 'email' => $two['email'], 'status' => $status, 'note' => $note]);
$info($two['id'], 'active');
check(q1('SELECT status FROM boarder_profiles WHERE user_id = ?', [$two['id']]) === 'active', 'pending to active');
check(q1('SELECT move_in_date FROM boarder_profiles WHERE user_id = ?', [$two['id']]) === date('Y-m-d'), 'move-in defaults to today when activated');
check((float) q1('SELECT amount FROM rent_charges WHERE boarder_id = ?', [$two['id']]) === 3000.0, 'the first rent cycle is the room price');
check(flashHas($admin, $info($two['id'], 'bogus'), 'Invalid boarder status'), 'an invalid status is refused');
$info($two['id'], 'on_notice', 'giving notice');
check((int) q1('SELECT COUNT(*) FROM boarder_status_log WHERE boarder_id = ?', [$two['id']]) >= 2, 'status changes are logged');
$dates = fn (string $in, string $out = '') => $admin->post("/admin/boarders/{$two['id']}/dates", ['move_in_date' => $in, 'move_out_date' => $out]);
$dates(date('Y-m-d', strtotime('-75 days')));
check((int) q1('SELECT COUNT(*) FROM rent_charges WHERE boarder_id = ?', [$two['id']]) === 3, '75 days of stay is three 30-day cycles');
check(flashHas($admin, $dates('2026-02-31'), 'Invalid move-in'), 'an impossible date is refused');
check(flashHas($admin, $dates('2026-05-01', '2026-04-01'), 'cannot be before'), 'move-out before move-in is refused');
$dates(date('Y-m-d', strtotime('+90 days')));
check((int) q1('SELECT COUNT(*) FROM rent_charges WHERE boarder_id = ?', [$two['id']]) === 0, 'a future move-in date charges nothing yet');
$dates('1900-01-01');
$rows = (int) q1('SELECT COUNT(*) FROM rent_charges WHERE boarder_id = ?', [$two['id']]);
knownDefect('F-03', $rows === 0 || $rows < 120, 'a move-in date in 1900 is refused (or cannot generate a runaway number of rent rows)', "{$rows} rent rows created");
$dates(date('Y-m-d', strtotime('-75 days')));
$future = date('Y-m-d', strtotime('+30 days'));
$dates(date('Y-m-d', strtotime('-10 days')), $future);
$st = q('SELECT status, bed_id FROM boarder_profiles WHERE user_id = ?', [$two['id']])[0];
knownDefect('F-05', $st['status'] !== 'moved_out' && $st['bed_id'] !== null, 'a FUTURE move-out date keeps the resident and the bed until that day', "status={$st['status']} bed=" . var_export($st['bed_id'], true));
$dates(date('Y-m-d', strtotime('-75 days')), date('Y-m-d', strtotime('-10 days')));
check(q1('SELECT status FROM boarder_profiles WHERE user_id = ?', [$two['id']]) === 'moved_out' && q1('SELECT bed_id FROM boarder_profiles WHERE user_id = ?', [$two['id']]) === null, 'a PAST move-out date marks moved_out and frees the bed');
check(flashHas($admin, $admin->post('/admin/beds/assign', ['boarder_id' => $two['id'], 'bed_id' => $beds[1]]), 'moved-out'), 'a moved-out resident cannot be given a bed');

section('Remove / archive / restore (history must survive)');
$del = makeResident($admin);
db()->prepare('INSERT INTO maintenance_requests (boarder_id, category, description, priority_tier, severity_score, status) VALUES (?, "plumbing", "history", "low", 5, "open")')->execute([$del['id']]);
db()->prepare('INSERT INTO incidents (reported_by, type, description) VALUES (?, "noise", "history")')->execute([$del['id']]);
db()->prepare('INSERT INTO sos_alerts (boarder_id, status) VALUES (?, "resolved")')->execute([$del['id']]);
$admin->post("/admin/boarders/{$del['id']}/delete");
$survived = (int) q1('SELECT COUNT(*) FROM maintenance_requests WHERE boarder_id = ?', [$del['id']]) + (int) q1('SELECT COUNT(*) FROM incidents WHERE reported_by = ?', [$del['id']]) + (int) q1('SELECT COUNT(*) FROM sos_alerts WHERE boarder_id = ?', [$del['id']]);
knownDefect('F-04', $survived === 3, 'removing a resident keeps their repair, incident and SOS history', "{$survived}/3 survived");
$arch = makeResident($admin);
db()->prepare('INSERT INTO payments (boarder_id, billing_period, expected_amount, claimed_amount, verification_status) VALUES (?, "2026-10", 100, 100, "pending")')->execute([$arch['id']]);
$admin->post("/admin/boarders/{$arch['id']}/delete");
check(q1('SELECT status FROM users WHERE id = ?', [$arch['id']]) === 'archived', 'a resident with payments is archived, not erased');
check((new Client('a'))->login($arch['email'], $arch['password'])->loc !== '/portal/dashboard', 'an archived resident cannot log in');
$admin->post("/admin/boarders/{$arch['id']}/restore");
check(q1('SELECT status FROM users WHERE id = ?', [$arch['id']]) === 'active', 'restore re-enables the account');
$admin->post('/admin/boarders/1/delete');
$admin->post('/admin/boarders/2/delete');
check((int) q1('SELECT COUNT(*) FROM users WHERE id IN (1, 2)') === 2, 'the delete route cannot remove the admin or staff account');

section('Password resets and staff accounts');
$admin->post("/admin/boarders/{$arch['id']}/password", ['new_password' => 'NewTemp#123']);
check((new Client('rs'))->login($arch['email'], 'NewTemp#123')->loc === '/portal/dashboard', 'admin can reset a resident password');
$admin->post('/admin/boarders/1/password', ['new_password' => 'Hacked#1234']);
check(!password_verify('Hacked#1234', (string) q1('SELECT password_hash FROM users WHERE id = 1')), 'the resident-reset route cannot reset the admin');
$se = 'staff' . uniq() . '@t.test';
check(flashHas($admin, $admin->post('/admin/staff', ['name' => 'Staff Two', 'email' => $se, 'password' => 'Staff#12345']), 'created'), 'create a staff account');
$s2 = (int) q1('SELECT id FROM users WHERE email = ?', [$se]);
check(flashHas($admin, $admin->post('/admin/staff', ['name' => 'Staff Two', 'email' => $se, 'password' => 'Staff#12345']), 'already in use'), 'duplicate staff e-mail refused');
$s2c = new Client('s2');
$s2c->login($se, 'Staff#12345');
check($s2c->get('/staff/dashboard')->code === 200, 'a new staff member can sign in');
$admin->post("/admin/staff/{$s2}/status", ['status' => 'inactive']);
check($s2c->get('/staff/dashboard')->code === 302, 'deactivating staff ends their open session at once');
$admin->post("/admin/staff/{$s2}/status", ['status' => 'active']);
$admin->post("/admin/staff/{$arch['id']}/status", ['status' => 'inactive']);
check(q1('SELECT status FROM users WHERE id = ?', [$arch['id']]) === 'active', 'the staff route cannot deactivate a resident');
$admin->post('/admin/staff/1/status', ['status' => 'inactive']);
check(q1('SELECT status FROM users WHERE id = 1') === 'active', 'the staff route cannot deactivate the admin');
check(flashHas($admin, $admin->post("/admin/staff/{$s2}/password", ['new_password' => 'x']), '8 to 72'), 'a staff reset needs 8 to 72 characters');

section('Electricity bills');
$room = makeRoom($admin, 2, 3000, 2);
$res = makeResident($admin, $room['beds'][0], 40);
$label = 'Oct ' . uniq();
$r = $admin->post('/admin/electricity', ['room_id' => $room['id'], 'label' => $label, 'amount' => 1000]);
check(flashHas($admin, $r, 'split among 1 resident'), 'a bill is split among the active residents of the room');
check(flashHas($admin, $admin->post('/admin/electricity', ['room_id' => $room['id'], 'label' => $label, 'amount' => 1000]), 'already entered'), 'the same bill twice is refused');
check(flashHas($admin, $admin->post('/admin/electricity', ['room_id' => $room['id'], 'label' => 'X', 'amount' => 0.5]), 'from'), 'an amount under 1 is refused');
check(flashHas($admin, $admin->post('/admin/electricity', ['room_id' => $room['id'], 'label' => 'X', 'amount' => '1e9']), 'from'), 'an absurd amount is refused');
$billId = (int) q1('SELECT id FROM electricity_bills WHERE label = ? AND room_id = ?', [$label, $room['id']]);
check((float) q1('SELECT amount FROM electricity_charges WHERE bill_id = ?', [$billId]) === 1000.0, 'the resident was charged the share');
check(flashHas($admin, $admin->post("/admin/electricity/{$billId}/delete"), 'removed') && !q1('SELECT COUNT(*) FROM electricity_charges WHERE bill_id = ?', [$billId]), 'deleting a bill removes the shares');

section('Occupancy figures agree everywhere');
$extra = makeResident($admin);
$admin->get('/admin/dashboard', true);
$admin->post('/admin/beds/assign', ['boarder_id' => $extra['id'], 'bed_id' => $room['beds'][1]]);
$real = (int) q1('SELECT COUNT(*) FROM beds WHERE status = "occupied"');
$admin->get('/admin/dashboard', true);
$snapshot = (int) q1('SELECT occupied_beds FROM occupancy_snapshots WHERE snapshot_date = CURDATE()');
knownDefect('F-12', $snapshot === $real, 'the dashboard occupancy follows bed changes made after the first visit today', "beds occupied now={$real}, dashboard uses {$snapshot}");
