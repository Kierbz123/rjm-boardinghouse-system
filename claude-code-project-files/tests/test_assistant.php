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
use App\Models\PenaltyRule;
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

echo "== Forms are filled in, never submitted (plan A5) ==\n";
$card = $ask('boarder', 'my faucet is leaking', 'en', $boarderId);
check($card['actions'] === [['label' => 'Open Report a Repair, filled in', 'href' => '/portal/maintenance/new',
    'prefill' => ['description' => 'my faucet is leaking', 'category' => 'plumbing']]], 'a described problem opens the repair form filled in, category guessed');
$card = $ask('boarder', 'sira ang ilaw sa kwarto', 'tl', $boarderId);
check(($card['actions'][0]['prefill']['category'] ?? '') === 'electrical' && str_contains($card['reply'], 'Submit Request'), 'Tagalog description: electrical, and the person is told to submit');
check(!isset($ask('boarder', 'How do I submit a maintenance request?', 'en', $boarderId)['actions'][0]['prefill']), 'a how-to question opens the empty form');
$card = $ask('boarder', 'my roommate is very noisy at night', 'en', $boarderId);
check(($card['actions'][0]['href'] ?? '') === '/staff/incidents' && ($card['actions'][0]['prefill']['type'] ?? '') === 'Noise Disturbance', 'a complaint opens the incident form with its type');
check(!isset($ask('staff', 'Log an incident', 'en', 2)['actions'][0]['prefill']), '"Log an incident" alone opens the empty form');
$card = $ask('admin', 'export the ledger', 'en', 1);
check($card['source'] === 'data' && str_starts_with($hrefsOf($card)[0], '/admin/ledger/export?from=' . date('Y-m-01')), 'admin gets a ledger download link for this month');
check(str_contains($hrefsOf($ask('admin', 'ledger last month', 'en', 1))[0], 'from=' . date('Y-m-01', strtotime('first day of last month'))), '"last month" changes the range');
check(($ask('staff', 'export the ledger', 'en', 2)['source'] ?? '') !== 'data', 'staff gets no ledger link');

echo "== Help answers come from the system's own rules (plan A3) ==\n";
$perDay = array_sum(array_column(array_filter(PenaltyRule::allActive(), fn ($r) => $r['condition_type'] === 'late_per_day'), 'amount'));
$feeText = $perDay > 0 ? number_format($perDay, 2) . ' per day' : 'no late fee is set';
$card = $ask('boarder', 'when is rent due?', 'en', $boarderId);
check($card['source'] === 'help' && str_contains($card['reply'], '5th') && str_contains($card['reply'], $feeText) && $hrefsOf($card) === ['/portal/payments/new'],
    "due date and the current late fee ({$feeText})");
check(str_contains($ask('boarder', 'kailan ang bayad?', 'tl', $boarderId)['reply'], 'ika-5'), 'due date in Tagalog');
check($ask('boarder', 'magkano ang multa?', 'tl', $boarderId)['source'] === 'help', 'a how-it-works question beats a figure on a tie');
$card = $ask('boarder', 'How do I pay my rent?', 'en', $boarderId);
check($card['source'] === 'help' && str_contains($card['reply'], 'receipt') && $hrefsOf($card) === ['/portal/payments/new'], 'how to pay explains the receipt and approval');
$card = $ask('boarder', 'what time is the curfew?', 'en', $boarderId);
check($card['source'] === 'help' && str_contains($card['reply'], 'There is no curfew.') && str_contains($card['reply'], 'not written in the system') && $card['actions'] === [],
    'no curfew, as on the landing page; other house rules are not invented: ' . $card['reply']);
check($ask('admin', 'how do I log out?', 'en', 1)['actions'] === [], 'log out is explained without a button');
check($hrefsOf($ask('admin', 'late fees', 'en', 1)) === ['/admin/penalty-rules'], 'admin late-fee help links Penalties');
check($hrefsOf($ask('staff', 'I forgot my password', 'en', 2)) === ['/profile'], 'password help links Profile');
check($hrefsOf($ask('staff', 'what does critical mean?', 'en', 2)) === ['/staff/maintenance'], 'priority help links the queue for staff');
check($ask('boarder', 'how much do I owe?', 'en', $boarderId)['source'] === 'data', 'figures still answer figure questions');

echo "== The AI layer can only choose from the role's own list (plan A4) ==\n";
$keys = fn (string $role) => array_keys(AssistantService::catalogue($role));
check(!array_filter($keys('boarder'), fn ($k) => preg_match('#whoOwes|pendingPayments|vacantBeds|openRepairs|activeSos|ledger|page:/admin|page:/staff/(dashboard|maintenance)#', $k)),
    'boarder list holds no staff or admin answers (' . count($keys('boarder')) . ' entries)');
check(!array_filter($keys('staff'), fn ($k) => preg_match('#whoOwes|pendingPayments|vacantBeds|occupancy|expenses|ledger|balance|page:/admin/(?!inquiry)#', $k)),
    'staff list holds no admin or boarder answers (' . count($keys('staff')) . ' entries)');
check(in_array('lookup:whoOwes', $keys('admin'), true) && in_array('page:/admin/rooms', $keys('admin'), true), 'admin list holds the admin answers (' . count($keys('admin')) . ' entries)');
foreach (['lookup:whoOwes', 'lookup:pendingPayments', 'page:/admin/payments', 'lookup:deleteEverything', 'help:99', 'nonsense'] as $key) {
    check(AssistantService::run($key, 'x', 'boarder', 'en', $boarderId) === null, "a boarder session cannot run \"{$key}\", whatever the model says");
}
$card = AssistantService::run('lookup:balance', 'x', 'boarder', 'en', $boarderId);
check($card !== null && $card['source'] === 'data' && $hrefsOf($card) === ['/portal/payments/new'], 'an allowed choice runs the normal lookup');
check(AssistantService::run('page:/admin/rooms', 'x', 'admin', 'tl', 1)['actions'][0] === ['label' => 'Buksan ang Rooms & Beds', 'href' => '/admin/rooms'], 'a chosen page becomes an Open button');

echo $failures === 0 ? "All assistant checks passed.\n" : "{$failures} assistant check(s) failed.\n";
exit($failures === 0 ? 0 : 1);
