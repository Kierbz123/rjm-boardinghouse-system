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
        $hasFile = (bool) array_filter($form, fn ($v) => $v instanceof CURLFile);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $hasFile ? $form : http_build_query($form));
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

echo "== Boarder payments always wait for an admin (C3) ==\n";
$receipt = new CURLFile(__DIR__ . '/../public/assets/images/landing-bg.jpg', 'image/jpeg', 'receipt.jpg');
$token = csrfFrom(http('GET', '/portal/payments/new', $jars['boarder'])['body']);
$owed = (float) App\Services\BillingService::calculateBalance(3)['total_outstanding'];
$countPayments = fn () => (int) $pdo->query('SELECT COUNT(*) FROM payments WHERE boarder_id = 3')->fetchColumn();
$notifBefore = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM notifications')->fetchColumn();
$before = $countPayments();
$r = http('POST', '/portal/payments', $jars['boarder'], ['csrf_token' => $token, 'payment_method' => 'gcash',
    'claimed_amount' => '1.00', 'expected_amount' => '1.00']);
check($r['location'] === '/portal/payments/new' && $countPayments() === $before, 'submission without a receipt is refused');
$r = http('POST', '/portal/payments', $jars['boarder'], ['csrf_token' => $token,
    'claimed_amount' => '1.00', 'proof' => $receipt]);
check($countPayments() === $before, 'submission without a payment method (GCash/Maya/bank) is refused');
$r = http('POST', '/portal/payments', $jars['boarder'], ['csrf_token' => $token, 'payment_method' => 'paypal',
    'claimed_amount' => '1.00', 'proof' => $receipt]);
check($countPayments() === $before, 'an unknown payment method is refused');
$r = http('POST', '/portal/payments', $jars['boarder'], ['csrf_token' => $token, 'payment_method' => 'gcash',
    'reference_number' => '1009 223 4455', 'claimed_amount' => '1.00', 'expected_amount' => '1.00', 'proof' => $receipt]);
