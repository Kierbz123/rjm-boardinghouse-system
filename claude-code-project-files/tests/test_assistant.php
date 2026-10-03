<?php
/**
 * Assistant routing without the AI model: the sidebar still shows exactly the
 * pages it did before the page registry, and questions land on the right page
 * for each role and never on a page that role cannot open. Run via tests/run.php.
 */

require_once __DIR__ . '/../src/autoload.php';

use App\Models\Bed;
use App\Models\BoarderProfile;
use App\Models\MaintenanceRequest;
use App\Services\AssistantService;
use App\Services\BillingService;
use App\Support\NavRegistry;

str_ends_with((string) getenv('DB_NAME'), '_test') || exit("Run through tests/run.php\n");

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

echo "== Lookups answer with real figures (plan A2) ==\n";
$boarderId = 3; // seed.php: admin=1, staff=2, boarder=3 in Room 101, Bed A
$ask = fn (string $role, string $text, string $lang = 'en', int $uid = 0) => AssistantService::answer($text, $role, $lang, $uid);
$hrefsOf = fn (?array $card) => array_column($card['actions'] ?? [], 'href');

$bal = BillingService::calculateBalance($boarderId);
$card = $ask('boarder', 'how much do I owe?', 'en', $boarderId);
$expected = $bal['total_outstanding'] > 0 ? number_format($bal['total_outstanding'], 2) : 'nothing to pay';
check($card['source'] === 'data' && str_contains($card['reply'], $expected) && $hrefsOf($card) === ['/portal/payments/new'],
    "boarder balance matches BillingService ({$expected}) and links Pay Rent");
check(str_contains($ask('boarder', 'magkano ang utang ko', 'tl', $boarderId)['reply'], 'babayaran'), 'balance answers in Tagalog');
$card = $ask('boarder', 'what room am I in?', 'en', $boarderId);
check($card['source'] === 'data' && str_contains($card['reply'], 'Room 101, Bed A') && !str_contains($card['reply'], 'Bed Bed'), 'boarder room and bed: ' . $card['reply']);
$card = $ask('boarder', 'has my repair been fixed yet?', 'en', $boarderId);
check($card['source'] === 'data' && $hrefsOf($card)[0] === '/portal/dashboard', 'boarder repair status: ' . strtok($card['reply'], "\n"));
$card = $ask('boarder', 'was my payment approved?', 'en', $boarderId);
check($card['source'] === 'data' && $hrefsOf($card) === ['/portal/payments/new'], 'boarder payment status: ' . strtok($card['reply'], "\n"));

$open = count(MaintenanceRequest::queueSorted());
$card = $ask('staff', 'how many repairs are open?', 'en', 2);
check($card['source'] === 'data' && str_contains($card['reply'], $open ? (string) $open : 'No repairs') && $hrefsOf($card) === ['/staff/maintenance'],
    'staff open repairs: ' . $card['reply']);
check($ask('staff', 'any sos alerts?', 'en', 2)['source'] === 'data', 'staff SOS lookup');
check($ask('staff', 'how many incidents are unresolved? open incidents', 'en', 2)['source'] === 'data', 'staff incident lookup');

$beds = Bed::counts();
$card = $ask('admin', 'which beds are vacant?', 'en', 1);
check($card['source'] === 'data' && str_contains($card['reply'], ($beds['total'] - $beds['occupied']) . " of {$beds['total']} beds") && !str_contains($card['reply'], 'Bed Bed'),
    'admin vacant beds: ' . $card['reply']);
$card = $ask('admin', 'payments waiting for review', 'en', 1);
check($card['source'] === 'data' && $hrefsOf($card) === ['/admin/payments'], 'admin payments waiting: ' . $card['reply']);
$card = $ask('admin', 'who owes the most?', 'en', 1);
check($card['source'] === 'data' && $hrefsOf($card)[0] === '/admin/boarders', 'admin who owes: ' . $card['reply']);
check($ask('admin', 'gastos this month', 'tl', 1)['source'] === 'data', 'admin expenses this month in Tagalog');
$boarder = BoarderProfile::find($boarderId);
$namePart = current(array_filter(explode(' ', $boarder['name']), fn ($p) => strlen($p) >= 3 && !NavRegistry::isPageWord($p)));
if ($namePart) {
    $card = $ask('admin', "balance of {$namePart}", 'en', 1);
    check($card['source'] === 'data' && $hrefsOf($card) === ["/admin/boarders/{$boarderId}"] && str_contains($card['reply'], $boarder['name']),
        "admin looks a boarder up by name (\"{$namePart}\"): " . $card['reply']);
    check(($ask('staff', "balance of {$namePart}", 'en', 2)['source'] ?? '') !== 'data', 'staff cannot look a boarder up by name');
}
check(NavRegistry::isPageWord('Boarder') && !NavRegistry::isPageWord('Juan'), 'page words are not treated as names');

echo "== Lookups respect the role ==\n";
foreach (['who owes the most?', 'payments waiting for review', 'which beds are vacant?', 'expenses this month', 'occupancy'] as $text) {
    check(($ask('staff', $text, 'en', 2)['source'] ?? '') !== 'data', "staff gets no admin figures for \"{$text}\"");
}
$boarderPages = array_column(NavRegistry::forRole('boarder'), 'href');
foreach (['who owes the most?', 'how many repairs are open?', 'any sos alerts?', 'which beds are vacant?', 'payments waiting for review', 'new inquiries today'] as $text) {
    $card = $ask('boarder', $text, 'en', $boarderId);
    check(array_diff($hrefsOf($card), $boarderPages) === [], "boarder asking \"{$text}\" is only offered boarder pages");
}

echo $failures === 0 ? "All assistant checks passed.\n" : "{$failures} assistant check(s) failed.\n";
exit($failures === 0 ? 0 : 1);
