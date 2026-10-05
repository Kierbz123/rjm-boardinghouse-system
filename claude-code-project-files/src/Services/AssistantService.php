<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\BoarderProfile;
use App\Models\Expense;
use App\Models\Incident;
use App\Models\Inquiry;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\PenaltyRule;
use App\Models\SosAlert;
use App\Support\NavRegistry;

/**
 * The assistant's answers that need no AI model. Everything here is decided by
 * PHP rules from the logged-in role, so it works with Ollama stopped and can
 * never show a page or a number the role is not allowed to see.
 *
 * Two kinds of answer: a lookup (real figures read through the models) when the
 * message asks for one, otherwise the page that matches. Nothing here changes data
 * beyond what opening the matching page already does.
 */
class AssistantService
{
    /**
     * Lookups: who may use each, and the phrases that ask for it. Phrases are kept
     * specific on purpose, so "pay rent" still means the page and not the balance.
     * A boarder's lookups only ever read that boarder's own rows.
     */
    private const LOOKUPS = [
        'balance' => [['boarder'], ['balance', 'balanse', 'owe', 'utang', 'how much', 'magkano', 'amount due', 'babayaran']],
        'myPayments' => [['boarder'], ['my payment', 'payment status', 'last payment', 'approved', 'rejected', 'na approve', 'huling bayad', 'bayad ko']],
        'myRepairs' => [['boarder'], ['my repair', 'my request', 'repair status', 'request status', 'fixed yet', 'been fixed', 'naayos', 'inaayos']],
        'myRoom' => [['boarder'], ['what room', 'which room', 'my room number', 'what bed', 'which bed', 'anong kwarto', 'anong kuwarto', 'saang kwarto', 'saang kuwarto']],
        'openRepairs' => [['staff', 'admin'], ['open repair', 'pending repair', 'how many repair', 'most urgent', 'urgent repair', 'pending request',
            'requests are pending', 'what maintenance', 'pending maintenance', 'ilang sira', 'ilang repair']],
        'activeSos' => [['staff', 'admin'], ['sos', 'emergency', 'saklolo']],
        'openIncidents' => [['staff', 'admin'], ['unresolved incident', 'open incident', 'pending incident', 'how many incident', 'ilang insidente']],
        'inquiries' => [['staff', 'admin'], ['new inquir', 'inquiries today', 'how many inquir', 'ilang inquiry', 'ilang nagtanong']],
        'pendingPayments' => [['admin'], ['payments waiting', 'payment waiting', 'waiting for review', 'pending payment', 'flagged', 'to approve',
            'approve payment', 'unverified', 'awaiting', 'susuriin']],
        'vacantBeds' => [['admin'], ['vacant', 'available bed', 'free bed', 'empty bed', 'available room', 'bakante']],
        'occupancy' => [['admin'], ['occupancy', 'how full', 'occupied', 'how many boarder', 'ilang boarder']],
        'whoOwes' => [['admin'], ['who owes', 'owes the most', 'unpaid', 'overdue', 'not paid', 'hasn t paid', 'haven t paid', 'may utang', 'di pa bayad']],
        'expensesThisMonth' => [['admin'], ['expenses this month', 'spent this month', 'how much spent', 'total expenses', 'gastos']],
        'ledger' => [['admin'], ['ledger', 'export', 'csv', 'download report']],
    ];

