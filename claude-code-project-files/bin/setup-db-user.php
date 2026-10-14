<?php
/**
 * Creates the database login the app should use instead of MariaDB's root, gives it rights on the
 * app's database only, and writes it to .env (other lines in .env are kept). The password is
 * random and is never printed.
 *
 *   php bin/setup-db-user.php                          create boardinghouse_app for rjm_boardinghouse
 *   php bin/setup-db-user.php --reset                  also replace the password of an existing login
 *   php bin/setup-db-user.php --user=NAME --db=NAME --env-file=PATH
 *
 * Connects as DB_ROOT_USER / DB_ROOT_PASS (default root with no password: XAMPP's default).
 */

$options = ['user' => 'boardinghouse_app', 'db' => getenv('DB_NAME') ?: 'rjm_boardinghouse', 'env-file' => dirname(__DIR__) . '/.env', 'reset' => false];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--reset') {
        $options['reset'] = true;
    } elseif (preg_match('/^--(user|db|env-file)=(.+)$/', $arg, $m)) {
        $options[$m[1]] = $m[2];
    } else {
        fwrite(STDERR, "Unknown option {$arg}\n");
        exit(2);
    }
}
if (!preg_match('/^[A-Za-z0-9_]{1,32}$/', $options['user']) || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $options['db'])) {
    fwrite(STDERR, "The user and database names may only contain letters, digits and underscores.\n");
    exit(2);
}

try {
    $root = new PDO(
        sprintf('mysql:host=%s;port=%s', getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_PORT') ?: '3306'),
        getenv('DB_ROOT_USER') ?: 'root',
        getenv('DB_ROOT_PASS') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    fwrite(STDERR, 'Could not connect as the administrator login: ' . $e->getMessage() . "\nSet DB_ROOT_USER / DB_ROOT_PASS if root has a password.\n");
    exit(1);
}

$user = $options['user'];
$db = $options['db'];
$hosts = ['localhost', '127.0.0.1'];
$exists = (int) $root->query("SELECT COUNT(*) FROM mysql.user WHERE User = " . $root->quote($user))->fetchColumn() > 0;
if ($exists && !$options['reset']) {
    fwrite(STDERR, "The login {$user} already exists. Run again with --reset to give it a new password.\n");
    exit(1);
}

$password = bin2hex(random_bytes(16));
foreach ($hosts as $host) {
    $account = $root->quote($user) . '@' . $root->quote($host);
    $root->exec("CREATE USER IF NOT EXISTS {$account} IDENTIFIED BY " . $root->quote($password));
    $root->exec("ALTER USER {$account} IDENTIFIED BY " . $root->quote($password));
    $root->exec("GRANT ALL PRIVILEGES ON `{$db}`.* TO {$account}");
}
$root->exec('FLUSH PRIVILEGES');

// Update .env in place: replace DB_USER / DB_PASS, keep every other line.
$file = $options['env-file'];
$lines = is_file($file) ? file($file, FILE_IGNORE_NEW_LINES) : [];
$lines = array_values(array_filter($lines, fn ($l) => !preg_match('/^\s*(DB_USER|DB_PASS)\s*=/', $l)));
$lines[] = "DB_USER={$user}";
$lines[] = "DB_PASS={$password}";
file_put_contents($file, implode("\n", $lines) . "\n");

echo "Database login '{$user}' is ready for `{$db}` and saved to {$file}.\n";
echo "Restart the web server so it picks the new login up.\n";
