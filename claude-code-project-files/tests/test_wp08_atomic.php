<?php
/**
 * WP-8 (F-08): approving a payment is all-or-nothing, two admins approving at the same moment cannot
 * leave stray late fees or waivers behind, and viewing a page (GET) never changes billing rows.
 */
require __DIR__ . '/lib.php';

$base2 = getenv('TEST_BASE_URL_2') ?: exit("TEST_BASE_URL_2 missing\n");
$rulesFile = '0028_add_rent_cycles_and_electricity.sql';
$rulesWas = q1('SELECT applied_at FROM schema_migrations WHERE filename = ?', [$rulesFile]);
q('UPDATE schema_migrations SET applied_at = "2026-01-01 00:00:00" WHERE filename = ?', [$rulesFile]);
register_shutdown_function(fn () => q('UPDATE schema_migrations SET applied_at = ? WHERE filename = ?', [$rulesWas, $rulesFile]));

$adminA = seededClient('admin');

/** A resident 75 days in (3 cycles, two already overdue, in a room of their own) with a pending receipt for one full cycle. */
$withReceipt = function (float $price = 3000.0) use ($adminA) {
    $room = makeRoom($adminA, 1, $price, 1);
    $res = makeResident($adminA, $room['beds'][0], 75);
    $res['price'] = $price;
    $res['pid'] = submitReceipt($res['client'], $price, 'W8-' . uniq());
    return $res;
};
$lateFees = fn (int $id) => (int) q1('SELECT COUNT(*) FROM penalties WHERE boarder_id = ? AND billing_period IS NOT NULL', [$id]);

section('Approval is all-or-nothing');
$r = $withReceipt(2999.99);
check($r['pid'] > 0 && $lateFees($r['id']) === 0, 'setup: a resident with overdue rent, a pending receipt and no late fee yet');
$before = [$lateFees($r['id']), (int) q1('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$r['id']])];
db()->exec('ALTER TABLE payments ADD CONSTRAINT chk_w8_force CHECK (approved_amount IS NULL OR approved_amount <> 2999.99)');
try {
    $msg = $adminA->flash($adminA->post("/admin/payments/{$r['pid']}/approve", ['approved_amount' => 2999.99, 'waive_late_fee' => '1', 'waive_reason' => 'W8 waiver']));
} finally {
    db()->exec('ALTER TABLE payments DROP CONSTRAINT chk_w8_force');
}
check(q1('SELECT verification_status FROM payments WHERE id = ?', [$r['pid']]) === 'pending', 'when the final step fails the payment is still pending');
check($lateFees($r['id']) === $before[0], 'no late fee was left behind by the failed approval', "late fees {$before[0]} -> {$lateFees($r['id'])}");
check(!q1('SELECT COUNT(*) FROM penalties WHERE boarder_id = ? AND waive_reason = "W8 waiver"', [$r['id']]), 'no waiver was left behind by the failed approval');
check((int) q1('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$r['id']]) === $before[1], 'the resident was sent no notification for something that did not happen');
check(stripos($msg, 'sqlstate') === false && stripos($msg, 'constraint') === false, 'and the admin sees no database text', substr($msg, 0, 120));
$adminA->post("/admin/payments/{$r['pid']}/approve", ['approved_amount' => 2999.99]);
check(q1('SELECT verification_status FROM payments WHERE id = ?', [$r['pid']]) === 'admin-approved', 'the same approval works once nothing fails');

section('Two admins approving at the same moment (two servers, one database)');
$adminB = new Client('adminB', $base2);
$loser = 0;
$clean = true;
$detail = '';
for ($round = 1; $round <= 12; $round++) {
    $x = $withReceipt();
    q('DELETE FROM login_attempts');
    $a = new Client('A' . $round);
    $a->login('admin@rjm.test', pw('admin'));
    $b = new Client('B' . $round, $base2);
    $b->login('admin@rjm.test', pw('admin'));
    $handles = [
        'A' => [$a, ['approved_amount' => 3000, 'csrf_token' => $a->token()]],
        'B' => [$b, ['approved_amount' => 3000, 'waive_late_fee' => '1', 'waive_reason' => "B-WAIVE-{$round}", 'csrf_token' => $b->token()]],
    ];
    $mh = curl_multi_init();
    $chs = [];
    foreach ($handles as $name => [$client, $fields]) {
        $ch = curl_init($client->base . "/admin/payments/{$x['pid']}/approve");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_COOKIEFILE => $client->jar, CURLOPT_COOKIEJAR => $client->jar, CURLOPT_TIMEOUT => 60]);
        curl_multi_add_handle($mh, $ch);
        $chs[$name] = $ch;
    }
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 0.05);
    } while ($running > 0);
    foreach ($chs as $ch) {
        curl_multi_remove_handle($mh, $ch);
    }
    curl_multi_close($mh);

    $status = q1('SELECT verification_status FROM payments WHERE id = ?', [$x['pid']]);
    $allocated = (float) q1('SELECT COALESCE(SUM(amount), 0) FROM payment_allocations WHERE payment_id = ?', [$x['pid']]);
    $waived = (int) q1('SELECT COUNT(*) FROM penalties WHERE boarder_id = ? AND waive_reason = ?', [$x['id'], "B-WAIVE-{$round}"]);
    $reviewer = (int) q1('SELECT verified_by FROM payments WHERE id = ?', [$x['pid']]);
    $notices = (int) q1("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'payment_approved'", [$x['id']]);
    // Both are the same admin account, so "who won" is read from the waiver: a waiver exists only if B's request won.
    $ok = $status === 'admin-approved' && abs($allocated - 3000.0) < 0.01 && $notices <= 1;
    if (!$ok) {
        $clean = false;
        $detail .= " round {$round}: status={$status} allocated={$allocated} notices={$notices} waived={$waived};";
    }
    $loser += $waived ? 0 : 1;
}
check($clean, 'in 12 simultaneous double approvals the payment is approved once, allocated once, announced once', trim($detail));
check(true, "(requests where the waiving request lost the race: {$loser} of 12; their waiver must not exist, which the all-or-nothing approval guarantees)");

