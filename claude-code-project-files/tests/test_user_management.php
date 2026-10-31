<?php
/** /admin/staff as User Management: one list of residents and staff, filters, paging, and the two existing pages behind tabs. */
require __DIR__ . '/lib.php';

$admin = seededClient('admin');
$tag = uniq();
$pw = 'Tmp-cobalt-river-fern';
$idOf = fn (string $email) => (int) q1('SELECT id FROM users WHERE email = ?', [$email]);
$rowsOf = fn (Res $r) => preg_match_all('/data-user-id="(\d+)"/', $r->body, $m) ? array_map('intval', $m[1]) : [];
$stat = fn (Res $r, string $key) => preg_match('/data-stat="' . $key . '"[^>]*>\s*(\d+)/', $r->body, $m) ? (int) $m[1] : -1;

$room = makeRoom($admin, 2, 3000, 2);
$res = makeResident($admin, $room['beds'][0]);
$admin->post("/admin/boarders/{$res['id']}/info", ['name' => "Mira {$tag}", 'email' => $res['email'], 'status' => '']);
$admin->post('/admin/staff', ['name' => "Paulo {$tag}", 'email' => "paulo{$tag}@t.test", 'password' => $pw, 'phone' => '0917 555 0303']);
$paulo = $idOf("paulo{$tag}@t.test");
$arch = makeResident($admin);
$admin->post("/admin/boarders/{$arch['id']}/info", ['name' => "Arch {$tag}", 'email' => $arch['email'], 'status' => '']);
$admin->post("/admin/boarders/{$arch['id']}/delete", ['reason' => 'test archive']);

section('All Users is the default view');
$page = $admin->get('/admin/staff?q=' . urlencode($tag));
check($page->code === 200 && $page->has('User Management'), 'the page is called User Management');
$ids = $rowsOf($page);
check(count(array_keys($ids, $res['id'])) === 1 && count(array_keys($ids, $paulo)) === 1, 'a resident and a staff member are each listed exactly once');
check(!in_array($arch['id'], $ids, true), 'archived residents are left out unless asked for');
check(!$rowsOf($admin->get('/admin/staff?q=' . urlencode('admin@rjm.test'))), 'administrator accounts are not listed');
check($page->has($room['number']) && $page->has('href="/admin/boarders/' . $res['id'] . '"'), "a resident's room is shown and links to their profile");
check($page->has('href="/admin/staff?view=staff#staff-' . $paulo . '"'), 'a staff member links to their row on the Staff tab');
check(!$page->has('$2y$'), 'no password hash reaches the page');

section('Counts match the database');
$full = $admin->get('/admin/staff');
$sql = fn (string $where) => (int) q1("SELECT COUNT(*) FROM users WHERE role IN ('boarder','staff') AND {$where}");
check($stat($full, 'total') === $sql("status <> 'archived'"), 'Total users = current residents and staff');
check($stat($full, 'boarders') === $sql("role = 'boarder' AND status <> 'archived'"), 'Boarders = current residents');
check($stat($full, 'staff') === $sql("role = 'staff' AND status <> 'archived'"), 'Staff = staff accounts');
check($stat($full, 'active') === $sql("status = 'active'"), 'Active accounts = accounts that can sign in');
check($stat($full, 'inactive') === $sql("status IN ('inactive','archived')"), 'Deactivated or archived = everyone who cannot sign in');

section('Search and filters work together');
$ids = $rowsOf($admin->get('/admin/staff?q=' . urlencode($tag) . '&type=staff'));
check(in_array($paulo, $ids, true) && !in_array($res['id'], $ids, true), 'type=staff shows only staff');
$ids = $rowsOf($admin->get('/admin/staff?q=' . urlencode($tag) . '&type=boarder'));
check(in_array($res['id'], $ids, true) && !in_array($paulo, $ids, true), 'type=boarder shows only residents');
$ids = $rowsOf($admin->get('/admin/staff?q=' . urlencode($tag) . '&status=archived'));
check($ids === [$arch['id']], 'status=archived shows the archived resident');
check($rowsOf($admin->get('/admin/staff?q=' . urlencode($room['number']))) === [$res['id']], 'search finds a resident by room number');
// Paulo's number is stored as "0917 555 0303": an admin may type it any of these ways.
foreach (['09175550303', '0917-555-0303', '+63 917 555 0303', '9175550303'] as $typed) {
    check(in_array($paulo, $rowsOf($admin->get('/admin/staff?q=' . urlencode($typed))), true), "search finds a phone number typed as {$typed}");
}
check(!$rowsOf($admin->get('/admin/staff?q=' . urlencode('zzz' . $tag))), 'a search with no digits does not match every account that has a phone');
$r = $admin->post("/admin/staff/{$paulo}/status", ['status' => 'inactive']);
check($r->loc === '/admin/staff?view=staff', 'a staff action returns to the Staff tab');
$ids = $rowsOf($admin->get('/admin/staff?q=' . urlencode($tag) . '&status=inactive'));
check($ids === [$paulo], 'status=inactive shows the deactivated staff member');
$ids = $rowsOf($admin->get('/admin/staff?q=' . urlencode($tag) . '&status=active'));
check(in_array($res['id'], $ids, true) && !in_array($paulo, $ids, true), 'status=active leaves out deactivated accounts');

