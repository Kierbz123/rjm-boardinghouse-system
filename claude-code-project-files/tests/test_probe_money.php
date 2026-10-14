<?php
/**
 * Audit probe 3 (14 Oct 2026): receipts and uploads, approval / reversal, the late-fee engine,
 * penalties and waivers, reminders, ledger and expenses. Open defects are knownDefect().
 */
require __DIR__ . '/lib.php';

$admin = seededClient('admin');
$staff = seededClient('staff');
$anon = new Client('anon');

// The late-fee rules only apply to rent due after migration 0028 went live; move that date back so
// overdue cycles can be tested, and put it back when this file ends.
$rulesFile = '0028_add_rent_cycles_and_electricity.sql';
$rulesWas = q1('SELECT applied_at FROM schema_migrations WHERE filename = ?', [$rulesFile]);
q('UPDATE schema_migrations SET applied_at = "2026-01-01 00:00:00" WHERE filename = ?', [$rulesFile]);
register_shutdown_function(fn () => q('UPDATE schema_migrations SET applied_at = ? WHERE filename = ?', [$rulesWas, $rulesFile]));

$room = makeRoom($admin, 2, 3500, 2);
$res = makeResident($admin, $room['beds'][0], 75);
$B = $res['id'];
$me = $res['client'];
$balance = fn () => (float) q1('SELECT outstanding_balance FROM boarder_profiles WHERE user_id = ?', [$B]);

section('Setup');
check(abs($balance() - 10500.0) < 0.01, 'a resident 75 days in owes 3 cycles x 3,500', 'balance=' . $balance());

