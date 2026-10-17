<?php
/**
 * WP-6 (F-13): an admin can record a cash / walk-in payment (staff cannot: owner decision, 15 Oct). It must count exactly
 * like an approved online payment (balance, allocations, "collected this month", ledger), get a
 * unique printable receipt, be refused when the numbers are wrong, and leave a trail.
 */
require __DIR__ . '/lib.php';

use App\Services\BillingService;

$base2 = getenv('TEST_BASE_URL_2') ?: exit("TEST_BASE_URL_2 missing\n");
// Rent cycles only count from this migration's date; push it back so a resident 75 days in has 3 cycles.
$rulesFile = '0028_add_rent_cycles_and_electricity.sql';
$rulesWas = q1('SELECT applied_at FROM schema_migrations WHERE filename = ?', [$rulesFile]);
q('UPDATE schema_migrations SET applied_at = "2026-01-01 00:00:00" WHERE filename = ?', [$rulesFile]);
register_shutdown_function(fn () => q('UPDATE schema_migrations SET applied_at = ? WHERE filename = ?', [$rulesWas, $rulesFile]));

$admin = seededClient('admin');
$staff = seededClient('staff');
$adminId = (int) q1('SELECT id FROM users WHERE email = "admin@rjm.test"');
$staffId = (int) q1('SELECT id FROM users WHERE email = "staff@rjm.test"');

/** A resident 75 days in, alone in a room: 3 cycles, the first two overdue. */
$resident = function (float $price = 3000.0) use ($admin) {
    $room = makeRoom($admin, 1, $price, 1);
    return makeResident($admin, $room['beds'][0], 75);
};
// http_build_query, not curl's array encoding, so a field like amount[]=1 really arrives as an array.
$record = fn (Client $by, array $f) => $by->req('POST', '/admin/payments/record',
    http_build_query($f + ['payment_method' => 'cash', 'received_on' => date('Y-m-d'), 'csrf_token' => $by->token()]));
$paymentRows = fn (int $boarderId) => (int) q1('SELECT COUNT(*) FROM payments WHERE boarder_id = ?', [$boarderId]);
$outstanding = fn (int $id) => (float) BillingService::calculateBalance($id, null, true)['total_outstanding'];
$collected = fn () => (float) BillingService::collectionsSummary()['collected_this_month'];
$allocs = fn (int $id) => array_map(fn ($r) => $r['allocation_type'] . ':' . $r['amount'],
    q('SELECT allocation_type, amount FROM payment_allocations WHERE boarder_id = ? ORDER BY id', [$id]));

section('Who may record a payment');
$r = $resident();
$res = $r['client']->get('/admin/payments/record');
check($res->code === 403 || $res->code === 302, 'a resident cannot open the record-payment form', "HTTP {$res->code}");
$before = $paymentRows($r['id']);
$r['client']->post('/admin/payments/record', ['boarder_id' => $r['id'], 'amount' => '500', 'payment_method' => 'cash', 'received_on' => date('Y-m-d')]);
check($paymentRows($r['id']) === $before, 'a resident cannot record a payment for themselves');
$res = $admin->get('/admin/payments/record');
check($res->code === 200 && $res->has('Record'), 'the admin opens the form', "HTTP {$res->code}");
// Owner, 15 Oct: money is admin-only. Staff may not record payments, so the form and the POST are closed to them.
$res = $staff->get('/admin/payments/record');
check($res->code === 403, 'staff cannot open the form', "HTTP {$res->code}");
$staffTarget = $resident();
$staff->req('POST', '/admin/payments/record', http_build_query(['csrf_token' => $staff->token(), 'boarder_id' => $staffTarget['id'], 'amount' => '500', 'payment_method' => 'cash', 'received_on' => date('Y-m-d')]));
check($paymentRows($staffTarget['id']) === 0, 'staff cannot record a payment');
check($staff->get('/admin/payments')->code !== 200, 'staff still cannot open the payments review page');

