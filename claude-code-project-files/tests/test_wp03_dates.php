<?php
/**
 * WP-3 (F-03, F-05, F-17 part, F-31): move-in / move-out dates are bounded at the form, the
 * controller and the database; a runaway number of rent rows is impossible; a future move-out is a
 * SCHEDULED move-out (resident and bed stay until the day) that can be cancelled; a read-only
 * finder reports absurd rows without touching them.
 */
require __DIR__ . '/lib.php';

use App\Services\BillingService;
use App\Services\HousekeepingService;

$project = dirname(__DIR__);
$admin = seededClient('admin');

function wp3PhpRun(array $args, array $env = []): array
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
    $proc = proc_open($cmd, [1 => ['pipe', 'w']], $pipes, dirname(__DIR__), $env + getenv());
    $out = stream_get_contents($pipes[1]);
    return [proc_close($proc), $out];
}

$opening = (string) q1("SELECT setting_value FROM settings WHERE setting_key = 'property_opening_date'");
check($opening !== '' && $opening !== '0000-00-00', 'the property opening date is stored in settings', $opening);
$setOpening = fn (string $d) => q("UPDATE settings SET setting_value = ? WHERE setting_key = 'property_opening_date'", [$d]);
register_shutdown_function(fn () => $setOpening($opening));

$room = makeRoom($admin, 3, 3000, 3);
$res = makeResident($admin, $room['beds'][0], 20);
$id = $res['id'];
$state = fn () => q('SELECT move_in_date, move_out_date, status, bed_id FROM boarder_profiles WHERE user_id = ?', [$id])[0];
$cycles = fn () => (int) q1('SELECT COUNT(*) FROM rent_charges WHERE boarder_id = ?', [$id]);
$dates = fn (string $in, string $out = '') => $admin->post("/admin/boarders/{$id}/dates", ['move_in_date' => $in, 'move_out_date' => $out]);
$twentyAgo = date('Y-m-d', strtotime('-20 days'));

section('Bounds: form, controller, database');
$limitFuture = date('Y-m-d', strtotime('+12 months'));
$before = $state();
$beforeCycles = $cycles();
foreach ([
    '1900-01-01' => 'long before the property opened',
    '0001-01-01' => 'year 1',
    date('Y-m-d', strtotime("{$opening} -1 day")) => 'the day before the opening date',
    date('Y-m-d', strtotime('+12 months +1 day')) => 'more than 12 months ahead',
    '2099-01-01' => 'year 2099',
] as $bad => $why) {
    $r = $dates($bad);
    $msg = $admin->flash($r);
    check($state() === $before && $cycles() === $beforeCycles && stripos($msg, 'between') !== false, "move-in {$bad} ({$why}) is refused with a range message and nothing changes", substr($msg, 0, 0));
}
$r = $dates($opening);
check($state()['move_in_date'] === $opening, "move-in exactly on the opening date ({$opening}) is accepted");
$r = $dates($limitFuture);
check($state()['move_in_date'] === $limitFuture && $cycles() === 0, 'move-in exactly 12 months ahead is accepted and charges nothing yet');
$dates($twentyAgo);
check(stripos($admin->flash($dates($twentyAgo, date('Y-m-d', strtotime('-30 days')))), 'cannot be before') !== false, 'move-out before move-in is refused');
check(stripos($admin->flash($dates($twentyAgo, date('Y-m-d', strtotime('+6 years')))), 'between') !== false, 'a move-out more than 5 years ahead is refused');
$html = $admin->get("/admin/boarders/{$id}", true)->body;
check(preg_match('/name="move_in_date"[^>]*min="' . preg_quote($opening, '/') . '"[^>]*max="' . preg_quote($limitFuture, '/') . '"/', $html) === 1
    || preg_match('/name="move_in_date"[^>]*max="' . preg_quote($limitFuture, '/') . '"[^>]*min="' . preg_quote($opening, '/') . '"/', $html) === 1, 'the date picker itself carries the same min and max');

$blocked = fn (string $sql, array $p = []) => (function () use ($sql, $p) {
    try {
        db()->prepare($sql)->execute($p);
        return false;
    } catch (PDOException $e) {
        return true;
    }
})();
check($blocked('UPDATE boarder_profiles SET move_in_date = "1900-01-01" WHERE user_id = ?', [$id]), 'the database trigger refuses a 1900 move-in even from raw SQL');
check($blocked('UPDATE boarder_profiles SET move_in_date = ? WHERE user_id = ?', [date('Y-m-d', strtotime('+3 years')), $id]), 'the database trigger refuses a move-in 3 years ahead');
check($blocked('UPDATE boarder_profiles SET move_out_date = DATE_SUB(move_in_date, INTERVAL 1 DAY) WHERE user_id = ?', [$id]), 'the database trigger refuses a move-out before the move-in');
db()->prepare('INSERT INTO users (role, name, email, password_hash) VALUES ("boarder", "Raw", ?, "x")')->execute(['raw' . uniq() . '@t.test']);
$rawId = (int) db()->lastInsertId();
check($blocked('INSERT INTO boarder_profiles (user_id, status, move_in_date) VALUES (?, "active", "1900-01-01")', [$rawId]), 'the trigger also guards inserts');
check(!$blocked('UPDATE boarder_profiles SET notes = "fine" WHERE user_id = ?', [$id]), 'an unrelated update on a valid row is not blocked');
q('DELETE FROM users WHERE id = ?', [$rawId]);