$pay = $pdo->query('SELECT * FROM payments WHERE boarder_id = 3 ORDER BY id DESC LIMIT 1')->fetch();
check($pay && $pay['verification_status'] === 'pending', 'a ₱1 "matching" payment waits as pending, not approved');
check($pay && $pay['payment_method'] === 'gcash' && $pay['reference_number'] === '1009 223 4455', 'method and reference number are saved');
check($pay && abs((float) $pay['expected_amount'] - $owed) < 0.01, 'expected amount is the server\'s figure, not the form\'s');
check(abs((float) App\Services\BillingService::calculateBalance(3)['total_outstanding'] - $owed) < 0.01, 'balance unchanged until an admin approves');
check($r['location'] === '/portal/payments/new', 'after submitting, the resident lands on Pay Rent');
$page = http('GET', '/portal/payments/new', $jars['boarder'])['body'];
check(str_contains($page, 'data-testid="payment-submitted-dialog"') && str_contains($page, 'Status: Pending review'), 'a pop-up confirms the receipt is pending review');
check(str_contains($page, 'GCash receipt for <strong>₱1.00</strong>'), 'the pop-up names the method and amount');
check(!str_contains(http('GET', '/portal/payments/new', $jars['boarder'])['body'], 'payment-submitted-dialog'), 'the pop-up shows only once');
check((int) $pdo->query("SELECT COUNT(*) FROM notifications n JOIN users u ON u.id = n.user_id
    WHERE u.role = 'admin' AND n.id > {$notifBefore} AND n.message LIKE 'New payment proof submitted by Boarder #3 %'")->fetchColumn() > 0,
    'admins are notified of the new receipt');
$r = http('POST', '/portal/payments', $jars['boarder'], ['csrf_token' => $token, 'payment_method' => 'maya',
    'claimed_amount' => '5.00', 'proof' => $receipt]);
check($r['location'] === '/portal/payments/new' && $countPayments() === $before + 1, 'a second receipt is refused while one is waiting for review');

$token = csrfFrom(http('GET', '/admin/payments', $jars['admin'])['body']);
http('POST', "/admin/payments/{$pay['id']}/reject", $jars['admin'], ['csrf_token' => $token]);
check($pdo->query("SELECT verification_status FROM payments WHERE id = {$pay['id']}")->fetchColumn() === 'pending', 'rejecting without a reason is refused');
http('POST', "/admin/payments/{$pay['id']}/approve", $jars['admin'], ['csrf_token' => $token]);
check($pdo->query("SELECT verification_status FROM payments WHERE id = {$pay['id']}")->fetchColumn() === 'admin-approved', 'admin approval works');
$note = $pdo->query("SELECT message FROM notifications WHERE user_id = 3 AND type = 'payment_approved' ORDER BY id DESC LIMIT 1")->fetchColumn();
check($note !== false && str_contains($note, '₱1.00 GCash payment was approved')
    && str_contains($note, $owed > 0 ? 'Applied to:' : 'kept as credit'), 'the resident is notified once the receipt is confirmed: ' . $note);
check((bool) preg_match('/data-testid="payment-status">\s*Approved/', http('GET', '/portal/payments/new', $jars['boarder'])['body']), 'Pay Rent history shows it as Approved');

$token = csrfFrom(http('GET', '/portal/payments/new', $jars['boarder'])['body']);
http('POST', '/portal/payments', $jars['boarder'], ['csrf_token' => $token, 'payment_method' => 'bank_transfer',
    'claimed_amount' => '2.00', 'proof' => $receipt]);
$second = (int) $pdo->query('SELECT MAX(id) FROM payments WHERE boarder_id = 3')->fetchColumn();
$token = csrfFrom(http('GET', '/admin/payments', $jars['admin'])['body']);
http('POST', "/admin/payments/{$second}/reject", $jars['admin'], ['csrf_token' => $token, 'reason' => 'Receipt is blurry']);
$row = $pdo->query("SELECT verification_status, review_note, reviewed_at FROM payments WHERE id = {$second}")->fetch();
check($row['verification_status'] === 'rejected' && $row['review_note'] === 'Receipt is blurry' && $row['reviewed_at'] !== null, 'rejection stores the reason and when');
$note = $pdo->query("SELECT message FROM notifications WHERE user_id = 3 AND type = 'payment_rejected' ORDER BY id DESC LIMIT 1")->fetchColumn();
check($note !== false && str_contains($note, 'Receipt is blurry'), 'the resident is told why it was rejected');
check(str_contains(http('GET', '/portal/payments/new', $jars['boarder'])['body'], 'Reason: Receipt is blurry'), 'Pay Rent history shows the reason');

echo "== Upload size limit is explained, not silently dropped ==\n";
$big = tempnam(sys_get_temp_dir(), 'rjmbig');
file_put_contents($big, "\xFF\xD8\xFF\xE0" . str_repeat("\0", 21 * 1024 * 1024));
$token = csrfFrom(http('GET', '/portal/payments/new', $jars['boarder'])['body']);
$before = $countPayments();
$r = http('POST', '/portal/payments', $jars['boarder'], ['csrf_token' => $token, 'payment_method' => 'gcash',
    'claimed_amount' => '10.00', 'proof' => new CURLFile($big, 'image/jpeg', 'big.jpg')]);
$page = http('GET', '/portal/payments/new', $jars['boarder'])['body'];
check($countPayments() === $before && str_contains($page, 'The limit is 20 MB'), '21 MB receipt: refused with "the limit is 20 MB"');
$token = csrfFrom(http('GET', '/portal/maintenance/new', $jars['boarder'])['body']);
$r = http('POST', '/portal/maintenance', $jars['boarder'], ['csrf_token' => $token, 'category' => 'plumbing',
    'description' => 'big video test', 'media' => new CURLFile($big, 'video/mp4', 'big.mp4')]);
$page = http('GET', '/portal/maintenance/new', $jars['boarder'])['body'];
check(str_contains($page, 'The limit is 20 MB') && $pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE description = 'big video test'")->fetchColumn() == 0,
    '21 MB repair video: refused with the limit instead of saving the request without it');
file_put_contents($big, str_repeat("\0", 26 * 1024 * 1024));
$r = http('POST', '/portal/maintenance', $jars['boarder'], ['csrf_token' => $token, 'category' => 'plumbing',
    'description' => 'huge video test', 'media' => new CURLFile($big, 'video/mp4', 'huge.mp4')]);
check($r['location'] === '/portal/maintenance/new' && str_contains(http('GET', '/portal/maintenance/new', $jars['boarder'])['body'], 'The limit is 20 MB'),
    '26 MB (over post_max_size): "too large", not "invalid session"');
unlink($big);
check(str_contains(http('GET', '/portal/maintenance/new', $jars['boarder'])['body'], 'up to 20 MB'), 'the repair form states the real limit');

echo "== Staff penalties use the rule's amount (decision 7a) ==\n";
$rule = $pdo->query("SELECT * FROM penalty_rules WHERE active = 1 LIMIT 1")->fetch();
$token = csrfFrom(http('GET', '/staff/dashboard', $jars['staff'])['body']);
http('POST', '/staff/penalties/issue', $jars['staff'], ['csrf_token' => $token, 'boarder_id' => 3,
    'rule_id' => $rule['id'], 'amount' => '99999', 'reason' => 'staff-amount-test']);
$issued = $pdo->query("SELECT amount FROM penalties WHERE reason = 'staff-amount-test'")->fetchColumn();
check($issued !== false && abs((float) $issued - (float) $rule['amount']) < 0.01, 'staff cannot set a custom amount');

echo "== Data integrity (B4) ==\n";
$q = fn (string $sql) => $pdo->query($sql)->fetchColumn();
$bedA = (int) $q("SELECT id FROM beds WHERE label = 'Bed A' LIMIT 1");
$bedB = (int) $q("SELECT id FROM beds WHERE label = 'Bed B' LIMIT 1");
$roomId = (int) $q("SELECT room_id FROM beds WHERE id = {$bedA}");
$token = csrfFrom(http('GET', '/admin/boarders', $jars['admin'])['body']);
$add = fn (array $f) => http('POST', '/admin/boarders', $jars['admin'], $f + ['csrf_token' => $token, 'password' => 'Password123']);

// Earlier test files move beds around; start this section from two known-vacant beds.
App\Models\Bed::vacate($bedA);
App\Models\Bed::vacate($bedB);
$pdo->exec("UPDATE boarder_profiles SET bed_id = NULL WHERE bed_id IN ({$bedA}, {$bedB})");

$add(['name' => 'Bad Email', 'email' => 'not-an-email']);
check($q("SELECT COUNT(*) FROM users WHERE name = 'Bad Email'") == 0, 'invalid email rejected');
$add(['name' => 'Mistake', 'email' => 'mistake@rjm.test', 'bed_id' => $bedB]);
$mistake = (int) $q("SELECT id FROM users WHERE email = 'mistake@rjm.test'");
check($mistake > 0 && $q("SELECT current_boarder_id FROM beds WHERE id = {$bedB}") == $mistake, 'boarder created on Bed B');
$add(['name' => 'Orphan', 'email' => 'orphan@rjm.test', 'bed_id' => $bedB]);
check($q("SELECT COUNT(*) FROM users WHERE email = 'orphan@rjm.test'") == 0, 'creating a boarder on an occupied bed leaves no orphan account');
$add(['name' => 'Holder', 'email' => 'holder@rjm.test', 'bed_id' => $bedA]);

http('POST', '/admin/beds/assign', $jars['admin'], ['csrf_token' => $token, 'boarder_id' => $mistake, 'bed_id' => $bedA]);
check((int) $q("SELECT bed_id FROM boarder_profiles WHERE user_id = {$mistake}") === $bedB
    && $q("SELECT status FROM beds WHERE id = {$bedB}") === 'occupied', 'moving to an occupied bed keeps the boarder in their old bed');

http('POST', "/admin/boarders/{$mistake}/status", $jars['admin'], ['csrf_token' => $token, 'status' => 'bogus']);
check($q("SELECT status FROM boarder_profiles WHERE user_id = {$mistake}") === 'pending', 'invalid status rejected');
try {
    $pdo->exec("UPDATE boarder_profiles SET status = 'bogus' WHERE user_id = {$mistake}");
    check(false, 'strict SQL mode rejects bad ENUM values');
} catch (PDOException $e) {
    check(true, 'strict SQL mode rejects bad ENUM values');
}

http('POST', '/admin/beds', $jars['admin'], ['csrf_token' => $token, 'room_id' => $roomId, 'label' => 'Bed C']);
check((int) $q("SELECT COUNT(*) FROM beds WHERE room_id = {$roomId}") === 2, 'cannot add beds beyond room capacity');

http('POST', "/admin/boarders/{$mistake}/delete", $jars['admin'], ['csrf_token' => $token]);
check($q("SELECT COUNT(*) FROM users WHERE id = {$mistake}") == 0, 'boarder without payment history is deleted');
check($q("SELECT status FROM beds WHERE id = {$bedB}") === 'vacant', 'their bed is freed');

$paymentsBefore = (int) $q('SELECT COUNT(*) FROM payments WHERE boarder_id = 3');
http('POST', '/admin/boarders/3/delete', $jars['admin'], ['csrf_token' => $token]);
check($q('SELECT status FROM users WHERE id = 3') === 'archived', 'boarder with payment history is archived, not deleted');
check((int) $q('SELECT COUNT(*) FROM payments WHERE boarder_id = 3') === $paymentsBefore && $paymentsBefore > 0, 'their payments are kept');
check($q('SELECT status FROM boarder_profiles WHERE user_id = 3') === 'moved_out' && $q('SELECT bed_id FROM boarder_profiles WHERE user_id = 3') === null, 'archived boarder is moved out with no bed');
check(!str_contains(http('GET', '/admin/boarders', $jars['admin'])['body'], 'boarder@rjm.test'), 'hidden from the resident list');
check(str_contains(http('GET', '/admin/boarders?archived=1', $jars['admin'])['body'], 'boarder@rjm.test'), 'listed under Show archived');
check(http('GET', '/portal/dashboard', loginAs('boarder@rjm.test', 'BoarderPass123!'))['code'] === 302, 'archived boarder cannot log in');
http('POST', '/admin/boarders/3/restore', $jars['admin'], ['csrf_token' => $token]);
check($q('SELECT status FROM users WHERE id = 3') === 'active', 'restore re-enables the account');

echo "== Inquiries (H10) ==\n";
$inq = fn (array $f) => http('POST', '/inquire', null, $f + ['name' => 'Prospect', 'phone' => '0917 123 4567', 'room_type' => 'Solo']);
$r = $inq(['message' => str_repeat('a', 300)]);
check($r['code'] === 302 && (int) $q("SELECT COUNT(*) FROM inquiries WHERE name = 'Prospect'") === 1, 'website inquiry stored in the inquiries table');
check((int) $q("SELECT COUNT(*) FROM notifications WHERE type = 'inquiry' AND message LIKE '%Prospect%'") >= 1, 'admins notified');
$inq(['website' => 'http://spam.example']);
check((int) $q("SELECT COUNT(*) FROM inquiries WHERE name = 'Prospect'") === 1, 'honeypot submissions are dropped');
$inq(['phone' => 'call me']);
check((int) $q("SELECT COUNT(*) FROM inquiries WHERE name = 'Prospect'") === 1, 'invalid phone rejected');
for ($i = 0; $i < 6; $i++) {
    $inq([]);
}
check((int) $q("SELECT COUNT(*) FROM inquiries WHERE name = 'Prospect'") === 5, 'at most 5 website inquiries per IP per hour');
$token = csrfFrom(http('GET', '/admin/inquiry-center', $jars['staff'])['body']);
http('POST', '/admin/inquiry-center/submit', $jars['staff'], ['csrf_token' => $token, 'name' => 'Walk In', 'phone' => '09171234567']);
check($q("SELECT source FROM inquiries WHERE name = 'Walk In'") === 'staff', 'staff-logged inquiry stored (not rate limited)');

echo "== Removed features stay removed; live score preview (B6) ==\n";
check(http('GET', '/qr/' . str_repeat('a', 64), null)['code'] === 404, 'unused QR approval routes are gone');
$token = csrfFrom(http('GET', '/portal/maintenance/new', $jars['boarder'])['body']);
$preview = json_decode(http('POST', '/api/maintenance/score-preview', $jars['boarder'],
    ['csrf_token' => $token, 'description' => 'Outlet sparking', 'category' => 'electrical'])['body'], true);
check(($preview['tier'] ?? null) === 'high', 'preview uses the server scoring (sparking outlet = high)');
check(http('POST', '/api/maintenance/score-preview', $jars['staff'], ['csrf_token' => $token])['code'] === 403, 'preview is boarder-only');

echo "== Removed SSE stream; AI endpoints guarded (M2, M18) ==\n";
check(http('GET', '/api/notifications/stream', $jars['admin'])['code'] === 404, 'stream endpoint gone');
check(http('POST', '/api/assistant/summarize-queue', $jars['staff'], [])['code'] !== 200, 'summarize-queue now requires the CSRF token');

echo "== Staff accounts page (decision 7b) ==\n";
check(http('GET', '/admin/staff', $jars['admin'])['code'] === 200, 'admin opens Staff Accounts');
check(http('GET', '/admin/staff', $jars['staff'])['code'] === 403, 'staff cannot manage staff');
$token = csrfFrom(http('GET', '/admin/staff', $jars['admin'])['body']);
http('POST', '/admin/staff', $jars['admin'], ['csrf_token' => $token, 'name' => 'New Staff', 'email' => 'newstaff@rjm.test', 'password' => 'StaffTemp123']);
$newStaff = (int) $q("SELECT id FROM users WHERE email = 'newstaff@rjm.test' AND role = 'staff'");
check($newStaff > 0, 'admin adds a staff member');
$staffJar = loginAs('newstaff@rjm.test', 'StaffTemp123');
check(http('GET', '/staff/dashboard', $staffJar)['code'] === 200, 'new staff member can log in');
http('POST', "/admin/staff/{$newStaff}/status", $jars['admin'], ['csrf_token' => $token, 'status' => 'inactive']);
check(http('GET', '/staff/dashboard', $staffJar)['code'] === 302, 'deactivating signs them out');
check(http('GET', '/staff/dashboard', loginAs('newstaff@rjm.test', 'StaffTemp123'))['code'] === 302, 'deactivated staff cannot log in');
http('POST', "/admin/staff/{$newStaff}/status", $jars['admin'], ['csrf_token' => $token, 'status' => 'active']);
check($q("SELECT status FROM users WHERE id = {$newStaff}") === 'active', 'reactivation works');
http('POST', '/admin/staff/1/status', $jars['admin'], ['csrf_token' => $token, 'status' => 'inactive']);
check($q('SELECT status FROM users WHERE id = 1') === 'active', 'cannot deactivate a non-staff account from this page');

echo "== Receipts are private (M12) ==\n";
$receiptPath = (string) $q("SELECT proof_path FROM payments WHERE boarder_id = 3 AND proof_path IS NOT NULL ORDER BY id DESC LIMIT 1");
check(str_starts_with($receiptPath, '/uploads/receipts/') && !is_file(__DIR__ . '/../public' . $receiptPath), 'receipt stored outside the web root');
$ownerJar = loginAs('boarder@rjm.test', 'BoarderPass123!');
check(http('GET', $receiptPath, $ownerJar)['code'] === 200, 'the boarder who paid can view it');
check(http('GET', $receiptPath, $jars['admin'])['code'] === 200, 'admins can view it');
check(http('GET', $receiptPath, $jars['staff'])['code'] === 404, 'staff cannot view receipts');
check(http('GET', $receiptPath, loginAs('holder@rjm.test', 'Password123'))['code'] === 404, 'another boarder cannot view it');
check(http('GET', $receiptPath, null)['code'] === 302, 'anonymous visitors are sent to login');
check(http('GET', '/uploads/receipts/..%2F..%2F.env', $jars['admin'])['code'] === 404, 'path tricks are refused');

echo "== Small fixes (L1, decision 8) ==\n";
check(http('HEAD', '/login', null)['code'] === 200, 'HEAD /login works');
$home = http('GET', '/', null)['body'];
check(!str_contains($home, '99.9%') && !str_contains($home, '< 15 mins'), 'landing shows no invented figures');

echo "== App and database clocks agree (H3) ==\n";
check($pdo->query("SELECT DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i')")->fetchColumn() === date('Y-m-d H:i'), 'NOW() matches PHP date()');

echo "== Security headers ==\n";
$h = http('GET', '/login', null)['headers'];
foreach (['X-Frame-Options: DENY', 'X-Content-Type-Options: nosniff', 'Content-Security-Policy:', 'Referrer-Policy:'] as $needle) {
    check(stripos($h, $needle) !== false, "header {$needle}");
}
check(stripos($h, 'X-Powered-By') === false, 'X-Powered-By hidden');
check(http('GET', '/logout', $jars['admin'])['code'] === 404, 'GET /logout no longer logs out');

echo "== Assistant answers with pages the role may open (plan A0/A1) ==\n";
$ask = function (string $role, array $body, bool $withToken = true) use ($jars, $base): array {
    if ($withToken) {
        $body['csrf_token'] = csrfFrom(http('GET', '/profile', $jars[$role])['body']);
    }
    $ch = curl_init($base . '/api/assistant/ask');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_COOKIEFILE => $jars[$role], CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($body)]);
    $json = json_decode((string) curl_exec($ch), true);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ['code' => $code, 'json' => is_array($json) ? $json : []];
};
$r = $ask('boarder', ['message' => 'where do I pay rent?']);
check($r['code'] === 200 && ($r['json']['actions'][0] ?? null) === ['label' => 'Open Pay Rent', 'href' => '/portal/payments/new'],
    'boarder "where do I pay rent?" -> Open Pay Rent button');
