<?php
/**
 * The migration runner itself: apply, status, rollback, trigger files. Uses a synthetic
 * migrations folder and a scratch database, never the project's migrations or the test DB.
 */
require __DIR__ . '/lib.php';

$scratch = 'rjm_migrate_selftest_' . uniq();
$root = new PDO(sprintf('mysql:host=%s;port=%s', getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_PORT') ?: '3306'), getenv('DB_USER') ?: 'root', getenv('DB_PASS') ?: '');
$root->exec("CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4");
$dir = sys_get_temp_dir() . '/rjm_migrations_' . uniq();
mkdir($dir);
register_shutdown_function(function () use ($root, $scratch, $dir) {
    $root->exec("DROP DATABASE IF EXISTS `{$scratch}`");
    array_map('unlink', glob("{$dir}/*"));
    @rmdir($dir);
});

file_put_contents("{$dir}/0001_widgets.sql", "CREATE TABLE widgets (id INT PRIMARY KEY, name VARCHAR(20) NOT NULL);\nINSERT INTO widgets VALUES (1, 'a');\n");
file_put_contents("{$dir}/0001_widgets.down.sql", "DROP TABLE IF EXISTS widgets;\n");
file_put_contents("{$dir}/0002_guard.sql", "-- @delimiter //\nCREATE TRIGGER widgets_guard BEFORE INSERT ON widgets FOR EACH ROW\nBEGIN\n    IF NEW.name = 'bad' THEN\n        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'bad name';\n    END IF;\nEND\n//\n");
file_put_contents("{$dir}/0002_guard.down.sql", "DROP TRIGGER IF EXISTS widgets_guard;\n");
file_put_contents("{$dir}/0003_nodown.sql", "CREATE TABLE gadgets (id INT PRIMARY KEY);\n");

$migrate = function (string ...$args) use ($scratch, $dir): array {
    $cmd = sprintf('%s %s %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg(__DIR__ . '/../database/migrate.php'), implode(' ', array_map('escapeshellarg', $args)));
    $env = ['DB_NAME' => $scratch, 'MIGRATIONS_DIR' => $dir] + getenv();
    $proc = proc_open($cmd, [1 => ['pipe', 'w']], $pipes, null, $env);
    $out = stream_get_contents($pipes[1]);
    return [proc_close($proc), $out];
};
$scratchDb = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s', getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_PORT') ?: '3306', $scratch), getenv('DB_USER') ?: 'root', getenv('DB_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tables = fn () => $scratchDb->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

section('Apply');
[$code, $out] = $migrate();
check($code === 0 && str_contains($out, '3 migration(s) applied'), 'all pending migrations apply', trim($out));
check(in_array('widgets', $tables(), true) && in_array('gadgets', $tables(), true), 'the tables exist');
$blocked = false;
try {
    $scratchDb->exec("INSERT INTO widgets VALUES (2, 'bad')");
} catch (PDOException $e) {
    $blocked = str_contains($e->getMessage(), 'bad name');
}
check($blocked, 'a trigger defined with the custom delimiter works');
[$code, $out] = $migrate();
check($code === 0 && str_contains($out, 'up to date'), 'a second run does nothing');

section('Status');
[$code, $out] = $migrate('--status');
check($code === 0 && substr_count($out, 'applied') >= 3 && str_contains($out, 'rollback: yes') && str_contains($out, 'rollback: -'), 'status lists every migration and whether it can be rolled back', trim($out));

section('Rollback');
[$code, $out] = $migrate('--rollback');
check($code !== 0 && str_contains($out, 'Cannot roll back 0003_nodown.sql'), 'a migration with no .down.sql refuses to roll back and says so');
file_put_contents("{$dir}/0003_nodown.down.sql", "DROP TABLE IF EXISTS gadgets;\n");
[$code, $out] = $migrate('--rollback');
check($code === 0 && !in_array('gadgets', $tables(), true), 'rolling back drops what the migration made');
[$code, $out] = $migrate('--rollback=2');
check($code === 0 && !in_array('widgets', $tables(), true), 'rolling back several steps undoes the trigger and the table');
check((int) $scratchDb->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() === 0, 'the migration log is empty again');
[$code, $out] = $migrate();
check($code === 0 && str_contains($out, '3 migration(s) applied') && in_array('widgets', $tables(), true), 'up, down and up again works');
[$code] = $migrate('--bogus');
check($code === 2, 'an unknown option is rejected');
