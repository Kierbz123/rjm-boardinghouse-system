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

// No password is written anywhere in the repository. Each account gets a random one, printed once
// below, and must choose its own at first sign-in. SEED_<ROLE>_PASSWORD lets the test runner or an
// installer pick the password instead; such an account is not forced to change it.
$generated = [];
$passwordFor = function (string $role) use (&$generated): string {
    $chosen = getenv('SEED_' . strtoupper($role) . '_PASSWORD');
    if ($chosen !== false && $chosen !== '') {
        return $chosen;
    }
    // 16 characters from an alphabet without look-alikes (0/O, 1/l/I), plus fixed classes so any password policy passes.
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $password = '';
    for ($i = 0; $i < 14; $i++) {
        $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $generated[$role] = $password . '#7';
};

$adminId = User::create('admin', 'Admin User', 'admin@rjm.test', $passwordFor('admin'));
$staffId = User::create('staff', 'Staff User', 'staff@rjm.test', $passwordFor('staff'));
$boarderId = User::create('boarder', 'Boarder User', 'boarder@rjm.test', $passwordFor('boarder'));
foreach (['admin' => $adminId, 'staff' => $staffId, 'boarder' => $boarderId] as $role => $id) {
    if (isset($generated[$role])) {
        App\Database::getConnection()->prepare('UPDATE users SET must_change_password = 1 WHERE id = ?')->execute([$id]);
    }
}

$roomId = Room::create('101', '1', 2, 3500.00);
$bedAId = Bed::create($roomId, 'Bed A');
Bed::create($roomId, 'Bed B');

Bed::assign($bedAId, $boarderId);
BoarderProfile::create($boarderId, $roomId, $bedAId);

PenaltyRule::create('Late rent penalty', 'late_per_day', 5.00);

if ($generated) {
    echo "Seeded accounts. These passwords are shown ONCE; each account must choose its own at first sign-in:\n";
    foreach (['admin', 'staff', 'boarder'] as $role) {
        if (isset($generated[$role])) {
            printf("  %-18s %s\n", "{$role}@rjm.test", $generated[$role]);
        }
    }
} else {
    echo "Seeded: admin@rjm.test / staff@rjm.test / boarder@rjm.test (passwords from SEED_*_PASSWORD)\n";
}
echo "Room 101 / Bed A assigned to boarder (user_id={$boarderId})\n";
