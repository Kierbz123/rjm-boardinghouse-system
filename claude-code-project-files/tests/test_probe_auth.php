<?php
/**
 * Audit probe 1 (14 Oct 2026): authentication, sessions, headers, the role matrix,
 * and malformed-input behaviour. Open defects are knownDefect() until their fix lands.
 */
require __DIR__ . '/lib.php';

$admin = seededClient('admin');
$staff = seededClient('staff');
$resident = makeResident($admin);   // the seeded resident gets archived by other test files
$boarder = $resident['client'];
$anon = new Client('anon');

section('Login, logout, lockout');
$r = $anon->get('/');
check($r->code === 200, 'landing page opens anonymously');
$r = $anon->get('/login');
check($r->code === 200, 'login page opens');
foreach (['x-frame-options', 'x-content-type-options', 'content-security-policy', 'referrer-policy'] as $h) {
    check(isset($r->headers[$h]), "security header {$h} present");
}
check(str_contains(file_get_contents($anon->jar), '#HttpOnly_'), 'session cookie is HttpOnly');

$r = $anon->req('POST', '/login', ['email' => 'a@b.test', 'password' => 'x']);
check($r->code === 302 && $r->loc === '/login' && $anon->get('/login', true)->has('expired'), 'POST /login without a CSRF token is bounced with a message');

$r1 = $anon->login('admin@rjm.test', 'wrong-password');
$m1 = $anon->flash($r1);
$r2 = $anon->login('ghost' . uniq() . '@rjm.test', 'wrong-password');
check(stripos($m1, 'Invalid email or password') !== false && flashHas($anon, $r2, 'Invalid email or password'), 'wrong password and unknown e-mail give the same message (no account enumeration)');

$c = new Client('role-redirects');
check($c->login('admin@rjm.test', pw('admin'))->loc === '/admin/dashboard', 'admin login goes to /admin/dashboard');
$c = new Client('role-redirects');
check($c->login('staff@rjm.test', pw('staff'))->loc === '/staff/dashboard', 'staff login goes to /staff/dashboard');
$c = new Client('role-redirects');
check($c->login($resident['email'], $resident['password'])->loc === '/portal/dashboard', 'resident login goes to /portal/dashboard');
$r = $admin->get('/login');
check($r->code === 302 && $r->loc === '/admin/dashboard', 'a signed-in user visiting /login is sent to their dashboard');
check($admin->get('/logout')->code === 404, 'GET /logout does not log out');
check($admin->req('POST', '/logout', [])->code === 400, 'POST /logout without a CSRF token is refused');

// Lock-out on a throwaway account so the seeded ones are never locked.
$victim = makeResident($admin);
$lock = new Client('lock');
for ($i = 0; $i < 5; $i++) {
    $lock->login($victim['email'], 'bad' . $i);
}
$r = $lock->login($victim['email'], $victim['password']);
check(flashHas($lock, $r, 'Too many failed attempts'), '5 wrong passwords lock the account, even for the right password');
q('DELETE FROM login_attempts');

section('Sessions');
check($anon->get('/api/notifications/unread')->code === 302, 'an anonymous API call redirects to /login');
$r = $anon->get('/admin/dashboard');
check($r->code === 302 && $r->loc === '/login', 'an anonymous admin page redirects to /login');
$fix = new Client('fix');
$fix->get('/login');
preg_match('/PHPSESSID\s+(\S+)/', file_get_contents($fix->jar), $before);
$fix->login('admin@rjm.test', pw('admin'));
preg_match('/PHPSESSID\s+(\S+)/', file_get_contents($fix->jar), $after);
check(($before[1] ?? 'a') !== ($after[1] ?? 'a'), 'the session id changes on login (no fixation)');
$r = (new Client('fake'))->req('GET', '/login', null, ['Cookie: PHPSESSID=deadbeefdeadbeefdeadbeefdeadbeef']);
check(($r->headers['set-cookie'] ?? '') !== '', 'a forged session id is replaced by a server-issued one');

section('Role matrix: every page x every role');
$pages = [
    '/admin/dashboard' => ['admin'], '/admin/inquiry-center' => ['admin', 'staff'], '/admin/boarders' => ['admin'], '/admin/staff' => ['admin'],
    '/admin/rooms' => ['admin'], '/admin/payments' => ['admin'], '/admin/expenses' => ['admin'], '/admin/penalty-rules' => ['admin'],
    '/admin/ledger/export' => ['admin'], '/admin/occupancy' => ['admin'], '/staff/dashboard' => ['admin', 'staff'],
    '/staff/maintenance' => ['admin', 'staff'], '/staff/maintenance/history' => ['admin', 'staff'],
    '/staff/incidents' => ['admin', 'staff', 'boarder'], '/staff/incidents/history' => ['admin', 'staff', 'boarder'],
    '/portal/incidents' => ['admin', 'staff', 'boarder'], '/portal/dashboard' => ['boarder'], '/portal/maintenance/new' => ['boarder'],
    '/portal/payments/new' => ['boarder'], '/profile' => ['admin', 'staff', 'boarder'], '/notifications' => ['admin', 'staff', 'boarder'],
    '/api/sos/active' => ['admin', 'staff'], '/api/notifications/unread' => ['admin', 'staff', 'boarder'],
    '/api/assistant/status' => ['admin', 'staff', 'boarder'],
];
$clients = ['admin' => $admin, 'staff' => $staff, 'boarder' => $boarder, 'anon' => $anon];
$deviations = [];
foreach ($pages as $path => $allowed) {
    foreach ($clients as $role => $client) {
        $r = $client->get($path);
        $ok = $role === 'anon' ? ($r->code === 302 && $r->loc === '/login')
            : (in_array($role, $allowed, true) ? $r->code === 200 : $r->code === 403);
        if (!$ok) {
            $deviations[] = "{$role} GET {$path} -> {$r->code}";
        }
    }
}
check(!$deviations, count($pages) * 4 . ' page/role combinations behave as designed', implode('; ', $deviations));

