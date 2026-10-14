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
// Tests never read the real .env (it holds the live database login).
putenv('APP_ENV_FILE=');
// A second server on the same database: concurrency tests send requests to both at once.
// (PHP_CLI_SERVER_WORKERS is not available on Windows, so two processes stand in for workers.)
$port2 = $port - 1;
putenv("TEST_BASE_URL_2=http://127.0.0.1:{$port2}");

// No fixed credentials anywhere: the seeded accounts get fresh random passwords every run.
foreach (['admin', 'staff', 'boarder'] as $role) {
    $password = bin2hex(random_bytes(6)) . 'Aa1!';
    putenv('SEED_' . strtoupper($role) . "_PASSWORD={$password}");
    putenv('TEST_' . strtoupper($role) . "_PASSWORD={$password}");
}

foreach (['database/migrate.php', 'database/seed.php'] as $script) {
    exec(escapeshellarg($php) . ' ' . escapeshellarg("{$root}/{$script}") . ' 2>&1', $out, $code);
    if ($code !== 0) {
        echo implode("\n", $out), "\nSetup failed at {$script}\n";
        exit(1);
    }
}

// Same upload limits as start-system.bat, so upload tests match real use.
$devNull = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
$servers = [];
foreach ([$port, $port2] as $p) {
    $servers[] = proc_open(
        [$php, '-d', 'upload_max_filesize=20M', '-d', 'post_max_size=25M',
            '-S', "127.0.0.1:{$p}", '-t', "{$root}/public", "{$root}/public/index.php"],
        [1 => ['file', $devNull, 'w'], 2 => ['file', $devNull, 'w']],
        $pipes,
        $root
    );
}
foreach ([$port, $port2] as $p) {
    for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $p); $i++) {
        usleep(100000);
    }
}

$tests = glob(__DIR__ . '/test_*.php');
if (!in_array('--with-ai', $argv, true)) {
    $tests = array_filter($tests, fn ($t) => basename($t) !== 'test_ai_assistant.php');
}
// `php tests/run.php wp02` runs only the files whose name contains "wp02".
$only = array_values(array_filter(array_slice($argv, 1), fn ($a) => !str_starts_with($a, '--')));
if ($only) {
    $tests = array_filter($tests, fn ($t) => (bool) array_filter($only, fn ($o) => str_contains(basename($t), $o)));
}

// Uploads from test requests land in the real storage/uploads; remove them afterwards.
$uploadsBefore = glob("{$root}/storage/uploads/*/*");

$failed = [];
foreach ($tests as $test) {
    echo "\n### " . basename($test) . "\n";
    passthru(escapeshellarg($php) . ' ' . escapeshellarg($test), $code);
    if ($code !== 0) {
        $failed[] = basename($test);
    }
}

foreach ($servers as $server) {
    proc_terminate($server);
}
array_map('unlink', array_diff(glob("{$root}/storage/uploads/*/*"), $uploadsBefore));

echo "\n" . str_repeat('=', 50) . "\n";
echo count($tests) - count($failed) . '/' . count($tests) . " test files passed.\n";
foreach ($failed as $f) {
    echo "  FAILED: {$f}\n";
}
exit($failed ? 1 : 0);
