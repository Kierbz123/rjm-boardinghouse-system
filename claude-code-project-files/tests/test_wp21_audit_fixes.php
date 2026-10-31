<?php
/**
 * WP-21, the first batch of fixes from PAGE-IPO-AUDIT-2026-10-29.md:
 *
 *  1. The 15-second notification poll must not keep a session alive for ever;
 *     the SOS poll on the staff dashboard still does (the front desk watches it).
 *  2. Approving, rejecting and reversing a payment leaves an audit trail.
 *  4. Maintenance and Incident History can be searched and filtered, their counts
 *     cover the whole filtered set rather than the 50 rows on screen, and an
 *     out-of-range ?page= lands on the last page.
 *  6. Admin has sidebar entries for the queue and incidents; a resident has one
 *     for Incident History, and that page shows them no staff-only links.
 */
require __DIR__ . '/lib.php';

use App\Support\NavRegistry;

/** The server-side session file behind a logged-in client, or null when it cannot be located. */
function sessionFileFor(Client $c): ?string
{
    if (!preg_match('/PHPSESSID\s+(\S+)/', (string) @file_get_contents($c->jar), $m)) {
        return null;
    }
    $file = rtrim(session_save_path() ?: sys_get_temp_dir(), "\\/") . DIRECTORY_SEPARATOR . 'sess_' . $m[1];
    return is_file($file) ? $file : null;
}

function lastActivity(string $file): ?int
{
    return preg_match('/last_activity\|i:(\d+);/', (string) @file_get_contents($file), $m) ? (int) $m[1] : null;
}

function setLastActivity(string $file, int $when): void
{
    file_put_contents($file, preg_replace('/last_activity\|i:\d+;/', "last_activity|i:{$when};", (string) file_get_contents($file)));
}

/** The numbers in the page's KPI cards, in order. */
function kpiValues(string $html): array
{
    preg_match_all('/class="kpi-value[^"]*"[^>]*>\s*([\d,]+)\s*</', $html, $m);
    return array_map(fn ($n) => (int) str_replace(',', '', $n), $m[1]);
}

/** How many <tbody> rows the history table shows. */
function rowCount(string $html): int
{
    return preg_match('#<tbody[^>]*>(.*?)</tbody>#s', $html, $m) ? substr_count($m[1], '<tr') : 0;
}

$admin = seededClient('admin');
$staff = seededClient('staff');

section('1. The notification poll does not keep a session alive');

$file = sessionFileFor($admin);
if ($file === null) {
    skip('idle timeout', 'session file not found (session.save_path differs between CLI and server)');
} else {
    $stale = time() - 29 * 60;
    setLastActivity($file, $stale);
    $admin->get('/api/notifications/unread');
    check(lastActivity($file) === $stale, 'the 15s notification poll leaves last_activity alone', 'now ' . var_export(lastActivity($file), true));

    setLastActivity($file, $stale);
    $admin->get('/admin/dashboard');
    check((int) lastActivity($file) > $stale, 'a real page view does refresh last_activity');

    setLastActivity($file, time() - 31 * 60);
    $res = $admin->get('/api/notifications/unread');
    check(($res->loc ?? '') === '/login', 'a session idle for 31 minutes is ended by the poll itself', 'HTTP ' . $res->code . ' -> ' . ($res->loc ?? 'no redirect'));

    $admin = seededClient('admin'); // the session above is gone
}

$staffFile = sessionFileFor($staff);
if ($staffFile === null) {
    skip('SOS poll keeps the front desk signed in', 'session file not found');
} else {
    $stale = time() - 29 * 60;
    setLastActivity($staffFile, $stale);
    $staff->get('/api/sos/active');
    check((int) lastActivity($staffFile) > $stale, 'the SOS poll still counts as activity (the front desk watches this page)');
}

section('2. Payment decisions leave an audit trail');