    /**
     * How the system works, written from what the code does (due day, late fee, approval,
     * scoring thresholds), so the answers stay true. `open` = the page offered per role.
     * {placeholders} are live values (see fact()). House rules the system does not hold,
     * such as visitors, are said to be missing, not invented.
     */
    private const HELP = [
        ['roles' => ['boarder', 'staff', 'admin'],
            'about' => 'when rent is due and how the late fee works',
            'ask' => ['due date', 'when is rent due', 'rent due', 'deadline', 'late fee', 'late payment', 'kailan ang bayad', 'kailan dapat', 'multa'],
            'en' => "Rent is due on or before the 5th of each month. A new resident's first payment is due 30 days after moving in, so nothing is due in your first 30 days. After a month's due date, a late fee is added for each day its rent is still unpaid: {late_fee}. It stops on the day you submit a receipt that the admin then approves (review time is not charged); if the receipt is rejected, it counts again. The admin can waive a late fee, with a reason.",
            'tl' => 'Dapat bayaran ang renta sa o bago ang ika-5 ng bawat buwan. Ang unang bayad ng bagong residente ay 30 araw pagkatapos lumipat, kaya walang babayaran sa unang 30 araw. Paglampas ng due date ng isang buwan, may late fee sa bawat araw na hindi pa bayad ang renta nito: {late_fee}. Titigil ito sa araw na nagpasa ka ng resibo na ia-approve ng admin (hindi sinisingil ang oras ng pagsusuri); kapag hindi tinanggap ang resibo, magpapatuloy ito. Puwedeng i-waive ng admin ang late fee, may dahilan.',
            'open' => ['boarder' => ['Pay Rent', '/portal/payments/new'], 'admin' => ['Penalties', '/admin/penalty-rules']]],
        ['roles' => ['boarder'],
            'about' => 'how to pay rent and how payments get approved',
            'ask' => ['how do i pay', 'how to pay', 'how can i pay', 'paano magbayad', 'paano ako magbabayad', 'paano bayaran'],
            'en' => 'Pay by GCash, Maya or bank transfer. Then open Pay Rent, choose how you paid, enter the amount on your receipt, upload a photo of it and submit. It shows as Pending review until an admin checks it, and you get a notification when it is approved or rejected. Approved payments clear your oldest unpaid month first; anything extra becomes credit.',
            'tl' => 'Magbayad sa GCash, Maya o bank transfer. Saka buksan ang Pay Rent, piliin kung paano ka nagbayad, ilagay ang halaga sa resibo, i-upload ang litrato nito at ipasa. Pending review ito hanggang suriin ng admin, at may notification ka kapag na-approve o hindi tinanggap. Ang na-approve na bayad ay unang ibabayad sa pinakalumang hindi pa bayad na buwan; ang sobra ay magiging credit.',
            'open' => ['boarder' => ['Pay Rent', '/portal/payments/new']]],
        ['roles' => ['boarder'],
            'about' => 'how to report something that needs repair',
            'ask' => ['how do i report', 'how to report', 'how do i submit a maintenance', 'how do i request a repair', 'paano mag report', 'paano magpaayos', 'paano ipaayos'],
            'en' => 'Open Report a Repair, pick a category, describe what is wrong and where, and add a photo if you can. The system rates how urgent it is from your description, and staff work on the most urgent first. You can also just tell me what is broken and I will fill in the form for you.',
            'tl' => 'Buksan ang Report a Repair, pumili ng category, ilarawan kung ano at saan ang sira, at maglagay ng litrato kung kaya. Tinatantya ng system kung gaano ito kaapurahan mula sa paglalarawan mo, at inuuna ng staff ang pinakaapurahan. Puwede mo ring sabihin sa akin kung ano ang sira at ako na ang maglalagay sa form.',
            'open' => ['boarder' => ['Report a Repair', '/portal/maintenance/new']]],
        ['roles' => ['boarder'],
            'about' => 'what the emergency SOS button does',
            'ask' => ['sos', 'emergency', 'saklolo', 'panic'],
            'en' => 'The SOS button is on your dashboard. Pressing it alerts the staff and the admin at once, with your room. Use it only for a real emergency. Pressing it again while your alert is still open does not send a second one.',
            'tl' => 'Nasa dashboard mo ang SOS button. Kapag pinindot, agad na naaabisuhan ang staff at admin, kasama ang kuwarto mo. Gamitin lang ito sa totoong emergency. Kapag pinindot ulit habang bukas pa ang alert mo, hindi na ito magpapadala ng pangalawa.',
            'open' => ['boarder' => ['Dashboard', '/portal/dashboard']]],
        ['roles' => ['boarder', 'staff', 'admin'],
            'about' => 'changing a password or a forgotten password',
            'ask' => ['change password', 'change my password', 'forgot password', 'forgot my password', 'reset password', 'new password', 'palitan ang password', 'nakalimutan ang password'],
            'en' => 'Open Profile, type your current password, then a new one of at least 8 characters, twice. Other devices signed in to your account are logged out. If you forgot your password, contact the house administrator.',
            'tl' => 'Buksan ang Profile, i-type ang kasalukuyang password, saka ang bago na hindi bababa sa 8 karakter, nang dalawang beses. Mala-log out ang ibang device na naka-sign in sa account mo. Kung nakalimutan mo ang password, lumapit sa house administrator.',
            'open' => ['boarder' => ['Profile', '/profile'], 'staff' => ['Profile', '/profile'], 'admin' => ['Profile', '/profile']]],
        ['roles' => ['boarder', 'staff', 'admin'],
            'about' => 'how to log out',
            'ask' => ['log out', 'logout', 'sign out', 'mag log out'],
            'en' => 'The log out button is at the bottom of the sidebar, beside your name. You are also logged out after 30 minutes without activity.',
            'tl' => 'Nasa ibaba ng sidebar ang log out button, katabi ng pangalan mo. Kusa ka ring mala-log out pagkalipas ng 30 minutong walang galaw.',
            'open' => []],
        ['roles' => ['boarder', 'admin'],
            'about' => 'how rent is worked out for a partial month',
            'ask' => ['prorate', 'partial month', 'half month', 'why is my rent', 'how is rent computed', 'how is rent calculated', 'kalahating buwan'],
            'en' => 'Rent is charged month by month from the move-in date. A month that is only partly stayed (the first or the last) is charged by the day: monthly rent × days stayed ÷ days in that month. Unpaid months carry over to the next.',
            'tl' => 'Sinisingil ang renta kada buwan mula sa petsa ng paglipat. Ang buwang hindi buo ang tinirhan (una o huli) ay sinisingil kada araw: buwanang renta × araw na tinirhan ÷ araw sa buwang iyon. Nadadala sa susunod na buwan ang hindi nabayaran.',
            'open' => ['boarder' => ['Pay Rent', '/portal/payments/new'], 'admin' => ['Boarders', '/admin/boarders']]],
        ['roles' => ['boarder', 'staff', 'admin'],
            'about' => 'how the urgency of a repair is decided',
            'ask' => ['priority', 'how urgent', 'critical mean', 'severity', 'urgency score'],
            'en' => 'Each repair gets a score from 0 to 100 from the words in its description, its category and whether a photo is attached. 70 and up is critical, 45 and up high, 20 and up medium, anything lower is low. The queue shows the most urgent first.',
            'tl' => 'Bawat repair ay may score na 0 hanggang 100 batay sa mga salita sa paglalarawan, sa category, at kung may litrato. 70 pataas ay critical, 45 pataas high, 20 pataas medium, mas mababa ay low. Nauuna sa queue ang pinakaapurahan.',
            'open' => ['boarder' => ['Report a Repair', '/portal/maintenance/new'], 'staff' => ['Maintenance Queue', '/staff/maintenance'], 'admin' => ['Maintenance Queue', '/staff/maintenance']]],
        ['roles' => ['boarder', 'staff', 'admin'],
            'about' => 'curfew (there is none) and other house rules',
            'ask' => ['curfew', 'visitor', 'house rule', 'dorm rule', 'bisita', 'patakaran ng bahay', 'bawal'],
            'en' => 'There is no curfew. Live CCTV covers the boardinghouse. Violations with a set penalty: {penalties}. Other house rules, such as visitors, are not written in the system, so please ask the house administrator.',
            'tl' => 'Walang curfew. May live CCTV sa buong boardinghouse. Mga paglabag na may nakatakdang multa: {penalties}. Ang ibang patakaran ng bahay, gaya ng bisita, ay hindi nakasulat sa system, kaya magtanong sa house administrator.',
            'open' => []],
    ];

