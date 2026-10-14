<?php
/**
 * Lists the demo accounts that still use the password database/seed.php gave them.
 * start-online.bat runs this before opening the tunnel, because those passwords are
 * written in the repository. Exit code 1 when any is unchanged. Changes nothing.
 * Run: php database/check-demo-passwords.php
 */

require_once __DIR__ . '/../src/autoload.php';

use App\Models\User;

$seeded = [
    'admin@rjm.test' => 'AdminPass123!',
    'staff@rjm.test' => 'StaffPass123!',
    'boarder@rjm.test' => 'BoarderPass123!',
];

$unchanged = [];
foreach ($seeded as $email => $password) {
    $user = User::findByEmail($email);
    if ($user && password_verify($password, $user['password_hash'])) {
        $unchanged[] = $email;
    }
}

echo implode("\n", $unchanged) . ($unchanged ? "\n" : '');
exit($unchanged ? 1 : 0);
