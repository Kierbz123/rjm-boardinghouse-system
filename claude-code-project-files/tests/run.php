<?php
/**
 * Test runner. Never touches the real database: it rebuilds a throwaway
 * `<DB_NAME>_test` database from migrations + seed, starts a private PHP
 * server on 127.0.0.1:8099 against it, then runs every tests/test_*.php.
 *
 * Run: php tests/run.php            (skips the slow Ollama test)
 *      php tests/run.php --with-ai   (includes tests/test_ai_assistant.php)
 */

$root = dirname(__DIR__);
$php = PHP_BINARY;
$testDb = (getenv('DB_NAME') ?: 'rjm_boardinghouse') . '_test';
$port = 8099;

// Fresh database. Connect without a schema so we can drop/create it.
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;charset=utf8mb4', getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_PORT') ?: '3306'),
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec("DROP DATABASE IF EXISTS `{$testDb}`");
$pdo->exec("CREATE DATABASE `{$testDb}` CHARACTER SET utf8mb4");
$pdo = null;

// Children (CLI scripts and the server) inherit this.
putenv("DB_NAME={$testDb}");
putenv("TEST_BASE_URL=http://127.0.0.1:{$port}");

foreach (['database/migrate.php', 'database/seed.php'] as $script) {
    exec(escapeshellarg($php) . ' ' . escapeshellarg("{$root}/{$script}") . ' 2>&1', $out, $code);
    if ($code !== 0) {
        echo implode("\n", $out), "\nSetup failed at {$script}\n";
        exit(1);
    }
}

$server = proc_open(
    [$php, '-S', "127.0.0.1:{$port}", '-t', "{$root}/public"],
    [1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']],
    $pipes,
    $root
);
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
    usleep(100000);
}

$tests = glob(__DIR__ . '/test_*.php');
if (!in_array('--with-ai', $argv, true)) {
    $tests = array_filter($tests, fn ($t) => basename($t) !== 'test_ai_assistant.php');
}

// Uploads from test requests land in the real public/uploads; remove them afterwards.
$uploadsBefore = glob("{$root}/public/uploads/*/*");

$failed = [];
foreach ($tests as $test) {
    echo "\n### " . basename($test) . "\n";
    passthru(escapeshellarg($php) . ' ' . escapeshellarg($test), $code);
    if ($code !== 0) {
        $failed[] = basename($test);
    }
}

proc_terminate($server);
array_map('unlink', array_diff(glob("{$root}/public/uploads/*/*"), $uploadsBefore));

echo "\n" . str_repeat('=', 50) . "\n";
echo count($tests) - count($failed) . '/' . count($tests) . " test files passed.\n";
foreach ($failed as $f) {
    echo "  FAILED: {$f}\n";
}
exit($failed ? 1 : 0);