    /**
     * @return array{reply:string, actions:list<array{label:string,href:string}>, source:string}|null
     *         null when no rule matches; the caller may then fall back to free-form chat
     */
    public static function answer(string $message, string $role, string $lang = 'en', int $userId = 0): ?array
    {
        $lang = $lang === 'tl' ? 'tl' : 'en';

        // Whichever matches the message best wins; a how-to explanation beats a figure on a tie.
        $help = $lookup = null;
        $best = 0;
        foreach (self::HELP as $i => $topic) {
            if (in_array($role, $topic['roles'], true) && ($score = NavRegistry::score($message, $topic['ask'])) > $best) {
                [$help, $best] = [$i, $score];
            }
        }
        foreach (self::LOOKUPS as $name => [$roles, $phrases]) {
            if (in_array($role, $roles, true) && ($score = NavRegistry::score($message, $phrases)) > $best) {
                [$lookup, $help, $best] = [$name, null, $score];
            }
        }
        if ($help !== null) {
            return self::helpCard($help, $role, $lang);
        }
        if ($lookup !== null) {
            return self::{$lookup}($lang, $userId, $message) + ['source' => 'data'];
        }
        if ($role === 'admin' && ($card = self::boarderByName($message, $lang))) {
            return $card + ['source' => 'data'];
        }

        $pages = NavRegistry::find($role, $message);
        if (!$pages) {
            return null;
        }
        if ($card = self::prefill($pages[0], $message, $lang)) {
            return $card + ['source' => 'pages'];
        }
        // The best match explains itself; up to two other matches are offered as buttons only.
        $actions = [];
        foreach ($pages as $page) {
            $actions[$page['label']] ??= self::open($lang, $page['label'], $page['href']);
        }
        return [
            'reply' => $pages[0]['about'][$lang],
            'actions' => array_slice(array_values($actions), 0, 3),
            'source' => 'pages',
        ];
    }