$r = $ask('boarder', ['message' => 'saan ako magbabayad ng renta', 'lang' => 'tl']);
check(($r['json']['actions'][0]['label'] ?? '') === 'Buksan ang Pay Rent' && str_contains($r['json']['reply'] ?? '', 'resibo'), 'Tagalog setting answers in Tagalog');
$r = $ask('staff', ['message' => 'open the maintenance queue']);
check(($r['json']['actions'][0]['href'] ?? '') === '/staff/maintenance', 'staff "maintenance queue" -> /staff/maintenance');
$r = $ask('admin', ['message' => 'show flagged payments']);
check(($r['json']['actions'][0]['href'] ?? '') === '/admin/payments', 'admin "flagged payments" -> /admin/payments');
$r = $ask('boarder', ['message' => 'payments expenses boarders staff accounts rooms occupancy maintenance queue']);
$hrefs = array_column($r['json']['actions'] ?? [], 'href');
check($r['code'] === 200 && $hrefs && !array_filter($hrefs, fn ($h) => http('GET', $h, $jars['boarder'])['code'] !== 200),
    'every page offered to a boarder opens for a boarder (' . implode(', ', $hrefs) . ')');
foreach ([['boarder', 'how much do I owe?'], ['staff', 'how many repairs are open?'], ['admin', 'who owes the most?'], ['admin', 'which beds are vacant?'], ['admin', 'export the ledger']] as [$role, $question]) {
    $r = $ask($role, ['message' => $question]);
    $hrefs = array_column($r['json']['actions'] ?? [], 'href');
    check(($r['json']['source'] ?? '') === 'data' && $hrefs && !array_filter($hrefs, fn ($h) => http('GET', $h, $jars[$role])['code'] !== 200),
        "{$role} \"{$question}\" -> figures, and every button opens: " . ($r['json']['reply'] ?? ''));
}
check(($ask('staff', ['message' => 'who owes the most? maintenance history'])['json']['source'] ?? '') === 'pages', 'staff asking for admin figures only gets a staff page');
check($ask('boarder', ['message' => 'pay rent'], false)['code'] === 403, 'ask requires the CSRF token');
check($ask('boarder', ['message' => '  '])['code'] === 400, 'ask rejects an empty message');
check(http('POST', '/api/assistant/ask', null, [])['code'] === 302, 'ask requires a login');
check(str_contains(http('GET', '/portal/dashboard', $jars['boarder'])['body'], 'href="/portal/payments/new"'), 'sidebar still links Pay Rent for boarders');

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
