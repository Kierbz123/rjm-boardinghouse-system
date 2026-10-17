<?php
/**
 * WP-1 (F-01, F-02): no published passwords; seeded accounts must choose their own password;
 * the tunnel launcher refuses while any account still has a default password or the database
 * login is root-without-password; the .env file is loaded safely.
 */
require __DIR__ . '/lib.php';

$project = dirname(__DIR__);
$mysql = new PDO(sprintf('mysql:host=%s;port=%s', getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_PORT') ?: '3306'), getenv('DB_USER') ?: 'root', getenv('DB_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$testDb = (string) getenv('DB_NAME');
$cleanup = [];
register_shutdown_function(function () use (&$cleanup) {
    foreach ($cleanup as $fn) {
        try {
            $fn();
        } catch (Throwable $e) {
        }
    }
});

/** Runs a PHP script in a child process with a controlled environment; returns [exit code, output]. */
function phpRun(array $args, array $env = [], array $drop = []): array
{
    $base = getenv();
    foreach ($drop as $k) {
        unset($base[$k]);
    }
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
    $proc = proc_open($cmd, [1 => ['pipe', 'w']], $pipes, dirname(__DIR__), $env + $base);
    $out = stream_get_contents($pipes[1]);
    return [proc_close($proc), $out];
}

/** A throwaway MariaDB login for the test database, or null when this server cannot create users. */
function makeDbUser(PDO $mysql, string $db, array &$cleanup): ?array
{
    $user = 'rjm_t_' . uniq();
    $pass = bin2hex(random_bytes(12));
    try {
        foreach (['localhost', '127.0.0.1'] as $host) {
            $mysql->exec("CREATE USER '{$user}'@'{$host}' IDENTIFIED BY '{$pass}'");
            $mysql->exec("GRANT ALL ON `{$db}`.* TO '{$user}'@'{$host}'");
        }
    } catch (PDOException $e) {
        echo '  (this MariaDB cannot create users: ' . substr($e->getMessage(), 0, 90) . ")
";
        return null;
    }
    $cleanup[] = function () use ($mysql, $user) {
        foreach (['localhost', '127.0.0.1'] as $host) {
            $mysql->exec("DROP USER IF EXISTS '{$user}'@'{$host}'");
        }
    };
    return [$user, $pass];
}

$published = ['Admin' . 'Pass123!', 'Staff' . 'Pass123!', 'Boarder' . 'Pass123!'];

section('Seeding: random passwords, forced change');
$scratch = 'rjm_wp01_' . uniq();
$mysql->exec("CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4");
$cleanup[] = fn () => $mysql->exec("DROP DATABASE IF EXISTS `{$scratch}`");
$noSeedEnv = ['DB_NAME' => $scratch, 'APP_ENV_FILE' => 'none'];
$dropSeed = ['SEED_ADMIN_PASSWORD', 'SEED_STAFF_PASSWORD', 'SEED_BOARDER_PASSWORD'];
phpRun(['database/migrate.php'], $noSeedEnv, $dropSeed);
[$code, $out] = phpRun(['database/seed.php'], $noSeedEnv, $dropSeed);
preg_match_all('/^\s+(\w+@rjm\.test)\s+(\S+)\s*$/m', $out, $m, PREG_SET_ORDER);
$printed = array_column($m, 2, 1);
check($code === 0 && count($printed) === 3, 'the seed script prints the three generated passwords once', trim($out));
$scratchDb = new PDO(sprintf('mysql:host=127.0.0.1;dbname=%s', $scratch), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$hashes = $scratchDb->query('SELECT email, password_hash, must_change_password FROM users')->fetchAll(PDO::FETCH_UNIQUE);
$ok = true;
foreach (['admin@rjm.test', 'staff@rjm.test', 'boarder@rjm.test'] as $email) {
    $p = $printed[$email] ?? '';
    $ok = $ok && strlen($p) >= 14 && !in_array($p, $published, true) && isset($hashes[$email]) && password_verify($p, $hashes[$email]['password_hash']);
}
check($ok && count(array_unique($printed)) === 3, 'each seeded password is random, distinct, at least 14 characters, and not a published one');
check(count(array_filter($hashes, fn ($h) => (int) $h['must_change_password'] === 1)) === 3, 'every generated account must choose a new password at first sign-in');
foreach ($published as $old) {
    check(!array_filter($hashes, fn ($h) => password_verify($old, $h['password_hash'])), 'a published demo password does not work on a fresh seed');
}
check((int) q1('SELECT COUNT(*) FROM users WHERE email IN ("admin@rjm.test","staff@rjm.test","boarder@rjm.test") AND must_change_password = 1') === 0,'accounts whose password the installer chose are not forced to change (the test database)');

section('Forced change at the door');
$admin = seededClient('admin');
$res = makeResident($admin);
q('UPDATE users SET must_change_password = 1 WHERE id = ?', [$res['id']]);
$c = $res['client'];
$r = $c->get('/portal/dashboard');
check($r->code === 302 && $r->loc === '/profile', 'a flagged account is sent to /profile instead of its dashboard', "HTTP {$r->code} {$r->loc}");
check($c->get('/notifications')->loc === '/profile', 'other pages bounce too');
$api = $c->get('/api/notifications/unread');
check($api->code === 403 && str_contains($api->body, 'password'), 'API calls get a JSON 403 that says a password change is needed', "HTTP {$api->code}");
$page = $c->get('/profile');
check($page->code === 200 && stripos($page->body, 'choose a new password') !== false, 'the profile page shows the "choose a new password" banner');
check($c->req('POST', '/logout', ['csrf_token' => $c->token()])->loc === '/login', 'signing out is still possible');
$c = new Client('res2');
$c->login($res['email'], $res['password']);
$c->post('/profile/password', ['current_password' => $res['password'], 'new_password' => 'Brand-new-tulip-orbit', 'confirm_password' => 'Brand-new-tulip-orbit']);
check((int) q1('SELECT must_change_password FROM users WHERE id = ?', [$res['id']]) === 0 && q1('SELECT password_changed_at FROM users WHERE id = ?', [$res['id']]) !== null, 'choosing a new password clears the flag and records when');
check($c->get('/portal/dashboard')->code === 200, 'the account can then use the system');
q('UPDATE users SET must_change_password = 1 WHERE id = ?', [$res['id']]);
$admin->post("/admin/boarders/{$res['id']}/password", ['new_password' => 'Admin-set-cobalt-fern']);
check((int) q1('SELECT must_change_password FROM users WHERE id = ?', [$res['id']]) === 1, 'an admin reset does not count as the person choosing their own password');
q('UPDATE users SET must_change_password = 0 WHERE id = ?', [$res['id']]);

section('Before going online');
$db1 = ['DB_NAME' => $testDb, 'APP_ENV_FILE' => 'none'];
// Other test files leave accounts on an admin-set temporary password (rightly flagged by the check);
// clear those so this section sees only what it sets up.
q('UPDATE users SET must_change_password = 0 WHERE id <> ?', [$res['id']]);
q('UPDATE users SET must_change_password = 1 WHERE id = ?', [$res['id']]);
[$code, $out] = phpRun(['bin/pre-online-check.php'], $db1);
check($code === 1 && str_contains($out, $res['email']), 'the pre-online check lists accounts that still have a default password', trim($out));
q('UPDATE users SET must_change_password = 0 WHERE id = ?', [$res['id']]);
[$code, $out] = phpRun(['bin/pre-online-check.php'], $db1);
check($code === 1 && str_contains($out, 'root'), 'it also objects while the database login is root without a password', trim($out));
$made = makeDbUser($mysql, $testDb, $cleanup);
[$dbUser, $dbPass] = $made ?? ['rjm_no_such_login', 'x'];
$db2 = ['DB_NAME' => $testDb, 'DB_USER' => $dbUser, 'DB_PASS' => $dbPass, 'APP_ENV_FILE' => 'none'];
[$code, $out] = phpRun(['bin/pre-online-check.php'], $db2);
$made ? check($code === 0, 'with no default passwords and a real database login it passes', trim($out)) : skip('the pre-online check passes with a real database login', 'this MariaDB cannot create users (its privilege tables are damaged)');

if (PHP_OS_FAMILY === 'Windows') {
    $ps = function (array $env) use ($project): array {
        $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($project . '\bin\go-online.ps1') . ' -CheckOnly 2>&1';
        $proc = proc_open($cmd, [1 => ['pipe', 'w']], $pipes, $project, $env + getenv());
        $out = stream_get_contents($pipes[1]);
        return [proc_close($proc), $out];
    };
    q('UPDATE users SET must_change_password = 1 WHERE id = ?', [$res['id']]);
    [$code, $out] = $ps($db1);
    check($code !== 0 && str_contains($out, $res['email']), 'the tunnel launcher REFUSES to start while an account has a default password', "exit {$code}: " . trim(substr($out, 0, 200)));
    q('UPDATE users SET must_change_password = 0 WHERE id = ?', [$res['id']]);
    [$code, $out] = $ps($db1);
    check($code !== 0 && str_contains($out, 'root'), 'the launcher refuses while the database login is root without a password', "exit {$code}: " . trim(substr($out, 0, 200)));
    [$code, $out] = $ps($db2);
    $made ? check($code === 0, 'the launcher check passes when everything is in order', "exit {$code}: " . trim(substr($out, 0, 200))) : skip('the launcher passes when everything is in order', 'this MariaDB cannot create users (its privilege tables are damaged)');
}

section('.env loading and the database user');
$envFile = sys_get_temp_dir() . '/rjm_env_' . uniq();
$cleanup[] = fn () => @unlink($envFile);
$probe = ['tests/helpers/whoami.php'];
file_put_contents($envFile, "# comment\nDB_USER={$dbUser}\nDB_PASS={$dbPass}\n");
[$code, $out] = phpRun($probe, ['DB_NAME' => $testDb, 'APP_ENV_FILE' => $envFile], ['DB_USER', 'DB_PASS']);
$info = json_decode($out, true) ?: [];
$made ? check($code === 0 && str_starts_with($info['who'] ?? '', $dbUser) && empty($info['insecure']), 'values in the .env file are used', trim($out)) : skip('values in the .env file are used', 'this MariaDB cannot create users (its privilege tables are damaged)');
file_put_contents($envFile, "DB_USER={$dbUser}\nDB_PASS=wrong-password\n");
[$code, $out] = phpRun($probe, ['DB_NAME' => $testDb, 'APP_ENV_FILE' => $envFile], ['DB_USER', 'DB_PASS']);
$info = json_decode($out, true) ?: [];
check($code === 0 && !empty($info['fellBack']) && !empty($info['insecure']), 'a stale .env login falls back to root, flagged as insecure, instead of taking the system down', trim($out));
[$code] = phpRun($probe, ['DB_NAME' => $testDb, 'APP_ENV_FILE' => $envFile, 'DB_USER' => $dbUser, 'DB_PASS' => 'wrong-password'], []);
check($code !== 0, 'real environment variables are never silently replaced: a wrong explicit login fails');

if ($made) {
    $setupUser = 'rjm_s_' . uniq();
    $cleanup[] = function () use ($mysql, $setupUser) {
        foreach (['localhost', '127.0.0.1'] as $host) {
            $mysql->exec("DROP USER IF EXISTS '{$setupUser}'@'{$host}'");
        }
    };
    @unlink($envFile);
    file_put_contents($envFile, "DB_HOST=127.0.0.1\nOTHER=keep-me\n");
    $setupArgs = ['bin/setup-db-user.php', "--user={$setupUser}", "--db={$testDb}", "--env-file={$envFile}"];
    [$code, $out] = phpRun($setupArgs, ['APP_ENV_FILE' => 'none']);
    $written = (string) @file_get_contents($envFile);
    check($code === 0 && str_contains($written, "DB_USER={$setupUser}") && preg_match('/^DB_PASS=\S{24,}$/m', $written) && str_contains($written, 'OTHER=keep-me'), 'the setup script creates the login and writes it to .env, keeping other lines', trim($out));
    check(!str_contains($out, trim(explode('DB_PASS=', $written)[1] ?? 'x')), 'the setup script never prints the password');
    [$code, $out] = phpRun(array_merge($probe, []), ['DB_NAME' => $testDb, 'APP_ENV_FILE' => $envFile], ['DB_USER', 'DB_PASS']);
    $info = json_decode($out, true) ?: [];
    check($code === 0 && str_starts_with($info['who'] ?? '', $setupUser) && empty($info['insecure']), 'the app can then connect with the new login', trim($out));
    [$code, $out] = phpRun($setupArgs, ['APP_ENV_FILE' => 'none']);
    check($code !== 0 && str_contains($out, '--reset'), 'running it again without --reset refuses to change an existing login');
    [$code] = phpRun(array_merge($setupArgs, ['--reset']), ['APP_ENV_FILE' => 'none']);
    check($code === 0, '--reset sets a new password');
} else {
    skip('bin/setup-db-user.php creates the login, writes .env and refuses a second run without --reset', 'this MariaDB cannot create users (its privilege tables are damaged)');
}

section('No published password anywhere in the repository');
$files = array_filter(explode("\n", (string) shell_exec('cd ' . escapeshellarg(dirname($project)) . ' && git ls-files --cached --others --exclude-standard')));
$hits = [];
foreach ($files as $file) {
    $path = dirname($project) . '/' . $file;
    if (!is_file($path) || filesize($path) > 2_000_000 || preg_match('#\.(woff2?|png|jpe?g|webp|gif|ico|mp4|zip)$#i', $file)) {
        continue;
    }
    $text = (string) file_get_contents($path);
    foreach ($published as $old) {
        if (str_contains($text, $old)) {
            $hits[] = $file;
            break;
        }
    }
}
check(!$hits, 'no tracked or untracked source/doc file contains a published demo password', implode(', ', $hits));