    private static function helpCard(int $topic, string $role, string $lang): array
    {
        $help = self::HELP[$topic];
        $open = $help['open'][$role] ?? null;
        return [
            'reply' => preg_replace_callback('/\{(\w+)\}/', fn ($m) => self::fact($m[1], $lang), $help[$lang]),
            'actions' => $open ? [self::open($lang, ...$open)] : [],
            'source' => 'help',
        ];
    }

    // ── For the AI layer: the model may only choose from this role's list ──────

    /** What each lookup answers, in plain words the model can choose between. */
    private const LOOKUP_ABOUT = [
        'balance' => 'how much the resident still owes (unpaid rent, dues, penalties)',
        'myPayments' => "whether the resident's payments were received, approved or rejected",
        'myRepairs' => 'progress of a repair the resident already reported (is someone coming, is it fixed yet)',
        'myRoom' => "the resident's room, bed and monthly rent",
        'openRepairs' => 'repairs waiting to be done and which is most urgent',
        'activeSos' => 'emergency SOS alerts that are active now',
        'openIncidents' => 'incident reports not yet resolved',
        'inquiries' => 'how many people inquired about a room',
        'pendingPayments' => 'payments and receipts the admin has not reviewed yet',
        'vacantBeds' => 'which beds or slots are free for new boarders',
        'occupancy' => 'how many beds are occupied',
        'whoOwes' => 'which boarders are behind on rent and how much they owe',
        'expensesThisMonth' => 'how much the house spent this month',
        'ledger' => 'download the ledger file',
    ];

    /** A readable key for a help topic, e.g. "help:due_date". */
    private static function helpKey(array $topic): string
    {
        return 'help:' . str_replace(' ', '_', $topic['ask'][0]);
    }

    /** @return array<string,string> every answer this role may be given: key => plain description */
    public static function catalogue(string $role): array
    {
        $entries = [];
        foreach (self::LOOKUPS as $name => [$roles]) {
            if (in_array($role, $roles, true)) {
                $entries["lookup:{$name}"] = self::LOOKUP_ABOUT[$name];
            }
        }
        foreach (self::HELP as $i => $topic) {
            if (in_array($role, $topic['roles'], true)) {
                $entries[self::helpKey($topic)] = 'a question about how it works: ' . $topic['about'];
            }
        }
        foreach (NavRegistry::forRole($role) as $page) {
            $entries["page:{$page['href']}"] ??= "wants the {$page['label']} page: " . $page['about']['en'];
        }
        return $entries;
    }

    /**
     * Runs one catalogue entry. The key comes from the model, so it is checked against
     * this role's own catalogue first: anything else returns null and nothing is run.
     */
    public static function run(string $key, string $message, string $role, string $lang = 'en', int $userId = 0): ?array
    {
        $lang = $lang === 'tl' ? 'tl' : 'en';
        if (!isset(self::catalogue($role)[$key])) {
            return null;
        }
        [$kind, $id] = explode(':', $key, 2);
        if ($kind === 'lookup') {
            return self::{$id}($lang, $userId, $message) + ['source' => 'data'];
        }
        if ($kind === 'help') {
            $topic = current(array_filter(array_keys(self::HELP), fn ($i) => self::helpKey(self::HELP[$i]) === $key));
            return self::helpCard($topic, $role, $lang);
        }
        $page = current(array_filter(NavRegistry::forRole($role), fn ($p) => $p['href'] === $id));
        // The model judged the message to be about this page; if the page has a form to start, start it.
        return (self::prefill($page, $message, $lang, true)
            ?? ['reply' => $page['about'][$lang], 'actions' => [self::open($lang, $page['label'], $page['href'])]]) + ['source' => 'pages'];
    }