section('An admin records a cash payment');
$online = $resident();
$cash = $resident();
check(abs($outstanding($online['id']) - $outstanding($cash['id'])) < 0.005 && $outstanding($cash['id']) > 0, 'setup: two identical residents who owe the same amount', (string) $outstanding($cash['id']));

// the online reference: receipt -> admin approves 3000
$pid = submitReceipt($online['client'], 3000.0, 'WP6-' . uniq());
$c0 = $collected();
$admin->post("/admin/payments/{$pid}/approve", ['approved_amount' => 3000]);
$c1 = $collected();

$c2 = $collected();
$record($admin, ['boarder_id' => $cash['id'], 'amount' => '3000', 'reference_number' => 'BOOK-' . uniq()]);
$c3 = $collected();
$row = q('SELECT * FROM payments WHERE boarder_id = ? ORDER BY id DESC LIMIT 1', [$cash['id']])[0] ?? [];
check(($row['payment_method'] ?? '') === 'cash' && ($row['verification_status'] ?? '') === 'admin-approved', 'the payment is stored as cash and approved at once', json_encode($row));
check((float) ($row['approved_amount'] ?? 0) === 3000.0 && (int) ($row['received_by'] ?? 0) === $adminId && ($row['recorded_by_role'] ?? '') === 'admin', 'amount, who received it and their role are on the record');
check(($row['received_on'] ?? '') === date('Y-m-d'), 'the date received is on the record');
check(preg_match('/^RJM-\d{6}$/', (string) ($row['receipt_number'] ?? '')) === 1, 'it has an issued receipt number', (string) ($row['receipt_number'] ?? ''));
check(abs($outstanding($online['id']) - $outstanding($cash['id'])) < 0.005, 'balance after cash equals balance after the same online payment', $outstanding($online['id']) . ' vs ' . $outstanding($cash['id']));
check($allocs($online['id']) === $allocs($cash['id']) && $allocs($cash['id']) !== [], 'it is allocated oldest-first exactly like the online payment', json_encode([$allocs($online['id']), $allocs($cash['id'])]));
check(abs(($c1 - $c0) - 3000.0) < 0.005 && abs(($c3 - $c2) - 3000.0) < 0.005, 'both add the same to "collected this month"', ($c1 - $c0) . ' vs ' . ($c3 - $c2));
$today = date('Y-m-d');
$csv = $admin->get("/admin/ledger/export?from={$today}&to={$today}")->body;
check(str_contains($csv, '"Payment received",' . ($row['id'] ?? 0) . ',') && str_contains($csv, '"Payment received",' . $pid . ','), 'both appear in the ledger export',
    'cash id ' . ($row['id'] ?? 0) . ', online id ' . $pid . '; csv has ' . substr_count($csv, "\n") . ' lines: ' . substr(preg_replace('/\s+/', ' ', $csv), 0, 300));
