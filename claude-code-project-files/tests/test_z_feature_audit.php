<?php
/**
 * Feature audit (October 2026): every screen action is followed from the form, through
 * the server, to the row it leaves in the database.
 *
 *  1. Wiring: every form and link on every rendered page points at a real route, and
 *     every state-changing route is reachable from a form on some page.
 *  2. The routes no other test file exercised, each checked in the database afterwards.
 *  3. The fixes this audit made: admin password reset, one resident per bed, input limits.
 *
 * Named test_z_* so it runs last and may change data freely. Run via tests/run.php only.
 */

require_once __DIR__ . '/../src/autoload.php';

$base = getenv('TEST_BASE_URL') ?: exit("Run through tests/run.php\n");
str_ends_with((string) getenv('DB_NAME'), '_test') || exit("Run through tests/run.php\n");
$pdo = App\Database::getConnection();
$failures = 0;

function check(bool $ok, string $label): void
{
    global $failures;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . "\n";
    $failures += $ok ? 0 : 1;
}

/** @return array{code:int, body:string, location:string, headers:string} */
function http(string $method, string $path, ?string $jar, array $form = [], array $headers = []): array
{
    global $base;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $headers]);
    if ($jar) {
        curl_setopt_array($ch, [CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    }
    if ($form) {
        $hasFile = (bool) array_filter($form, fn ($v) => $v instanceof CURLFile);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $hasFile ? $form : http_build_query($form));
    }
    $raw = (string) curl_exec($ch);
    $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    preg_match('/^Location:\s*(\S+)/mi', substr($raw, 0, $size), $m);
    return ['code' => $code, 'body' => substr($raw, $size), 'location' => $m[1] ?? '', 'headers' => substr($raw, 0, $size)];
}

function csrfFrom(string $html): string
{
    preg_match('/name="csrf_token" value="([a-f0-9]+)"|name="csrf-token" content="([a-f0-9]+)"/', $html, $m);
    return ($m[1] ?? '') ?: ($m[2] ?? '');
}

/** Logs in and returns [cookie jar, csrf token]; the token is '' when the login failed. */
function loginAs(string $email, string $password): array
{
    $jar = tempnam(sys_get_temp_dir(), 'rjm');
    $token = csrfFrom(http('GET', '/login', $jar)['body']);
    $r = http('POST', '/login', $jar, ['csrf_token' => $token, 'email' => $email, 'password' => $password]);
    $in = $r['code'] === 302 && $r['location'] !== '/login';
    return [$jar, $in ? csrfFrom(http('GET', '/profile', $jar)['body']) : ''];
}

function hasPhpError(string $body): bool
{
    return (bool) preg_match('/(Warning|Notice|Deprecated|Fatal error)<\/b>:|Something went wrong/', $body);
}

function one(string $sql, array $params = []): mixed
{
    global $pdo;
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return $s->fetchColumn();
}

function row(string $sql, array $params = []): array
{
    global $pdo;
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return $s->fetch() ?: [];
}

$tag = substr((string) time(), -6);
$newPassword = fn () => 'T' . bin2hex(random_bytes(6)) . '!a';

// ---------------------------------------------------------------------------------
echo "== Fixtures, created through the admin screens ==\n";
[$admin, $adminT] = loginAs('admin@rjm.test', getenv('TEST_ADMIN_PASSWORD'));
check($adminT !== '', 'admin signs in');
[$staff, $staffT] = loginAs('staff@rjm.test', getenv('TEST_STAFF_PASSWORD'));
check($staffT !== '', 'staff signs in');

