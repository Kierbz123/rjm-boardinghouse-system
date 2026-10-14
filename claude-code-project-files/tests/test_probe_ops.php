<?php
/**
 * Audit probe 4 (14 Oct 2026): repairs, SOS, incidents, notifications, inquiries and the landing
 * page, profile, assistant. Open defects are knownDefect() until their fix lands.
 */
require __DIR__ . '/lib.php';

$admin = seededClient('admin');
$staff = seededClient('staff');
$anon = new Client('anon');

$room = makeRoom($admin, 2, 3000, 2);
$res = makeResident($admin, $room['beds'][0], 10);
$B = $res['id'];
$me = $res['client'];
$other = makeResident($admin)['client'];

section('Repairs: resident report to staff queue to resolved');
$report = function (array $fields, ?string $file = null, string $mime = 'image/png') use ($me) {
    $fields['csrf_token'] = $me->token();
    if ($file) {
        $fields['media'] = new CURLFile($file, $mime, 'p.' . ($mime === 'video/mp4' ? 'mp4' : 'png'));
    }
    return $me->req('POST', '/portal/maintenance', $fields);
};
check($me->get('/portal/maintenance/new')->code === 200, 'the report-a-repair form opens');
check(flashHas($me, $report(['category' => 'plumbing', 'description' => '']), 'Description is required'), 'an empty description is refused');
check(flashHas($me, $report(['category' => 'hacking', 'description' => 'x']), 'valid category'), 'an invalid category is refused');
check(flashHas($me, $report(['category' => 'plumbing', 'description' => str_repeat('a', 5001)]), '5,000'), 'a description over 5,000 characters is refused');
$report(['category' => 'electrical', 'description' => 'There is an exposed wire sparking near the bed, smell of smoke'], tmpFileWith(png(), 'png'));
$mid = (int) q1('SELECT id FROM maintenance_requests WHERE boarder_id = ? ORDER BY id DESC LIMIT 1', [$B]);
$row = q('SELECT priority_tier, room_id, media_path FROM maintenance_requests WHERE id = ?', [$mid])[0] ?? [];
check(($row['priority_tier'] ?? '') === 'critical', 'dangerous keywords plus a photo score CRITICAL');
check(!empty($row['room_id']), 'the room comes from the resident profile, not the form');
$report(['category' => 'other', 'description' => 'Squeaky door, small scratch ' . uniq()]);
$mid2 = (int) q1('SELECT id FROM maintenance_requests WHERE boarder_id = ? ORDER BY id DESC LIMIT 1', [$B]);
check(q1('SELECT priority_tier FROM maintenance_requests WHERE id = ?', [$mid2]) === 'low', 'a cosmetic issue scores LOW');
$r = $me->req('POST', '/api/maintenance/score-preview', http_build_query(['csrf_token' => $me->token(), 'description' => 'fire and smoke', 'category' => 'electrical']), ['Content-Type: application/x-www-form-urlencoded']);
$j = json_decode($r->body, true);
check(!empty($j['ok']) && in_array($j['tier'] ?? '', ['high', 'critical'], true), 'the live score preview works');
$queue = $staff->get('/staff/maintenance');
check(strpos($queue->body, 'exposed wire') !== false && strpos($queue->body, 'exposed wire') < strpos($queue->body, 'Squeaky door'), 'the staff queue lists the urgent request first');
$staff->post("/staff/maintenance/{$mid}/status", ['status' => 'in_progress']);
check(q1('SELECT status FROM maintenance_requests WHERE id = ?', [$mid]) === 'in_progress', 'staff set in_progress');
check((int) q1('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND message LIKE ?', [$B, "%#{$mid} is now in progress%"]) === 1, 'the resident is notified');
check(flashHas($staff, $staff->post("/staff/maintenance/{$mid}/status", ['status' => 'bogus']), 'invalid status'), 'an invalid status is refused');
$staff->post("/staff/maintenance/{$mid}/status", ['status' => 'resolved']);
check(q1('SELECT status FROM maintenance_requests WHERE id = ?', [$mid]) === 'resolved' && (int) q1('SELECT resolved_by FROM maintenance_requests WHERE id = ?', [$mid]) > 0, 'resolved, recording who resolved it');
$staff->post("/staff/maintenance/{$mid}/status", ['status' => 'open']);
knownDefect('F-18', q1('SELECT status FROM maintenance_requests WHERE id = ?', [$mid]) === 'resolved', 'a resolved repair cannot be silently re-opened without a reason');
check($staff->get('/staff/maintenance/history?page=99999')->code === 200 && $staff->get('/staff/maintenance/history?page=-5')->code === 200, 'history paging survives huge and negative page numbers');
check(!$other->get('/portal/dashboard')->has('exposed wire'), "another resident's dashboard does not show the request");
$media = q1('SELECT media_path FROM maintenance_requests WHERE id = ?', [$mid]);
check($staff->get($media)->code === 200 && $me->get($media)->code === 200 && $other->get($media)->code === 404, 'repair photos open for staff and owner, not for other residents');