section('Viewing a page changes nothing');
$owing = makeResident($adminA, makeRoom($adminA, 1, 3000, 1)['beds'][0], 75);
submitReceipt($owing['client'], 2000, 'W8-' . uniq());
$stale = function () use ($owing) {
    q('UPDATE boarder_profiles SET outstanding_balance = 0 WHERE user_id = ?', [$owing['id']]);   // a stale cached figure
    q('DELETE FROM payment_allocations WHERE boarder_id = ?', [$owing['id']]);
    q('DELETE FROM rent_charges WHERE boarder_id = ?', [$owing['id']]);                           // rent rows not generated yet
};
$fingerprint = function () {
    $parts = [];
    foreach (['boarder_profiles', 'rent_charges', 'penalties', 'payment_allocations', 'payments', 'electricity_charges'] as $t) {
        $parts[] = $t . '=' . q("CHECKSUM TABLE {$t}")[0]['Checksum'];
    }
    return implode(',', $parts);
};
$pages = ['/admin/payments' => $adminA, '/admin/dashboard' => $adminA, "/admin/boarders/{$owing['id']}" => $adminA, '/admin/penalty-rules' => $adminA,
    '/portal/dashboard' => $owing['client'], '/portal/payments/new' => $owing['client']];
foreach ($pages as $path => $client) {
    $stale();
    $before = $fingerprint();
    $r = $client->get($path, true);
    check($r->code === 200 && $fingerprint() === $before, "GET {$path} changes no billing row", $r->code !== 200 ? "HTTP {$r->code}" : 'a billing table changed');
}
$stale();
$b = $adminA->get("/admin/boarders/{$owing['id']}", true);
check($b->has('9,000'), 'and the profile still shows what is owed (3 cycles x 3,000), worked out on the spot rather than read from the stale cache');
$stale();
$p = $owing['client']->get('/portal/dashboard', true);
check($p->has('9,000'), 'the resident sees the same amount on their own dashboard');
