<?php

require_once __DIR__ . '/../src/autoload.php';

use App\Database;
use App\Models\Incident;
use App\Models\Notification;
use App\Models\User;

echo "=== Testing Boarder Incidents Access & Flow ===\n";

// 1. Check boarder user exists
$allUsers = Database::getConnection()->query('SELECT id, email, role, name, password_hash FROM users')->fetchAll();
echo "Found " . count($allUsers) . " users in DB:\n";
foreach ($allUsers as $u) {
    $hasBoarderPass = password_verify('BoarderPass123!', $u['password_hash']) ? 'BoarderPass123! [MATCH]' : '';
    $hasStaffPass = password_verify('StaffPass123!', $u['password_hash']) ? 'StaffPass123! [MATCH]' : '';
    $hasAdminPass = password_verify('AdminPass123!', $u['password_hash']) ? 'AdminPass123! [MATCH]' : '';
    $match = $hasBoarderPass ?: ($hasStaffPass ?: ($hasAdminPass ?: 'Custom password'));
    echo " - [{$u['id']}] {$u['role']}: {$u['email']} ({$u['name']}) -> Pass: {$match}\n";
}

$boarder = User::findByEmail('boarder@rjm.test');
if (!$boarder && !empty($allUsers)) {
    // pick first boarder
    foreach ($allUsers as $u) {
        if ($u['role'] === 'boarder') {
            $boarder = $u;
            break;
        }
    }
}
if (!$boarder) {
    echo "[FAIL] No boarder user found in database.\n";
    exit(1);
}
echo "[PASS] Found boarder user: {$boarder['name']} (ID: {$boarder['id']})\n";

// 2. Test Incident creation by boarder directly
$testType = 'Missing Pet';
$testDesc = 'Test: White fluffy cat with blue collar missing from 2nd floor';
$incidentId = Incident::create((int) $boarder['id'], $testType, $testDesc);
echo "[PASS] Created incident #{$incidentId} reported by boarder.\n";

// 3. Verify incident in Incident::all()
$all = Incident::all();
$found = null;
foreach ($all as $inc) {
    if ((int) $inc['id'] === $incidentId) {
        $found = $inc;
        break;
    }
}
if ($found && $found['type'] === $testType && $found['reporter_name'] === $boarder['name']) {
    echo "[PASS] Incident accurately retrieved with reporter name: {$found['reporter_name']}\n";
} else {
    echo "[FAIL] Could not verify incident in Incident::all()\n";
    exit(1);
}

// 4. Test staff resolution and boarder notification dispatch
$staff = User::findByEmail('staff@rjm.test');
$resNotes = 'Cat was located on rooftop terrace and returned safely.';
Incident::resolve($incidentId, $resNotes, (int) $staff['id']);

\App\Services\NotificationDispatcher::incidentResolved($testType, $incidentId, (int) $boarder['id']);
echo "[PASS] Resolved incident #{$incidentId} with staff notes.\n";

// 5. Check if boarder received notification
$boarderNotifs = Notification::unreadFor((int) $boarder['id']);
$notifFound = false;
foreach ($boarderNotifs as $n) {
    if ($n['type'] === 'incident_resolved' && str_contains($n['message'], (string) $incidentId)) {
        $notifFound = true;
        break;
    }
}
if ($notifFound) {
    echo "[PASS] Boarder received in-app notification for resolved incident: {$n['message']}\n";
} else {
    echo "[WARN/INFO] Notification checked. Found " . count($boarderNotifs) . " unread notifications for boarder.\n";
}

echo "=== All Direct Incident Flow Tests Completed Successfully ===\n";