$room = makeRoom($admin, 1, 3000.0, 1);
$payer = makeResident($admin, $room['beds'][0], 40);
$auditRows = fn (int $paymentId) => q("SELECT action, actor_id, details FROM audit_log WHERE entity_type = 'payment' AND entity_id = ? ORDER BY id", [$paymentId]);
$adminId = (int) q1('SELECT id FROM users WHERE email = "admin@rjm.test"');

$pid = submitReceipt($payer['client'], 3000.0, 'WP21-' . uniq());
check($pid > 0, 'setup: the resident submitted a receipt');
check($auditRows($pid) === [], 'submitting a receipt alone writes no decision row');

$admin->post("/admin/payments/{$pid}/approve", ['approved_amount' => '3000']);
$rows = $auditRows($pid);
check(count($rows) === 1 && $rows[0]['action'] === 'payment_approved', 'approving writes exactly one payment_approved row', json_encode(array_column($rows, 'action')));
check(($rows[0]['actor_id'] ?? null) == $adminId, 'the row names the admin who approved');
check(str_contains((string) ($rows[0]['details'] ?? ''), '3000'), 'the row keeps the approved amount', (string) ($rows[0]['details'] ?? ''));

$admin->post("/admin/payments/{$pid}/approve", ['approved_amount' => '3000']);
check(count($auditRows($pid)) === 1, 'approving an already-approved payment writes nothing more');

$admin->post("/admin/payments/{$pid}/reject", ['reason' => 'WP21 reversal check']);
$rows = $auditRows($pid);
check(count($rows) === 2 && ($rows[1]['action'] ?? '') === 'payment_reversed', 'reversing an approved payment is recorded as payment_reversed', json_encode(array_column($rows, 'action')));
check(str_contains((string) ($rows[1]['details'] ?? ''), 'WP21 reversal check'), 'the reversal keeps the admin reason');

$pid2 = submitReceipt($payer['client'], 1500.0, 'WP21-' . uniq());
$admin->post("/admin/payments/{$pid2}/reject", ['reason' => 'blurry receipt']);
$rows = $auditRows($pid2);
check(count($rows) === 1 && ($rows[0]['action'] ?? '') === 'payment_rejected', 'rejecting a pending receipt is recorded as payment_rejected', json_encode(array_column($rows, 'action')));

section('4. History pages can be searched, and their counts cover the whole set');

// 55 repairs under one search token: more than one page of 50, with a known resolved/open split.
$mark = 'WP21MARK' . uniq();
$boarderId = (int) $payer['id'];
for ($i = 0; $i < 55; $i++) {
    $resolved = $i < 30;
    q('INSERT INTO maintenance_requests (boarder_id, room_id, category, description, priority_tier, status, resolved_at, resolved_by)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
        $boarderId, $room['id'], $i % 2 ? 'plumbing' : 'electrical',
        $mark . ' leak number ' . $i, $i < 10 ? 'critical' : 'low',
        $resolved ? 'resolved' : 'open', $resolved ? date('Y-m-d H:i:s') : null, $resolved ? $adminId : null,
    ]);
}
// Two rows that differ only by the character an unescaped LIKE would treat as a wildcard.
q('INSERT INTO maintenance_requests (boarder_id, category, description, priority_tier, status) VALUES (?, "other", ?, "low", "open")', [$boarderId, $mark . ' under_score hit']);
q('INSERT INTO maintenance_requests (boarder_id, category, description, priority_tier, status) VALUES (?, "other", ?, "low", "open")', [$boarderId, $mark . ' underXscore miss']);

$page1 = $staff->get('/staff/maintenance/history?q=' . urlencode($mark));
check($page1->code === 200, 'the history page accepts a search term', 'HTTP ' . $page1->code);
check(rowCount($page1->body) === 50, 'page 1 of the filtered set holds 50 rows', (string) rowCount($page1->body));

