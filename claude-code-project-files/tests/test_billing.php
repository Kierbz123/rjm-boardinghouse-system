<?php
/**
 * Billing rules (owner decisions, Oct 2026):
 *  - rent is charged in 30-day cycles counted from the move-in date; unpaid cycles carry over
 *  - each cycle is due 30 days after it starts; a cycle cut short by a move-out is prorated by days
 *  - a room's electricity bill is split equally among its residents and falls due with their rent
 *  - payments always need admin approval and apply oldest-first: rent, then electricity, then penalties
 *  - late fees: one per boarder per rule per cycle, never stacked by re-running the check
 * Runs against the throwaway test database via tests/run.php.
 */

require_once __DIR__ . '/../src/autoload.php';

use App\Database;
use App\Models\User;
use App\Models\Room;
use App\Models\BoarderProfile;
use App\Models\ElectricityBill;
use App\Models\Payment;
use App\Models\Penalty;
use App\Models\PenaltyRule;
use App\Services\BillingService;
use App\Services\PenaltyEngine;

if (!str_ends_with((string) getenv('DB_NAME'), '_test')) {
    exit("Refusing to run outside the test database. Use tests/run.php\n");
}

$failures = 0;
function check($actual, $expected, string $label): void
{
    global $failures;
    $ok = is_float($expected) ? abs((float) $actual - $expected) < 0.005 : $actual === $expected;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . ($ok ? '' : ' — expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)) . "\n";
    if (!$ok) {
        $failures++;
    }
}

$pdo = Database::getConnection();
$adminId = (int) $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
$rate = 3000.00;
$roomId = Room::create('B-' . mt_rand(1000, 9999), '2', 1, $rate);

$today = new DateTimeImmutable('today');
$key = fn (DateTimeImmutable $d) => $d->format('Y-m-d');
$newResident = function (string $moveIn, ?int $room = null) use ($pdo, $roomId, $adminId): int {
    $id = User::create('boarder', 'Billing Tester', 'billing-' . mt_rand() . '@rjm.test', 'Secret123!');
    BoarderProfile::create($id, $room ?? $roomId, null);
    $pdo->prepare('UPDATE boarder_profiles SET move_in_date = ? WHERE user_id = ?')->execute([$moveIn, $id]);
    BoarderProfile::updateStatus($id, 'active', $adminId, 'test');
    return $id;
};

// Moved in 40 days ago: the first 30-day cycle has ended, the second is running.
$moveIn = $today->modify('-40 days');
$first = $key($moveIn);
$second = $key($moveIn->modify('+30 days'));
$boarderId = $newResident($first);

echo "== Rent runs in 30-day cycles from the move-in date; unpaid cycles carry over ==\n";
$b = BillingService::calculateBalance($boarderId);
check(array_keys($b['unpaid_rent']), [$first, $second], 'one charge per cycle started, named by its first day');
check($b['unpaid_rent'][$first] ?? null, $rate, 'the first cycle is charged in full: it starts on the move-in day');
check($b['unpaid_rent'][$second] ?? null, $rate, 'the running cycle is charged in full');
check($b['total_outstanding'], 2 * $rate, 'both cycles owed');

echo "== Rent is due every 30 days, counted from move-in ==\n";
$due = fn (string $period) => BillingService::dueDate($period)->format('Y-m-d');
check($due('2026-10-20'), '2026-11-19', 'moved in 20 Oct: the first rent is due 19 Nov');
check($due('2026-11-19'), '2026-12-19', 'the next one 30 days later, 19 Dec');
check($due('2026-12-19'), '2027-01-18', 'then 18 Jan: every 30 days, not a fixed day of the month');
check(BillingService::periodLabel('2026-10-20'), 'Oct 20 – Nov 18, 2026', 'a cycle is named by its 30 days');
check(BillingService::periodLabel('2026-12-19'), 'Dec 19, 2026 – Jan 17, 2027', 'a cycle that crosses New Year shows both years');
check(BillingService::periodLabel('2026-10'), 'October 2026', 'month keys from before cycles still read as a month');
check([BillingService::periodLabel('2026-13'), BillingService::periodLabel('2026-02-31'), BillingService::periodLabel('Aug rent')],
    ['2026-13', '2026-02-31', 'Aug rent'], 'a period that is not a real month or date is shown as it is, not thrown on');
check($b['rent_due_dates'], [$first => $key($moveIn->modify('+30 days')), $second => $key($moveIn->modify('+60 days'))], 'each unpaid cycle carries its due date');
check($b['next_due_date'], $key($moveIn->modify('+30 days')), 'next due date is the earliest unpaid one');
$next = fn (string $movedIn, string $on) => BillingService::nextDueDate($movedIn, new DateTimeImmutable($on))->format('Y-m-d');
check($next('2026-10-20', '2026-10-20'), '2026-11-19', 'on move-in day the next due date is 30 days away');
check($next('2026-10-20', '2026-11-19'), '2026-11-19', 'on a due date the next due date is that day');
check($next('2026-10-20', '2026-11-20'), '2026-12-19', 'the day after, it is the following one');

echo "== Payments apply oldest cycle first, only after approval ==\n";
$p1 = Payment::create($boarderId, $second, 0, $rate, null);
check(BillingService::calculateBalance($boarderId)['total_outstanding'], 2 * $rate, 'pending payment changes nothing');
check(Payment::setVerification($p1, 'admin-approved', $adminId), true, 'admin approves');
$b = BillingService::calculateBalance($boarderId);
check(isset($b['unpaid_rent'][$first]), false, 'payment filed under the running cycle still settles the oldest first');
check($b['total_outstanding'], $rate, 'the running cycle is still owed');
check(Payment::setVerification($p1, 'admin-approved', $adminId), false, 'approving twice is refused');

echo "== Penalties come after rent; full payment settles them ==\n";
$ruleId = PenaltyRule::create('Damage ' . mt_rand(), 'flat_damage', 250.00);
$penId = Penalty::createManual($boarderId, $ruleId, 250.00, 'broken chair', date('Y-m-d'), $adminId);
$p2 = Payment::create($boarderId, $second, 0, $rate + 250, null);
Payment::setVerification($p2, 'admin-approved', $adminId);
$b = BillingService::calculateBalance($boarderId);
check($b['total_outstanding'], 0.0, 'nothing owed');
check(Penalty::find($penId)['status'], 'paid', 'penalty marked paid');
check((int) Penalty::find($penId)['paid_payment_id'], $p2, 'penalty linked to the payment');

echo "== Reversing an approval restores what was owed ==\n";
check(Payment::setVerification($p2, 'rejected', $adminId), true, 'admin reverses');
$b = BillingService::calculateBalance($boarderId);
check($b['total_outstanding'], $rate + 250, 'rent and penalty owed again');
check(Penalty::find($penId)['status'], 'unpaid', 'penalty back to unpaid');
check((int) $pdo->query("SELECT COUNT(*) FROM payment_allocations WHERE payment_id = {$p2}")->fetchColumn(), 0, 'no allocations left on the reversed payment');

echo "== Overpayment becomes credit ==\n";
$p3 = Payment::create($boarderId, $second, 0, $rate + 250 + 500, null);
Payment::setVerification($p3, 'admin-approved', $adminId);
$b = BillingService::calculateBalance($boarderId);
check($b['total_outstanding'], 0.0, 'nothing owed');
check($b['credit'], 500.0, '₱500 credit');
App\Services\NotificationDispatcher::paymentApproved($boarderId, Payment::find($p3),
    App\Services\PaymentAllocationService::getAllocationsForPayment($p3), $b);
$note = (string) $pdo->query("SELECT message FROM notifications WHERE user_id = {$boarderId} AND type = 'payment_approved' ORDER BY id DESC LIMIT 1")->fetchColumn();
check(str_contains($note, BillingService::periodLabel($second) . ' rent') && str_contains($note, '₱500.00 was kept as credit'), true,
    'approval notice names the cycle it paid and the credit kept: ' . $note);

echo "== Admin waiver is not owed and not re-opened ==\n";
$waived = Penalty::createManual($boarderId, $ruleId, 999.00, 'waive me', date('Y-m-d'), $adminId);
Penalty::markPaid($waived, $adminId);
$b = BillingService::calculateBalance($boarderId);
check($b['credit'], 500.0, 'waived penalty does not eat credit');
check(Penalty::find($waived)['status'], 'paid', 'waiver stays paid');

echo "== Late fees never stack ==\n";
// The test database applied the rules migration today; pretend these rules have always
// applied so earlier cycles can be tested, then check the cut-off itself further down.
$setRulesStart = fn (string $date) => $pdo->prepare('UPDATE schema_migrations SET applied_at = ? WHERE filename = ?')
    ->execute([$date . ' 00:00:00', BillingService::RULES_MIGRATION]);
$setRulesStart('2000-01-01');
PenaltyRule::create('Late fee ' . mt_rand(), 'late_per_day', 10.00);
$lateRules = array_filter(PenaltyRule::allActive(), fn ($r) => $r['condition_type'] === 'late_per_day');
$perDay = array_sum(array_map(fn ($r) => (float) $r['amount'], $lateRules));
$fees = fn (int $id, ?string $period = null) => (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM penalties WHERE boarder_id = {$id}"
    . ($period ? " AND billing_period = '{$period}'" : ''))->fetchColumn();
$countFees = fn (int $id, string $period) => (int) $pdo->query("SELECT COUNT(*) FROM penalties WHERE boarder_id = {$id} AND billing_period = '{$period}'")->fetchColumn();

// Moved in 70 days ago: cycle 1 was due 40 days ago, cycle 2 ten days ago, cycle 3 is running.
$longStay = $key($today->modify('-70 days'));
$c1 = $longStay;
$c2 = $key($today->modify('-40 days'));
$dueDay = $today->modify('-10 days'); // cycle 2's due date
$day7 = $dueDay->modify('+7 days');

$late = $newResident($longStay);
PenaltyEngine::runCheck($dueDay);
check($countFees($late, $c2), 0, 'no late fee on the due date itself');
PenaltyEngine::runCheck($dueDay->modify('+5 days'));
PenaltyEngine::runCheck($dueDay->modify('+5 days'));
check($countFees($late, $c2), count($lateRules), 'one fee per rule for the cycle');
check($fees($late, $c2), $perDay * 5, 'running twice, five days after the due date: 5 days late, not 10');
PenaltyEngine::runCheck($day7);
check($fees($late, $c2), $perDay * 7, 'running two days later updates the fee to 7 days late');
check($fees($late, $c1), $perDay * 37, 'an unpaid earlier cycle keeps its own late fee, counted from its own due date');

// Moved in 10 days ago: the first rent is due 30 days after move-in, so no fee before then.
$newMoveIn = $today->modify('-10 days');
$newcomer = $newResident($key($newMoveIn));
PenaltyEngine::runCheck($newMoveIn->modify('+24 days'));
check($fees($newcomer), 0.0, 'a new resident gets no late fee in their first 30 days');
PenaltyEngine::runCheck($newMoveIn->modify('+32 days'));
check($fees($newcomer, $key($newMoveIn)), $perDay * 2, 'two days after the first due date: 2 days late');

echo "== No backdated late fees on rent due before the rules went live (review W4) ==\n";
$setRulesStart($key($dueDay->modify('-5 days')));
$arrears = $newResident($longStay);
PenaltyEngine::runCheck($day7);
check($fees($arrears, $c1), 0.0, 'the first cycle was due before the start date: no new fee');
check($fees($arrears, $c2), $perDay * 7, 'the second (due after the start date) is charged normally');
$setRulesStart('2000-01-01');

echo "== Late days stop when the receipt is submitted, not when it is approved ==\n";
// A resident whose rent is all unpaid sends a receipt (for everything owed, unless $amount is given) on $on.
$receiptOn = function (int $boarder, DateTimeImmutable $on, ?float $amount = null) use ($pdo): int {
    $owed = BillingService::calculateBalance($boarder)['total_outstanding'];
    $id = Payment::create($boarder, date('Y-m'), $owed, $amount ?? $owed, null, 'gcash');
    $pdo->prepare('UPDATE payments SET created_at = ? WHERE id = ?')->execute([$on->format('Y-m-d') . ' 10:00:00', $id]);
    return $id;
};

$onTime = $newResident($longStay);
$receiptOn($onTime, $dueDay->modify('-2 days'));
PenaltyEngine::runCheck($day7);
check($fees($onTime, $c2), 0.0, 'receipt sent two days before the due date, still waiting a week after it: no fee for that cycle');

$lateSender = $newResident($longStay);
$late3 = $receiptOn($lateSender, $dueDay->modify('+3 days'));
PenaltyEngine::runCheck($day7);
check($fees($lateSender, $c2), $perDay * 3, 'receipt sent 3 days late: a fee for those 3 days only, not the 7 until the check');

check(Payment::setVerification($late3, 'rejected', $adminId, 'blurry'), true, 'that receipt is rejected');
PenaltyEngine::runCheck($day7);
check($fees($lateSender, $c2), $perDay * 7, 'after rejection the cycle counts as late again (7 days)');

$partial = $newResident($longStay);
$receiptOn($partial, $dueDay->modify('+3 days'), $rate * 1.5); // pays cycle 1 and half of cycle 2
PenaltyEngine::runCheck($day7);
check($fees($partial, $c2), $perDay * 7, 'a receipt that does not cover the cycle does not stop its fee');

echo "== The admin decides on the late fee when approving ==\n";
$charged = $newResident($longStay);
$pc = $receiptOn($charged, $dueDay->modify('+3 days'));
$preview = App\Services\PaymentReviewService::lateFeePreview(Payment::find($pc), $day7);
check($preview['total'] > 0 && str_contains($preview['text'], BillingService::periodLabel($c2) . ': 3 day(s) late'), true,
    'the approve dialog previews the cycle\'s fee as 3 days: ' . $preview['text']);
$r = App\Services\PaymentReviewService::approve($pc, $adminId, (float) Payment::find($pc)['claimed_amount'], null, $day7);
check($r['ok'], true, 'approved without waiving');
check($fees($charged, $c2), $perDay * 3, 'the fee charged is for the 3 days until the receipt was submitted, though approved 4 days later');

$forgiven = $newResident($longStay);
$pf = $receiptOn($forgiven, $dueDay->modify('+3 days'));
$r = App\Services\PaymentReviewService::approve($pf, $adminId, (float) Payment::find($pf)['claimed_amount'], 'GCash was down that day', $day7);
$waivedFee = $pdo->query("SELECT status, paid_payment_id, waived_by, waive_reason FROM penalties
    WHERE boarder_id = {$forgiven} AND billing_period = '{$c2}' LIMIT 1")->fetch();
check($r['ok'] && count($r['waived']) >= 1, true, 'approved with a waiver');
check([$waivedFee['status'], $waivedFee['paid_payment_id'], (int) $waivedFee['waived_by'], $waivedFee['waive_reason']],
    ['paid', null, $adminId, 'GCash was down that day'], 'the waiver records who and why');
check(BillingService::calculateBalance($forgiven)['penalties_due'], 0.0, 'nothing owed for the waived fee');
check(App\Services\PaymentReviewService::approve($pf, $adminId, 1.0, null, $day7)['ok'], false, 'approving the same receipt again is refused');

echo "== Correcting a move-in date takes the old cycles' unpaid late fees with it ==\n";
check($fees($late, $c1) > 0 && $fees($late, $c2) > 0, true, 'the resident has unpaid late fees on two cycles');
BoarderProfile::updateDatesAndStatus($late, $key($today->modify('-69 days')), null, $adminId);
check($fees($late, $c1) + $fees($late, $c2), 0.0, 'no fee is left on a cycle that no longer exists');
check(Penalty::find($waived)['status'], 'paid', 'other residents\' penalties are untouched');

echo "== Finished cycles keep the price they were billed at ==\n";
Room::update($roomId, 'B-upd-' . mt_rand(), '2', 1, 4000.00);
BillingService::calculateBalance($boarderId);
check((float) $pdo->query("SELECT amount FROM rent_charges WHERE boarder_id = {$boarderId} AND period = '{$first}'")->fetchColumn(), $rate, 'the finished cycle is unchanged');
check((float) $pdo->query("SELECT amount FROM rent_charges WHERE boarder_id = {$boarderId} AND period = '{$second}'")->fetchColumn(), 4000.0, 'the running cycle follows the new price');

echo "== A cycle cut short by a move-out is charged by the day ==\n";
$moveOut = $moveIn->modify('+9 days'); // stayed 10 days
BoarderProfile::updateDatesAndStatus($boarderId, $first, $key($moveOut), $adminId);
$charges = $pdo->query("SELECT period, amount FROM rent_charges WHERE boarder_id = {$boarderId}")->fetchAll(PDO::FETCH_KEY_PAIR);
check(array_keys($charges), [$first], 'no charge after the move-out');
check((float) $charges[$first], round($rate * 10 / 30, 2), '10 of 30 days = a third of the rent');

echo "== A room's electricity bill is split equally and falls due with the rent ==\n";
$elecRoom = Room::create('E-' . mt_rand(1000, 9999), '2', 3, $rate);
$r1MoveIn = $today->modify('-5 days');
$r1 = $newResident($key($r1MoveIn), $elecRoom);
$r2 = $newResident($key($today->modify('-40 days')), $elecRoom);
$bill = ElectricityBill::create($elecRoom, 1500.00, 'September 2026', $adminId);
check($bill['share'], 750.0, '₱1,500 between the 2 people in the room = ₱750 each');
$b = BillingService::calculateBalance($r1);
check($b['electricity_due'], 750.0, 'the share is owed');
check($b['total_outstanding'], $rate + 750, 'on top of the rent');
check($b['unpaid_electricity'][0]['due_date'] ?? null, $key($r1MoveIn->modify('+30 days')), 'it is due on the same day as the resident\'s next rent');
check($bill['due_dates'][$r2] ?? null, $key($today->modify('+20 days')), 'each resident\'s share follows their own rent due date');

$tryBill = function (int $room, string $label) use ($adminId): bool {
    try {
        ElectricityBill::create($room, 500.00, $label, $adminId);
        return true;
    } catch (RuntimeException) {
        return false;
    }
};
check($tryBill($elecRoom, 'September 2026'), false, 'the same bill cannot be entered twice for a room');
check($tryBill(Room::create('E-empty-' . mt_rand(1000, 9999), '2', 1, $rate), 'September 2026'), false, 'a room nobody is staying in cannot be billed');

$pay = Payment::create($r1, $key($r1MoveIn), 0, $rate + 250, null);
Payment::setVerification($pay, 'admin-approved', $adminId);
$b = BillingService::calculateBalance($r1);
check($b['rent_due'], 0.0, 'a payment clears the rent first');
check($b['electricity_due'], 500.0, 'then the electricity share with what is left');
$alloc = App\Services\PaymentAllocationService::getAllocationsForPayment($pay);
check(array_column($alloc, 'allocation_type'), ['rent', 'electricity'], 'the payment records what it paid, in that order');
check($alloc[1]['electricity_label'] ?? null, 'September 2026', 'and names the electricity bill');

$r3 = $newResident($key($today), $elecRoom);
$bill3 = ElectricityBill::create($elecRoom, 1000.00, 'October 2026', $adminId);
check($bill3['share'], 333.33, '₱1,000 between 3 people = ₱333.33 each');
check(count($bill3['due_dates']), 3, 'everyone staying in the room on the day shares the bill');

check(ElectricityBill::delete($bill['id']), true, 'a bill entered by mistake can be removed');
check(BillingService::calculateBalance($r1)['electricity_due'], 83.33, 'what was paid toward it moves to the next charge (333.33 − 250)');
check(BillingService::calculateBalance($r2)['electricity_due'], 333.33, 'the other resident owes only the remaining bill');
check(ElectricityBill::delete($bill['id']), false, 'removing it twice finds nothing');
$erased = true;
try {
    $pdo->exec("DELETE FROM users WHERE id = {$r3}");
} catch (PDOException $e) {
    $erased = false;
}
check($erased, false, 'a resident with an electricity share cannot be erased: the database refuses');
try {
    Room::delete($elecRoom);
    $deleted = 'deleted';
} catch (RuntimeException $e) {
    $deleted = $e->getMessage();
}
check($deleted, 'Cannot delete a room that has electricity bills on record.', 'a room with electricity bills on record is refused with a message, not an error page');

echo "== Dashboard: rent & collections summary ==\n";
// Moved in 40 days ago: the first cycle fell due 10 days ago, the second is not due yet.
$owing = $newResident($key($today->modify('-40 days')), Room::create('C-' . mt_rand(1000, 9999), '2', 1, $rate));
$mine = fn (array $c) => array_values(array_filter($c['debtors'], fn ($d) => $d['user_id'] === $owing))[0] ?? null;
$c0 = BillingService::collectionsSummary(null, 1000);
check($mine($c0)['owed'] ?? null, 2 * $rate, 'a resident with two unpaid cycles is listed with everything they owe');
check($mine($c0)['overdue'] ?? null, $rate, 'only the cycle past its due date counts as overdue');
check($mine($c0)['oldest_due'] ?? null, $key($today->modify('-10 days')), 'and shows the oldest due date');
check(count(BillingService::collectionsSummary(null, 1)['debtors']), 1, 'the list is cut to the limit asked for');
$pay = Payment::create($owing, $key($today->modify('-40 days')), 0, 2 * $rate, null);
Payment::setVerification($pay, 'admin-approved', $adminId);
$c1 = BillingService::collectionsSummary(null, 1000);
check($mine($c1), null, 'a resident who has paid up drops off the list');
check($c1['collected_this_month'] - $c0['collected_this_month'], 2 * $rate, 'an approved payment counts as collected this month');
check($c0['total_outstanding'] - $c1['total_outstanding'], 2 * $rate, 'and comes off the total outstanding');
check($c0['overdue_count'] - $c1['overdue_count'], 1, 'and off the overdue count');

echo $failures === 0 ? "All billing checks passed.\n" : "{$failures} billing check(s) failed.\n";
exit($failures === 0 ? 0 : 1);