    /**
     * Forms the assistant can start from the message itself. `when` = words that show the
     * message describes a problem (not a how-to question); `pick` guesses one extra field.
     * The browser fills the form after the person opens it; nothing is submitted for them.
     */
    private const PREFILL = [
        '/portal/maintenance/new' => [
            'when' => ['broken', 'leak', 'not working', 'clogged', 'damage', 'won t', 'doesn t', 'no water', 'spark', 'flicker', 'stuck',
                'sira', 'nasira', 'guba', 'naguba', 'tulo', 'barado', 'walang tubig', 'ayaw'],
            'pick' => ['category', [
                'plumbing' => ['faucet', 'sink', 'leak', 'toilet', 'pipe', 'drain', 'clog', 'water', 'shower', 'gripo', 'tulo', 'barado', 'tubig', 'lababo', 'inidoro'],
                'electrical' => ['light', 'outlet', 'socket', 'wire', 'switch', 'spark', 'power', 'breaker', 'bulb', 'ilaw', 'kuryente', 'saksakan'],
                'appliance' => ['aircon', 'air con', 'electric fan', 'heater', 'dispenser', 'refrigerator', 'fridge', 'bentilador'],
                'structural' => ['door', 'window', 'wall', 'ceiling', 'roof', 'floor', 'lock', 'pinto', 'bintana', 'kisame', 'bubong', 'sahig', 'dingding'],
            ]],
            'reply' => ["Open the form and I'll fill in what you wrote. Check the category, add a photo if you can, then press Submit Request.",
                'Buksan ang form at ilalagay ko ang isinulat mo. Tingnan ang category, maglagay ng litrato kung kaya, saka pindutin ang Submit Request.'],
        ],
        '/staff/incidents' => [
            'when' => ['noise', 'noisy', 'loud', 'theft', 'stolen', 'fight', 'harass', 'missing', 'lost', 'nakaw', 'ingay', 'maingay', 'nawala', 'nawawala', 'nag away'],
            'pick' => ['type', [
                'Noise Disturbance' => ['noise', 'noisy', 'loud', 'ingay', 'maingay'],
                'Lost Belongings' => ['theft', 'stolen', 'lost', 'missing', 'nakaw', 'nawala', 'nawawala'],
            ]],
            'reply' => ["Open the form and I'll fill in what you wrote. Check the details, then submit the report yourself.",
                'Buksan ang form at ilalagay ko ang isinulat mo. Tingnan ang detalye, saka ikaw ang magpapasa ng report.'],
        ],
    ];

    private static function prefill(array $page, string $message, string $lang, bool $describesProblem = false): ?array
    {
        $form = self::PREFILL[$page['href']] ?? null;
        if (!$form || !($describesProblem || NavRegistry::score($message, $form['when']))) {
            return null;
        }
        $fields = ['description' => $message];
        [$field, $choices] = $form['pick'];
        $scores = array_filter(array_map(fn ($words) => NavRegistry::score($message, $words), $choices));
        if ($scores) {
            $fields[$field] = array_search(max($scores), $scores, true); // first choice wins a tie
        }
        return [
            'reply' => self::t($lang, ...$form['reply']),
            'actions' => [[
                'label' => self::t($lang, 'Open %s, filled in', 'Buksan ang %s na may laman', $page['label']),
                'href' => $page['href'],
                'prefill' => $fields,
            ]],
        ];
    }

    // ── Boarder: own data only ($userId is the logged-in boarder) ──────────────

    private static function balance(string $lang, int $userId): array
    {
        $b = BillingService::calculateBalance($userId);
        if ($b['total_outstanding'] > 0) {
            $months = implode(', ', array_map(fn ($p) => date('F Y', strtotime($p . '-01')), array_keys($b['unpaid_rent'])));
            $reply = self::t($lang, 'You owe %s in total. Rent: %s%s. Penalties: %s.', 'May babayaran kang %s lahat. Renta: %s%s. Multa: %s.',
                self::peso($b['total_outstanding']), self::peso($b['rent_due']), $months ? " ({$months})" : '', self::peso($b['penalties_due']));
        } else {
            $reply = self::t($lang, 'You have nothing to pay right now.', 'Wala kang babayaran ngayon.');
            if ($b['credit'] > 0) {
                $reply .= ' ' . self::t($lang, 'You have %s paid in advance.', 'May %s kang paunang bayad.', self::peso($b['credit']));
            }
        }
        $waiting = array_sum(array_column(array_filter(Payment::allForBoarder($userId),
            fn ($p) => in_array($p['verification_status'], ['pending', 'flagged'], true)), 'claimed_amount'));
        if ($waiting > 0) {
            $reply .= ' ' . self::t($lang, 'A payment of %s is still waiting for an admin, so it is not counted yet.',
                'May bayad kang %s na hinihintay pang suriin ng admin, kaya hindi pa ito nabibilang.', self::peso($waiting));
        }
        return ['reply' => $reply, 'actions' => [self::open($lang, 'Pay Rent', '/portal/payments/new')]];
    }

    private static function myPayments(string $lang, int $userId): array
    {
        $status = [
            'pending' => ['waiting for an admin', 'hinihintay ang admin'],
            'flagged' => ['waiting for an admin', 'hinihintay ang admin'], // older rows only
            'auto-matched' => ['approved', 'aprubado'],
            'admin-approved' => ['approved', 'aprubado'],
            'rejected' => ['rejected', 'hindi tinanggap'],
        ];
        $lines = array_map(fn ($p) => sprintf('%s, %s: %s', self::peso($p['claimed_amount']), date('F Y', strtotime($p['billing_period'] . '-01')),
            self::t($lang, ...$status[$p['verification_status']])), Payment::allForBoarder($userId, 3));
        return [
            'reply' => $lines
                ? self::t($lang, 'Your latest payments:', 'Mga huli mong bayad:') . "\n" . implode("\n", $lines)
                : self::t($lang, 'You have not sent any payments yet.', 'Wala ka pang naipapadalang bayad.'),
            'actions' => [self::open($lang, 'Pay Rent', '/portal/payments/new')],
        ];
    }

