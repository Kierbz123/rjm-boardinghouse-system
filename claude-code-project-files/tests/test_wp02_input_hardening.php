<?php
/**
 * WP-2 (F-07, F-09, F-10, F-27): malformed input is a clean 400/validation error, never a 500;
 * one shared password rule (8 to 72 BYTES) everywhere; room capacity is bounded; failures
 * never show database text to users.
 */
require __DIR__ . '/lib.php';

$project = dirname(__DIR__);
$admin = seededClient('admin');
$staff = seededClient('staff');
$anon = new Client('anon');
$res = makeResident($admin);
$me = $res['client'];

section('Every route in the route table survives hostile input');
preg_match_all('/\$router->add\(\'(GET|POST)\', \'([^\']+)\'/', (string) file_get_contents($project . '/public/index.php'), $m, PREG_SET_ORDER);
$routes = [];
foreach ($m as [, $method, $path]) {
    $routes[] = [$method, preg_replace('/\{[a-z_]+\}/i', '1', $path)];
}
check(count($routes) > 70, 'the route table was read (' . count($routes) . ' routes)');

$fieldNames = ['name', 'email', 'password', 'amount', 'room_id', 'bed_id', 'description', 'type', 'category', 'label', 'room_number', 'capacity',
    'condition_type', 'reason', 'approved_amount', 'current_password', 'new_password', 'confirm_password', 'payment_method', 'claimed_amount',
    'phone', 'boarder_id', 'rule_id', 'base_price', 'floor', 'count', 'reference_number', 'note', 'notes', 'status', 'message', 'resolution_notes',
    'return_to', 'move_in_date', 'move_out_date', 'due_date', 'room_type', 'staff_notes', 'website'];

$bad = [];
$clients = ['admin' => $admin, 'resident' => $me];
foreach ($routes as [$method, $path]) {
    foreach ($method === 'POST' && !in_array($path, ['/login', '/inquire', '/logout'], true) ? $clients : ['anon' => $anon] as $who => $client) {
        $token = $method === 'POST' ? $client->token() : '';
        $variants = [];
        if ($method === 'POST') {
            $arrays = ['csrf_token' => $token];
            $nuls = ['csrf_token' => $token];
            $big = ['csrf_token' => $token];
            foreach ($fieldNames as $k) {
                $arrays[$k] = ['x', 'y'];
                $nuls[$k] = "a\0b";
                $big[$k] = str_repeat('Z', 40000);
            }
            $variants = ['array csrf token' => [['csrf_token' => ['x']], 400], 'array fields' => [$arrays, 400], 'NUL byte' => [$nuls, 400], 'huge fields' => [$big, null]];
        } else {
            $variants = ['array query' => [null, 400]];
        }
        foreach ($variants as $label => [$fields, $want]) {
            if ($method === 'GET') {
                $r = $client->get($path . '?a[]=1&b[x]=2');
            } else {
                $r = $client->req('POST', $path, http_build_query($fields), ['Content-Type: application/x-www-form-urlencoded']);
            }
            $ok = $want === 400 ? $r->code === 400 : $r->code < 500;
            if (!$ok) {
                $bad[] = "{$who} {$method} {$path} [{$label}] -> {$r->code}";
            }
        }
        if ($method === 'POST' && $client->get('/profile')->code !== 200) { // a variant ended the session (e.g. /logout): sign in again
            $client = $who === 'admin' ? ($admin = seededClient('admin')) : $client;
            if ($who === 'resident') {
                $client->login($res['email'], $res['password']);
            }
            $clients[$who] = $client;
        }
    }
}
check(!$bad, 'array / NUL / oversized input gets a clean 400 (or validation error) on all ' . count($routes) . ' routes', implode("\n      ", array_slice($bad, 0, 12)) . (count($bad) > 12 ? "\n      ... " . count($bad) . ' in total' : ''));
$admin = seededClient('admin');
$me = new Client('res');
$me->login($res['email'], $res['password']);