$post = [
    ['/admin/boarders', ['name' => 'x', 'email' => 'x@x.test', 'password' => 'password123'], ['admin']],
    ['/admin/staff', ['name' => 'x', 'email' => 'y@x.test', 'password' => 'password123'], ['admin']],
    ['/admin/rooms', ['room_number' => '999', 'capacity' => 2, 'base_price' => 100], ['admin']],
    ['/admin/expenses', ['category' => 'x', 'amount' => 1], ['admin']],
    ['/admin/penalty-rules', ['name' => 'z', 'condition_type' => 'late_per_day', 'amount' => 1], ['admin']],
    ['/admin/penalties/run-check', [], ['admin']], ['/admin/notifications/rent-due', [], ['admin']],
    ['/admin/payments/1/approve', ['approved_amount' => 1], ['admin']], ['/admin/payments/1/reject', ['reason' => 'abc'], ['admin']],
    ['/staff/maintenance/1/status', ['status' => 'open'], ['admin', 'staff']], ['/staff/incidents/1/resolve', ['resolution_notes' => 'n'], ['admin', 'staff']],
    ['/staff/penalties/issue', [], ['admin']], ['/staff/sos/1/ack', [], ['admin', 'staff']], ['/staff/sos/1/resolve', [], ['admin', 'staff']],
    ['/portal/maintenance', [], ['boarder']], ['/portal/payments', [], ['boarder']], ['/api/sos', [], ['boarder']],
    ['/api/assistant/analyze-ticket', [], ['admin', 'staff']], ['/api/assistant/summarize-queue', [], ['admin', 'staff']],
    ['/api/assistant/suggest-category', [], ['boarder']],
];
$deviations = [];
foreach ($post as [$path, $fields, $allowed]) {
    foreach (['staff' => $staff, 'boarder' => $boarder, 'admin' => $admin] as $role => $client) {
        if (!in_array($role, $allowed, true) && $client->post($path, $fields)->code !== 403) {
            $deviations[] = "{$role} POST {$path}";
        }
    }
}
check(!$deviations, 'forbidden roles get 403 on every sensitive POST', implode('; ', $deviations));

section('Malformed input must never be a 500');
$routes = ['/login', '/admin/boarders', '/admin/staff', '/admin/rooms', '/admin/beds', '/admin/expenses', '/admin/penalty-rules',
    '/admin/penalties/issue', '/admin/electricity', '/profile/update', '/profile/password', '/staff/incidents', '/portal/maintenance',
    '/portal/payments', '/inquire', '/admin/beds/assign', '/admin/inquiry-center/submit'];
$byRoute = ['/login' => $anon, '/inquire' => $anon, '/portal/maintenance' => $boarder, '/portal/payments' => $boarder,
    '/profile/update' => $boarder, '/profile/password' => $boarder, '/staff/incidents' => $staff];
$bad = [];
foreach ($routes as $path) {
    $client = $byRoute[$path] ?? $admin;
    $token = $client->token();
    $arrays = ['csrf_token' => $token];
    foreach (['name', 'email', 'password', 'amount', 'room_id', 'bed_id', 'description', 'type', 'category', 'label', 'room_number', 'capacity',
        'condition_type', 'reason', 'approved_amount', 'current_password', 'new_password', 'confirm_password', 'payment_method',
        'claimed_amount', 'phone', 'boarder_id', 'rule_id', 'base_price', 'floor', 'count', 'reference_number'] as $k) {
        $arrays[$k] = ['x'];
    }
    foreach (['array csrf token' => ['csrf_token' => ['x']], 'array fields' => $arrays] as $kind => $fields) {
        $r = $client->req('POST', $path, http_build_query($fields), ['Content-Type: application/x-www-form-urlencoded']);
        if ($r->code >= 500) {
            $bad[] = "{$kind} POST {$path} -> {$r->code}";
        }
    }
}
check(!$bad, 'array-typed form fields never cause a 500', implode('; ', array_slice($bad, 0, 4)) . (count($bad) > 4 ? ' ...' : ''));

foreach (['/admin/boarders/abc', '/admin/boarders/-1', '/admin/boarders/99999999999999999999', '/admin/boarders/0', '/uploads/receipts/../../../.env',
    '/uploads/receipts/..%2f..%2f.env', '/index.php', '/public/index.php', '/src/autoload.php', '/.env', '/storage/uploads', '/logs/app.log',
    '/database/seed.php', '/composer.json'] as $path) {
    $r = $admin->get($path);
    $leak = $r->code === 200 && (str_contains($r->body, 'DB_PASS') || str_contains($r->body, '<?php'));
    check($r->code < 500 && !$leak, "admin GET {$path} is harmless", "HTTP {$r->code}");
}