    private static function myRepairs(string $lang, int $userId): array
    {
        $status = [
            'open' => ['waiting for staff', 'naghihintay ng staff'],
            'in_progress' => ['being worked on', 'inaayos na'],
            'resolved' => ['finished', 'tapos na'],
        ];
        $lines = array_map(fn ($r) => sprintf('%s, %s: %s', ucfirst($r['category']), date('j M', strtotime($r['created_at'])),
            self::t($lang, ...$status[$r['status']])), MaintenanceRequest::allForBoarder($userId, 3));
        return [
            'reply' => $lines
                ? self::t($lang, 'Your latest repair requests:', 'Mga huli mong repair request:') . "\n" . implode("\n", $lines)
                : self::t($lang, 'You have no repair requests.', 'Wala kang repair request.'),
            'actions' => [self::open($lang, 'Dashboard', '/portal/dashboard'), self::open($lang, 'Report a Repair', '/portal/maintenance/new')],
        ];
    }

    private static function myRoom(string $lang, int $userId): array
    {
        $p = BoarderProfile::findWithFullDetails($userId);
        return [
            'reply' => !empty($p['room_number'])
                ? self::t($lang, 'You are in Room %s, %s. Monthly rent is %s.', 'Nasa Room %s, %s ka. %s ang renta kada buwan.',
                    $p['room_number'], self::bed($p['bed_label']), self::peso($p['base_price']))
                : self::t($lang, 'You have no room assigned yet. Ask the admin.', 'Wala ka pang nakatalagang kuwarto. Magtanong sa admin.'),
            'actions' => [self::open($lang, 'Dashboard', '/portal/dashboard')],
        ];
    }

    // ── Staff and admin ────────────────────────────────────────────────────────

    private static function openRepairs(string $lang, int $userId): array
    {
        $queue = MaintenanceRequest::queueSorted(); // most urgent first
        if ($queue) {
            $tiers = array_count_values(array_column($queue, 'priority_tier'));
            $byTier = implode(', ', array_map(fn ($tier) => "{$tiers[$tier]} {$tier}",
                array_values(array_intersect(['critical', 'high', 'medium', 'low'], array_keys($tiers)))));
            $reply = self::t($lang, '%s open: %s. Most urgent: %s in Room %s, reported %s.',
                '%s: %s. Pinakaapurahan: %s sa Room %s, iniulat noong %s.',
                $lang === 'tl' ? count($queue) . ' repair ang bukas' : self::count(count($queue), 'repair is', 'repairs are'),
                $byTier, $queue[0]['category'], $queue[0]['room_number'] ?? '-', date('j M', strtotime($queue[0]['created_at'])));
        } else {
            $reply = self::t($lang, 'No repairs are open.', 'Walang bukas na repair.');
        }
        return ['reply' => $reply, 'actions' => [self::open($lang, 'Maintenance Queue', '/staff/maintenance')]];
    }

    private static function activeSos(string $lang, int $userId): array
    {
        $alerts = SosAlert::activeAlerts();
        $list = implode('; ', array_map(fn ($a) => sprintf('%s, Room %s, %s%s', $a['boarder_name'], $a['room_number'] ?? '-',
            date('j M H:i', strtotime($a['created_at'])), $a['status'] === 'acknowledged' ? self::t($lang, ' (acknowledged)', ' (natanggap na)') : ''), $alerts));
        return [
            'reply' => $alerts
                ? self::t($lang, '%s active: %s.', '%s: %s.',
                    $lang === 'tl' ? count($alerts) . ' SOS alert ang aktibo' : self::count(count($alerts), 'SOS alert is', 'SOS alerts are'), $list)
                : self::t($lang, 'No SOS alerts are active.', 'Walang aktibong SOS alert.'),
            'actions' => [self::open($lang, 'Staff Dashboard', '/staff/dashboard')],
        ];
    }

    private static function openIncidents(string $lang, int $userId): array
    {
        $open = array_values(array_filter(Incident::all(), fn ($i) => !$i['resolved'])); // newest first
        return [
            'reply' => $open
                ? self::t($lang, '%s not resolved yet. Latest: %s, %s.', '%s. Pinakabago: %s, %s.',
                    $lang === 'tl' ? count($open) . ' insidente ang hindi pa nareresolba' : self::count(count($open), 'incident is', 'incidents are'),
                    $open[0]['type'], date('j M', strtotime($open[0]['created_at'])))
                : self::t($lang, 'All incidents are resolved.', 'Naresolba na ang lahat ng insidente.'),
            'actions' => [self::open($lang, 'Incidents', '/staff/incidents')],
        ];
    }