section('SOS');
$sos = fn (Client $c) => json_decode($c->req('POST', '/api/sos', ['csrf_token' => $c->token()])->body, true);
q('DELETE FROM sos_alerts WHERE boarder_id = ?', [$B]);
$a = $sos($me);
check(!empty($a['ok']) && !empty($a['alert_id']), 'the SOS button creates an alert');
$sid = (int) $a['alert_id'];
check((int) $sos($me)['alert_id'] === $sid && (int) q1('SELECT COUNT(*) FROM sos_alerts WHERE boarder_id = ?', [$B]) === 1, 'a second press within 2 minutes reuses the alert');
check(in_array('active', array_column(json_decode($staff->get('/api/sos/active')->body, true), 'status'), true), 'the staff polling endpoint lists it');
check((int) q1("SELECT COUNT(*) FROM notifications WHERE type = 'sos' AND entity_id = ?", [$sid]) >= 2, 'staff and admin are both notified');
check($me->post("/staff/sos/{$sid}/ack")->code === 403, 'a resident cannot acknowledge');
$staff->post("/staff/sos/{$sid}/ack");
check(q1('SELECT status FROM sos_alerts WHERE id = ?', [$sid]) === 'acknowledged' && (int) q1('SELECT acknowledged_by FROM sos_alerts WHERE id = ?', [$sid]) > 0, 'staff acknowledge, recording who');
check((int) q1("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'sos_acknowledged'", [$B]) === 1, 'the resident is told help is responding');
$staff->post("/staff/sos/{$sid}/resolve");
check(q1('SELECT status FROM sos_alerts WHERE id = ?', [$sid]) === 'resolved', 'staff resolve');
check($staff->req('POST', '/api/sos', ['csrf_token' => $staff->token()])->code === 403, 'staff cannot trigger an SOS');

section('Incidents');
$file = fn (Client $c, array $f) => $c->post('/staff/incidents', $f);
check(flashHas($me, $file($me, ['type' => '', 'description' => 'x']), 'both required'), 'an empty type is refused');
$tag = uniq();
$file($me, ['type' => 'Noise', 'description' => "Loud music {$tag}"]);
$iid = (int) q1('SELECT id FROM incidents WHERE description = ?', ["Loud music {$tag}"]);
$file($staff, ['type' => 'Theft', 'description' => "Missing phone {$tag}"]);
check($iid > 0, 'a resident files an incident');
check(!$other->get('/staff/incidents')->has($tag) && !$other->get('/staff/incidents/history')->has($tag), "a resident sees only their own incidents");
check($staff->get('/staff/incidents')->has("Loud music {$tag}") && $staff->get('/staff/incidents')->has("Missing phone {$tag}"), 'staff see every incident');
check($me->post("/staff/incidents/{$iid}/resolve", ['resolution_notes' => 'self'])->code === 403, 'a resident cannot resolve');
check(flashHas($staff, $staff->post("/staff/incidents/{$iid}/resolve", ['resolution_notes' => '']), 'required'), 'resolving without notes is refused');
$staff->post("/staff/incidents/{$iid}/resolve", ['resolution_notes' => 'Spoke to the residents']);
check((int) q1('SELECT resolved FROM incidents WHERE id = ?', [$iid]) === 1, 'staff resolve with notes');
check(flashHas($staff, $staff->post("/staff/incidents/{$iid}/resolve", ['resolution_notes' => 'again']), 'already resolved'), 'resolving twice is refused');
check((int) q1("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'incident_resolved'", [$B]) === 1, 'the reporter is notified');
check(flashHas($staff, $file($staff, ['type' => str_repeat('t', 101), 'description' => 'd']), '100 characters'), 'a type over 100 characters is refused');
$file($staff, ['type' => '<img src=x onerror=alert(1)>', 'description' => '<script>alert(2)</script>']);
$page = $staff->get('/staff/incidents');
check(!$page->has('<script>alert(2)') && !$page->has('<img src=x onerror'), 'stored XSS in incident fields is escaped');

