<?php
/**
 * Applies database/migrations/NNNN_name.sql in filename order and records each one in
 * schema_migrations, so a file is never run twice. Every migration added from 0031 on
 * has a NNNN_name.down.sql that undoes it.
 *
 * Run: php database/migrate.php                 apply everything pending
 *      php database/migrate.php --status        list applied / pending (and which have a rollback)
 *      php database/migrate.php --rollback      undo the most recent migration
 *      php database/migrate.php --rollback=3    undo the three most recent
 * Uses the same DB_* env vars as the app.
 *
 * Databases created before this runner existed have no schema_migrations rows,
 * and some were hand-patched (e.g. missing 0015). Statements that fail only
 * because their object already exists are treated as already applied, so the
 * first run on such a database brings it up to date instead of crashing.
 *
 * File format: statements end with ";" at the end of a line. A file whose header
 * contains "-- @delimiter //" (needed for triggers, whose bodies contain ";") ends
 * statements with a line holding only "//" instead.
 */

require_once __DIR__ . '/../src/autoload.php';

use App\Database;

// MariaDB "already exists" errors: table, column, key/index, FK name, FK errno 121.
const ALREADY_APPLIED_ERRORS = [1050, 1060, 1061, 1826, 1005];

/** Runs every statement in a migration file. $tolerant = skip "already exists" errors. */
function runMigrationFile(PDO $pdo, string $file, bool $tolerant): void
{
    $text = file_get_contents($file);
    $pattern = str_contains($text, '-- @delimiter //') ? '/^\/\/\s*$/m' : '/;\s*$/m';
    foreach (preg_split($pattern, $text) as $sql) {
        $body = trim(preg_replace('/^\s*--.*$/m', '', $sql));
        if ($body === '') {
            continue;
        }
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            $code = (int) ($e->errorInfo[1] ?? 0);
            if (!$tolerant || !in_array($code, ALREADY_APPLIED_ERRORS, true)) {
                fwrite(STDERR, 'FAILED ' . basename($file) . ": {$e->getMessage()}\n");
                exit(1);
            }
            echo '  ' . basename($file) . ": skipped statement, already applied ({$code})\n";
        }
    }
}

$pdo = Database::getConnection();
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
    filename   VARCHAR(255) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$applied = array_flip($pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
$files = array_values(array_filter(glob((getenv('MIGRATIONS_DIR') ?: __DIR__ . '/migrations') . '/*.sql'), fn ($f) => !str_ends_with($f, '.down.sql')));
sort($files);
$downFor = fn (string $file) => preg_replace('/\.sql$/', '.down.sql', $file);

$mode = 'up';
$rollbackCount = 1;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--status') {
        $mode = 'status';
    } elseif (preg_match('/^--rollback(?:=(\d+))?$/', $arg, $m)) {
        $mode = 'rollback';
        $rollbackCount = max(1, (int) ($m[1] ?? 1));
    } else {
        fwrite(STDERR, "Unknown option {$arg}\n");
        exit(2);
    }
}

if ($mode === 'status') {
    foreach ($files as $file) {
        $name = basename($file);
        printf("%-9s %-60s %s\n", isset($applied[$name]) ? 'applied' : 'PENDING', $name, is_file($downFor($file)) ? 'rollback: yes' : 'rollback: -');
    }
    exit(0);
}

if ($mode === 'rollback') {
    $appliedFiles = array_filter($files, fn ($f) => isset($applied[basename($f)]));
    $undone = 0;
    foreach (array_slice(array_reverse(array_values($appliedFiles)), 0, $rollbackCount) as $file) {
        $down = $downFor($file);
        if (!is_file($down)) {
            fwrite(STDERR, 'Cannot roll back ' . basename($file) . ': ' . basename($down) . " does not exist.\n");
            exit(1);
        }
        runMigrationFile($pdo, $down, false);
        $pdo->prepare('DELETE FROM schema_migrations WHERE filename = ?')->execute([basename($file)]);
        echo 'Rolled back ' . basename($file) . "\n";
        $undone++;
    }
    echo $undone === 0 ? "Nothing to roll back.\n" : "{$undone} migration(s) rolled back.\n";
    exit(0);
}

$count = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        continue;
    }
    runMigrationFile($pdo, $file, true);
    $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)')->execute([$name]);
    echo "Applied {$name}\n";
    $count++;
}

echo $count === 0 ? "Database is up to date.\n" : "{$count} migration(s) applied.\n";