    private static function inquiries(string $lang, int $userId): array
    {
        $s = Inquiry::stats();
        return [
            'reply' => self::t($lang, 'Inquiries today: %d. All so far: %d.', 'Mga inquiry ngayong araw: %d. Lahat: %d.', $s['today'], $s['total']),
            'actions' => [self::open($lang, 'Inquiry Center', '/admin/inquiry-center')],
        ];
    }

    // ── Admin only ─────────────────────────────────────────────────────────────

    private static function pendingPayments(string $lang, int $userId): array
    {
        $waiting = array_filter(Payment::all(), fn ($p) => in_array($p['verification_status'], ['pending', 'flagged'], true));
        // Every receipt waits as "pending"; the ones whose amount differs from what was owed need a closer look.
        $differs = count(array_filter($waiting, fn ($p) => abs((float) $p['expected_amount'] - (float) $p['claimed_amount']) > 0.01));
        return [
            'reply' => $waiting
                ? self::t($lang, '%s waiting for you (%s in total); %d of them differ from the amount owed.', '%s (%s lahat); %d ang iba sa halagang dapat bayaran.',
                    $lang === 'tl' ? count($waiting) . ' bayad ang naghihintay sa iyo' : self::count(count($waiting), 'payment is', 'payments are'),
                    self::peso(array_sum(array_column($waiting, 'claimed_amount'))), $differs)
                : self::t($lang, 'No payments are waiting for review.', 'Walang bayad na naghihintay ng review.'),
            'actions' => [self::open($lang, 'Payments', '/admin/payments')],
        ];
    }

    private static function vacantBeds(string $lang, int $userId): array
    {
        $beds = Bed::all();
        $vacant = array_values(array_filter($beds, fn ($b) => $b['status'] === 'vacant'));
        $names = array_map(fn ($b) => "Room {$b['room_number']} " . self::bed($b['label']), array_slice($vacant, 0, 6));
        if (count($vacant) > 6) {
            $names[] = self::t($lang, 'and %d more', 'at %d pa', count($vacant) - 6);
        }
        return [
            'reply' => $vacant
                ? self::t($lang, '%d of %d beds vacant: %s.', '%d sa %d higaan ang bakante: %s.', count($vacant), count($beds), implode(', ', $names))
                : self::t($lang, 'All %d beds are occupied.', 'Okupado ang lahat ng %d higaan.', count($beds)),
            'actions' => [self::open($lang, 'Rooms & Beds', '/admin/rooms')],
        ];
    }

    private static function occupancy(string $lang, int $userId): array
    {
        $c = Bed::counts();
        return [
            'reply' => self::t($lang, '%d of %d beds occupied (%d%%).', '%d sa %d higaan ang okupado (%d%%).',
                $c['occupied'], $c['total'], $c['total'] ? round($c['occupied'] / $c['total'] * 100) : 0),
            'actions' => [self::open($lang, 'Occupancy', '/admin/occupancy'), self::open($lang, 'Rooms & Beds', '/admin/rooms')],
        ];
    }

    private static function whoOwes(string $lang, int $userId): array
    {
        // ponytail: recalculates every current boarder's balance (the same call their dashboard makes).
        // Fine for one house; read the cached boarder_profiles.outstanding_balance if this ever gets slow.
        $owing = [];
        foreach (BoarderProfile::all() as $b) {
            $due = BillingService::calculateBalance((int) $b['user_id'])['total_outstanding'];
            if ($due > 0) {
                $owing[] = ['id' => (int) $b['user_id'], 'name' => $b['name'], 'due' => $due];
            }
        }
        usort($owing, fn ($a, $b) => $b['due'] <=> $a['due']);
        $top = array_slice($owing, 0, 3);
        return [
            'reply' => $owing
                ? self::t($lang, '%s %s in total. Highest: %s.', '%s na %s lahat. Pinakamalaki: %s.',
                    $lang === 'tl' ? count($owing) . ' boarder ang may utang' : self::count(count($owing), 'boarder owes', 'boarders owe'),
                    self::peso(array_sum(array_column($owing, 'due'))),
                    implode(', ', array_map(fn ($o) => "{$o['name']} " . self::peso($o['due']), $top)))
                : self::t($lang, 'No boarder owes anything right now.', 'Walang boarder na may utang ngayon.'),
            'actions' => [self::open($lang, 'Boarders', '/admin/boarders'),
                ...array_map(fn ($o) => self::open($lang, $o['name'], "/admin/boarders/{$o['id']}"), array_slice($top, 0, 2))],
        ];
    }