$kpis = kpiValues($page1->body);
check(($kpis[0] ?? 0) === 57, 'Total Records counts the whole filtered set, not the page', json_encode($kpis));
check(($kpis[1] ?? -1) === 30, 'Resolved counts the whole filtered set, not the page', json_encode($kpis));
check(($kpis[2] ?? -1) === 27, 'Open counts the whole filtered set, not the page', json_encode($kpis));

$resolvedOnly = $staff->get('/staff/maintenance/history?q=' . urlencode($mark) . '&status=resolved');
check((kpiValues($resolvedOnly->body)[0] ?? 0) === 30, 'the status filter narrows the set', json_encode(kpiValues($resolvedOnly->body)));
$critical = $staff->get('/staff/maintenance/history?q=' . urlencode($mark) . '&tier=critical');
check((kpiValues($critical->body)[0] ?? 0) === 10, 'the priority filter narrows the set', json_encode(kpiValues($critical->body)));

$underscore = $staff->get('/staff/maintenance/history?q=' . urlencode($mark . ' under_score'));
check($underscore->has('under_score hit') && !$underscore->has('underXscore miss'), 'an underscore in the search term is a literal, not a wildcard');

$last = $staff->get('/staff/maintenance/history?q=' . urlencode($mark) . '&page=9999');
check(rowCount($last->body) === 7, 'an out-of-range page shows the last page, not an empty table', (string) rowCount($last->body));
check(str_contains($last->body, 'q=' . urlencode($mark)) || str_contains($last->body, 'q=' . rawurlencode($mark)), 'paging links keep the search term');

$incMark = 'WP21INC' . uniq();
q('INSERT INTO incidents (reported_by, type, description, resolved, resolution_notes, resolved_by, resolved_at)
   VALUES (?, "Noise", ?, 1, "settled", ?, NOW())', [$boarderId, $incMark . ' loud music', $adminId]);
q('INSERT INTO incidents (reported_by, type, description) VALUES (?, "Theft", ?)', [$boarderId, $incMark . ' missing slippers']);

$incPage = $staff->get('/staff/incidents/history?q=' . urlencode($incMark));
check((kpiValues($incPage->body)[0] ?? 0) === 2, 'incident history can be searched', json_encode(kpiValues($incPage->body)));
$incOpen = $staff->get('/staff/incidents/history?q=' . urlencode($incMark) . '&status=open');
check($incOpen->has('missing slippers') && !$incOpen->has('loud music'), 'incident history filters by status');

$otherResident = makeResident($admin, null, null);
q('INSERT INTO incidents (reported_by, type, description) VALUES (?, "Noise", ?)', [(int) $otherResident['id'], $incMark . ' someone else reported this']);
$mine = $payer['client']->get('/staff/incidents/history?q=' . urlencode($incMark));
check($mine->code === 200 && !$mine->has('someone else reported this'), 'a resident searching the archive still sees only their own incidents', 'HTTP ' . $mine->code);

section('6. The sidebar offers every page its role may open');

$sidebar = fn (string $role) => array_merge(...array_values(array_map(
    fn ($items) => array_column($items, 'label', 'href'),
    NavRegistry::sidebar($role)
)));
$adminNav = $sidebar('admin');
check(isset($adminNav['/staff/maintenance']), 'admin has a sidebar link to the Maintenance Queue');
check(isset($adminNav['/staff/incidents']), 'admin has a sidebar link to Incidents');
$boarderNav = $sidebar('boarder');
check(isset($boarderNav['/staff/incidents/history']), 'a resident has a sidebar link to Incident History');

$residentHistory = $payer['client']->get('/staff/incidents/history');
check($residentHistory->code === 200, 'a resident can open Incident History', 'HTTP ' . $residentHistory->code);
check(!str_contains($residentHistory->body, 'href="/staff/dashboard"') && !str_contains($residentHistory->body, 'href="/staff/maintenance/history"'),
    'the resident view of Incident History offers no staff-only links');