http('POST', '/admin/rooms', $admin, ['csrf_token' => $adminT, 'room_number' => "A{$tag}", 'floor' => '2', 'capacity' => 3, 'base_price' => '1500']);
$roomId = (int) one('SELECT id FROM rooms WHERE room_number = ?', ["A{$tag}"]);
check($roomId > 0, 'room saved');
http('POST', '/admin/beds', $admin, ['csrf_token' => $adminT, 'room_id' => $roomId, 'label' => 'Bed 1', 'count' => 3]);
$beds = $pdo->query("SELECT id FROM beds WHERE room_id = {$roomId} ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
check(count($beds) === 3, 'three beds saved from one "add 3 beds" action');
[$bed1, $bed2, $bed3] = array_map('intval', $beds);

$pw1 = $newPassword();
$email1 = "audit.one.{$tag}@rjm.test";
$email2 = "audit.two.{$tag}@rjm.test";
http('POST', '/admin/boarders', $admin, ['csrf_token' => $adminT, 'name' => 'Audit One', 'email' => $email1, 'password' => $pw1, 'bed_id' => $bed1]);
http('POST', '/admin/boarders', $admin, ['csrf_token' => $adminT, 'name' => 'Audit Two', 'email' => $email2, 'password' => $newPassword()]);
$r1 = (int) one('SELECT id FROM users WHERE email = ?', [$email1]);
$r2 = (int) one('SELECT id FROM users WHERE email = ?', [$email2]);
check($r1 > 0 && $r2 > 0, 'two residents saved');
check((int) one('SELECT current_boarder_id FROM beds WHERE id = ?', [$bed1]) === $r1 && (int) one('SELECT bed_id FROM boarder_profiles WHERE user_id = ?', [$r1]) === $bed1,
    'bed and profile both record the first resident on Bed 1');
http('POST', "/admin/boarders/{$r1}/info", $admin, ['csrf_token' => $adminT, 'name' => 'Audit One', 'email' => $email1, 'status' => 'active', 'reason' => 'audit']);
check(one('SELECT status FROM boarder_profiles WHERE user_id = ?', [$r1]) === 'active', 'status change saved');
check((int) one('SELECT COUNT(*) FROM boarder_status_log WHERE boarder_id = ? AND new_status = "active"', [$r1]) === 1, 'status change logged with who and why');

[$res, $resT] = loginAs($email1, $pw1);
check($resT !== '', 'the new resident can sign in with the password the admin set');

// Things that must exist for their per-row forms to be on screen during the wiring crawl.
http('POST', '/portal/maintenance', $res, ['csrf_token' => $resT, 'category' => 'plumbing', 'description' => "audit leak {$tag}"]);
$ticket = (int) one('SELECT id FROM maintenance_requests WHERE description = ?', ["audit leak {$tag}"]);
http('POST', '/staff/incidents', $res, ['csrf_token' => $resT, 'type' => 'Noise', 'description' => "audit incident {$tag}"]);
$incident = (int) one('SELECT id FROM incidents WHERE description = ?', ["audit incident {$tag}"]);
$sos = (int) (json_decode(http('POST', '/api/sos', $res, ['csrf_token' => $resT])['body'], true)['alert_id'] ?? 0);
http('POST', '/portal/payments', $res, ['csrf_token' => $resT, 'payment_method' => 'maya', 'claimed_amount' => '100.00',
    'proof' => new CURLFile(__DIR__ . '/../public/assets/images/landing-bg.jpg', 'image/jpeg', 'receipt.jpg')]);
$ruleId = (int) one('SELECT id FROM penalty_rules WHERE active = 1 ORDER BY id LIMIT 1');
http('POST', '/admin/penalties/issue', $admin, ['csrf_token' => $adminT, 'boarder_id' => $r1, 'rule_id' => $ruleId, 'amount' => '50', 'reason' => "audit fee {$tag}"]);
$penalty = (int) one('SELECT id FROM penalties WHERE reason = ?', ["audit fee {$tag}"]);
http('POST', '/admin/electricity', $admin, ['csrf_token' => $adminT, 'room_id' => $roomId, 'label' => "Audit {$tag}", 'amount' => '300']);
http('POST', '/admin/penalties/issue', $admin, ['csrf_token' => $adminT, 'boarder_id' => $r2, 'rule_id' => $ruleId, 'amount' => '10', 'reason' => "audit two {$tag}"]);
http('POST', "/admin/boarders/{$r2}/delete", $admin, ['csrf_token' => $adminT]);
http('POST', '/admin/inquiry-center/submit', $admin, ['csrf_token' => $adminT, 'name' => "Audit prospect {$tag}", 'phone' => '09171234567']);
$inquiry = (int) one('SELECT id FROM inquiries WHERE name = ?', ["Audit prospect {$tag}"]);
check($ticket > 0 && $incident > 0 && $sos > 0 && $penalty > 0 && $inquiry > 0, 'repair request, incident, SOS, penalty and inquiry saved');
check(one('SELECT status FROM users WHERE id = ?', [$r2]) === 'archived', 'a resident with a penalty on record is archived, not erased');

// ---------------------------------------------------------------------------------
echo "== Wiring: every form and link on every page leads to a real route ==\n";
preg_match_all("/router->add\\('(GET|POST)',\\s*'([^']+)'/", file_get_contents(__DIR__ . '/../public/index.php'), $m, PREG_SET_ORDER);
$routes = array_map(fn ($x) => [$x[1], $x[2], '#^' . preg_replace('#\{[a-z_]+\}#i', '[^/]+', $x[2]) . '$#'], $m);
$matches = function (string $method, string $path) use ($routes): ?string {
    $path = rtrim(strtok($path, '?#'), '/') ?: '/';
    foreach ($routes as [$rm, $pattern, $rx]) {
        if ($rm === $method && preg_match($rx, $path)) {
            return "{$rm} {$pattern}";
        }
    }
    return null;
};

$pages = [
    'visitor' => [null, ['/', '/login']],
    'admin' => [$admin, ['/admin/dashboard', '/admin/inquiry-center', "/admin/inquiries/{$inquiry}", '/admin/boarders', '/admin/boarders?archived=1', "/admin/boarders/{$r1}",
        '/admin/staff', '/admin/rooms', '/admin/payments', '/admin/expenses', '/admin/penalty-rules', '/admin/occupancy', '/staff/dashboard',
        '/staff/maintenance', '/staff/maintenance/history', '/staff/incidents', '/staff/incidents/history', '/notifications', '/profile']],
    'staff' => [$staff, ['/staff/dashboard', '/staff/maintenance', '/staff/maintenance/history', '/staff/incidents', '/staff/incidents/history',
        '/admin/inquiry-center', '/notifications', '/profile']],
    'resident' => [$res, ['/portal/dashboard', '/portal/maintenance/new', '/portal/payments/new', '/staff/incidents', '/staff/incidents/history',
        '/notifications', '/profile']],
];
$reached = [];
$badForms = [];
$badLinks = [];
$brokenPages = [];
foreach ($pages as $role => [$jar, $paths]) {
    $links = [];
    foreach ($paths as $path) {
        $r = http('GET', $path, $jar);
        if ($r['code'] !== 200 || hasPhpError($r['body'])) {
            $brokenPages[] = "{$role} {$path} ({$r['code']})";
        }
        preg_match_all('/<form\b[^>]*>/i', $r['body'], $forms);
        foreach ($forms[0] as $tag_) {
            $method = preg_match('/method="(\w+)"/i', $tag_, $mm) ? strtoupper($mm[1]) : 'GET';
            if ($method === 'DIALOG' || !preg_match('/action="([^"]*)"/i', $tag_, $am)) {
                continue; // dialogs and script-driven forms are checked through their endpoints below
            }
            $hit = $matches($method, html_entity_decode($am[1]));
            $hit === null ? $badForms[] = "{$role} {$path}: {$method} {$am[1]}" : $reached[$hit] = true;
        }
        preg_match_all('/href="(\/[^"\/][^"]*|\/)"/', $r['body'], $hrefs);
        foreach ($hrefs[1] as $href) {
            $href = html_entity_decode($href);
            if (!str_starts_with($href, '/assets/')) {
                $links[$href] = $path;
            }
        }
    }
    foreach ($links as $href => $from) {
        if ($matches('GET', $href) === null) {
            $badLinks[] = "{$role} {$from}: {$href} has no route";
            continue;
        }
        $r = http('GET', $href, $jar);
        if (!in_array($r['code'], [200, 302], true) || hasPhpError($r['body'])) {
            $badLinks[] = "{$role} {$from}: {$href} answered {$r['code']}";
        }
    }
}
check($brokenPages === [], 'every page renders for its role with no PHP error' . ($brokenPages ? ': ' . implode('; ', $brokenPages) : ''));
check($badForms === [], 'every form posts to a route that exists' . ($badForms ? ': ' . implode('; ', $badForms) : ''));
check($badLinks === [], 'every link opens' . ($badLinks ? ': ' . implode('; ', $badLinks) : ''));

// State-changing routes that are called by script (fetch) or kept as an alias, not by a plain form.
$scripted = ['POST /api/sos', 'POST /api/maintenance/score-preview', 'POST /api/notifications/{id}/read', 'POST /api/assistant/ask',
    'POST /api/assistant/suggest-description', 'POST /api/assistant/suggest-category', 'POST /api/assistant/analyze-ticket',
    'POST /api/assistant/summarize-queue', 'POST /portal/incidents', 'POST /notifications/{id}/read',
    // Only on screen while a move-out is scheduled; clicked through in test_wp03_dates.php.
    'POST /admin/boarders/{id}/move-out/cancel'];
$unreachable = [];
foreach ($routes as [$rm, $pattern]) {
    if ($rm === 'POST' && empty($reached["POST {$pattern}"]) && !in_array("POST {$pattern}", $scripted, true)) {
        $unreachable[] = $pattern;
    }
}
check($unreachable === [], 'every state-changing route has a form on some page' . ($unreachable ? ': missing for ' . implode(', ', $unreachable) : ''));

// ---------------------------------------------------------------------------------
echo "== Rooms and beds: edit and delete ==\n";
http('POST', "/admin/rooms/{$roomId}/update", $admin, ['csrf_token' => $adminT, 'room_number' => "A{$tag}", 'floor' => '3', 'capacity' => 4, 'base_price' => '1750.50']);
$room = row('SELECT * FROM rooms WHERE id = ?', [$roomId]);
check($room['floor'] === '3' && (int) $room['capacity'] === 4 && $room['base_price'] === '1750.50', 'room edit saved (floor, capacity, price)');
http('POST', "/admin/rooms/{$roomId}/update", $admin, ['csrf_token' => $adminT, 'room_number' => "A{$tag}", 'floor' => '3', 'capacity' => 2, 'base_price' => '1750.50']);
check((int) one('SELECT capacity FROM rooms WHERE id = ?', [$roomId]) === 4, 'capacity below the number of beds is refused');
http('POST', "/admin/rooms/{$roomId}/update", $admin, ['csrf_token' => $adminT, 'room_number' => '101', 'floor' => '3', 'capacity' => 4, 'base_price' => '1']);
check(one('SELECT room_number FROM rooms WHERE id = ?', [$roomId]) === "A{$tag}", 'renaming to an existing room number is refused');
http('POST', "/admin/beds/{$bed3}/update", $admin, ['csrf_token' => $adminT, 'label' => 'Window bed']);
check(one('SELECT label FROM beds WHERE id = ?', [$bed3]) === 'Window bed', 'bed label edit saved');
http('POST', "/admin/beds/{$bed3}/update", $admin, ['csrf_token' => $adminT, 'label' => 'Bed 2']);
check(one('SELECT label FROM beds WHERE id = ?', [$bed3]) === 'Window bed', 'a label already used in the room is refused');
http('POST', "/admin/beds/{$bed1}/delete", $admin, ['csrf_token' => $adminT]);
check((int) one('SELECT COUNT(*) FROM beds WHERE id = ?', [$bed1]) === 1, 'an occupied bed cannot be deleted');
http('POST', "/admin/beds/{$bed3}/delete", $admin, ['csrf_token' => $adminT]);
check((int) one('SELECT COUNT(*) FROM beds WHERE id = ?', [$bed3]) === 0, 'a vacant bed is deleted');
http('POST', "/admin/rooms/{$roomId}/delete", $admin, ['csrf_token' => $adminT]);
check((int) one('SELECT COUNT(*) FROM rooms WHERE id = ?', [$roomId]) === 1, 'a room that still has beds cannot be deleted');
check(http('POST', "/admin/rooms/{$roomId}/update", $staff, ['csrf_token' => $staffT, 'room_number' => 'x', 'capacity' => 1, 'base_price' => 1])['code'] === 403, 'staff cannot edit rooms');

echo "== One resident per bed ==\n";
http('POST', '/admin/boarders', $admin, ['csrf_token' => $adminT, 'name' => 'Audit Three', 'email' => "audit.three.{$tag}@rjm.test", 'password' => $newPassword()]);
$r3 = (int) one('SELECT id FROM users WHERE email = ?', ["audit.three.{$tag}@rjm.test"]);
http('POST', '/admin/beds/assign', $admin, ['csrf_token' => $adminT, 'boarder_id' => $r3, 'bed_id' => $bed1]);
check((int) one('SELECT current_boarder_id FROM beds WHERE id = ?', [$bed1]) === $r1 && one('SELECT bed_id FROM boarder_profiles WHERE user_id = ?', [$r3]) === null,
    'a second resident cannot be put on an occupied bed');
http('POST', '/admin/beds/assign', $admin, ['csrf_token' => $adminT, 'boarder_id' => $r1, 'bed_id' => $bed2]);
check((int) one('SELECT bed_id FROM boarder_profiles WHERE user_id = ?', [$r1]) === $bed2 && one('SELECT status FROM beds WHERE id = ?', [$bed1]) === 'vacant'
    && (int) one('SELECT current_boarder_id FROM beds WHERE id = ?', [$bed2]) === $r1, 'moving a resident frees the old bed and fills the new one');
$refused = false;
try {
    $pdo->prepare('UPDATE boarder_profiles SET bed_id = ? WHERE user_id = ?')->execute([$bed2, $r3]);
} catch (PDOException $e) {
    $refused = true;
}
check($refused, 'the database itself refuses two profiles on one bed');
// A stale pointer must never empty a bed someone else holds.
App\Models\Bed::vacateHeldBy($r3);
check(one('SELECT status FROM beds WHERE id = ?', [$bed2]) === 'occupied', 'freeing the beds of someone who holds none leaves other residents\' beds alone');
// The opposite fault: the bed says the third resident holds it, their profile does not.
$pdo->prepare('UPDATE beds SET status = "occupied", current_boarder_id = ? WHERE id = ?')->execute([$r3, $bed1]);
$bedSpare = App\Models\Bed::create($roomId, 'Spare');
$r = http('POST', '/admin/beds/assign', $admin, ['csrf_token' => $adminT, 'boarder_id' => $r3, 'bed_id' => $bedSpare]);
check($r['code'] === 302 && (int) one('SELECT COUNT(*) FROM beds WHERE current_boarder_id = ?', [$r3]) === 1
    && (int) one('SELECT bed_id FROM boarder_profiles WHERE user_id = ?', [$r3]) === $bedSpare && one('SELECT status FROM beds WHERE id = ?', [$bed1]) === 'vacant',
    'a resident whose old bed the profile lost track of can still be moved, and ends up on exactly one bed');
check((int) one('SELECT COUNT(*) FROM boarder_profiles bp JOIN beds b ON b.id = bp.bed_id WHERE b.current_boarder_id IS NULL OR b.current_boarder_id <> bp.user_id') === 0,
    'no profile points at a bed held by someone else');

echo "== Resident record: details, dates, notes, restore ==\n";
http('POST', "/admin/boarders/{$r1}/info", $admin, ['csrf_token' => $adminT, 'name' => 'Audit One Edited', 'email' => $email1, 'contact_number' => '0917 000 1111',
    'emergency_contact_number' => '0918 222 3333', 'return_to' => "/admin/boarders/{$r1}"]);
$p = row('SELECT u.name, bp.contact_number, bp.emergency_contact_number FROM users u JOIN boarder_profiles bp ON bp.user_id = u.id WHERE u.id = ?', [$r1]);
check($p['name'] === 'Audit One Edited' && $p['contact_number'] === '0917 000 1111' && $p['emergency_contact_number'] === '0918 222 3333', 'details edit saved');
http('POST', "/admin/boarders/{$r1}/info", $admin, ['csrf_token' => $adminT, 'name' => 'X', 'email' => 'admin@rjm.test']);
check(one('SELECT email FROM users WHERE id = ?', [$r1]) === $email1, 'an email that belongs to another account is refused');
http('POST', '/admin/boarders/1/info', $admin, ['csrf_token' => $adminT, 'name' => 'Hijacked', 'email' => 'hijack@rjm.test']);
check(one('SELECT name FROM users WHERE id = 1') !== 'Hijacked', 'the resident-edit route refuses an id that is not a resident');
http('POST', "/admin/boarders/{$r1}/dates", $admin, ['csrf_token' => $adminT, 'move_in_date' => '2026-09-01', 'move_out_date' => '']);
check(one('SELECT move_in_date FROM boarder_profiles WHERE user_id = ?', [$r1]) === '2026-09-01', 'move-in date saved');
http('POST', "/admin/boarders/{$r1}/dates", $admin, ['csrf_token' => $adminT, 'move_in_date' => '2026-02-30', 'move_out_date' => '']);
http('POST', "/admin/boarders/{$r1}/dates", $admin, ['csrf_token' => $adminT, 'move_in_date' => '2026-09-01', 'move_out_date' => '2026-08-01']);
check(one('SELECT move_in_date FROM boarder_profiles WHERE user_id = ?', [$r1]) === '2026-09-01' && one('SELECT move_out_date FROM boarder_profiles WHERE user_id = ?', [$r1]) === null,
    'an impossible date and a move-out before move-in are both refused');
http('POST', "/admin/boarders/{$r1}/notes", $admin, ['csrf_token' => $adminT, 'notes' => "pays every 5th {$tag}"]);
check(one('SELECT notes FROM boarder_profiles WHERE user_id = ?', [$r1]) === "pays every 5th {$tag}", 'private note saved');
check(http('POST', "/admin/boarders/{$r1}/notes", $admin, ['notes' => 'no token'])['code'] === 400, 'note without the session token is refused');
http('POST', "/admin/boarders/{$r2}/restore", $admin, ['csrf_token' => $adminT]);
check(one('SELECT status FROM users WHERE id = ?', [$r2]) === 'active', 'archived resident restored');
http('POST', '/admin/boarders/2/restore', $admin, ['csrf_token' => $adminT]);
check(str_contains(http('GET', '/admin/boarders', $admin)['body'], 'No archived resident'), 'restoring an id that is not an archived resident says so');

echo "== Forgotten password: the admin sets a new one ==\n";
foreach (range(1, 5) as $i) {
    loginAs($email1, 'wrong-password');
}
check(loginAs($email1, $pw1)[1] === '', 'five wrong passwords lock the account, even with the right one');
$hashBefore = one('SELECT password_hash FROM users WHERE id = ?', [$r1]);
http('POST', "/admin/boarders/{$r1}/password", $admin, ['csrf_token' => $adminT, 'new_password' => 'short']);
http('POST', "/admin/boarders/{$r1}/password", $admin, ['csrf_token' => $adminT, 'new_password' => str_repeat('Ab1!', 19)]);
check(one('SELECT password_hash FROM users WHERE id = ?', [$r1]) === $hashBefore, 'a password under 8 or over 72 characters is refused');
check(App\Services\PasswordResetService::byAdmin(['id' => 1, 'role' => 'admin', 'email' => 'admin@rjm.test'], $newPassword(), 1) !== null
    && password_verify(getenv('TEST_ADMIN_PASSWORD'), (string) one('SELECT password_hash FROM users WHERE id = 1')), 'the reset service itself refuses an admin account');
check(http('POST', "/admin/boarders/{$r1}/password", $staff, ['csrf_token' => $staffT, 'new_password' => $newPassword()])['code'] === 403, 'staff cannot reset a resident password');
check(http('POST', "/admin/boarders/{$r1}/password", $res, ['csrf_token' => $resT, 'new_password' => $newPassword()])['code'] === 403, 'a resident cannot reset a password');
check(http('POST', "/admin/boarders/{$r1}/password", $admin, ['new_password' => $newPassword()])['code'] === 400, 'reset without the session token is refused');
check(http('POST', "/admin/boarders/{$r1}/password", null, ['new_password' => $newPassword()])['location'] === '/login', 'a visitor is sent to the login page');
check(one('SELECT password_hash FROM users WHERE id = ?', [$r1]) === $hashBefore, 'none of the refused attempts changed the password');
$pw1b = $newPassword();
$r = http('POST', "/admin/boarders/{$r1}/password", $admin, ['csrf_token' => $adminT, 'new_password' => $pw1b]);
check($r['code'] === 302 && password_verify($pw1b, (string) one('SELECT password_hash FROM users WHERE id = ?', [$r1])), 'admin reset stores the new password as a hash');
check(!str_contains((string) one('SELECT password_hash FROM users WHERE id = ?', [$r1]), $pw1b), 'the password itself is not stored');
check(http('GET', '/portal/dashboard', $res)['code'] === 302, 'the resident is signed out of the session they had open');
check(loginAs($email1, $pw1)[1] === '', 'the old password no longer works');
[$res, $resT] = loginAs($email1, $pw1b);
check($resT !== '', 'the new password works at once (the lockout was lifted)');
check((int) one('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND message LIKE "An administrator set a new password%"', [$r1]) === 1, 'the resident is told inside the system');
http('POST', '/admin/boarders/1/password', $admin, ['csrf_token' => $adminT, 'new_password' => $newPassword()]);
check(password_verify(getenv('TEST_ADMIN_PASSWORD'), (string) one('SELECT password_hash FROM users WHERE id = 1')), 'the resident route cannot be used on an admin account');
$staffPw = $newPassword();
http('POST', '/admin/staff', $admin, ['csrf_token' => $adminT, 'name' => 'Audit Staff', 'email' => "audit.staff.{$tag}@rjm.test", 'password' => $newPassword()]);
$s1 = (int) one('SELECT id FROM users WHERE email = ?', ["audit.staff.{$tag}@rjm.test"]);
http('POST', "/admin/staff/{$s1}/password", $admin, ['csrf_token' => $adminT, 'new_password' => $staffPw]);
check(loginAs("audit.staff.{$tag}@rjm.test", $staffPw)[1] !== '', 'staff password reset works the same way');
http('POST', "/admin/staff/{$r1}/password", $admin, ['csrf_token' => $adminT, 'new_password' => $newPassword()]);
check(password_verify($pw1b, (string) one('SELECT password_hash FROM users WHERE id = ?', [$r1])), 'the staff route cannot be used on a resident');

echo "== Resident's own profile ==\n";
http('POST', '/profile/update', $res, ['csrf_token' => $resT, 'name' => 'Audit One Self', 'email' => $email1, 'contact_number' => '0999 123 4567', 'emergency_contact_number' => '']);
$p = row('SELECT u.name, bp.contact_number, bp.emergency_contact_number FROM users u JOIN boarder_profiles bp ON bp.user_id = u.id WHERE u.id = ?', [$r1]);
check($p['name'] === 'Audit One Self' && $p['contact_number'] === '0999 123 4567' && $p['emergency_contact_number'] === null, 'profile edit saved');
$r = http('POST', '/profile/update', $res, ['csrf_token' => $resT, 'name' => 'Audit One Self', 'email' => $email1, 'contact_number' => str_repeat('9', 60)]);
check($r['code'] === 302 && !hasPhpError(http('GET', '/profile', $res)['body']) && one('SELECT contact_number FROM boarder_profiles WHERE user_id = ?', [$r1]) === '0999 123 4567',
    'a 60-character contact number is refused with a message, not an error page');
http('POST', '/profile/update', $res, ['csrf_token' => $resT, 'name' => 'X', 'email' => 'staff@rjm.test']);
check(one('SELECT email FROM users WHERE id = ?', [$r1]) === $email1, 'taking another account\'s email is refused');

echo "== Penalty rules and penalties ==\n";
http('POST', "/admin/penalty-rules/{$ruleId}/amount", $admin, ['csrf_token' => $adminT, 'amount' => '75.25']);
check(one('SELECT amount FROM penalty_rules WHERE id = ?', [$ruleId]) === '75.25', 'rule amount edit saved');
foreach (['0', '2000000', 'abc', '-5'] as $bad) {
    http('POST', "/admin/penalty-rules/{$ruleId}/amount", $admin, ['csrf_token' => $adminT, 'amount' => $bad]);
}
check(one('SELECT amount FROM penalty_rules WHERE id = ?', [$ruleId]) === '75.25', 'zero, negative, non-numeric and oversized amounts are refused');
$r = http('POST', '/admin/penalty-rules', $admin, ['csrf_token' => $adminT, 'name' => "Audit rule {$tag}", 'condition_type' => 'manual', 'amount' => '99999999999']);
check($r['code'] === 302 && (int) one('SELECT COUNT(*) FROM penalty_rules WHERE name = ?', ["Audit rule {$tag}"]) === 0, 'an oversized new rule is refused, not an error page');
$r = http('POST', '/admin/penalty-rules', $admin, ['csrf_token' => $adminT]);
check($r['code'] === 302 && !hasPhpError($r['body']), 'an empty new-rule form is refused cleanly');
$r = http('POST', '/admin/penalty-rules/999999/amount', $admin, ['csrf_token' => $adminT, 'amount' => '10']);
check(str_contains(http('GET', '/admin/penalty-rules', $admin)['body'], 'Penalty rule not found'), 'editing a rule that does not exist says so');
http('POST', "/admin/penalty-rules/{$ruleId}/toggle", $admin, ['csrf_token' => $adminT, 'active' => '0']);
check((int) one('SELECT active FROM penalty_rules WHERE id = ?', [$ruleId]) === 0, 'rule deactivated');
http('POST', '/staff/penalties/issue', $staff, ['csrf_token' => $staffT, 'boarder_id' => $r1, 'rule_id' => $ruleId, 'amount' => '1', 'reason' => "audit off {$tag}"]);
check((int) one('SELECT COUNT(*) FROM penalties WHERE reason = ?', ["audit off {$tag}"]) === 0, 'a deactivated rule cannot be used to issue a penalty');
http('POST', "/admin/penalty-rules/{$ruleId}/toggle", $admin, ['csrf_token' => $adminT, 'active' => '1']);
check((int) one('SELECT active FROM penalty_rules WHERE id = ?', [$ruleId]) === 1, 'rule reactivated');
$owedBefore = (float) App\Services\BillingService::calculateBalance($r1)['total_outstanding'];
http('POST', "/admin/penalties/{$penalty}/mark-paid", $admin, ['csrf_token' => $adminT]);
$pen = row('SELECT status, waived_by, paid_at FROM penalties WHERE id = ?', [$penalty]);
check($pen['status'] === 'paid' && (int) $pen['waived_by'] === 1 && $pen['paid_at'] !== null, 'penalty marked paid, with the admin who did it');
check(abs($owedBefore - 50 - (float) App\Services\BillingService::calculateBalance($r1)['total_outstanding']) < 0.01, 'the resident\'s balance dropped by the penalty');
http('POST', "/admin/penalties/{$penalty}/mark-paid", $admin, ['csrf_token' => $adminT]);
check(str_contains(http('GET', '/admin/penalty-rules', $admin)['body'], 'already been marked as paid'), 'marking it paid twice is refused');
$r = http('POST', '/admin/penalties/issue', $admin, ['csrf_token' => $adminT, 'boarder_id' => $r1, 'rule_id' => $ruleId, 'amount' => '5000000', 'reason' => "audit big {$tag}"]);
check((int) one('SELECT COUNT(*) FROM penalties WHERE reason = ?', ["audit big {$tag}"]) === 0, 'an oversized penalty is refused');

echo "== Repairs: staff work the queue ==\n";
http('POST', "/staff/maintenance/{$ticket}/status", $staff, ['csrf_token' => $staffT, 'status' => 'in_progress']);
check(one('SELECT status FROM maintenance_requests WHERE id = ?', [$ticket]) === 'in_progress', 'status saved: in progress');
check((int) one('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND message LIKE ?', [$r1, "%#{$ticket}%"]) >= 1, 'the resident is notified');
http('POST', "/staff/maintenance/{$ticket}/status", $staff, ['csrf_token' => $staffT, 'status' => 'done']);
check(one('SELECT status FROM maintenance_requests WHERE id = ?', [$ticket]) === 'in_progress', 'a status that does not exist is refused');
http('POST', "/staff/maintenance/{$ticket}/status", $staff, ['csrf_token' => $staffT, 'status' => 'resolved']);
$t = row('SELECT status, resolved_by, resolved_at FROM maintenance_requests WHERE id = ?', [$ticket]);
check($t['status'] === 'resolved' && (int) $t['resolved_by'] === 2 && $t['resolved_at'] !== null, 'resolved, with who and when');
check(http('POST', "/staff/maintenance/{$ticket}/status", $res, ['csrf_token' => $resT, 'status' => 'open'])['code'] === 403, 'a resident cannot change a repair status');

echo "== Incidents and SOS ==\n";
http('POST', "/staff/incidents/{$incident}/resolve", $staff, ['csrf_token' => $staffT, 'resolution_notes' => '']);
check((int) one('SELECT resolved FROM incidents WHERE id = ?', [$incident]) === 0, 'resolving without notes is refused');
check(http('POST', "/staff/incidents/{$incident}/resolve", $res, ['csrf_token' => $resT, 'resolution_notes' => 'x'])['code'] === 403, 'a resident cannot resolve an incident');
http('POST', "/staff/incidents/{$incident}/resolve", $staff, ['csrf_token' => $staffT, 'resolution_notes' => 'Spoke to both rooms.']);
$i = row('SELECT resolved, resolution_notes, resolved_by FROM incidents WHERE id = ?', [$incident]);
check((int) $i['resolved'] === 1 && $i['resolution_notes'] === 'Spoke to both rooms.' && (int) $i['resolved_by'] === 2, 'incident resolved, with notes and who');
http('POST', "/staff/incidents/{$incident}/resolve", $staff, ['csrf_token' => $staffT, 'resolution_notes' => 'second time']);
check(one('SELECT resolution_notes FROM incidents WHERE id = ?', [$incident]) === 'Spoke to both rooms.', 'resolving it twice changes nothing');
$active = json_decode(http('GET', '/api/sos/active', $staff)['body'], true);
check(is_array($active) && in_array($sos, array_map(fn ($a) => (int) $a['id'], $active), true), 'the open SOS is in the live list staff poll');
check(http('GET', '/api/sos/active', $res)['code'] === 403, 'a resident cannot read the SOS list');
check(http('POST', "/staff/sos/{$sos}/ack", $res, ['csrf_token' => $resT])['code'] === 403, 'a resident cannot acknowledge an SOS');
http('POST', "/staff/sos/{$sos}/ack", $staff, ['csrf_token' => $staffT]);
$a = row('SELECT status, acknowledged_by FROM sos_alerts WHERE id = ?', [$sos]);
check($a['status'] === 'acknowledged' && (int) $a['acknowledged_by'] === 2, 'SOS acknowledged, with who');
http('POST', "/staff/sos/{$sos}/resolve", $staff, ['csrf_token' => $staffT]);
check(one('SELECT status FROM sos_alerts WHERE id = ?', [$sos]) === 'resolved' && one('SELECT resolved_at FROM sos_alerts WHERE id = ?', [$sos]) !== null, 'SOS resolved, with when');
$active = json_decode(http('GET', '/api/sos/active', $staff)['body'], true);
check(!in_array($sos, array_map(fn ($a) => (int) $a['id'], $active), true), 'it leaves the live list');

echo "== Notifications ==\n";
$unread = json_decode(http('GET', '/api/notifications/unread', $res)['body'], true);
check(is_array($unread) && count($unread) >= 3 && count(array_filter($unread, fn ($n) => (int) $n['user_id'] !== $r1)) === 0, 'the unread list holds only this resident\'s notifications');
[$n1, $n2, $n3] = array_map(fn ($n) => (int) $n['id'], array_slice($unread, 0, 3));
$r = http('POST', "/api/notifications/{$n1}/read", $res, ['csrf_token' => $resT], ['X-Requested-With: XMLHttpRequest']);
check((json_decode($r['body'], true)['ok'] ?? false) === true && (int) one('SELECT is_read FROM notifications WHERE id = ?', [$n1]) === 1, 'marked read from the bell');
http('POST', "/notifications/{$n1}/toggle-read", $res, ['csrf_token' => $resT, 'is_read' => '1']);
check((int) one('SELECT is_read FROM notifications WHERE id = ?', [$n1]) === 0, 'marked unread again');
http('POST', "/notifications/{$n2}/toggle-pin", $res, ['csrf_token' => $resT]);
check((int) one('SELECT is_pinned FROM notifications WHERE id = ?', [$n2]) === 1, 'pinned');
http('POST', "/notifications/{$n3}/delete", $res, ['csrf_token' => $resT]);
check((int) one('SELECT COUNT(*) FROM notifications WHERE id = ?', [$n3]) === 0, 'deleted');
$foreign = (int) one('SELECT id FROM notifications WHERE user_id = 1 ORDER BY id DESC LIMIT 1');
http('POST', "/notifications/{$foreign}/delete", $res, ['csrf_token' => $resT]);
$r = http('POST', "/api/notifications/{$foreign}/read", $res, ['csrf_token' => $resT], ['X-Requested-With: XMLHttpRequest']);
check($foreign > 0 && (int) one('SELECT COUNT(*) FROM notifications WHERE id = ?', [$foreign]) === 1 && $r['code'] === 403, 'another person\'s notification cannot be read or deleted');
http('POST', '/notifications/read-all', $res, ['csrf_token' => $resT]);
check((int) one('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$r1]) === 0, 'mark all read');
check(json_decode(http('GET', '/api/notifications/unread', $res)['body'], true) === [], 'the unread list is then empty');

echo "== Expenses, ledger, occupancy, assistant status ==\n";
http('POST', '/admin/expenses', $admin, ['csrf_token' => $adminT, 'category' => 'Repairs', 'amount' => '250.75', 'description' => "audit expense {$tag}"]);
$e = row('SELECT * FROM expenses WHERE description = ?', ["audit expense {$tag}"]);
check($e && $e['amount'] === '250.75' && (int) $e['staff_id'] === 1, 'expense saved with who recorded it');
http('POST', '/admin/expenses', $admin, ['csrf_token' => $adminT, 'category' => '', 'amount' => '10', 'description' => "audit bad {$tag}"]);
http('POST', '/admin/expenses', $admin, ['csrf_token' => $adminT, 'category' => 'Repairs', 'amount' => '-1', 'description' => "audit bad {$tag}"]);
check((int) one('SELECT COUNT(*) FROM expenses WHERE description = ?', ["audit bad {$tag}"]) === 0, 'an expense with no category or a negative amount is refused');
$csv = http('GET', '/admin/ledger/export?from=' . date('Y-m-01') . '&to=' . date('Y-m-d'), $admin);
check(str_contains($csv['headers'], 'text/csv') && str_contains($csv['body'], 'Type,ID,"User Name"') && str_contains($csv['body'], "audit expense {$tag}"), 'ledger export is a CSV that contains the expense just recorded');
check(http('GET', '/admin/ledger/export', $staff)['code'] === 403, 'staff cannot export the ledger');
http('GET', '/admin/occupancy', $admin);
$snap = row('SELECT * FROM occupancy_snapshots WHERE DATE(created_at) = CURDATE() ORDER BY id DESC LIMIT 1');
$bedsNow = row("SELECT COUNT(*) AS total, SUM(status = 'occupied') AS occupied FROM beds");
check($snap && (int) $snap['total_beds'] === (int) $bedsNow['total'] && (int) $snap['occupied_beds'] === (int) $bedsNow['occupied'], 'today\'s occupancy snapshot matches the beds table');
$status = json_decode(http('GET', '/api/assistant/status', $res)['body'], true);
check(($status['ok'] ?? false) === true && is_bool($status['available'] ?? null), 'assistant status answers whether the local AI is running');
check(http('GET', '/landing', null)['code'] === 200, '/landing opens the landing page');

echo "\n" . ($failures === 0 ? "All feature-audit checks passed.\n" : "{$failures} feature-audit check(s) failed.\n");
exit($failures === 0 ? 0 : 1);