    private static function expensesThisMonth(string $lang, int $userId): array
    {
        $month = array_filter(Expense::all(), fn ($e) => str_starts_with($e['created_at'], date('Y-m')));
        return [
            'reply' => self::t($lang, '%s spent in %s, across %s.', '%s ang nagastos ngayong %s, sa %s.',
                self::peso(array_sum(array_column($month, 'amount'))), date('F Y'),
                $lang === 'tl' ? count($month) . ' gastos' : count($month) . (count($month) === 1 ? ' expense' : ' expenses')),
            'actions' => [self::open($lang, 'Expenses', '/admin/expenses')],
        ];
    }

    /** A download link, not an action: the ledger export only reads. */
    private static function ledger(string $lang, int $userId, string $message = ''): array
    {
        $last = NavRegistry::score($message, ['last month', 'previous month', 'nakaraang buwan']) > 0;
        [$from, $to] = $last ? [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month'))]
            : [date('Y-m-01'), date('Y-m-d')];
        return [
            'reply' => self::t($lang, 'The ledger downloads as a spreadsheet file (CSV) covering %s to %s. Say "ledger last month" for the month before.',
                'Mada-download ang ledger bilang spreadsheet file (CSV) mula %s hanggang %s. Sabihin ang "ledger last month" para sa nakaraang buwan.',
                date('j M Y', strtotime($from)), date('j M Y', strtotime($to))),
            'actions' => [['label' => self::t($lang, 'Download the ledger', 'I-download ang ledger'), 'href' => "/admin/ledger/export?from={$from}&to={$to}"],
                self::open($lang, 'Payments', '/admin/payments')],
        ];
    }

    /** "balance of Juan", "open Maria's profile": a current boarder named in the message. */
    private static function boarderByName(string $message, string $lang): ?array
    {
        foreach (BoarderProfile::all() as $b) {
            // Name parts that are also page words ("Boarder", "Staff") would match almost anything; skip them.
            $parts = array_filter(preg_split('/\s+/', trim($b['name'])),
                fn ($part) => mb_strlen($part) >= 3 && !NavRegistry::isPageWord($part));
            if (!$parts || !NavRegistry::score($message, $parts)) {
                continue;
            }
            $due = BillingService::calculateBalance((int) $b['user_id'])['total_outstanding'];
            return [
                'reply' => self::t($lang, '%s: Room %s, %s, %s. Owes %s.', '%s: Room %s, %s, %s. Utang: %s.',
                    $b['name'], $b['room_number'] ?? '-', self::bed($b['bed_label']), str_replace('_', ' ', $b['status']), self::peso($due)),
                'actions' => [self::open($lang, $b['name'], "/admin/boarders/{$b['user_id']}")],
            ];
        }
        return null;
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /** Live values the help text quotes, read from where the rest of the system keeps them. */
    private static function fact(string $name, string $lang): string
    {
        $rules = PenaltyRule::allActive(); // as set by the admin on the Penalties page
        if ($name === 'late_fee') {
            $perDay = array_sum(array_column(array_filter($rules, fn ($r) => $r['condition_type'] === 'late_per_day'), 'amount'));
            return $perDay > 0
                ? self::t($lang, '%s per day', '%s kada araw', self::peso($perDay))
                : self::t($lang, 'no late fee is set right now', 'walang nakatakdang late fee ngayon');
        }
        $flat = array_filter($rules, fn ($r) => $r['condition_type'] !== 'late_per_day');
        return $flat
            ? implode(', ', array_map(fn ($r) => "{$r['name']} " . self::peso($r['amount']), $flat))
            : self::t($lang, 'none set', 'walang nakatakda');
    }

    private static function t(string $lang, string $en, string $tl, mixed ...$args): string
    {
        return sprintf($lang === 'tl' ? $tl : $en, ...$args);
    }

    /** @return array{label:string,href:string} */
    private static function open(string $lang, string $label, string $href): array
    {
        return ['label' => self::t($lang, 'Open %s', 'Buksan ang %s', $label), 'href' => $href];
    }

    private static function peso(float|string|null $amount): string
    {
        return '₱' . number_format((float) $amount, 2);
    }

    /** Labels are stored either as "A" or as "Bed A"; always read as "Bed A". */
    private static function bed(?string $label): string
    {
        return $label === null ? 'no bed' : (stripos($label, 'bed') === 0 ? $label : "Bed {$label}");
    }

    private static function count(int $n, string $one, string $many): string
    {
        return $n . ' ' . ($n === 1 ? $one : $many);
    }
}
