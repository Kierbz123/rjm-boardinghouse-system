<?php
/**
 * Assistant routing without the AI model: the sidebar still shows exactly the
 * pages it did before the page registry, and questions land on the right page
 * for each role and never on a page that role cannot open. Run via tests/run.php.
 */

require_once __DIR__ . '/../src/autoload.php';

use App\Services\AssistantService;
use App\Support\NavRegistry;

$failures = 0;
function check(bool $ok, string $label): void
{
    global $failures;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . "\n";
    $failures += $ok ? 0 : 1;
}

echo "== Sidebar is unchanged ==\n";
$sidebarBefore = [
    'admin' => [
        'Overview' => ['/admin/dashboard' => 'Dashboard', '/admin/inquiry-center' => 'Inquiry Center', '/admin/occupancy' => 'Occupancy'],
        'Boarding' => ['/admin/boarders' => 'Boarders', '/admin/rooms' => 'Rooms & Beds', '/admin/staff' => 'Staff Accounts'],
        'Finance' => ['/admin/payments' => 'Payments', '/admin/expenses' => 'Expenses', '/admin/penalty-rules' => 'Penalties'],
        'Records' => ['/staff/maintenance/history' => 'Maintenance History', '/staff/incidents/history' => 'Incident History'],
    ],
    'staff' => [
        'Overview' => ['/staff/dashboard' => 'Dashboard', '/admin/inquiry-center' => 'Inquiry Center'],
        'Active Tasks' => ['/staff/maintenance' => 'Maintenance Queue', '/staff/incidents' => 'Incidents'],
        'Records' => ['/staff/maintenance/history' => 'Maintenance History', '/staff/incidents/history' => 'Incident History'],
    ],
    'boarder' => [
        'Overview' => ['/portal/dashboard' => 'Dashboard'],
        'Resident Services' => ['/portal/maintenance/new' => 'Report a Repair', '/portal/payments/new' => 'Pay Rent'],
        'Community' => ['/staff/incidents' => 'Incidents'],
    ],
];
foreach ($sidebarBefore as $role => $expected) {
    $actual = array_map(fn ($items) => array_column($items, 'label', 'href'), NavRegistry::sidebar($role));
    check($actual === $expected, "{$role} sidebar sections, order, links and labels");
}
check(NavRegistry::sidebar('nobody') === [], 'unknown role gets no sidebar');

echo "== Questions land on the right page ==\n";
$top = fn (string $role, string $text) => NavRegistry::find($role, $text)[0]['href'] ?? null;
$cases = [
    ['boarder', 'where do I pay rent?', '/portal/payments/new'],
    ['boarder', 'Saan ako magbabayad ng renta?', '/portal/payments/new'],
    ['boarder', 'my faucet is leaking', '/portal/maintenance/new'],
    ['boarder', 'sira ang ilaw sa kwarto', '/portal/maintenance/new'],
    ['boarder', 'How do I submit a maintenance request?', '/portal/maintenance/new'],
    ['boarder', 'change my password', '/profile'],
    ['boarder', 'home', '/portal/dashboard'],
    ['boarder', 'I want to complain about noise', '/staff/incidents'],
    ['staff', 'open the maintenance queue', '/staff/maintenance'],
    ['staff', 'repair history', '/staff/maintenance/history'],
    ['staff', 'who asked about a room', '/admin/inquiry-center'],
    ['staff', 'dashboard', '/staff/dashboard'],
    ['admin', 'show flagged payments', '/admin/payments'],
    ['admin', 'dashboard', '/admin/dashboard'],
    ['admin', 'vacant beds', '/admin/rooms'],
    ['admin', 'add a staff account', '/admin/staff'],
    ['admin', 'late fees', '/admin/penalty-rules'],
    ['admin', 'gastos this month', '/admin/expenses'],
    ['admin', 'notifications', '/notifications'],
];
foreach ($cases as [$role, $text, $href]) {
    $got = $top($role, $text);
    check($got === $href, "{$role}: \"{$text}\" -> {$href}" . ($got === $href ? '' : ' (got ' . var_export($got, true) . ')'));
}
check(NavRegistry::find('boarder', 'the current weather is nice') === [], '"current" does not match "rent"; unrelated text matches nothing');

echo "== A role is never offered a page it cannot open ==\n";
$adminOnly = ['/admin/dashboard', '/admin/occupancy', '/admin/boarders', '/admin/rooms', '/admin/staff',
    '/admin/payments', '/admin/expenses', '/admin/penalty-rules'];
$everything = 'dashboard payments expenses boarders staff accounts rooms beds occupancy penalties maintenance queue history inquiry incidents';
check(array_intersect(array_column(NavRegistry::find('staff', $everything), 'href'), $adminOnly) === [], 'staff asking for every page gets no admin-only page');
$boarderHrefs = array_column(NavRegistry::find('boarder', $everything), 'href');
check(array_filter($boarderHrefs, fn ($h) => str_starts_with($h, '/admin') || str_starts_with($h, '/staff/maintenance') || $h === '/staff/dashboard') === [],
    'boarder asking for every page gets no admin or staff page');
check(NavRegistry::find('nobody', $everything) === [], 'unknown role gets no pages');

echo "== Answer cards ==\n";
$card = AssistantService::answer('where do I pay rent?', 'boarder', 'en');
check($card !== null && $card['actions'][0] === ['label' => 'Open Pay Rent', 'href' => '/portal/payments/new'], 'card carries an "Open Pay Rent" button');
check($card !== null && str_contains($card['reply'], 'receipt') && $card['source'] === 'pages', 'card explains the page in English');
$card = AssistantService::answer('saan magbabayad', 'boarder', 'tl');
check($card !== null && $card['actions'][0]['label'] === 'Buksan ang Pay Rent' && str_contains($card['reply'], 'resibo'), 'Tagalog setting answers in Tagalog');
$card = AssistantService::answer('history', 'staff', 'en');
check($card !== null && array_column($card['actions'], 'href') === ['/staff/maintenance/history', '/staff/incidents/history'], 'an unclear request offers both matching pages');
check(AssistantService::answer('what is the meaning of life', 'boarder', 'en') === null, 'no match returns null (caller falls back to chat)');
check(AssistantService::answer('payments', 'nobody', 'en') === null, 'unknown role gets nothing');

echo $failures === 0 ? "All assistant checks passed.\n" : "{$failures} assistant check(s) failed.\n";
exit($failures === 0 ? 0 : 1);
