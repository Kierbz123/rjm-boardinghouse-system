<?php

require_once __DIR__ . '/../src/autoload.php';

use App\Database;
use App\Models\User;
use App\Models\Room;
use App\Models\Bed;
use App\Models\BoarderProfile;
use App\Models\PenaltyRule;
use App\Models\Penalty;
use App\Models\Payment;
use App\Services\BillingService;
use App\Services\PaymentAllocationService;

echo "=========================================================\n";
echo "  RJM BOARDINGHOUSE - ACCOUNTING & ALLOCATION TEST SUITE  \n";
echo "=========================================================\n\n";

$pdo = Database::getConnection();

function assertEqual($actual, $expected, string $label) {
    if ($actual == $expected) {
        echo "  [PASS] {$label}: expected {$expected}, got {$actual}\n";
    } else {
        echo "  [FAIL] {$label}: expected {$expected}, got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// 1. Setup / Lookup test users
$boarder = $pdo->query("SELECT * FROM users WHERE role = 'boarder' LIMIT 1")->fetch();
if (!$boarder) {
    $boarderId = User::create('boarder', 'Boarder User', 'boarder_test@rjm.test', 'BoarderPass123!');
    $roomId = (int) $pdo->query("SELECT id FROM rooms LIMIT 1")->fetchColumn();
    if (!$roomId) {
        $roomId = Room::create('101', '1', 2, 3500.00);
        $bedId = Bed::create($roomId, 'Bed A');
    } else {
        $bedId = (int) $pdo->query("SELECT id FROM beds WHERE room_id = {$roomId} LIMIT 1")->fetchColumn();
    }
    BoarderProfile::create($boarderId, $roomId, $bedId);
    $boarder = $pdo->query("SELECT * FROM users WHERE id = {$boarderId}")->fetch();
}
$boarderId = (int) $boarder['id'];
$admin = $pdo->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1")->fetch();
$staff = $pdo->query("SELECT * FROM users WHERE role = 'staff' LIMIT 1")->fetch();
$adminId = (int) $admin['id'];
$staffId = (int) $staff['id'];

// Clean up any test records for clean repeatable testing
$pdo->exec("DELETE FROM payment_allocations WHERE boarder_id = {$boarderId}");
$pdo->exec("DELETE FROM payments WHERE boarder_id = {$boarderId}");
$pdo->exec("DELETE FROM penalties WHERE boarder_id = {$boarderId}");

// Ensure penalty rules exist
$ruleTrash = $pdo->query("SELECT * FROM penalty_rules WHERE name = 'Misplace Trash'")->fetch();
if (!$ruleTrash) {
    $ruleTrashId = PenaltyRule::create('Misplace Trash', 'flat_damage', 100.00);
} else {
    $ruleTrashId = (int) $ruleTrash['id'];
}

$ruleAppliance = $pdo->query("SELECT * FROM penalty_rules WHERE name = 'Unauthorized Appliance'")->fetch();
if (!$ruleAppliance) {
    $ruleApplianceId = PenaltyRule::create('Unauthorized Appliance', 'flat_damage', 200.00);
} else {
    $ruleApplianceId = (int) $ruleAppliance['id'];
}

// Ensure boarder's room base price is 3500.00
$stmt = $pdo->prepare('UPDATE rooms r JOIN boarder_profiles bp ON bp.room_id = r.id SET r.base_price = 3500.00 WHERE bp.user_id = ?');
$stmt->execute([$boarderId]);

echo "Step 1: Baseline Invariant Check\n";
BillingService::syncBalance($boarderId);
$bal = BillingService::calculateBalance($boarderId);
assertEqual($bal['rent_due'], 3500.00, "Initial Rent Due");
assertEqual($bal['penalties_due'], 0.00, "Initial Penalties Due");
assertEqual($bal['total_outstanding'], 3500.00, "Initial Total Balance");

$cachedBal = (float) $pdo->query("SELECT outstanding_balance FROM boarder_profiles WHERE user_id = {$boarderId}")->fetchColumn();
assertEqual($cachedBal, 3500.00, "Cached Balance in boarder_profiles");

echo "\nStep 2: Cumulative Penalty Addition\n";
$dueDate1 = date('Y-m-d', strtotime('+30 days'));
$pen1Id = Penalty::createManual($boarderId, $ruleTrashId, 100.00, "Trash left in hallway 2F", $dueDate1, $adminId);

$bal = BillingService::calculateBalance($boarderId);
assertEqual($bal['penalties_due'], 100.00, "Penalties Due after Penalty #1 (₱100)");
assertEqual($bal['total_outstanding'], 3600.00, "Total Balance after Penalty #1 (₱3,600)");
$cachedBal = (float) $pdo->query("SELECT outstanding_balance FROM boarder_profiles WHERE user_id = {$boarderId}")->fetchColumn();
assertEqual($cachedBal, 3600.00, "Cached Balance synchronized to ₱3,600");

$dueDate2 = date('Y-m-d', strtotime('+25 days'));
$pen2Id = Penalty::createManual($boarderId, $ruleApplianceId, 200.00, "Hotplate in room", $dueDate2, $staffId);

$bal = BillingService::calculateBalance($boarderId);
assertEqual($bal['penalties_due'], 300.00, "Penalties Due after Penalty #2 (₱300)");
assertEqual($bal['total_outstanding'], 3800.00, "Total Balance after Penalty #2 (₱3,800)");
$cachedBal = (float) $pdo->query("SELECT outstanding_balance FROM boarder_profiles WHERE user_id = {$boarderId}")->fetchColumn();
assertEqual($cachedBal, 3800.00, "Cached Balance synchronized to ₱3,800");

echo "\nStep 3: Manual Override & Idempotency Check on markPaid()\n";
// Admin manually overrides Penalty #1
$markRes = Penalty::markPaid($pen1Id, $adminId);
assertEqual($markRes['ok'], true, "markPaid() succeeds on first execution");

$pen1 = Penalty::find($pen1Id);
assertEqual($pen1['status'], 'paid', "Penalty #1 status flipped to 'paid'");
assertEqual($pen1['paid_payment_id'], null, "paid_payment_id is NULL on manual override");

$bal = BillingService::calculateBalance($boarderId);
assertEqual($bal['penalties_due'], 200.00, "Penalties Due drops to ₱200 (only Penalty #2 remaining)");
assertEqual($bal['total_outstanding'], 3700.00, "Total Balance drops to ₱3,700");

// Trigger markPaid() a second time on the same penalty (idempotency check)
$markRes2 = Penalty::markPaid($pen1Id, $adminId);
assertEqual($markRes2['ok'], false, "markPaid() correctly rejected on duplicate submission");
$bal = BillingService::calculateBalance($boarderId);
assertEqual($bal['total_outstanding'], 3700.00, "Balance unchanged after duplicate markPaid() attempt");

echo "\nStep 4: Combined Full Payment Settlement\n";
// Add another penalty so boarder owes: Rent ₱3,500 + Penalty #2 ₱200 + Penalty #3 ₱100 = ₱3,800
$pen3Id = Penalty::createManual($boarderId, $ruleTrashId, 100.00, "Unsorted trash", $dueDate1, $adminId);
$bal = BillingService::calculateBalance($boarderId);
assertEqual($bal['total_outstanding'], 3800.00, "Total Balance is ₱3,800 before payment");

// Boarder submits ONE combined payment of ₱3,800
$currentPeriod = date('Y-m');
$paymentId = Payment::create($boarderId, $currentPeriod, 3800.00, 3800.00, 'receipts/test_combined.jpg');

// Admin approves the payment
Payment::setVerification($paymentId, 'admin-approved', $adminId);

// Check allocations created
$allocations = PaymentAllocationService::getAllocationsForPayment($paymentId);
assertEqual(count($allocations), 3, "Exactly 3 allocation records created (1 rent + 2 penalties)");

// Verify rent allocation
$rentAlloc = array_values(array_filter($allocations, fn($a) => $a['allocation_type'] === 'rent'))[0];
assertEqual($rentAlloc['amount'], 3500.00, "Rent allocated ₱3,500");

// Verify penalties automatically marked paid and linked to payment
$pen2 = Penalty::find($pen2Id);
assertEqual($pen2['status'], 'paid', "Penalty #2 automatically marked 'paid'");
assertEqual((int)$pen2['paid_payment_id'], $paymentId, "Penalty #2 linked to paymentId #{$paymentId}");

$pen3 = Penalty::find($pen3Id);
assertEqual($pen3['status'], 'paid', "Penalty #3 automatically marked 'paid'");
assertEqual((int)$pen3['paid_payment_id'], $paymentId, "Penalty #3 linked to paymentId #{$paymentId}");

// Verify final balance is exactly ₱0.00
$bal = BillingService::calculateBalance($boarderId);
assertEqual($bal['rent_due'], 0.00, "Rent Due is ₱0.00");
assertEqual($bal['penalties_due'], 0.00, "Penalties Due is ₱0.00");
assertEqual($bal['total_outstanding'], 0.00, "Total Balance is ₱0.00 (All Obligations Settled)");
$cachedBal = (float) $pdo->query("SELECT outstanding_balance FROM boarder_profiles WHERE user_id = {$boarderId}")->fetchColumn();
assertEqual($cachedBal, 0.00, "Cached Balance synchronized to ₱0.00");

echo "\nStep 5: Partial Combined Payment (Deterministic Allocation Order)\n";
// Create next test cycle scenario:
// Next month billing period e.g. 2026-10
$nextPeriod = '2026-10';
$pen4Id = Penalty::createManual($boarderId, $ruleTrashId, 100.00, "Trash penalty", '2026-10-15', $adminId);
$pen5Id = Penalty::createManual($boarderId, $ruleApplianceId, 200.00, "Appliance penalty", '2026-10-20', $staffId);

// Total owed for next cycle: Rent ₱3,500 + ₱100 + ₱200 = ₱3,800
// Boarder submits partial payment of ₱2,000 for nextPeriod
$partialPayId = Payment::create($boarderId, $nextPeriod, 3800.00, 2000.00, 'receipts/test_partial.jpg');
Payment::setVerification($partialPayId, 'admin-approved', $adminId);

$partialAllocations = PaymentAllocationService::getAllocationsForPayment($partialPayId);
assertEqual(count($partialAllocations), 1, "Only 1 allocation created (all ₱2,000 applied to rent first)");
assertEqual($partialAllocations[0]['allocation_type'], 'rent', "Allocation applied to Rent");
assertEqual($partialAllocations[0]['amount'], 2000.00, "₱2,000 allocated to Rent");

// Verify penalties #4 and #5 remain unpaid
$pen4 = Penalty::find($pen4Id);
assertEqual($pen4['status'], 'unpaid', "Penalty #4 remains 'unpaid' under partial payment");
$pen5 = Penalty::find($pen5Id);
assertEqual($pen5['status'], 'unpaid', "Penalty #5 remains 'unpaid' under partial payment");

echo "\nStep 6: Partial Payment Covering Remaining Rent and One Penalty\n";
// Boarder submits payment of ₱1,600:
// ₱1,500 should complete remaining rent, and ₱100 should cover oldest Penalty #4!
$pay3Id = Payment::create($boarderId, $nextPeriod, 1800.00, 1600.00, 'receipts/test_partial2.jpg');
Payment::setVerification($pay3Id, 'admin-approved', $adminId);

$alloc3 = PaymentAllocationService::getAllocationsForPayment($pay3Id);
assertEqual(count($alloc3), 2, "2 allocations created (₱1,500 rent + ₱100 penalty)");

// Check Penalty #4 is now paid
$pen4 = Penalty::find($pen4Id);
assertEqual($pen4['status'], 'paid', "Penalty #4 is now fully settled and 'paid'");
assertEqual((int)$pen4['paid_payment_id'], $pay3Id, "Penalty #4 linked to paymentId #{$pay3Id}");

// Penalty #5 should still be unpaid
$pen5 = Penalty::find($pen5Id);
assertEqual($pen5['status'], 'unpaid', "Penalty #5 remains 'unpaid'");

echo "\n=========================================================\n";
echo "  ALL ACCOUNTING, TRANSACTION, & ALLOCATION TESTS PASSED! \n";
echo "=========================================================\n";
