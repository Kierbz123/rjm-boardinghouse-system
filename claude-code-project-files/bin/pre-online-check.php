<?php
/**
 * Run by bin/go-online.ps1 before the tunnel opens. Prints one line per problem and exits 1 if
 * the system should NOT be put on the internet yet; exits 0 when it is fine; 2 when it could not
 * check (database down), which also counts as "not safe to open".
 *
 *   ACCOUNT <email>   still has a seeded / default password and must choose its own
 *   DATABASE ...      the app is logged in to MariaDB as root without a password
 *
 * Changes nothing. Run: php bin/pre-online-check.php
 */

require_once __DIR__ . '/../src/autoload.php';

use App\Database;

try {
    $pdo = Database::getConnection();
    $problems = [];
    foreach ($pdo->query('SELECT email FROM users WHERE must_change_password = 1 ORDER BY role, email')->fetchAll(PDO::FETCH_COLUMN) as $email) {
        $problems[] = "ACCOUNT {$email}";
    }
    if (Database::describe()['insecure']) {
        $problems[] = 'DATABASE the app uses the MariaDB root login with no password; run bin/setup-db-user.php';
    }
} catch (Throwable $e) {
    echo 'UNKNOWN could not check: ' . $e->getMessage() . "\n";
    exit(2);
}

echo implode("\n", $problems) . ($problems ? "\n" : '');
exit($problems ? 1 : 0);