section('Paging and sorting');
$pg = "Pg{$tag}";
for ($i = 1; $i <= 26; $i++) {
    $admin->post('/admin/staff', ['name' => sprintf('%s %02d', $pg, $i), 'email' => "pg{$tag}n{$i}@t.test", 'password' => $pw]);
}
$p1 = $admin->get('/admin/staff?q=' . urlencode($pg) . '&type=staff');
check(count($rowsOf($p1)) === 25 && $p1->has('26 people'), 'page 1 holds 25 of 26 matches and says how many matched');
check($p1->has('page=2') && $p1->has('q=' . urlencode($pg)) && $p1->has('type=staff'), 'the next-page link keeps the search and filters');
$p2 = $admin->get('/admin/staff?q=' . urlencode($pg) . '&type=staff&page=2');
check(!str_contains((string) (preg_match('/href="([^"]*type=staff[^"]*)"[^>]*class="[^"]*kpi-card/', $p2->body, $m) ? $m[1] : 'page='), 'page='),
    'changing the filter from a later page starts again at page 1');
check($rowsOf($p2) === [$idOf("pg{$tag}n26@t.test")], 'page 2 holds the last one, sorted by name');
$newest = $rowsOf($admin->get('/admin/staff?q=' . urlencode($pg) . '&sort=newest'));
check(($newest[0] ?? 0) === $idOf("pg{$tag}n26@t.test"), 'sort=newest puts the latest account first');

section('Bad input is handled, never a 500');
foreach (['?status=%3Cscript%3E&type=x&sort=zzz&page=-5', '?page=abc', '?page=99999', '?q[]=x', '?view=nope'] as $qs) {
    $r = $admin->get('/admin/staff' . $qs);
    check($r->code < 500 && !$r->has('<script>alert'), "GET /admin/staff{$qs} does not fail");
}
$admin->post('/admin/staff', ['name' => "Esc <i>{$tag}</i>", 'email' => "esc{$tag}@t.test", 'password' => $pw]);
$r = $admin->get('/admin/staff?q=' . urlencode("esc{$tag}"));
check($r->has("Esc &lt;i&gt;{$tag}") && !$r->has("<i>{$tag}</i>"), 'names are escaped');
check($admin->get('/admin/staff?q=' . urlencode("x' OR '1'='1"))->code === 200, 'a quote in the search is just text');

section('The existing pages stay reachable');
$staffTab = $admin->get('/admin/staff?view=staff');
check($staffTab->has('Add a staff member') && $staffTab->has('id="add-staff"') && $staffTab->has('id="staff-' . $paulo . '"'), 'the Staff tab is the staff page, with anchors for the deep links');
$r = $admin->get('/admin/staff?view=boarders');
check($r->code === 200 && $r->has('Resident Directory') && $r->has('id="boarders-table"') && $r->has('initBoardersPage') && $r->has('data-user-tabs'),
    'the Boarders tab is the Resident Directory, with its tabs and its search script');
check($r->has('id="boarder-row-' . $res['id'] . '"') && !$r->has('id="boarder-row-' . $arch['id'] . '"'), 'it lists current residents and leaves out archived ones');
check($r->has('href="/admin/staff?view=boarders" class="btn btn-sm btn-primary" aria-current="page"'), 'the Boarders tab is highlighted');
check($admin->get('/admin/staff?view=boarders&archived=1')->has('id="boarder-row-' . $arch['id'] . '"'), 'Show archived lists the archived resident');
$boardersPage = $admin->get('/admin/boarders');
check($boardersPage->has('id="add-boarder"') && !$boardersPage->has('id="boarders-table"'),
    'the Boarders page keeps Add Boarder, without the directory');
$r = $admin->post("/admin/boarders/{$res['id']}/info", ['name' => "Mira {$tag}", 'email' => $res['email'], 'status' => '', 'return_to' => '/admin/staff?view=boarders']);
check($r->loc === '/admin/staff?view=boarders', 'saving a resident from the directory returns to the directory');
$r = $admin->post("/admin/boarders/{$res['id']}/info", ['name' => "Mira {$tag}", 'email' => $res['email'], 'status' => '', 'return_to' => '//evil.example/admin/boarders']);
check($r->loc === '/admin/staff?view=boarders', 'an outside return_to is ignored');

section('Only the administrator gets in');
check(seededClient('staff')->get('/admin/staff')->code === 403, 'staff get 403');
check($res['client']->get('/admin/staff?view=all')->code === 403, 'residents get 403'); // not the seeded boarder: test_http_rbac changes its password
check(str_contains((string) (new Client('anon'))->get('/admin/staff')->loc, '/login'), 'visitors are sent to sign in');

section('Resident changes are audited like staff changes');
check((int) q1("SELECT COUNT(*) FROM audit_log WHERE action = 'resident_created' AND entity_type = 'user' AND entity_id = ?", [$res['id']]) === 1, 'adding a resident writes an audit row');
$details = (string) q1("SELECT details FROM audit_log WHERE action = 'resident_updated' AND entity_id = ? ORDER BY id DESC LIMIT 1", [$res['id']]);
check(str_contains($details, '"name"') && !str_contains($details, 'Mira'), 'editing a resident records which fields changed, not their values');
