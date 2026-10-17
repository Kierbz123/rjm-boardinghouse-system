<?php
/**
 * Gives admin@/staff@/boarder@rjm.test a new random temporary password, prints each once, and makes
 * the account choose its own at next sign-in (same rules as database/seed.php). For when the seed's
 * one-time printout was lost. Nothing is written to disk.
 *
 *   php bin/reset-demo-passwords.php
 */

require_once __DIR__ . '/../src/autoload.php';

use App\Database;
use App\Models\User;

$alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$pdo = Database::getConnection();
foreach (['admin@rjm.test', 'staff@rjm.test', 'boarder@rjm.test'] as $email) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $id = $stmt->fetchColumn();
    if (!$id) {
        echo "{$email}: no such account, skipped\n";
        continue;
    }
    $password = '';
    for ($i = 0; $i < 14; $i++) {
        $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    $password .= '#7';
    User::updatePassword((int) $id, $password);
    $pdo->prepare('UPDATE users SET must_change_password = 1 WHERE id = ?')->execute([$id]);
    echo "{$email}  {$password}\n";
}
echo "Sign in with each, then choose a new password under Profile.\n";