section('Receipt upload validation');
$sub = function (array $fields, ?string $file = null, string $name = 'receipt.png', string $mime = 'image/png') use ($me) {
    $fields['csrf_token'] = $me->token();
    if ($file) {
        $fields['proof'] = new CURLFile($file, $mime, $name);
    }
    return $me->req('POST', '/portal/payments', $fields);
};
$good = ['payment_method' => 'gcash', 'reference_number' => 'GC-' . uniq(), 'claimed_amount' => '3500'];
$png = tmpFileWith(png(), 'png');
check(flashHas($me, $sub($good), 'attach a photo'), 'no file attached is refused');
check(flashHas($me, $sub($good, tmpFileWith("<?php echo 'x'; ?>", 'png'), 'shell.png'), 'Unsupported file type'), 'PHP code renamed .png is refused (content is sniffed)');
check(flashHas($me, $sub($good, tmpFileWith('GIF89a' . str_repeat('a', 20), 'png'), 'a.png'), 'Unsupported'), 'a GIF disguised as PNG is refused');
check(flashHas($me, $sub($good, tmpFileWith('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'svg'), 'x.svg', 'image/svg+xml'), 'Unsupported'), 'a script-capable SVG is refused');
foreach ([['payment_method' => 'bitcoin'], ['reference_number' => 'GC<script>'], ['claimed_amount' => '0'], ['claimed_amount' => '-5'], ['claimed_amount' => '9999999'], ['claimed_amount' => 'abc'], ['claimed_amount' => 'INF'], ['claimed_amount' => 'NaN']] as $bad) {
    $sub($bad + $good, $png);
    check((int) q1('SELECT COUNT(*) FROM payments WHERE boarder_id = ?', [$B]) === 0, 'invalid ' . json_encode($bad) . ' is refused');
}
$big = tmpFileWith(png() . str_repeat("\0", 21 * 1024 * 1024), 'png');
check(flashHas($me, $sub($good, $big), 'too large') && (int) q1('SELECT COUNT(*) FROM payments WHERE boarder_id = ?', [$B]) === 0, 'a file over 20 MB is refused with a clear message');
@unlink($big);
$sub($good, $png);
$pid = (int) q1('SELECT id FROM payments WHERE boarder_id = ? ORDER BY id DESC LIMIT 1', [$B]);
check($pid > 0 && q1('SELECT verification_status FROM payments WHERE id = ?', [$pid]) === 'pending', 'a valid receipt is saved as PENDING, never auto-approved');
check(abs($balance() - 10500.0) < 0.01, 'the balance does not move while a receipt is pending');
check(flashHas($me, $sub($good, $png), 'already have a receipt'), 'a second receipt while one is pending is refused');

section('Who may open a receipt');
$proof = q1('SELECT proof_path FROM payments WHERE id = ?', [$pid]);
$other = makeResident($admin);
check($admin->get($proof)->code === 200, 'admin can open it');
check($me->get($proof)->code === 200, 'the owner can open it');
check($staff->get($proof)->code === 404, 'staff cannot (financial record)');
check($anon->get($proof)->code === 302, 'an anonymous visitor cannot');
check($other['client']->get($proof)->code === 404, 'another resident cannot');
check(!file_exists(__DIR__ . '/../public' . $proof), 'receipts are stored outside the web root');

section('Review: approve, allocate, reverse');
check(flashHas($admin, $admin->post("/admin/payments/{$pid}/approve", ['approved_amount' => 9999]), 'no more than'), 'approving MORE than claimed is refused');
check(flashHas($admin, $admin->post("/admin/payments/{$pid}/approve", ['approved_amount' => 0]), 'more than'), 'approving 0 is refused');
check(flashHas($admin, $admin->post("/admin/payments/{$pid}/approve", ['approved_amount' => 2000, 'waive_late_fee' => '1', 'waive_reason' => 'x']), 'give a reason'), 'waiving without a real reason is refused');
$admin->post("/admin/payments/{$pid}/approve", ['approved_amount' => 2000]);
check(q1('SELECT verification_status FROM payments WHERE id = ?', [$pid]) === 'admin-approved' && (float) q1('SELECT approved_amount FROM payments WHERE id = ?', [$pid]) === 2000.0, 'approving LESS than claimed credits only what the receipt shows');
check(flashHas($admin, $admin->post("/admin/payments/{$pid}/approve", ['approved_amount' => 2000]), 'already been reviewed'), 'approving twice is refused');
check(flashHas($admin, $admin->post("/admin/payments/{$pid}/reject", ['reason' => 'x']), 'Give a reason'), 'a rejection needs a reason');
$rej = $admin->post("/admin/payments/{$pid}/reject", ['reason' => 'Receipt unclear, reversing']);
check(q1('SELECT verification_status FROM payments WHERE id = ?', [$pid]) === 'rejected' && !q1('SELECT COUNT(*) FROM payment_allocations WHERE payment_id = ?', [$pid]), 'rejecting an approved payment reverses it and removes its allocations', 'status=' . q1('SELECT verification_status FROM payments WHERE id = ?', [$pid]) . ' HTTP ' . $rej->code . ' ' . $admin->flash($rej));
check(flashHas($admin, $admin->post("/admin/payments/{$pid}/reject", ['reason' => 'again again']), 'already rejected'), 'rejecting twice is refused');
check(flashHas($admin, $admin->post('/admin/payments/999999/approve', ['approved_amount' => 5]), 'not found'), 'an unknown payment id says so');
check(str_contains((string) q1("SELECT message FROM notifications WHERE user_id = ? AND type = 'payment_rejected' ORDER BY id DESC LIMIT 1", [$B]), 'reversed'), 'the resident is told why the approval was reversed');
$pid2 = submitReceipt($me, 3500, 'GC-' . uniq());
$admin->post("/admin/payments/{$pid2}/approve", ['approved_amount' => 3500]);
check(q1('SELECT verification_status FROM payments WHERE id = ?', [$pid2]) === 'admin-approved', 'the resubmitted receipt is approved');
knownDefect('F-13', $admin->get('/admin/payments/record')->code === 200, 'an admin can record a cash / walk-in payment (GET /admin/payments/record)');

section('Late fees, penalties, waivers');
$admin->post('/admin/penalties/run-check');
$late = q('SELECT billing_period, amount FROM penalties WHERE boarder_id = ? AND billing_period IS NOT NULL ORDER BY billing_period', [$B]);
check(count($late) >= 1, 'the late-fee check creates fees for overdue cycles', json_encode($late));
$count1 = count($late);
$admin->post('/admin/penalties/run-check');
check($count1 === (int) q1('SELECT COUNT(*) FROM penalties WHERE boarder_id = ? AND billing_period IS NOT NULL', [$B]), 'running the check twice never duplicates a fee');
$ruleName = 'Damage ' . uniq();
check(flashHas($admin, $admin->post('/admin/penalty-rules', ['name' => $ruleName, 'condition_type' => 'flat_damage', 'amount' => 500]), 'created'), 'create a custom rule');
$rule = (int) q1('SELECT id FROM penalty_rules WHERE name = ?', [$ruleName]);
check(flashHas($admin, $admin->post('/admin/penalty-rules', ['name' => 'Bad', 'condition_type' => 'x', 'amount' => 0]), 'more than'), 'a rule amount of 0 is refused');
$admin->post("/admin/penalty-rules/{$rule}/amount", ['amount' => 750]);
check((float) q1('SELECT amount FROM penalty_rules WHERE id = ?', [$rule]) === 750.0, 'edit a rule amount');
$due = date('Y-m-d', strtotime('+10 days'));
$admin->post('/admin/penalties/issue', ['boarder_id' => $B, 'rule_id' => $rule, 'amount' => 123.45, 'reason' => 'Broken chair', 'due_date' => $due]);
$manual = q('SELECT id, amount FROM penalties WHERE boarder_id = ? AND reason = "Broken chair"', [$B])[0] ?? null;
check($manual && (float) $manual['amount'] === 123.45, 'an admin issues a manual penalty with a custom amount');
$staff->post('/staff/penalties/issue', ['boarder_id' => $B, 'rule_id' => $rule, 'amount' => 1, 'reason' => 'Staff issued', 'due_date' => $due]);
check((float) q1('SELECT amount FROM penalties WHERE boarder_id = ? AND reason = "Staff issued"', [$B]) === 750.0, 'a staff-issued penalty uses the rule amount, not the typed one');
check(flashHas($admin, $admin->post('/admin/penalties/issue', ['boarder_id' => 1, 'rule_id' => $rule, 'amount' => 5, 'due_date' => '2030-01-01']), 'not found'), 'a penalty for an admin id is refused');
check(flashHas($admin, $admin->post('/admin/penalties/issue', ['boarder_id' => $B, 'rule_id' => $rule, 'amount' => 5, 'due_date' => '2030-02-31']), 'Invalid due date'), 'an impossible due date is refused');
check(flashHas($admin, $admin->post('/admin/penalties/issue', ['boarder_id' => $B, 'rule_id' => $rule, 'amount' => 5, 'reason' => str_repeat('r', 300)]), '255'), 'a reason over 255 characters is refused');
$admin->post("/admin/penalty-rules/{$rule}/toggle", ['active' => '0']);
check(flashHas($admin, $admin->post('/admin/penalties/issue', ['boarder_id' => $B, 'rule_id' => $rule, 'amount' => 5]), 'deactivated'), 'a deactivated rule cannot be used');
$admin->post("/admin/penalty-rules/{$rule}/toggle", ['active' => '1']);
$admin->post("/admin/penalties/{$manual['id']}/mark-paid");
$wr = q('SELECT status, waive_reason FROM penalties WHERE id = ?', [$manual['id']])[0];
check($wr['status'] === 'paid', '"Mark paid" settles the penalty');
knownDefect('F-06', !empty($wr['waive_reason']), 'settling a penalty without payment records a reason', 'waive_reason=' . var_export($wr['waive_reason'], true));
check(flashHas($admin, $admin->post("/admin/penalties/{$manual['id']}/mark-paid"), 'already'), 'settling twice is refused');
$admin->post('/admin/notifications/rent-due');
$n1 = (int) q1('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = "rent_due"', [$B]);
$admin->post('/admin/notifications/rent-due');
$n2 = (int) q1('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = "rent_due"', [$B]);
knownDefect('F-15', $n1 === $n2, 'pressing "send rent-due reminders" twice does not send duplicates', "{$n1} -> {$n2}");

section('Ledger and expenses');
check(flashHas($admin, $admin->post('/admin/expenses', ['category' => 'Repairs', 'amount' => 1500.5, 'description' => '=HYPERLINK("http://evil","x")']), 'recorded'), 'record an expense');
check(flashHas($admin, $admin->post('/admin/expenses', ['category' => '', 'amount' => 5]), 'Category is required'), 'an empty category is refused');
check(flashHas($admin, $admin->post('/admin/expenses', ['category' => 'X', 'amount' => -5]), 'greater than zero'), 'a negative amount is refused');
check(flashHas($admin, $admin->post('/admin/expenses', ['category' => 'X', 'amount' => 99999999]), 'greater than zero'), 'an amount above 10,000,000 is refused');
$admin->post('/admin/expenses', ['category' => '=1+1', 'amount' => 5, 'description' => '@SUM(A1)']);
$from = date('Y-m-01');
$csv = $admin->get("/admin/ledger/export?from={$from}&to=" . date('Y-m-d'));
check($csv->code === 200 && str_contains($csv->headers['content-type'] ?? '', 'text/csv') && str_starts_with($csv->body, "\xEF\xBB\xBF"), 'the ledger exports a CSV with a byte-order mark');
check(str_contains($csv->body, "'=1+1"), 'formula-looking text is neutralised in the CSV');
check($staff->get('/admin/ledger/export')->code === 403, 'staff cannot export the ledger');
check($admin->get('/admin/ledger/export?from=garbage&to=also')->code === 200, 'garbage dates fall back safely');
preg_match('/"TOTAL PAYMENTS RECEIVED",,,,,([0-9.]+)/', $csv->body, $m);
$sum = (float) q1("SELECT COALESCE(SUM(COALESCE(approved_amount, claimed_amount)), 0) FROM payments WHERE verification_status IN ('admin-approved','auto-matched') AND created_at >= ?", [$from]);
check(isset($m[1]) && abs((float) $m[1] - $sum) < 0.01, 'the ledger payment total equals the approved payments in the period', 'csv=' . ($m[1] ?? '?') . " db={$sum}");

// A receipt sent 40 days ago but approved today: the ledger and the dashboard must agree about this month.
$old = makeResident($admin, null, 40);
$oldPid = submitReceipt($old['client'], 1000, 'GC-' . uniq());
db()->prepare('UPDATE payments SET created_at = DATE_SUB(created_at, INTERVAL 40 DAY) WHERE id = ?')->execute([$oldPid]);
$admin->post("/admin/payments/{$oldPid}/approve", ['approved_amount' => 1000]);
$csv = $admin->get("/admin/ledger/export?from={$from}&to=" . date('Y-m-d'));
preg_match('/"TOTAL PAYMENTS RECEIVED",,,,,([0-9.]+)/', $csv->body, $m);
$collected = \App\Services\BillingService::collectionsSummary()['collected_this_month'];
knownDefect('F-11', isset($m[1]) && abs((float) $m[1] - $collected) < 0.01, 'the ledger and the dashboard agree on what was collected this month', 'ledger=' . ($m[1] ?? '?') . " dashboard={$collected}");
preg_match('/"PENALTIES CHARGED \(receivable\)",,,,,([0-9.]+)/', $csv->body, $m2);
$unpaid = (float) q1("SELECT COALESCE(SUM(amount), 0) FROM penalties WHERE status = 'unpaid' AND applied_at >= ?", [$from]);
knownDefect('F-11', isset($m2[1]) && abs((float) $m2[1] - $unpaid) < 0.01, 'the ledger "receivable" counts only penalties still unpaid', 'ledger=' . ($m2[1] ?? '?') . " unpaid={$unpaid}");

// F-32 (found while porting this probe): the CSV's Content-Length ignores the 3-byte BOM, so clients cut the last total.
$tail = $admin->get("/admin/ledger/export?from={$from}&to=" . date('Y-m-d'));
knownDefect('F-32', (bool) preg_match('/"PENALTIES CHARGED \(receivable\)",,,,,\d+\.\d\d\s*$/', $tail->body), 'the last line of the ledger CSV arrives complete (two decimals)', 'ends with: ' . substr(trim($tail->body), -12));