$page = $admin->get('/admin/payments')->body;
check(str_contains($page, (string) ($row['receipt_number'] ?? '-')) && stripos($page, 'Cash') !== false, 'the Payments page lists it with its receipt number and method');
$note = q1('SELECT message FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$cash['id']]);
check($note !== null && stripos($note, 'payment') !== false, 'the resident is told the payment was received', (string) $note);

section('Late fees stop at the date the cash was received');
$late = $resident();
$received = date('Y-m-d', strtotime('-40 days'));
$record($admin, ['boarder_id' => $late['id'], 'amount' => '3000', 'received_on' => $received]);
$lateRow = q('SELECT * FROM payments WHERE boarder_id = ? ORDER BY id DESC LIMIT 1', [$late['id']])[0] ?? [];
check(($lateRow['received_on'] ?? '') === $received, 'a payment taken 40 days ago can be entered with that date');
$fee = (float) q1('SELECT COALESCE(SUM(amount), 0) FROM penalties WHERE boarder_id = ? AND billing_period IS NOT NULL', [$late['id']]);
$now = $resident();
$record($admin, ['boarder_id' => $now['id'], 'amount' => '3000']);
$feeNow = (float) q1('SELECT COALESCE(SUM(amount), 0) FROM penalties WHERE boarder_id = ? AND billing_period IS NOT NULL', [$now['id']]);
check($lateRow !== [] && $feeNow > 0 && $fee < $feeNow, 'late fees are counted to the day received, not to today', "fee when received 40 days ago {$fee}, when received today {$feeNow}");

section('Receipt numbers are unique and the receipt prints');
$record($admin, ['boarder_id' => $cash['id'], 'amount' => '500']);
$row2 = q('SELECT * FROM payments WHERE boarder_id = ? ORDER BY id DESC LIMIT 1', [$cash['id']])[0] ?? [];
check(($row2['receipt_number'] ?? '') !== '' && $row2['receipt_number'] !== ($row['receipt_number'] ?? null), 'a second payment gets a different number');
try {
    db()->prepare('UPDATE payments SET receipt_number = ? WHERE id = ?')->execute([$row['receipt_number'] ?? 'x', $row2['id'] ?? 0]);
    $dup = false;
} catch (PDOException $e) {
    $dup = true;
}
check($dup && ($row2['receipt_number'] ?? '') !== '', 'the database itself refuses a duplicate receipt number');
foreach ([['admin', $admin], ['the resident who paid', $cash['client']]] as [$who, $client]) {
    $res = $client->get('/payments/' . ($row['id'] ?? 0) . '/receipt');
    check($res->code === 200 && $res->has((string) $row['receipt_number']) && $res->has('3,000.00'), "the receipt opens for {$who}", "HTTP {$res->code}");
}
$res = $staff->get('/payments/' . ($row['id'] ?? 0) . '/receipt');
check(in_array($res->code, [403, 404], true) && !$res->has((string) $row['receipt_number']), 'staff cannot open a receipt (money is admin-only)', "HTTP {$res->code}");
$res = $online['client']->get('/payments/' . ($row['id'] ?? 0) . '/receipt');
check(in_array($res->code, [403, 404], true), 'another resident cannot open it', "HTTP {$res->code}");
$res = (new Client('anon'))->get('/payments/' . ($row['id'] ?? 0) . '/receipt');
check($res->code !== 200 && !$res->has((string) ($row['receipt_number'] ?? '-')), 'nor can someone not signed in');
$pend = submitReceipt($now['client'], 100.0, 'WP6P-' . uniq());
check($pend > 0 && $admin->get("/payments/{$pend}/receipt")->code === 404, 'a payment still waiting for review has no receipt');

section('A wrongly entered payment can be reversed');
$s = $resident();
$record($admin, ['boarder_id' => $s['id'], 'amount' => '1200']);
$srow = q('SELECT * FROM payments WHERE boarder_id = ? ORDER BY id DESC LIMIT 1', [$s['id']])[0] ?? [];
check(($srow['recorded_by_role'] ?? '') === 'admin' && ($srow['verification_status'] ?? '') === 'admin-approved', 'the entry counts at once');
$fresh = $resident();
$admin->post('/admin/payments/' . ($srow['id'] ?? 0) . '/reject', ['reason' => 'Entered on the wrong resident']);
check(q1('SELECT verification_status FROM payments WHERE id = ?', [$srow['id'] ?? 0]) === 'rejected' && abs($outstanding($s['id']) - $outstanding($fresh['id'])) < 0.005, 'the admin can reverse it, and the balance is recalculated');

section('Bad input is refused and nothing is written');
$v = $resident();
$good = ['boarder_id' => $v['id'], 'amount' => '500'];
$cases = [
    'zero amount' => ['amount' => '0'], 'negative amount' => ['amount' => '-50'], 'over the limit' => ['amount' => '1000001'],
    'text amount' => ['amount' => 'abc'], 'array amount' => ['amount' => ['1']], 'three decimals' => ['amount' => '10.555'],
    'unknown method' => ['payment_method' => 'bitcoin'], 'future date' => ['received_on' => date('Y-m-d', strtotime('+1 day'))],
    'before the resident moved in' => ['received_on' => date('Y-m-d', strtotime('-400 days'))], 'not a date' => ['received_on' => '31/02/2026'],
    'unknown resident' => ['boarder_id' => '99999999'], 'a staff account as resident' => ['boarder_id' => $staffId],
    'reference with symbols' => ['reference_number' => '<script>'], 'reference too long' => ['reference_number' => str_repeat('9', 51)],
];
foreach ($cases as $label => $override) {
    $n = $paymentRows($v['id']);
    $res = $record($admin, $override + $good);
    check($paymentRows($v['id']) === $n && (int) q1('SELECT COUNT(*) FROM payments WHERE boarder_id = ?', [$staffId]) === 0 && $res->code < 500 && $res->code !== 404, "refused: {$label}",
        "HTTP {$res->code}; rows {$n} -> " . $paymentRows($v['id']) . '; ' . json_encode(q('SELECT id, claimed_amount, payment_method FROM payments WHERE boarder_id = ? ORDER BY id DESC LIMIT 1', [$v['id']])));
}
$archived = $resident();
$admin->post("/admin/boarders/{$archived['id']}/delete", ['reason' => 'WP6 test']);
$n = $paymentRows($archived['id']);
$record($admin, ['boarder_id' => $archived['id'], 'amount' => '500']);
check($paymentRows($archived['id']) === $n, 'refused: an archived resident');
$ref = 'DUP-' . uniq();
$record($admin, $good + ['reference_number' => $ref]);
$n = $paymentRows($v['id']);
$record($admin, $good + ['reference_number' => $ref]);
check($n === 1 && $paymentRows($v['id']) === $n, 'refused: the same method and reference twice (a double click or the same slip)', "rows {$n} -> " . $paymentRows($v['id']));
$res = $admin->post('/admin/payments/record', $good + ['csrf_token' => 'wrong'], false);
check($res->code === 400, 'refused: missing or wrong CSRF token', "HTTP {$res->code}");

section('Two payments recorded at the same moment (two servers, one database)');
$race = $resident();
q('DELETE FROM login_attempts');
$a = new Client('A');
$a->login('admin@rjm.test', pw('admin'));
$b = new Client('B', $base2);
$b->login('admin@rjm.test', pw('admin'));
$mh = curl_multi_init();
$handles = [];
foreach (['A' => $a, 'B' => $b] as $k => $cl) {
    $ch = curl_init(($cl->base ?? getenv('TEST_BASE_URL')) . '/admin/payments/record');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cl->jar, CURLOPT_COOKIEFILE => $cl->jar, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['csrf_token' => $cl->token(), 'boarder_id' => $race['id'], 'amount' => 1000, 'payment_method' => 'cash', 'received_on' => date('Y-m-d')]),
        CURLOPT_TIMEOUT => 60]);
    curl_multi_add_handle($mh, $ch);
    $handles[$k] = $ch;
}
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh, 0.2);
} while ($running);
$codes = array_map(fn ($ch) => curl_getinfo($ch, CURLINFO_HTTP_CODE), $handles);
$rows = q('SELECT receipt_number FROM payments WHERE boarder_id = ? AND payment_method = "cash"', [$race['id']]);
check(count($rows) === 2 && count(array_unique(array_column($rows, 'receipt_number'))) === 2, 'both are recorded, each with its own receipt number', json_encode([$codes, $rows]));
$allocated = (float) q1('SELECT COALESCE(SUM(amount), 0) FROM payment_allocations WHERE boarder_id = ?', [$race['id']]);
check(abs($allocated - 2000.0) < 0.005, 'and together they are allocated exactly once (₱2,000)', (string) $allocated);
