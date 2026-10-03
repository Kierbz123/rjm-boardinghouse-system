<?php
/**
 * HTTP-level checks against the private test server started by tests/run.php:
 * every role's pages render without PHP errors, and every role is kept out of
 * the others' areas. Run via tests/run.php only (needs TEST_BASE_URL + seed data).
 */

$base = getenv('TEST_BASE_URL') ?: exit("Run through tests/run.php\n");
$failures = 0;

function check(bool $ok, string $label): void
{
    global $failures;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . "\n";
    if (!$ok) {
        $failures++;
    }
}

/** @return array{code:int, body:string, location:string} */
function http(string $method, string $path, ?string $jar, array $form = []): array
{
    global $base;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 20,
    ]);
    if ($jar) {
        curl_setopt_array($ch, [CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    }
    if ($form) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    }
    $raw = (string) curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $headers = substr($raw, 0, $headerSize);
    preg_match('/^Location:\s*(\S+)/mi', $headers, $m);
    return ['code' => $code, 'body' => substr($raw, $headerSize), 'location' => $m[1] ?? '', 'headers' => $headers];
}

function csrfFrom(string $html): string
{
    preg_match('/name="csrf_token" value="([a-f0-9]+)"|name="csrf-token" content="([a-f0-9]+)"/', $html, $m);
    return ($m[1] ?? '') ?: ($m[2] ?? '');
}

function loginAs(string $email, string $password): string
{
    $jar = tempnam(sys_get_temp_dir(), 'rjm');
    $token = csrfFrom(http('GET', '/login', $jar)['body']);
    http('POST', '/login', $jar, ['csrf_token' => $token, 'email' => $email, 'password' => $password]);
    return $jar;
}

function hasPhpError(string $body): bool
{
    return (bool) preg_match('/(Warning|Notice|Deprecated|Fatal error)<\/b>:|Something went wrong/', $body);
}

$jars = [
    'admin' => loginAs('admin@rjm.test', 'AdminPass123!'),
    'staff' => loginAs('staff@rjm.test', 'StaffPass123!'),
    'boarder' => loginAs('boarder@rjm.test', 'BoarderPass123!'),
];
$boarderId = 3; // seed.php creates admin=1, staff=2, boarder=3

$pages = [
    'admin' => ['/admin/dashboard', '/admin/boarders', "/admin/boarders/{$boarderId}", '/admin/rooms',
        '/admin/payments', '/admin/expenses', '/admin/penalty-rules', '/admin/occupancy',
        '/admin/inquiry-center', '/staff/dashboard', '/staff/maintenance', '/staff/maintenance/history',
        '/staff/incidents', '/staff/incidents/history', '/notifications', '/profile'],
    'staff' => ['/staff/dashboard', '/staff/maintenance', '/staff/maintenance/history', '/staff/incidents',
        '/staff/incidents/history', '/admin/inquiry-center', '/notifications', '/profile'],
    'boarder' => ['/portal/dashboard', '/portal/maintenance/new', '/portal/payments/new', '/portal/incidents',
        '/notifications', '/profile'],
];

echo "== Pages render for their own role ==\n";
foreach ($pages as $role => $paths) {
    foreach ($paths as $path) {
        $r = http('GET', $path, $jars[$role]);
        $ok = $r['code'] === 200 && !hasPhpError($r['body']);
        check($ok, "{$role} GET {$path} -> {$r['code']}");
        if (!$ok && preg_match('#.{0,200}((Warning|Notice|Deprecated|Fatal error)</b>:|Something went wrong).{0,300}#s', $r['body'], $m)) {
            echo '         ' . trim(strip_tags($m[0])) . "\n";
        }
    }
}

echo "== Roles are kept out of other areas (403) ==\n";
$forbidden = [
    'staff' => ['/admin/dashboard', '/admin/payments', '/admin/boarders', '/portal/dashboard'],
    'boarder' => ['/admin/dashboard', '/admin/payments', '/staff/dashboard', '/staff/maintenance', '/admin/inquiry-center'],
    'admin' => ['/portal/dashboard'],
];
foreach ($forbidden as $role => $paths) {
    foreach ($paths as $path) {
        check(http('GET', $path, $jars[$role])['code'] === 403, "{$role} blocked from {$path}");
    }
}

echo "== Anonymous visitors are sent to /login ==\n";
foreach (['/admin/dashboard', '/staff/dashboard', '/portal/dashboard', '/profile'] as $path) {
    $r = http('GET', $path, null);
    check($r['code'] === 302 && $r['location'] === '/login', "anonymous {$path} -> login");
}

echo "== CSRF is required on state changes ==\n";
check(http('POST', '/admin/rooms', $jars['admin'], ['room_number' => 'X1', 'capacity' => 1])['code'] === 400, 'POST /admin/rooms without token rejected');