section('Runaway rent rows cannot happen');
$setOpening('1900-01-01');
q('UPDATE boarder_profiles SET move_in_date = "1900-01-01" WHERE user_id = ?', [$id]);
$threw = false;
try {
    BillingService::calculateBalance($id);
} catch (RuntimeException $e) {
    $threw = true;
}
check($threw, 'recalculating a balance that would need more than 120 rent cycles stops with an error');
check($cycles() <= 120, 'and no runaway rows were written', "rows={$cycles()}");
check($admin->get('/admin/dashboard')->code === 200 && $admin->get('/admin/boarders')->code === 200 && $admin->get('/admin/penalty-rules')->code === 200, 'the dashboard, resident list and penalties pages still open with one bad resident');
check($admin->get("/admin/boarders/{$id}")->code < 500, "the bad resident's own page does not crash");
$r = $admin->post('/admin/penalties/run-check');
check($r->code < 500, 'the late-fee check skips the bad resident instead of crashing');

section('Read-only finder');
$setOpening($opening); // the 1900 move-in is outside the real range again
[$code, $out] = wp3PhpRun(['bin/find-absurd-rent.php']);
check($code === 1 && str_contains($out, $res['email']), 'bin/find-absurd-rent.php lists the resident whose dates are absurd (exit 1)', trim(substr($out, 0, 300)));
$snapshot = fn () => [q1('SELECT COUNT(*) FROM rent_charges'), q1('SELECT COUNT(*) FROM boarder_profiles'), q1('SELECT SUM(outstanding_balance) FROM boarder_profiles'), q1('SELECT MAX(id) FROM penalties')];
$a = $snapshot();
wp3PhpRun(['bin/find-absurd-rent.php']);
check($snapshot() === $a, 'running the finder changed nothing');
q('UPDATE boarder_profiles SET move_in_date = ? WHERE user_id = ?', [$twentyAgo, $id]);
$admin->get("/admin/boarders/{$id}", true);
[$code, $out] = wp3PhpRun(['bin/find-absurd-rent.php']);
check(!str_contains($out, $res['email']), 'once the date is valid the finder no longer lists them');

section('Scheduled move-out');
$out20 = date('Y-m-d', strtotime('+20 days'));
$msg = $admin->flash($dates($twentyAgo, $out20));
$s = $state();
check($s['status'] === 'active' && $s['bed_id'] !== null && $s['move_out_date'] === $out20, 'a future move-out keeps the resident ACTIVE and in their bed', json_encode($s));
check(q1('SELECT status FROM beds WHERE id = ?', [$room['beds'][0]]) === 'occupied', 'the bed stays occupied');
check(stripos($msg, 'scheduled') !== false, 'the admin is told it is scheduled', substr($msg, 0, 120));
$page = $admin->get("/admin/boarders/{$id}", true)->body;
check(str_contains($page, 'Cancel move-out') && str_contains($page, $out20), 'the profile shows the scheduled date with a Cancel move-out button');
check((int) q1("SELECT COUNT(*) FROM audit_log WHERE action = 'move_out_scheduled' AND entity_id = ?", [$id]) === 1, 'scheduling is written to the audit trail');
check(HousekeepingService::applyDueMoveOuts(new DateTimeImmutable('today')) === 0 && $state()['status'] === 'active', 'the daily job does nothing before the date');

$admin->post("/admin/boarders/{$id}/move-out/cancel");
$s = $state();
check($s['move_out_date'] === null && $s['status'] === 'active' && $s['bed_id'] !== null, 'cancelling clears the date and changes nothing else');
check((int) q1("SELECT COUNT(*) FROM audit_log WHERE action = 'move_out_cancelled' AND entity_id = ?", [$id]) === 1, 'cancelling is written to the audit trail');
check(stripos($admin->flash($admin->post("/admin/boarders/{$id}/move-out/cancel")), 'no scheduled') !== false, 'cancelling when nothing is scheduled says so');
check($admin->post("/admin/boarders/{$id}/move-out/cancel", [], false)->code === 400, 'cancel without a CSRF token is refused');
check($res['client']->post("/admin/boarders/{$id}/move-out/cancel")->code === 403, 'a resident cannot cancel');

$dates($twentyAgo, $out20);
$moved = HousekeepingService::applyDueMoveOuts(new DateTimeImmutable('+21 days'));
$s = $state();
check($moved >= 1 && $s['status'] === 'moved_out' && $s['bed_id'] === null, 'on the day, the job marks the resident moved out and frees the bed', json_encode($s));
check(q1('SELECT status FROM beds WHERE id = ?', [$room['beds'][0]]) === 'vacant', 'the bed is vacant');
check((int) q1("SELECT COUNT(*) FROM boarder_status_log WHERE boarder_id = ? AND reason LIKE '%Scheduled move-out%'", [$id]) === 1, 'the status log says why');
check(HousekeepingService::applyDueMoveOuts(new DateTimeImmutable('+30 days')) === 0, 'running the job again changes nothing');

$res2 = makeResident($admin, $room['beds'][1], 20);
$admin->post("/admin/boarders/{$res2['id']}/dates", ['move_in_date' => $twentyAgo, 'move_out_date' => date('Y-m-d', strtotime('-1 day'))]);
check(q1('SELECT status FROM boarder_profiles WHERE user_id = ?', [$res2['id']]) === 'moved_out', 'a move-out date in the past still takes effect at once');

$res3 = makeResident($admin, $room['beds'][2], 20);
q('UPDATE boarder_profiles SET move_out_date = ? WHERE user_id = ?', [date('Y-m-d', strtotime('-1 day')), $res3['id']]);
$admin->get('/admin/dashboard', true);
check(q1('SELECT status FROM boarder_profiles WHERE user_id = ?', [$res3['id']]) === 'moved_out', 'opening the admin dashboard applies a move-out whose day has arrived');
