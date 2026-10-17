<?php
/**
 * Staff Accounts audit (14 Oct 2026): password rule (15 characters to 72 bytes, guessable passwords
 * refused), temporary passwords that must be replaced and expire, lower-case unique e-mails,
 * deactivate / reactivate, admin-only + CSRF on every action, and an audit trail for each action.
 */
require __DIR__ . '/lib.php';

use App\Support\PasswordPolicy;

$admin = seededClient('admin');
$adminId = (int) q1("SELECT id FROM users WHERE email = 'admin@rjm.test'");
$ok = fn (string $p, array $ctx = []) => PasswordPolicy::problem($p, $ctx) === null;
$addStaff = fn (string $email, string $password, string $name = 'Crew Member') => $admin->post('/admin/staff', ['name' => $name, 'email' => $email, 'password' => $password]);
$idOf = fn (string $email) => (int) q1('SELECT id FROM users WHERE email = ?', [$email]);

section('Password length: characters and bytes');
$p14 = 'violet-harbor1';
$p15 = 'violet-harbor12';
check(mb_strlen($p14) === 14 && !$ok($p14), '14 characters refused');
check(mb_strlen($p15) === 15 && $ok($p15), '15 characters accepted');
$b72 = substr(str_repeat('amber lantern quiet harbor ', 3), 0, 72);
check(strlen($b72) === 72 && $ok($b72) && !$ok($b72 . 'x'), '72 bytes accepted, 73 refused');
$cjk = '黄河远上白云间一片孤城万仞山羌笛';               // 16 characters, 48 bytes
check($ok($cjk), 'each Unicode character counts once: 16 Chinese characters (48 bytes) accepted');
$accented = 'crème brûlée à la café';                     // 22 characters, 26 bytes
check(mb_strlen($accented) >= 15 && $ok($accented), 'accented letters and spaces are allowed');
$emoji = str_repeat("\u{1F600}\u{1F33B}\u{1F680}\u{1F308}\u{1F34E}\u{1F3B8}", 3); // 18 characters, 72 bytes
check(strlen($emoji) === 72 && $ok($emoji) && !$ok($emoji . "\u{1F431}"), 'emoji: 72 bytes accepted, one more emoji (76 bytes) refused');
check(str_contains((string) PasswordPolicy::problem('short'), '15 characters') && str_contains((string) PasswordPolicy::problem('short'), '72 bytes'), 'the message names both limits');

section('Guessable passwords are refused with a reason');
check(!$ok('rjm boarding house 2026!'), 'the boarding house name plus a year');
check(!$ok('Password1234567890'), 'a common word plus a digit run');
check(!$ok('qwertyuiop1234567'), 'keyboard runs');
check(!$ok('aaaaaaaaaaaaaaaaaaaa'), 'one repeated character');
check(!$ok('Maria Lourdes 1990!', ['Maria Lourdes', 'mlourdes@example.test']), "the person's own name");
check($ok('correct horse battery staple'), 'a phrase of unrelated words passes');
check($ok('first power answer'), 'ordinary words that contain short keyboard runs ("rst", "wer") pass');
check(!$ok("violet harbor\0lantern quartz"), 'a NUL byte is refused (bcrypt would stop reading there)');
$e = 'guess' . uniq() . '@t.test';
$r = $addStaff($e, 'rjm boardinghouse 2026', 'Guess Test');
check(!$idOf($e) && flashHas($admin, $r, 'easy to guess'), 'the create form refuses it and says why');

section('E-mail: trimmed, lower-case, unique in any letter case (deactivated included)');
$tag = uniq();
$mixed = "  Crew.{$tag}@T.Test ";
$addStaff($mixed, 'Tmp-cobalt-river-fern', "  Crew   {$tag} ");
$crewId = $idOf(strtolower(trim($mixed)));
check($crewId > 0 && q1('SELECT email FROM users WHERE id = ?', [$crewId]) === strtolower(trim($mixed)), 'stored lower-case and trimmed');
check(q1('SELECT name FROM users WHERE id = ?', [$crewId]) === "Crew {$tag}", 'the name is trimmed and inner spaces collapsed');
$admin->post("/admin/staff/{$crewId}/status", ['status' => 'inactive']);
$r = $addStaff(strtoupper("crew.{$tag}@t.test"), 'Tmp-cobalt-river-fern');
check(flashHas($admin, $r, 'already in use') && (int) q1('SELECT COUNT(*) FROM users WHERE email = ?', ["crew.{$tag}@t.test"]) === 1, 'the same address in capitals is refused, even while the holder is deactivated');
$admin->post("/admin/staff/{$crewId}/status", ['status' => 'active']);