echo "== Wrong password does not log in ==\n";
$jar = loginAs('admin@rjm.test', 'wrong-password');
check(http('GET', '/admin/dashboard', $jar)['code'] === 302, 'bad credentials stay logged out');

require_once __DIR__ . '/../src/autoload.php';
$pdo = App\Database::getConnection();

echo "== Maintenance submission (C2, M4) ==\n";
$token = csrfFrom(http('GET', '/portal/maintenance/new', $jars['boarder'])['body']);
$r = http('POST', '/portal/maintenance', $jars['boarder'], ['csrf_token' => $token, 'category' => 'plumbing',
    'description' => 'Sink leak test-c2', 'room_id' => 999]);
check($r['code'] === 302 && $r['location'] === '/portal/dashboard', "submit redirects to dashboard (got {$r['code']})");
$row = $pdo->query("SELECT * FROM maintenance_requests WHERE description = 'Sink leak test-c2'")->fetch();
$profileRoom = $pdo->query('SELECT room_id FROM boarder_profiles WHERE user_id = 3')->fetchColumn();
check($row && $row['room_id'] == $profileRoom, 'room taken from profile, not the form');
check((int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE message LIKE 'New maintenance request #{$row['id']} %'")->fetchColumn() >= 1, 'staff notified with the request id');
$r = http('POST', '/portal/maintenance', $jars['boarder'], ['csrf_token' => $token, 'category' => 'bogus', 'description' => 'x']);
check($r['location'] === '/portal/maintenance/new', 'invalid category rejected');

echo "== Boarders only see their own incidents (H4) ==\n";
$token = csrfFrom(http('GET', '/staff/incidents', $jars['staff'])['body']);
http('POST', '/staff/incidents', $jars['staff'], ['csrf_token' => $token, 'type' => 'Noise', 'description' => 'staff-only-incident-h4']);
check(str_contains(http('GET', '/staff/incidents', $jars['admin'])['body'], 'staff-only-incident-h4'), 'admin sees the staff report');
foreach (['/portal/incidents', '/staff/incidents', '/staff/incidents/history'] as $path) {
    check(!str_contains(http('GET', $path, $jars['boarder'])['body'], 'staff-only-incident-h4'), "boarder cannot see it at {$path}");
}

echo "== SOS (M1) ==\n";
$token = csrfFrom(http('GET', '/portal/dashboard', $jars['boarder'])['body']);
$first = json_decode(http('POST', '/api/sos', $jars['boarder'], ['csrf_token' => $token])['body'], true);
$second = json_decode(http('POST', '/api/sos', $jars['boarder'], ['csrf_token' => $token])['body'], true);
check(!empty($first['alert_id']) && $first['alert_id'] === $second['alert_id'], 'repeat press reuses the open alert');
$msg = $pdo->query("SELECT message FROM notifications WHERE entity_type = 'sos_alert' AND entity_id = {$first['alert_id']} LIMIT 1")->fetchColumn();
check($msg !== false && str_contains($msg, 'Room 101'), 'staff notice is linked to the alert and shows the room number');

echo "== App and database clocks agree (H3) ==\n";
check($pdo->query("SELECT DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i')")->fetchColumn() === date('Y-m-d H:i'), 'NOW() matches PHP date()');

echo "== Security headers ==\n";
$h = http('GET', '/login', null)['headers'];
foreach (['X-Frame-Options: DENY', 'X-Content-Type-Options: nosniff', 'Content-Security-Policy:', 'Referrer-Policy:'] as $needle) {
    check(stripos($h, $needle) !== false, "header {$needle}");
}
check(stripos($h, 'X-Powered-By') === false, 'X-Powered-By hidden');
check(http('GET', '/logout', $jars['admin'])['code'] === 404, 'GET /logout no longer logs out');

echo "== Password change ends the user's other sessions ==\n";
$a = loginAs('boarder@rjm.test', 'BoarderPass123!');
$b = loginAs('boarder@rjm.test', 'BoarderPass123!');
$token = csrfFrom(http('GET', '/profile', $a)['body']);
http('POST', '/profile/password', $a, ['csrf_token' => $token, 'current_password' => 'BoarderPass123!',
    'new_password' => 'NewBoarderPass1!', 'confirm_password' => 'NewBoarderPass1!']);
check(http('GET', '/portal/dashboard', $a)['code'] === 200, 'session that changed the password stays in');
check(http('GET', '/portal/dashboard', $b)['code'] === 302, 'other session is logged out');

echo $failures === 0 ? "All HTTP checks passed.\n" : "{$failures} HTTP check(s) failed.\n";
exit($failures === 0 ? 0 : 1);
