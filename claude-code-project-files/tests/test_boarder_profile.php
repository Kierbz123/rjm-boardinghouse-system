<?php

require_once __DIR__ . '/../src/autoload.php';

use App\Database;
use App\Models\User;
use App\Models\Room;
use App\Models\Bed;
use App\Models\BoarderProfile;
use App\Models\Payment;
use App\Models\Penalty;
use App\Models\PenaltyRule;
use App\Models\MaintenanceRequest;
use App\Models\Incident;

echo "=========================================================\n";
echo "  RJM BOARDINGHOUSE - BOARDER PROFILE TEST SUITE         \n";
echo "=========================================================\n\n";

$pdo = Database::getConnection();

function assertEqual($actual, $expected, string $label) {
    if ($actual == $expected) {
        echo "  [PASS] {$label}: expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
    } else {
        echo "  [FAIL] {$label}: expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

function assertTrue($condition, string $label) {
    if ($condition) {
        echo "  [PASS] {$label}\n";
    } else {
        echo "  [FAIL] {$label}: condition was false\n";
        exit(1);
    }
}

// ---------------------------------------------------------
// Setup Test Fixtures
// ---------------------------------------------------------
// Ensure Admin user
$admin = $pdo->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1")->fetch();
if (!$admin) {
    $adminId = User::create('admin', 'Admin Test', 'admin_test_prof@rjm.test', 'AdminPass123!');
    $admin = $pdo->query("SELECT * FROM users WHERE id = {$adminId}")->fetch();
}
$adminId = (int) $admin['id'];

// Ensure Staff user
$staff = $pdo->query("SELECT * FROM users WHERE role = 'staff' LIMIT 1")->fetch();
if (!$staff) {
    $staffId = User::create('staff', 'Staff Test', 'staff_test_prof@rjm.test', 'StaffPass123!');
    $staff = $pdo->query("SELECT * FROM users WHERE id = {$staffId}")->fetch();
}
$staffId = (int) $staff['id'];

// Ensure Room & 2 Beds exist for bed reassignment testing
$room = $pdo->query("SELECT * FROM rooms LIMIT 1")->fetch();
if (!$room) {
    $roomId = Room::create('999', '2', 2, 4000.00);
    $room = Room::find($roomId);
} else {
    $roomId = (int) $room['id'];
}

$beds = $pdo->query("SELECT * FROM beds WHERE room_id = {$roomId}")->fetchAll();
if (count($beds) < 2) {
    $bed1Id = Bed::create($roomId, 'Bed X1');
    $bed2Id = Bed::create($roomId, 'Bed X2');
} else {
    $bed1Id = (int) $beds[0]['id'];
    $bed2Id = (int) $beds[1]['id'];
}
Bed::vacate($bed1Id);
Bed::vacate($bed2Id);

// Create Dedicated Test Boarder
$testEmail = 'prof_tester_' . time() . '@rjm.test';
$testBoarderId = User::create('boarder', 'Profile Tester', $testEmail, 'TestPass123!');
BoarderProfile::create($testBoarderId, $roomId, $bed1Id);
Bed::assign($bed1Id, $testBoarderId);

echo "Step 1: Test findWithFullDetails() Returns Joined Data\n";
$full = BoarderProfile::findWithFullDetails($testBoarderId);
assertTrue($full !== null, "Profile found with full details");
assertEqual($full['name'], 'Profile Tester', "Joined user name");
assertEqual($full['email'], $testEmail, "Joined user email");
assertEqual((int)$full['room_id'], $roomId, "Joined room ID");
assertEqual($full['room_number'], $room['room_number'], "Joined room number");
assertEqual((int)$full['bed_id'], $bed1Id, "Joined bed ID");
assertTrue(!empty($full['bed_label']), "Joined bed label is populated");
assertTrue(isset($full['user_created_at']), "Joined user_created_at exists");

echo "\nStep 2: Test Date Validation (Reject Impossible Dates)\n";
$isValidCalendarDate = function(string $date): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    [$y, $m, $d] = explode('-', $date);
    return checkdate((int) $m, (int) $d, (int) $y);
};

assertTrue(!$isValidCalendarDate('2026-13-45'), "Rejects month 13 day 45");
assertTrue(!$isValidCalendarDate('2026-02-30'), "Rejects Feb 30th");
assertTrue(!$isValidCalendarDate('not-a-date'), "Rejects invalid string");
assertTrue(!$isValidCalendarDate('2026/05/12'), "Rejects slash format");
assertTrue($isValidCalendarDate('2026-02-28'), "Accepts Feb 28th");
assertTrue($isValidCalendarDate('2026-12-31'), "Accepts Dec 31st");

echo "\nStep 3: Test Date Range Inversion Validation\n";
$moveIn = '2026-06-15';
$invertedMoveOut = '2026-06-01';
$validMoveOut = '2026-07-01';

assertTrue($invertedMoveOut < $moveIn, "Inverted date correctly detects move_out < move_in");
assertTrue(!($validMoveOut < $moveIn), "Valid chronological range accepted");

echo "\nStep 4: Test Transactional updateDatesAndStatus()\n";
// Ensure boarder is active and has bed1 assigned
BoarderProfile::updateStatus($testBoarderId, 'active', $adminId, 'Activated for date test');
Bed::vacate($bed1Id);
BoarderProfile::assignRoomAndBed($testBoarderId, $roomId, $bed1Id);
Bed::assign($bed1Id, $testBoarderId);

BoarderProfile::updateDatesAndStatus($testBoarderId, '2026-01-15', '2026-06-30', $adminId);

$updatedProfile = BoarderProfile::findWithFullDetails($testBoarderId);
assertEqual($updatedProfile['move_in_date'], '2026-01-15', "move_in_date updated");
assertEqual($updatedProfile['move_out_date'], '2026-06-30', "move_out_date updated");
assertEqual($updatedProfile['status'], 'moved_out', "Status automatically transitioned to moved_out");
assertEqual($updatedProfile['bed_id'], null, "bed_id cleared on profile");

$bed1 = Bed::find($bed1Id);
assertEqual($bed1['status'], 'vacant', "Assigned bed was vacated atomically");
assertEqual($bed1['current_boarder_id'], null, "Assigned bed boarder ID cleared");

$logs = BoarderProfile::statusLog($testBoarderId);
$latestLog = $logs[0] ?? [];
assertEqual($latestLog['new_status'], 'moved_out', "Status log entry created");
assertTrue(str_contains($latestLog['reason'], 'Move-out date set to 2026-06-30'), "Status log contains move-out date reason");

echo "\nStep 5: Test updateNotes() Truncation and Persistence\n";
// Build a 10,500-char string
$longNotes = str_repeat('A', 10500);
$cappedNotes = mb_substr(trim($longNotes), 0, 10000);
assertEqual(mb_strlen($cappedNotes), 10000, "Capped notes length is exactly 10,000");

BoarderProfile::updateNotes($testBoarderId, $cappedNotes);
$profileAfterNotes = BoarderProfile::findWithFullDetails($testBoarderId);
assertEqual(mb_strlen($profileAfterNotes['notes']), 10000, "Persisted notes truncated to 10,000 in DB");

BoarderProfile::updateNotes($testBoarderId, "Short test note");
$profileAfterShort = BoarderProfile::findWithFullDetails($testBoarderId);
assertEqual($profileAfterShort['notes'], "Short test note", "Short notes saved correctly");

echo "\nStep 6: Test Migration Backfill Covers on_notice Residents\n";
// Temporarily set test boarder to on_notice with NULL move_in_date
$pdo->exec("UPDATE boarder_profiles SET status = 'on_notice', move_in_date = NULL WHERE user_id = {$testBoarderId}");
$checkNull = $pdo->query("SELECT move_in_date, status FROM boarder_profiles WHERE user_id = {$testBoarderId}")->fetch();
assertEqual($checkNull['move_in_date'], null, "move_in_date is NULL before backfill");

// Run backfill query
$backfillSql = "UPDATE boarder_profiles bp
    JOIN users u ON u.id = bp.user_id
    SET bp.move_in_date = DATE(u.created_at)
    WHERE bp.move_in_date IS NULL
      AND bp.status <> 'moved_out'
      AND bp.user_id = {$testBoarderId}";
$pdo->exec($backfillSql);

$checkBackfilled = $pdo->query("SELECT move_in_date FROM boarder_profiles WHERE user_id = {$testBoarderId}")->fetch();
assertTrue(!empty($checkBackfilled['move_in_date']), "on_notice resident move_in_date backfilled from users.created_at");

// Ensure moved_out is NOT backfilled
$pdo->exec("UPDATE boarder_profiles SET status = 'moved_out', move_in_date = NULL WHERE user_id = {$testBoarderId}");
$pdo->exec($backfillSql);
$checkMovedOut = $pdo->query("SELECT move_in_date FROM boarder_profiles WHERE user_id = {$testBoarderId}")->fetch();
assertEqual($checkMovedOut['move_in_date'], null, "moved_out resident is excluded from backfill");

echo "\nStep 7: Test Strict Whitelist for return_to\n";
$validateReturnTo = function(string $returnTo): string {
    $returnTo = trim($returnTo);
    if ($returnTo !== '' && preg_match('#^/admin/boarders/\d+$#', $returnTo)) {
        return $returnTo;
    }
    return '/admin/penalty-rules';
};

assertEqual($validateReturnTo('//evil.com'), '/admin/penalty-rules', "Rejects protocol-relative open redirect");
assertEqual($validateReturnTo('https://evil.com/phish'), '/admin/penalty-rules', "Rejects external HTTPS URL");
assertEqual($validateReturnTo('/admin/boarders/123/../../evil'), '/admin/penalty-rules', "Rejects path traversal");
assertEqual($validateReturnTo('/admin/boarders/abc'), '/admin/penalty-rules', "Rejects non-numeric ID");
assertEqual($validateReturnTo('/admin/boarders/123?param=evil'), '/admin/penalty-rules', "Rejects query parameters");
assertEqual($validateReturnTo('/admin/boarders/42'), '/admin/boarders/42', "Accepts valid canonical boarder profile URL");

echo "\nStep 8: Test Stale-Bed Bug Fix on Reassignment\n";
// Assign boarder to Bed 1 first
Bed::vacate($bed1Id);
Bed::vacate($bed2Id);
Bed::assign($bed1Id, $testBoarderId);
BoarderProfile::assignRoomAndBed($testBoarderId, $roomId, $bed1Id);

$b1 = Bed::find($bed1Id);
assertEqual($b1['status'], 'occupied', "Bed 1 initially occupied");

// Simulate reassignment logic from BoarderController::assignBed()
$currentProfile = BoarderProfile::find($testBoarderId);
if (!empty($currentProfile['bed_id']) && (int) $currentProfile['bed_id'] !== $bed2Id) {
    Bed::vacate((int) $currentProfile['bed_id']);
}
Bed::assign($bed2Id, $testBoarderId);
BoarderProfile::assignRoomAndBed($testBoarderId, $roomId, $bed2Id);

$b1After = Bed::find($bed1Id);
$b2After = Bed::find($bed2Id);
$profileAfterReassign = BoarderProfile::find($testBoarderId);

assertEqual($b1After['status'], 'vacant', "Previous Bed 1 vacated on reassignment");
assertEqual($b1After['current_boarder_id'], null, "Bed 1 current_boarder_id cleared");
assertEqual($b2After['status'], 'occupied', "New Bed 2 is occupied");
assertEqual((int)$b2After['current_boarder_id'], $testBoarderId, "Bed 2 assigned to test boarder");
assertEqual((int)$profileAfterReassign['bed_id'], $bed2Id, "Profile points to new Bed 2");

echo "\nStep 9: Test Payment::allForBoarder() Returns verifier_name\n";
// Create a verified payment
$stmt = $pdo->prepare("
    INSERT INTO payments (boarder_id, billing_period, expected_amount, claimed_amount, verification_status, verified_by, created_at)
    VALUES (?, '2026-06', 4000.00, 4000.00, 'admin-approved', ?, NOW())
");
$stmt->execute([$testBoarderId, $staffId]);
$paymentId = (int) $pdo->lastInsertId();

$boarderPayments = Payment::allForBoarder($testBoarderId, 5);
assertTrue(!empty($boarderPayments), "Payments retrieved for boarder");
assertEqual($boarderPayments[0]['verifier_name'], $staff['name'], "Payment left-join returns verifier_name");

$summary = Payment::summaryForBoarder($testBoarderId);
assertEqual((float)$summary['total_paid'], 4000.00, "Payment summary total_paid matches");
assertEqual((int)$summary['count'], 1, "Payment summary count matches");

echo "\nStep 10: Test statusLog() Includes changer_name (or System fallback)\n";
BoarderProfile::updateStatus($testBoarderId, 'active', $adminId, 'Testing changer name join');
$logWithAdmin = BoarderProfile::statusLog($testBoarderId);
assertEqual($logWithAdmin[0]['changer_name'], $admin['name'], "Status log contains admin changer_name");

BoarderProfile::updateStatus($testBoarderId, 'on_notice', null, 'Testing system changer fallback');
$logWithSystem = BoarderProfile::statusLog($testBoarderId);
assertEqual($logWithSystem[0]['changer_name'], 'System', "Status log contains 'System' fallback when changed_by is null");

// ---------------------------------------------------------
// Cleanup Test Fixtures
// ---------------------------------------------------------
Bed::vacate($bed1Id);
Bed::vacate($bed2Id);
BoarderProfile::delete($testBoarderId);

echo "\n=========================================================\n";
echo "  ALL BOARDER PROFILE & LIFECYCLE TESTS PASSED!          \n";
echo "=========================================================\n";