section('Temporary password: forced change, expiry');
$email = "temp{$tag}@t.test";
$r = $addStaff($email, 'Tmp-saffron-kite-dune');
$sid = $idOf($email);
check(flashHas($admin, $r, 'choose their own password'), 'the success message says the password is temporary');
$row = q('SELECT must_change_password, temp_password_expires_at FROM users WHERE id = ?', [$sid])[0];
$hours = (strtotime((string) $row['temp_password_expires_at']) - time()) / 3600;
check((int) $row['must_change_password'] === 1 && $hours > 71 && $hours <= 72, 'a new account must change its password, which expires in 72 hours', var_export($row, true));
$c = new Client('temp');
check($c->login($email, 'Tmp-saffron-kite-dune')->loc === '/staff/dashboard' && $c->get('/staff/dashboard')->loc === '/profile', 'first sign-in lands on "choose a new password"');
$c->post('/profile/password', ['current_password' => 'Tmp-saffron-kite-dune', 'new_password' => 'Own-amber-ridge-moth', 'confirm_password' => 'Own-amber-ridge-moth']);
$row = q('SELECT must_change_password, temp_password_expires_at, last_login_at FROM users WHERE id = ?', [$sid])[0];
check((int) $row['must_change_password'] === 0 && $row['temp_password_expires_at'] === null && $c->get('/staff/dashboard')->code === 200, 'choosing their own clears the flag and the expiry');
check($row['last_login_at'] !== null, 'the sign-in time is recorded');

$e2 = "late{$tag}@t.test";
$addStaff($e2, 'Tmp-walnut-meadow-fig');
$late = new Client('late');
$late->login($e2, 'Tmp-walnut-meadow-fig');
check($late->get('/profile')->code === 200, 'signed in on the temporary password (only "choose a password" is open)');
q('UPDATE users SET temp_password_expires_at = NOW() - INTERVAL 1 MINUTE WHERE email = ?', [$e2]);
check($late->get('/profile')->loc === '/login', 'a session opened before the expiry ends when it passes');
$r = $late->login($e2, 'Tmp-walnut-meadow-fig');
check($r->loc === '/login' && flashHas($late, $r, 'temporary password has expired'), 'an expired temporary password does not sign in, and says so');
check(flashHas($late, $late->login($e2, 'wrong-password-entirely'), 'Invalid email or password'), 'a wrong password still gets the generic message (no hint the account exists)');
check($admin->get('/admin/staff')->has('Temporary password expired'), 'the admin sees which temporary passwords expired');
$admin->post('/admin/staff/' . $idOf($e2) . '/password', ['new_password' => 'Tmp-second-heron-lamp']);
check((new Client('late2'))->login($e2, 'Tmp-second-heron-lamp')->loc === '/staff/dashboard', 'a fresh temporary password from the admin works again');

section('Reset: new temporary password, other sessions end');
$before = q1('SELECT password_hash FROM users WHERE id = ?', [$sid]);
$r = $admin->post("/admin/staff/{$sid}/password", ['new_password' => 'Reset-plum-anchor-sky']);
check(flashHas($admin, $r, 'signed out') && q1('SELECT password_hash FROM users WHERE id = ?', [$sid]) !== $before, 'the admin sets a new temporary password');
check($c->get('/staff/dashboard')->loc === '/login', "the staff member's open session ended");
check((int) q1('SELECT must_change_password FROM users WHERE id = ?', [$sid]) === 1 && q1('SELECT temp_password_expires_at FROM users WHERE id = ?', [$sid]) !== null, 'the reset password is temporary too');
check(!str_contains($admin->get('/admin/staff')->body, 'Reset-plum-anchor-sky'), 'the page never shows a password');
$r = $admin->post("/admin/staff/{$sid}/password", ['new_password' => "Crew Member {$email}"]);
check(flashHas($admin, $r, 'easy to guess'), "a reset password made of the person's name and e-mail is refused");
$c = new Client('temp2');
$c->login($email, 'Reset-plum-anchor-sky');
$c->post('/profile/password', ['current_password' => 'Reset-plum-anchor-sky', 'new_password' => 'Own-amber-ridge-moth2', 'confirm_password' => 'Own-amber-ridge-moth2']);

