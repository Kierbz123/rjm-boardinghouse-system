<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\BoarderProfile;
use App\Models\Expense;
use App\Models\Incident;
use App\Models\Inquiry;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
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
    ];

    /**
     * @return array{reply:string, actions:list<array{label:string,href:string}>, source:string}|null
     *         null when no rule matches; the caller may then fall back to free-form chat
     */
    public static function answer(string $message, string $role, string $lang = 'en', int $userId = 0): ?array
    {
        $lang = $lang === 'tl' ? 'tl' : 'en';

        $lookup = null;
        $best = 0;
        foreach (self::LOOKUPS as $name => [$roles, $phrases]) {
            if (in_array($role, $roles, true) && ($score = NavRegistry::score($message, $phrases)) > $best) {
                [$lookup, $best] = [$name, $score];
            }
        }
        if ($lookup !== null) {
            return self::{$lookup}($lang, $userId) + ['source' => 'data'];
        }
        if ($role === 'admin' && ($card = self::boarderByName($message, $lang))) {
            return $card + ['source' => 'data'];
        }

        $pages = NavRegistry::find($role, $message);
        if (!$pages) {
            return null;
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
            'flagged' => ['being reviewed by an admin', 'sinusuri ng admin'],
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
        $by = array_count_values(array_column($waiting, 'verification_status')) + ['pending' => 0, 'flagged' => 0];
        return [
            'reply' => $waiting
                ? self::t($lang, '%s waiting for you (%s in total): %d pending, %d flagged.', '%s (%s lahat): %d pending, %d flagged.',
                    $lang === 'tl' ? count($waiting) . ' bayad ang naghihintay sa iyo' : self::count(count($waiting), 'payment is', 'payments are'),
                    self::peso(array_sum(array_column($waiting, 'claimed_amount'))), $by['pending'], $by['flagged'])
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
