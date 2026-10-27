<?php
/**
 * WP-4 (F-04): residents are archived, never erased. Repairs, incidents, SOS and payment history
 * always survive, in the application AND in the database (foreign keys refuse a delete).
 */
require __DIR__ . '/lib.php';

$admin = seededClient('admin');
$room = makeRoom($admin, 3, 3000, 3);

$addHistory = function (int $id) {
    db()->prepare('INSERT INTO maintenance_requests (boarder_id, category, description, priority_tier, severity_score, status) VALUES (?, "plumbing", "history", "low", 5, "open")')->execute([$id]);
    db()->prepare('INSERT INTO incidents (reported_by, type, description) VALUES (?, "noise", "history")')->execute([$id]);
    db()->prepare('INSERT INTO sos_alerts (boarder_id, status) VALUES (?, "resolved")')->execute([$id]);
};
$counts = fn (int $id) => [
    (int) q1('SELECT COUNT(*) FROM maintenance_requests WHERE boarder_id = ?', [$id]),
    (int) q1('SELECT COUNT(*) FROM incidents WHERE reported_by = ?', [$id]),
    (int) q1('SELECT COUNT(*) FROM sos_alerts WHERE boarder_id = ?', [$id]),
    (int) q1('SELECT COUNT(*) FROM payments WHERE boarder_id = ?', [$id]),
];

section('Removing a resident archives them');
$a = makeResident($admin, $room['beds'][0], 10);
$addHistory($a['id']);
$before = $counts($a['id']);
$admin->post("/admin/boarders/{$a['id']}/delete", ['reason' => 'Left without notice']);
check((int) q1('SELECT COUNT(*) FROM users WHERE id = ?', [$a['id']]) === 1 && q1('SELECT status FROM users WHERE id = ?', [$a['id']]) === 'archived', 'a resident with only repair / incident / SOS history is archived, not erased');
check($counts($a['id']) === $before && $before[0] + $before[1] + $before[2] === 3, 'their repair, incident and SOS records are all still there', json_encode($counts($a['id'])));
check(q1('SELECT status FROM beds WHERE id = ?', [$room['beds'][0]]) === 'vacant', 'the bed is freed');
check((new Client('x'))->login($a['email'], $a['password'])->loc !== '/portal/dashboard', 'an archived resident cannot sign in');

$b = makeResident($admin);
$admin->post("/admin/boarders/{$b['id']}/delete");
check((int) q1('SELECT COUNT(*) FROM users WHERE id = ?', [$b['id']]) === 1 && q1('SELECT status FROM users WHERE id = ?', [$b['id']]) === 'archived', 'even a resident with NO history is archived (there is no hard delete any more)');
check(!method_exists(\App\Models\BoarderProfile::class, 'delete'), 'BoarderProfile::delete() no longer exists');

section('Archived residents view');
$row = q('SELECT archived_at, archived_by, archive_reason FROM users WHERE id = ?', [$a['id']])[0] ?? [];
check(!empty($row['archived_at']) && (int) $row['archived_by'] === 1 && $row['archive_reason'] === 'Left without notice', 'the date, who archived them and why are stored', json_encode($row));
$list = $admin->get('/admin/staff?view=boarders&archived=1', true)->body;
check(str_contains($list, 'Left without notice') && str_contains($list, 'Test Resident'), 'the archived view shows the reason');
check(preg_match('/Archived (on )?' . preg_quote(date('M j, Y'), '/') . '/i', $list) === 1 || str_contains($list, date('M j, Y')), 'the archived view shows when');
check(!str_contains($admin->get('/admin/staff?view=boarders', true)->body, $a['email']), 'archived residents are not in the current list');
check(flashHas($admin, $admin->post("/admin/boarders/{$b['id']}/restore"), 'restored') && q1('SELECT status FROM users WHERE id = ?', [$b['id']]) === 'active', 'restore brings an archived resident back');
check(q1('SELECT archived_at FROM users WHERE id = ?', [$b['id']]) === null, 'and clears the archive details');
check((int) q1("SELECT COUNT(*) FROM audit_log WHERE action = 'resident_archived' AND entity_id = ?", [$a['id']]) === 1 && (int) q1("SELECT COUNT(*) FROM audit_log WHERE action = 'resident_restored' AND entity_id = ?", [$b['id']]) === 1, 'archive and restore are in the audit trail');
$admin->post("/admin/boarders/{$a['id']}/restore");
$admin->post("/admin/boarders/{$a['id']}/delete", ['reason' => str_repeat('r', 300)]);
check(q1('SELECT status FROM users WHERE id = ?', [$a['id']]) === 'active', 'a reason over 255 characters is refused and nothing is archived');
check($admin->post('/admin/boarders/1/delete')->code < 500 && q1('SELECT status FROM users WHERE id = 1') === 'active', 'the admin account cannot be archived through this route');

section('The database refuses to erase history');
$refused = function (string $sql, array $p = []): bool {
    try {
        db()->prepare($sql)->execute($p);
        return false;
    } catch (PDOException $e) {
        return true;
    }
};
$c = makeResident($admin);
$addHistory($c['id']);
check($refused('DELETE FROM users WHERE id = ?', [$c['id']]), 'a raw DELETE of a resident with repair / incident / SOS history is refused');
$d = makeResident($admin, $room['beds'][1], 40);
$pid = submitReceipt($d['client'], 3000, 'W4-' . uniq());
$admin->post("/admin/payments/{$pid}/approve", ['approved_amount' => 3000]);
check((int) q1('SELECT COUNT(*) FROM payment_allocations WHERE boarder_id = ?', [$d['id']]) > 0, 'a payment was approved and allocated');
check($refused('DELETE FROM users WHERE id = ?', [$d['id']]), 'a raw DELETE of a resident with payments is refused');
$rule = q1("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_allocations' AND CONSTRAINT_NAME = 'fk_pa_boarder'");
check($rule === 'RESTRICT', 'payment_allocations.boarder_id no longer cascades on delete', (string) $rule);