section('Deactivate / reactivate');
$boarderId = (int) q1("SELECT id FROM users WHERE email = 'boarder@rjm.test'");
q('INSERT INTO sos_alerts (boarder_id, status, acknowledged_by) VALUES (?, "acknowledged", ?)', [$boarderId, $sid]);
$r = $admin->post("/admin/staff/{$sid}/status", ['status' => 'inactive']);
check(q1('SELECT status FROM users WHERE id = ?', [$sid]) === 'inactive', 'deactivated');
check(flashHas($admin, $r, '1 SOS alert(s) they acknowledged are still open'), 'the admin is told about SOS alerts the person left open');
check($c->get('/staff/dashboard')->loc === '/login', 'their open session ends at once');
check((new Client('d'))->login($email, 'Own-amber-ridge-moth2')->loc === '/login', 'they cannot sign in');
check(flashHas($admin, $admin->post("/admin/staff/{$sid}/status", ['status' => 'inactive']), 'already deactivated') && (int) q1("SELECT COUNT(*) FROM audit_log WHERE action = 'staff_deactivated' AND entity_id = ?", [$sid]) === 1, 'deactivating twice changes nothing and logs nothing');
$notifBefore = (int) q1('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$adminId]);
App\Services\NotificationDispatcher::emergencySosTriggered(0, $boarderId);
check((int) q1('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$adminId]) === $notifBefore + 1, 'SOS alerts always reach the admin too, so no staff deactivation leaves SOS unanswered');
$admin->post("/admin/staff/{$sid}/status", ['status' => 'active']);
check((new Client('re'))->login($email, 'Own-amber-ridge-moth2')->loc === '/staff/dashboard', 'reactivated: they can sign in again');
$admin->post("/admin/staff/{$adminId}/status", ['status' => 'inactive']);
check(q1('SELECT status FROM users WHERE id = ?', [$adminId]) === 'active', 'the admin cannot deactivate themselves through this page');

section('Admin only, CSRF on every action');
$routes = [
    ['GET', '/admin/staff', []],
    ['POST', '/admin/staff', ['name' => 'X', 'email' => 'x' . uniq() . '@t.test', 'password' => 'Tmp-cobalt-river-fern']],
    ['POST', "/admin/staff/{$sid}/status", ['status' => 'inactive']],
    ['POST', "/admin/staff/{$sid}/password", ['new_password' => 'Hijack-cobalt-river-fern']],
];
$hash = q1('SELECT password_hash FROM users WHERE id = ?', [$sid]);
// A fresh resident: test_http_rbac changes the seeded boarder's password.
foreach (['staff' => seededClient('staff'), 'boarder' => makeResident($admin)['client']] as $role => $who) {
    foreach ($routes as [$method, $path, $fields]) {
        $res = $method === 'GET' ? $who->get($path) : $who->post($path, $fields);
        check($res->code === 403, "{$role}: {$method} {$path} is 403", "HTTP {$res->code}");
    }
}
foreach ($routes as [$method, $path, $fields]) {
    $res = (new Client('visitor'))->req($method, $path, $method === 'GET' ? null : $fields);
    check($res->loc === '/login', "visitor: {$method} {$path} goes to the login page");
}
$admin = seededClient('admin');
foreach (array_slice($routes, 1) as [, $path, $fields]) {
    check($admin->post($path, $fields, false)->code === 400, "admin without a CSRF token: POST {$path} is 400");
}
check(q1('SELECT password_hash FROM users WHERE id = ?', [$sid]) === $hash && q1('SELECT status FROM users WHERE id = ?', [$sid]) === 'active', 'none of the refused requests changed the account');

section('Audit trail');
$trail = q("SELECT action, actor_id, details FROM audit_log WHERE entity_type = 'user' AND entity_id = ? ORDER BY id", [$sid]);
$actions = array_column($trail, 'action');
check($actions === ['staff_created', 'password_reset', 'staff_deactivated', 'staff_reactivated'], 'create, reset, deactivate and reactivate are each recorded once', implode(',', $actions));
check(!array_filter($trail, fn ($t) => (int) $t['actor_id'] !== $adminId), 'every entry names the admin who did it');
check(!array_filter($trail, fn ($t) => !array_key_exists('ip', json_decode((string) $t['details'], true) ?: [])), 'every entry records the IP address');
$allDetails = implode(' ', array_column(q("SELECT details FROM audit_log WHERE entity_type = 'user'"), 'details'));
check(!str_contains($allDetails, 'Tmp-') && !str_contains($allDetails, 'Reset-plum') && !str_contains($allDetails, '$2y$'), 'no password or hash is ever written to the trail');
$page = $admin->get('/admin/staff');
check($page->has('History for') && $page->has('Temporary password set') && $page->has('Reactivated'), "the page shows each staff member's history");

section('Owner decisions, 15 Oct 2026: money is admin-only');
$staffC = seededClient('staff');
$admin = seededClient('admin');
$resident = makeResident($admin);
$rule = (int) q1('SELECT id FROM penalty_rules WHERE active = 1 LIMIT 1');
$reason = 'staff-money-' . uniq();
check($staffC->post('/staff/penalties/issue', ['boarder_id' => $resident['id'], 'rule_id' => $rule, 'amount' => 5, 'reason' => $reason])->code === 403
    && !q1('SELECT COUNT(*) FROM penalties WHERE reason = ?', [$reason]), 'staff cannot issue a penalty');
check(!$staffC->get('/staff/dashboard')->has('/staff/penalties/issue'), 'the staff dashboard no longer offers the penalty form');
check($staffC->get('/admin/payments/record')->code === 403, 'staff cannot open Record Payment');
check($staffC->post('/admin/payments/record', ['boarder_id' => $resident['id'], 'amount' => 100])->code === 403, 'staff cannot record a payment');
check(!$staffC->get('/staff/dashboard', true)->has('href="/admin/payments/record"'), 'Record Payment is gone from the staff sidebar');
check($staffC->post('/admin/expenses', ['category' => 'x', 'amount' => 1])->code === 403, 'staff cannot record an expense');
q('INSERT INTO payments (boarder_id, billing_period, expected_amount, claimed_amount, approved_amount, verification_status) VALUES (?, "2026-10", 100, 100, 100, "admin-approved")', [$resident['id']]);
$paymentId = (int) q1('SELECT MAX(id) FROM payments WHERE boarder_id = ?', [$resident['id']]);
check($staffC->get("/payments/{$paymentId}/receipt")->code === 404 && $admin->get("/payments/{$paymentId}/receipt")->code !== 404, "staff cannot open a resident's receipt; the admin can");

section('Owner decisions, 15 Oct 2026: staff phone numbers for SOS backup');
$pe = 'phone' . uniq() . '@t.test';
check(flashHas($admin, $admin->post('/admin/staff', ['name' => 'Phone Bad', 'email' => $pe, 'password' => 'Tmp-cobalt-river-fern', 'phone' => 'call me']), 'phone number') && !$idOf($pe), 'a malformed phone number is refused on create');
$admin->post('/admin/staff', ['name' => 'Rosa Dela Cruz', 'email' => $pe, 'password' => 'Tmp-cobalt-river-fern', 'phone' => ' 0917  555 0101 ']);
$pid = $idOf($pe);
check($pid > 0 && q1('SELECT phone FROM users WHERE id = ?', [$pid]) === '0917 555 0101', 'the phone is stored trimmed with single spaces');
check($admin->get('/admin/staff')->has('0917 555 0101'), 'the staff list shows it');
$portal = $resident['client']->get('/portal/dashboard')->body;
check(str_contains($portal, 'Call Rosa Dela Cruz') && str_contains($portal, 'href="tel:09175550101"'), 'residents see "Call <name>: <number>" beside the SOS button, as a tap-to-call link');
$admin->post("/admin/staff/{$pid}/phone", ['phone' => '+63 917 555 0202']);
check(q1('SELECT phone FROM users WHERE id = ?', [$pid]) === '+63 917 555 0202', 'the admin changes it from the list');
check(flashHas($admin, $admin->post("/admin/staff/{$pid}/phone", ['phone' => '12']), 'phone number') && q1('SELECT phone FROM users WHERE id = ?', [$pid]) === '+63 917 555 0202', 'a too-short number is refused and the old one kept');
$trail = q("SELECT details FROM audit_log WHERE entity_type = 'user' AND entity_id = ? AND action = 'staff_phone_changed'", [$pid]);
check(count($trail) === 1 && !str_contains((string) $trail[0]['details'], '0202'), 'the change is in the history, without the number itself');
check($staffC->post("/admin/staff/{$pid}/phone", ['phone' => '0917 000 0000'])->code === 403 && $admin->post("/admin/staff/{$pid}/phone", ['phone' => '0917 000 0000'], false)->code === 400, 'staff get 403 and a missing CSRF token gets 400');
$admin->post("/admin/staff/{$pid}/status", ['status' => 'inactive']);
check(!str_contains($resident['client']->get('/portal/dashboard')->body, 'Call Rosa Dela Cruz'), 'a deactivated staff member is no longer listed for SOS');
$admin->post("/admin/staff/{$pid}/phone", ['phone' => '']);
check(q1('SELECT phone FROM users WHERE id = ?', [$pid]) === null, 'an empty field removes the number');

section('Owner decisions, 15 Oct 2026: retention and on-duty rules are shown');
check($admin->get('/admin/staff')->has('5 years after the person leaves'), 'the admin page states the 5-year retention rule');
check($staffC->get('/profile')->has('5 years after you leave'), 'staff see the retention rule on their Profile page');
check($staffC->get('/staff/dashboard')->has('keep this page open on the front-desk computer'), 'the staff dashboard says to keep it open while on duty');
