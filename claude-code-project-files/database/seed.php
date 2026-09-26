<?php
/**
 * Seeds one account per role (Phase 1 verification) plus a room/bed and a
 * penalty rule so Phase 2/3 flows have something to work against.
 * Run: php database/seed.php   (from the project root, with .env configured)
 */

require_once __DIR__ . '/../src/autoload.php';

use App\Models\User;
use App\Models\Room;
use App\Models\Bed;
use App\Models\BoarderProfile;
use App\Models\PenaltyRule;

$adminId = User::create('admin', 'Admin User', 'admin@rjm.test', 'AdminPass123!');
$staffId = User::create('staff', 'Staff User', 'staff@rjm.test', 'StaffPass123!');
$boarderId = User::create('boarder', 'Boarder User', 'boarder@rjm.test', 'BoarderPass123!');

$roomId = Room::create('101', '1', 2, 3500.00);
$bedAId = Bed::create($roomId, 'Bed A');
Bed::create($roomId, 'Bed B');

Bed::assign($bedAId, $boarderId);
BoarderProfile::create($boarderId, $roomId, $bedAId);

PenaltyRule::create('Late rent penalty', 'late_per_day', 5.00);

echo "Seeded: admin@rjm.test / staff@rjm.test / boarder@rjm.test (see this file for passwords)\n";
echo "Room 101 / Bed A assigned to boarder (user_id={$boarderId})\n";