section('Notifications');
$mine = (int) q1('SELECT id FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$B]);
$theirs = (int) q1('SELECT id FROM notifications WHERE user_id <> ? ORDER BY id DESC LIMIT 1', [$B]);
check(flashHas($me, $me->post("/notifications/{$theirs}/read"), 'Access denied') && (int) q1('SELECT is_read FROM notifications WHERE id = ?', [$theirs]) === 0, "cannot mark someone else's notification read");
$me->post("/notifications/{$theirs}/delete");
check((int) q1('SELECT COUNT(*) FROM notifications WHERE id = ?', [$theirs]) === 1, "cannot delete someone else's notification");
$me->post("/notifications/{$mine}/read");
check((int) q1('SELECT is_read FROM notifications WHERE id = ?', [$mine]) === 1, 'mark one read');
$me->post("/notifications/{$mine}/toggle-read", ['is_read' => '1']);
check((int) q1('SELECT is_read FROM notifications WHERE id = ?', [$mine]) === 0, 'toggle back to unread');
$me->post("/notifications/{$mine}/toggle-pin");
check((int) q1('SELECT is_pinned FROM notifications WHERE id = ?', [$mine]) === 1, 'pin');
$me->post('/notifications/read-all');
check(!q1('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$B]) && json_decode($me->get('/api/notifications/unread')->body, true) === [], 'mark all read empties the unread list');
$me->post("/notifications/{$mine}/delete");
check(!q1('SELECT COUNT(*) FROM notifications WHERE id = ?', [$mine]), 'delete one of my own');

section('Inquiries and the landing page');
q('DELETE FROM inquiries WHERE ip_address = ?', ['127.0.0.1']);
q("DELETE FROM notifications WHERE entity_type = 'inquiry' AND entity_id NOT IN (SELECT id FROM inquiries)");
$base = (int) q1('SELECT COUNT(*) FROM inquiries');
$anon->req('POST', '/inquire', ['name' => 'Juan', 'phone' => '09171234567', 'email' => 'j@x.test', 'room_type' => 'Solo', 'message' => 'Is a room free?', 'website' => '']);
check((int) q1('SELECT COUNT(*) FROM inquiries') === $base + 1, 'a public inquiry is saved');
$anon->req('POST', '/inquire', ['name' => 'Bot', 'phone' => '09171234567', 'website' => 'http://spam']);
check((int) q1('SELECT COUNT(*) FROM inquiries') === $base + 1, 'the honeypot field silently drops bots');
$anon->req('POST', '/inquire', ['name' => '', 'phone' => 'abc']);
check((int) q1('SELECT COUNT(*) FROM inquiries') === $base + 1, 'an invalid inquiry is refused');
$j = json_decode($anon->req('POST', '/inquire', ['name' => 'J2', 'phone' => '09171234567'], ['Accept: application/json'])->body, true);
check(!empty($j['success']), 'JSON mode works for the fetch() form');
for ($i = 0; $i < 4; $i++) {
    $anon->req('POST', '/inquire', ['name' => "S{$i}", 'phone' => '09171234567'], ['Accept: application/json']);
}
$last = json_decode($anon->req('POST', '/inquire', ['name' => 'S9', 'phone' => '09171234567'], ['Accept: application/json'])->body, true);
check(($last['success'] ?? true) === false, 'the per-visitor limit of 5 an hour engages');
check($admin->get('/admin/inquiry-center')->has('Juan'), 'admin sees recent inquiries with contact details');
check(!$staff->get('/admin/inquiry-center')->has('09171234567'), "staff do not see prospects' phone numbers");
q('UPDATE rooms SET base_price = 9999 WHERE id = ?', [$room['id']]);
knownDefect('F-21', $anon->get('/')->has('9,999'), 'landing page prices follow the rooms table');
knownDefect('F-21', !str_contains(strtolower($anon->get('/')->body), 'cctv'), 'the landing page makes no CCTV claim while no CCTV feature exists');
q('UPDATE rooms SET base_price = 3000 WHERE id = ?', [$room['id']]);
check($anon->get('/landing')->code === 200, '/landing alias works');

section('Profile');
check(flashHas($me, $me->post('/profile/update', ['name' => 'Demo Boarder', 'email' => $res['email'], 'contact_number' => '0917 123', 'emergency_contact_number' => '0918']), 'updated'), 'a resident updates contact numbers');
check(flashHas($me, $me->post('/profile/update', ['name' => 'Demo', 'email' => 'admin@rjm.test']), 'already registered'), "another account's e-mail cannot be taken");
check(flashHas($me, $me->post('/profile/update', ['name' => 'Demo', 'email' => $res['email'], 'contact_number' => str_repeat('1', 51)]), '50 characters'), 'a contact number over 50 characters is refused');
check(flashHas($me, $me->post('/profile/password', ['current_password' => 'wrong', 'new_password' => 'NewPassw0rd!', 'confirm_password' => 'NewPassw0rd!']), 'incorrect'), 'a wrong current password is refused');
check(flashHas($me, $me->post('/profile/password', ['current_password' => $res['password'], 'new_password' => 'short', 'confirm_password' => 'short']), '8 to 72'), 'a weak new password is refused');
check(flashHas($me, $me->post('/profile/password', ['current_password' => $res['password'], 'new_password' => 'NewPassw0rd!', 'confirm_password' => 'Different1!']), 'do not match'), 'a confirmation mismatch is refused');
$second = new Client('second');
$second->login($res['email'], $res['password']);
$me->post('/profile/password', ['current_password' => $res['password'], 'new_password' => 'Changed#Pass1', 'confirm_password' => 'Changed#Pass1']);
check($second->get('/portal/dashboard')->code === 302 && $me->get('/portal/dashboard')->code === 200, 'changing the password signs out other sessions and keeps this one');

section('Assistant');
$ask = fn (Client $c, string $m) => json_decode($c->postJson('/api/assistant/ask', ['message' => $m, 'lang' => 'en'])->body, true);
check(str_contains(json_encode($ask($me, 'how much do I owe')), 'owe'), 'a resident can ask what they owe (rule-based, works without the AI model)');
check(str_contains(json_encode($ask($admin, 'who owes the most')), 'owe'), 'an admin can ask who owes the most');
check($me->postJson('/api/assistant/analyze-ticket', ['ticket_id' => 1])->code === 403, 'a resident cannot call a staff-only AI endpoint');
check($me->postJson('/api/assistant/ask', ['message' => 'hi'], false)->code === 403, 'an AI call without a CSRF token is refused');
$codes = [];
for ($i = 0; $i < 12; $i++) {
    $codes[] = $me->postJson('/api/assistant/suggest-category', ['description' => 'leaking tap'])->code;
}
check(in_array(429, $codes, true), 'the AI rate limit (10 a minute) engages', implode(',', $codes));
