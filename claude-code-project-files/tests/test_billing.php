<?php
/**
 * Billing rules (owner decisions, Oct 2026):
 *  - rent accrues every month from move-in; unpaid months carry over
 *  - partial first/last months are prorated by days
 *  - payments always need admin approval and apply oldest-first: rent months, then penalties
 *  - late fees: one per boarder per rule per month, never stacked by re-running the check
 * Runs against the throwaway test database via tests/run.php.
 */

require_once __DIR__ . '/../src/autoload.php';

use App\Database;
use App\Models\User;
use App\Models\Room;
use App\Models\BoarderProfile;
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

// Moved in on the 11th of last month.
$lastMonth = new DateTimeImmutable('first day of last month');
$moveIn = $lastMonth->modify('+10 days');
$daysInLast = (int) $lastMonth->format('t');
$lastPeriod = $lastMonth->format('Y-m');
$thisPeriod = date('Y-m');
$firstCharge = round($rate * ($daysInLast - 10) / $daysInLast, 2);

$boarderId = User::create('boarder', 'Billing Tester', 'billing-' . mt_rand() . '@rjm.test', 'Secret123!');
BoarderProfile::create($boarderId, $roomId, null);
$pdo->prepare('UPDATE boarder_profiles SET move_in_date = ? WHERE user_id = ?')->execute([$moveIn->format('Y-m-d'), $boarderId]);
BoarderProfile::updateStatus($boarderId, 'active', $adminId, 'test');

echo "== Rent accrues from move-in, prorated, unpaid months carry over ==\n";
$b = BillingService::calculateBalance($boarderId);
check($b['unpaid_rent'][$lastPeriod] ?? null, $firstCharge, "first month prorated ({$daysInLast} days, from the 11th)");
check($b['unpaid_rent'][$thisPeriod] ?? null, $rate, 'this month charged in full');
check($b['total_outstanding'], round($firstCharge + $rate, 2), 'both months owed');

echo "== Payments apply oldest month first, only after approval ==\n";
$p1 = Payment::create($boarderId, $thisPeriod, 0, $firstCharge, null);
check(BillingService::calculateBalance($boarderId)['total_outstanding'], round($firstCharge + $rate, 2), 'pending payment changes nothing');
check(Payment::setVerification($p1, 'admin-approved', $adminId), true, 'admin approves');
$b = BillingService::calculateBalance($boarderId);
check(isset($b['unpaid_rent'][$lastPeriod]), false, 'payment marked "this month" still settles last month first');
check($b['total_outstanding'], $rate, 'this month still owed');
check(Payment::setVerification($p1, 'admin-approved', $adminId), false, 'approving twice is refused');

echo "== Penalties come after rent; full payment settles them ==\n";
$ruleId = PenaltyRule::create('Damage ' . mt_rand(), 'flat_damage', 250.00);
$penId = Penalty::createManual($boarderId, $ruleId, 250.00, 'broken chair', date('Y-m-d'), $adminId);
$p2 = Payment::create($boarderId, $thisPeriod, 0, $rate + 250, null);
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
$p3 = Payment::create($boarderId, $thisPeriod, 0, $rate + 250 + 500, null);
Payment::setVerification($p3, 'admin-approved', $adminId);
$b = BillingService::calculateBalance($boarderId);
check($b['total_outstanding'], 0.0, 'nothing owed');
check($b['credit'], 500.0, '₱500 credit');

echo "== Admin waiver is not owed and not re-opened ==\n";
$waived = Penalty::createManual($boarderId, $ruleId, 999.00, 'waive me', date('Y-m-d'), $adminId);
Penalty::markPaid($waived, $adminId);
$b = BillingService::calculateBalance($boarderId);
check($b['credit'], 500.0, 'waived penalty does not eat credit');
check(Penalty::find($waived)['status'], 'paid', 'waiver stays paid');

echo "== Late fees never stack ==\n";
$late = User::create('boarder', 'Late Payer', 'late-' . mt_rand() . '@rjm.test', 'Secret123!');
BoarderProfile::create($late, $roomId, null);
$pdo->prepare('UPDATE boarder_profiles SET move_in_date = ? WHERE user_id = ?')->execute([date('Y-m-01'), $late]);
BoarderProfile::updateStatus($late, 'active', $adminId, 'test');
PenaltyRule::create('Late fee ' . mt_rand(), 'late_per_day', 10.00);
$perDay = array_sum(array_map(fn ($r) => (float) $r['amount'],
    array_filter(PenaltyRule::allActive(), fn ($r) => $r['condition_type'] === 'late_per_day')));
$countFees = fn () => (int) $pdo->query("SELECT COUNT(*) FROM penalties WHERE boarder_id = {$late} AND billing_period = '{$thisPeriod}'")->fetchColumn();
$feeTotal = fn () => (float) $pdo->query("SELECT SUM(amount) FROM penalties WHERE boarder_id = {$late} AND billing_period = '{$thisPeriod}'")->fetchColumn();
$day10 = new DateTimeImmutable(date('Y-m-10'));
PenaltyEngine::runCheck($day10);
PenaltyEngine::runCheck($day10);
$feesPerRun = $countFees();
check($feesPerRun >= 1, true, 'a late fee was charged');
check($feeTotal(), $perDay * 5, 'running twice on the 10th: 5 days late, not 10');
PenaltyEngine::runCheck(new DateTimeImmutable(date('Y-m-12')));
check($countFees(), $feesPerRun, 'running on the 12th updates, does not add');
check($feeTotal(), $perDay * 7, 'fee follows days late (7)');
check(PenaltyEngine::runCheck(new DateTimeImmutable(date('Y-m-05'))), [], 'nothing on or before the 5th');

echo "== Past months keep the price they were billed at ==\n";
Room::update($roomId, 'B-upd-' . mt_rand(), '2', 1, 4000.00);
BillingService::calculateBalance($boarderId);
check((float) $pdo->query("SELECT amount FROM rent_charges WHERE boarder_id = {$boarderId} AND period = '{$lastPeriod}'")->fetchColumn(), $firstCharge, 'last month unchanged');
check((float) $pdo->query("SELECT amount FROM rent_charges WHERE boarder_id = {$boarderId} AND period = '{$thisPeriod}'")->fetchColumn(), 4000.0, 'this month follows the new price');

echo "== Move-out mid-month is prorated ==\n";
$moveOut = $lastMonth->modify('+19 days'); // 20th of last month
BoarderProfile::updateDatesAndStatus($boarderId, $moveIn->format('Y-m-d'), $moveOut->format('Y-m-d'), $adminId);
$charges = $pdo->query("SELECT period, amount FROM rent_charges WHERE boarder_id = {$boarderId}")->fetchAll(PDO::FETCH_KEY_PAIR);
check(array_keys($charges), [$lastPeriod], 'no charge after the move-out month');
check((float) $charges[$lastPeriod], round($rate * 10 / $daysInLast, 2), 'the 11th to the 20th = 10 days');

echo $failures === 0 ? "All billing checks passed.\n" : "{$failures} billing check(s) failed.\n";
exit($failures === 0 ? 0 : 1);