section('JSON endpoints');
foreach (['/api/assistant/ask' => ['message' => ['array']], '/api/assistant/suggest-category' => ['description' => ['array']], '/api/assistant/suggest-description' => ['draft' => ['a'], 'category' => ['b']]] as $path => $json) {
    $r = $me->postJson($path, $json);
    check($r->code >= 400 && $r->code < 500, "{$path} with array values is a 4xx", "HTTP {$r->code}");
}
$r = $me->req('POST', '/api/assistant/ask', json_encode(['message' => 'hi', 'csrf_token' => ['x']]), ['Content-Type: application/json']);
check($r->code === 403 || $r->code === 400, 'a non-string csrf_token in JSON is refused cleanly', "HTTP {$r->code}");
$r = $me->req('POST', '/api/assistant/ask', '{not json', ['Content-Type: application/json']);
check($r->code === 400, 'broken JSON is a 400');

section('One password rule: 8 to 72 bytes, everywhere');
$tooLong = str_repeat('p', 73);
$emoji = str_repeat("\u{1F600}", 20);          // 20 characters but 80 bytes
$exactly72 = str_repeat('q', 71) . '1';
$ok72Email = 'p72' . uniq() . '@t.test';
$tryCreate = fn (string $password, string $email) => $admin->post('/admin/boarders', ['name' => 'P', 'email' => $email, 'password' => $password]);
$mk = function (string $password) use ($tryCreate): bool {
    $email = 'pw' . uniq() . '@t.test';
    $tryCreate($password, $email);
    return (bool) q1('SELECT COUNT(*) FROM users WHERE email = ?', [$email]);
};
check(!$mk($tooLong), 'create resident: 73 bytes refused');
check(!$mk($emoji), 'create resident: 20 emoji (80 bytes) refused');
check(!$mk('1234567'), 'create resident: 7 characters refused');
check($mk($exactly72), 'create resident: exactly 72 bytes accepted');
$staffEmail = fn () => 'st' . uniq() . '@t.test';
$e = $staffEmail();
$admin->post('/admin/staff', ['name' => 'S', 'email' => $e, 'password' => $tooLong]);
check(!q1('SELECT COUNT(*) FROM users WHERE email = ?', [$e]), 'create staff: 73 bytes refused');
$e2 = $staffEmail();
$r = $admin->post('/admin/staff', ['name' => 'S', 'email' => $e2, 'password' => $emoji]);
check(!q1('SELECT COUNT(*) FROM users WHERE email = ?', [$e2]) && $r->code < 500, 'create staff: 80 bytes of emoji refused');
$before = q1('SELECT password_hash FROM users WHERE id = ?', [$res['id']]);
$flash = $me->flash($me->post('/profile/password', ['current_password' => $res['password'], 'new_password' => $tooLong, 'confirm_password' => $tooLong]));
check(q1('SELECT password_hash FROM users WHERE id = ?', [$res['id']]) === $before && stripos($flash, '72') !== false, 'profile change: 73 bytes refused with a message that says 72', substr($flash, 0, 0));
$flash = $me->flash($me->post('/profile/password', ['current_password' => $res['password'], 'new_password' => $res['password'], 'confirm_password' => $res['password']]));
check(q1('SELECT password_hash FROM users WHERE id = ?', [$res['id']]) === $before && stripos($flash, 'different') !== false, 'profile change: the new password must differ from the current one');
$admin->post("/admin/boarders/{$res['id']}/password", ['new_password' => $tooLong]);
check(q1('SELECT password_hash FROM users WHERE id = ?', [$res['id']]) === $before, 'admin reset of a resident: 73 bytes refused');
$staffId = (int) q1('SELECT id FROM users WHERE email = ?', ['staff@rjm.test']);
$staffHash = q1('SELECT password_hash FROM users WHERE id = ?', [$staffId]);
$admin->post("/admin/staff/{$staffId}/password", ['new_password' => $emoji]);
check(q1('SELECT password_hash FROM users WHERE id = ?', [$staffId]) === $staffHash, 'admin reset of staff: 80 bytes of emoji refused');
$threw = false;
try {
    App\Models\User::create('boarder', 'X', 'direct' . uniq() . '@t.test', $tooLong);
} catch (Throwable $e) {
    $threw = true;
}
check($threw, 'the model itself refuses an over-long password (a controller that forgets cannot create a truncated one)');

