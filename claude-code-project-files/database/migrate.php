<?php
/**
 * Applies database/migrations/*.sql in filename order and records each one in
 * schema_migrations, so a file is never run twice.
 *
 * Run: php database/migrate.php   (uses the same DB_* env vars as the app)
 *
 * Databases created before this runner existed have no schema_migrations rows,
 * and some were hand-patched (e.g. missing 0015). Statements that fail only
 * because their object already exists are treated as already applied, so the
 * first run on such a database brings it up to date instead of crashing.
 */

require_once __DIR__ . '/../src/autoload.php';

use App\Database;

// MariaDB "already exists" errors: table, column, key/index, FK name, FK errno 121.
const ALREADY_APPLIED_ERRORS = [1050, 1060, 1061, 1826, 1005];

$pdo = Database::getConnection();
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
    filename   VARCHAR(255) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$applied = array_flip($pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
$files = glob(__DIR__ . '/migrations/*.sql');
sort($files);

$count = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        continue;
    }

    // Statements end with ";" at end of line; migrations never put ";" mid-line.
    $statements = preg_split('/;\s*$/m', file_get_contents($file));
    foreach ($statements as $sql) {
        $body = trim(preg_replace('/^\s*--.*$/m', '', $sql));
        if ($body === '') {
            continue;
        }
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            $code = (int) ($e->errorInfo[1] ?? 0);
            if (!in_array($code, ALREADY_APPLIED_ERRORS, true)) {
                fwrite(STDERR, "FAILED {$name}: {$e->getMessage()}\n");
                exit(1);
            }
            echo "  {$name}: skipped statement, already applied ({$code})\n";
        }
    }

    $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)')->execute([$name]);
    echo "Applied {$name}\n";
    $count++;
}

echo $count === 0 ? "Database is up to date.\n" : "{$count} migration(s) applied.\n";