$pages = ['/admin/boarders', '/admin/staff', "/admin/boarders/{$res['id']}", '/profile'];
foreach ($pages as $page) {
    $html = ($page === '/profile' ? $me : $admin)->get($page, true)->body;
    check(str_contains($html, 'data-max-bytes="72"'), "{$page} password fields carry the 72-byte limit for the browser");
}
check(str_contains((string) file_get_contents($project . '/public/assets/js/app.js'), 'data-max-bytes'), 'app.js enforces data-max-bytes (counter and message before submit)');

section('Rooms: bounded capacity, honest messages');
$n = 'W' . uniq();
foreach ([0, 51, '99999999999', -3, 'abc'] as $cap) {
    $r = $admin->post('/admin/rooms', ['room_number' => $n . $cap, 'capacity' => $cap, 'base_price' => 100]);
    check($r->code < 500 && !q1('SELECT COUNT(*) FROM rooms WHERE room_number = ?', [$n . $cap]), "capacity {$cap} is refused cleanly", "HTTP {$r->code}");
}
$r = $admin->post('/admin/rooms', ['room_number' => $n . 'ok', 'capacity' => 50, 'base_price' => 100]);
check((bool) q1('SELECT COUNT(*) FROM rooms WHERE room_number = ?', [$n . 'ok']), 'capacity 50 is accepted');
$roomId = (int) q1('SELECT id FROM rooms WHERE room_number = ?', [$n . 'ok']);
$r = $admin->post("/admin/rooms/{$roomId}/update", ['room_number' => $n . 'ok', 'capacity' => 99, 'base_price' => 100]);
check((int) q1('SELECT capacity FROM rooms WHERE id = ?', [$roomId]) === 50, 'updating a room to capacity 99 is refused');
$r = $admin->post('/admin/rooms/999999/update', ['room_number' => 'Q' . uniq(), 'capacity' => 2, 'base_price' => 100]);
$msg = $admin->flash($r);
check(stripos($msg, 'not found') !== false && stripos($msg, 'updated') === false, 'updating a room that does not exist says "not found", not "updated"');
$blocked = false;
try {
    db()->exec("UPDATE rooms SET capacity = 99 WHERE id = {$roomId}");
} catch (PDOException $e) {
    $blocked = true;
}
check($blocked, 'the database itself refuses a capacity outside 1 to 50');

section('Database errors never reach the screen');
$room = makeRoom($admin, 2, 3000, 0);
$res2 = makeResident($admin, null, 20);
$rule = (int) q1('SELECT id FROM penalty_rules WHERE active = 1 ORDER BY id LIMIT 1');
$force = function (string $table, string $check, callable $act) {
    $name = 'chk_force_' . uniq();
    db()->exec("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$check})");
    try {
        return $act();
    } finally {
        db()->exec("ALTER TABLE {$table} DROP CONSTRAINT {$name}");
    }
};
$leaks = fn (string $text) => (bool) preg_match('/SQLSTATE|constraint|CONSTRAINT|chk_force|`|INSERT INTO|Integrity/i', $text);
$text = $force('penalties', "COALESCE(reason, '') <> 'ZZ-FORCE'", fn () => $staff->flash($staff->post('/staff/penalties/issue', ['boarder_id' => $res2['id'], 'rule_id' => $rule, 'amount' => 5, 'reason' => 'ZZ-FORCE', 'due_date' => date('Y-m-d', strtotime('+5 days'))])));
check(!$leaks($text) && $text !== '', 'a failing penalty insert shows staff a safe message, not SQL', substr($text, 0, 120));
$text = $force('beds', "label <> 'ZZ'", fn () => $admin->flash($admin->post('/admin/beds', ['room_id' => $room['id'], 'label' => 'ZZ', 'count' => 1])));
check(!$leaks($text) && $text !== '', 'a failing bed insert shows a safe message', substr($text, 0, 120));
$when = date('Y-m-d', strtotime('-12 days'));
$text = $force('boarder_profiles', "COALESCE(move_in_date, '2000-01-01') <> '{$when}'", fn () => $admin->flash($admin->post("/admin/boarders/{$res2['id']}/dates", ['move_in_date' => $when, 'move_out_date' => ''])));
check(!$leaks($text) && $text !== '', 'a failing date update shows a safe message', substr($text, 0, 120));
