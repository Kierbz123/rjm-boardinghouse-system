<?php

namespace App\Services;

use App\Database;

/**
 * The single source of truth for what a boarder owes.
 *
 *   owed = rent charges + electricity shares + penalties - approved payments
 *
 * Rent is charged in 30-day cycles counted from the move-in date, and each cycle
 * is due 30 days after it starts (see dueDate()). A cycle cut short by a move-out
 * is charged by the day. A room's electricity bill is split equally among its
 * residents (Models\ElectricityBill) and falls due with their rent. Approved
 * payments are applied oldest-first: rent cycles in order, then electricity
 * shares, then penalties by due date. Anything left over is credit.
 *
 * Allocations are rebuilt from scratch on every calculation, so approving,
 * rejecting or reversing a payment, adding a penalty or changing move dates can
 * never leave stale allocations behind.
 * ponytail: full rebuild per call; fine at tens of rows per boarder. Cache it if it ever profiles hot.
 */
class BillingService
{
    /** 'auto-matched' only exists on payments recorded before every receipt needed an admin. */
    public const APPROVED = ['auto-matched', 'admin-approved'];

    /** Rent is charged, and falls due, every this many days counted from the move-in date. */
    public const CYCLE_DAYS = 30;

    /** More rent cycles than this for one resident means a wrong move-in date, never a real stay (10 years). */
    public const MAX_CYCLES = 120;

    /** The migration that brought in the current due-date rules (see rulesStartDate()). */
    public const RULES_MIGRATION = '0028_add_rent_cycles_and_electricity.sql';

    /**
     * When a rent cycle is due: 30 days after it starts. $period is the cycle's first
     * day (Y-m-d). Moving in on 20 Oct makes rent due on 19 Nov, 19 Dec, 18 Jan, ...
     */
    public static function dueDate(string $period): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($period))->modify('+' . self::CYCLE_DAYS . ' days');
    }

    /** The resident's next rent due date on or after $today: when a new electricity share falls due. */
    public static function nextDueDate(?string $moveIn, ?\DateTimeImmutable $today = null): \DateTimeImmutable
    {
        $today = $today ?? new \DateTimeImmutable('today');
        $start = $moveIn ? new \DateTimeImmutable($moveIn) : $today;
        $elapsed = max(0, (int) $start->diff($today)->format('%r%a'));
        $cycles = max(1, (int) ceil($elapsed / self::CYCLE_DAYS));
        return $start->modify('+' . ($cycles * self::CYCLE_DAYS) . ' days');
    }

    /**
     * @return array{boarder_id:int, base_price:float, billing_period:string, rent_due:float,
     *   electricity_due:float, penalties_due:float, total_outstanding:float, credit:float,
     *   unpaid_rent:array<string,float>, rent_due_dates:array<string,string>,
     *   unpaid_electricity:list<array{id:int, label:string, amount:float, due_date:string}>,
     *   next_due_date:?string, unpaid_penalties:array}
     */
    public static function calculateBalance(int $boarderId, ?\PDO $pdo = null): array
    {
        $pdo = $pdo ?? Database::getConnection();
        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }

        try {
            // Serialises concurrent recalculations for the same boarder.
            $stmt = $pdo->prepare('
                SELECT bp.move_in_date, bp.move_out_date, r.base_price
                FROM boarder_profiles bp
                LEFT JOIN rooms r ON r.id = bp.room_id
                WHERE bp.user_id = ?
                FOR UPDATE
            ');
            $stmt->execute([$boarderId]);
            $profile = $stmt->fetch() ?: null;

            self::syncRentCharges($boarderId, $profile, $pdo);
            $result = self::allocate($boarderId, $pdo);
            $result['base_price'] = (float) ($profile['base_price'] ?? 0);

            if ($profile) {
                $pdo->prepare('UPDATE boarder_profiles SET outstanding_balance = ? WHERE user_id = ?')
                    ->execute([$result['total_outstanding'], $boarderId]);
            }

            if ($ownTx) {
                $pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * The day these due-date rules went live (when RULES_MIGRATION was applied). Late
     * fees are only charged on rent due from then on, so the first penalty run after
     * an upgrade never backdates fees onto rent that was already overdue under the
     * old rules (security review W4). Null when the date isn't recorded.
     */
    public static function rulesStartDate(): ?string
    {
        $stmt = Database::getConnection()->prepare('SELECT DATE(applied_at) FROM schema_migrations WHERE filename = ?');
        $stmt->execute([self::RULES_MIGRATION]);
        return $stmt->fetchColumn() ?: null;
    }

    /**
     * A rent cycle by its first day: "2026-10-20" → "Oct 20 – Nov 18, 2026".
     * Month keys from before cycles still read as a month: "2026-10" → "October 2026".
     */
    public static function periodLabel(string $period): string
    {
        // payments.billing_period is free text on very old rows: anything that is not a real
        // month or date is shown as it is, never thrown on.
        if (!preg_match('/\A\d{4}-(0[1-9]|1[0-2])(-(0[1-9]|[12]\d|3[01]))?\z/', $period)) {
            return $period;
        }
        if (strlen($period) === 7) {
            return (new \DateTimeImmutable($period . '-01'))->format('F Y');
        }
        if (!checkdate((int) substr($period, 5, 2), (int) substr($period, 8, 2), (int) substr($period, 0, 4))) {
            return $period; // e.g. 2026-02-31
        }
        $from = new \DateTimeImmutable($period);
        $to = $from->modify('+' . (self::CYCLE_DAYS - 1) . ' days');
        return $from->format($from->format('Y') === $to->format('Y') ? 'M j' : 'M j, Y') . ' – ' . $to->format('M j, Y');
    }

    /** Recalculates and caches the balance; returns the amount owed. */
    public static function syncBalance(int $boarderId, ?\PDO $pdo = null): float
    {
        return self::calculateBalance($boarderId, $pdo)['total_outstanding'];
    }

    /**
     * The house-wide picture for the admin dashboard: what current residents owe, how
     * much of that is past its due date, what was collected this calendar month, and
     * who owes (overdue first, then largest).
     * ponytail: recalculates every resident's balance per call; fine for a house of tens.
     * Read the cached boarder_profiles.outstanding_balance instead if the dashboard ever feels slow.
     *
     * @return array{total_outstanding:float, overdue_amount:float, overdue_count:int, owing_count:int,
     *   collected_this_month:float, debtors:list<array{user_id:int, name:string, room_number:?string,
     *   owed:float, overdue:float, oldest_due:?string}>}
     */
    public static function collectionsSummary(?\DateTimeImmutable $today = null, int $limit = 5): array
    {
        $today = $today ?? new \DateTimeImmutable('today');
        $todayKey = $today->format('Y-m-d');
        $pdo = Database::getConnection();

        $residents = $pdo->query("
            SELECT bp.user_id, u.name, r.room_number
            FROM boarder_profiles bp
            JOIN users u ON u.id = bp.user_id
            LEFT JOIN rooms r ON r.id = bp.room_id
            WHERE bp.status IN ('active', 'on_notice')
        ")->fetchAll();

        $debtors = [];
        foreach ($residents as $r) {
            try {
                $b = self::calculateBalance((int) $r['user_id'], $pdo);
            } catch (\RuntimeException $e) { // one resident with a bad date must not take the dashboard down
                \App\Support\Logger::error("Balance of resident {$r['user_id']} skipped: " . $e->getMessage());
                continue;
            }
            if ($b['total_outstanding'] <= 0) {
                continue;
            }
            // Everything still unpaid as [due date, amount]; a penalty may have no due date.
            $items = [];
            foreach ($b['unpaid_rent'] as $period => $amount) {
                $items[] = [$b['rent_due_dates'][$period], $amount];
            }
            foreach ($b['unpaid_electricity'] as $e) {
                $items[] = [$e['due_date'], $e['amount']];
            }
            foreach ($b['unpaid_penalties'] as $p) {
                $items[] = [$p['due_date'] ?? null, $p['remaining_amount']];
            }
            $overdue = 0.0;
            foreach ($items as [$due, $amount]) {
                if ($due !== null && $due < $todayKey) { // late from the day after the due date
                    $overdue += (float) $amount;
                }
            }
            $dates = array_filter(array_column($items, 0));
            $debtors[] = [
                'user_id' => (int) $r['user_id'],
                'name' => $r['name'],
                'room_number' => $r['room_number'],
                'owed' => $b['total_outstanding'],
                'overdue' => round($overdue, 2),
                'oldest_due' => $dates ? min($dates) : null,
            ];
        }
        usort($debtors, fn ($a, $b) => [$b['overdue'], $b['owed']] <=> [$a['overdue'], $a['owed']]);

        $placeholders = implode(',', array_fill(0, count(self::APPROVED), '?'));
        // Counted in the month the admin approved it; older rows without a review time use the upload time.
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(approved_amount, claimed_amount)), 0) FROM payments
                               WHERE verification_status IN ({$placeholders})
                                 AND COALESCE(reviewed_at, created_at) >= ? AND COALESCE(reviewed_at, created_at) < ?");
        $monthStart = $today->modify('first day of this month');
        $stmt->execute(array_merge(self::APPROVED, [$monthStart->format('Y-m-d'), $monthStart->modify('+1 month')->format('Y-m-d')]));

        return [
            'total_outstanding' => round(array_sum(array_column($debtors, 'owed')), 2),
            'overdue_amount' => round(array_sum(array_column($debtors, 'overdue')), 2),
            'overdue_count' => count(array_filter($debtors, fn ($d) => $d['overdue'] > 0)),
            'owing_count' => count($debtors),
            'collected_this_month' => (float) $stmt->fetchColumn(),
            'debtors' => array_slice($debtors, 0, $limit),
        ];
    }

    /**
     * One charge per 30-day cycle started, counted from the move-in date and keyed by
     * the cycle's first day. The running cycle follows today's room price; finished
     * cycles keep the rate they were billed at. A cycle cut short by a move-out is
     * charged by the day (rate × days stayed ÷ 30).
     */
    private static function syncRentCharges(int $boarderId, ?array $profile, \PDO $pdo): void
    {
        $existing = $pdo->prepare('SELECT period, monthly_rate FROM rent_charges WHERE boarder_id = ?');
        $existing->execute([$boarderId]);
        $billedRates = $existing->fetchAll(\PDO::FETCH_KEY_PAIR);

        $periods = [];
        if ($profile && $profile['move_in_date']) {
            $today = new \DateTimeImmutable('today');
            $moveOut = $profile['move_out_date'] ? new \DateTimeImmutable($profile['move_out_date']) : null;
            $last = $moveOut ? min($moveOut, $today) : $today;
            $step = '+' . self::CYCLE_DAYS . ' days';

            // Refuse before writing anything: a move-in date far in the past once produced 24,000+ rent rows.
            $days = (int) (new \DateTimeImmutable($profile['move_in_date']))->diff($last)->format('%r%a');
            if ($days >= 0 && intdiv($days, self::CYCLE_DAYS) + 1 > self::MAX_CYCLES) {
                throw new \RuntimeException('This resident would owe more than ' . self::MAX_CYCLES . ' rent cycles, so the move-in date '
                    . $profile['move_in_date'] . ' must be wrong. Correct it first.');
            }

            for ($start = new \DateTimeImmutable($profile['move_in_date']); $start <= $last; $start = $start->modify($step)) {
                $end = $start->modify('+' . (self::CYCLE_DAYS - 1) . ' days');
                $days = ($moveOut && $moveOut < $end) ? (int) $start->diff($moveOut)->days + 1 : self::CYCLE_DAYS;

                $period = $start->format('Y-m-d');
                $rate = ($end < $today && isset($billedRates[$period]))
                    ? (float) $billedRates[$period]
                    : (float) ($profile['base_price'] ?? 0);
                $amount = round($rate * $days / self::CYCLE_DAYS, 2);

                $pdo->prepare('
                    INSERT INTO rent_charges (boarder_id, period, monthly_rate, amount, due_date) VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE monthly_rate = VALUES(monthly_rate), amount = VALUES(amount), due_date = VALUES(due_date)
                ')->execute([$boarderId, $period, $rate, $amount, self::dueDate($period)->format('Y-m-d')]);
                $periods[] = $period;
            }
        }

        // Cycles outside the occupancy window (dates corrected, or never moved in) owe nothing,
        // and neither does an unpaid automatic late fee for one. Cycle keys follow the move-in
        // date, so correcting that date would otherwise strand its fees. Month-keyed fees from
        // before cycles (7 characters) are left alone.
        $placeholders = $periods ? implode(',', array_fill(0, count($periods), '?')) : "''";
        $pdo->prepare("DELETE FROM rent_charges WHERE boarder_id = ? AND period NOT IN ({$placeholders})")
            ->execute(array_merge([$boarderId], $periods));
        $pdo->prepare("DELETE FROM penalties WHERE boarder_id = ? AND status = 'unpaid'
                       AND CHAR_LENGTH(billing_period) = 10 AND billing_period NOT IN ({$placeholders})")
            ->execute(array_merge([$boarderId], $periods));
    }

    /** Applies approved payments oldest-first to rent cycles, then electricity shares, then penalties. */
    private static function allocate(int $boarderId, \PDO $pdo): array
    {
        $obligations = [];

        $stmt = $pdo->prepare('SELECT period, amount, due_date FROM rent_charges WHERE boarder_id = ? ORDER BY period');
        $stmt->execute([$boarderId]);
        foreach ($stmt->fetchAll() as $c) {
            $obligations[] = ['type' => 'rent', 'ref' => $c['period'], 'due' => (float) $c['amount'], 'paid' => 0.0, 'by' => null,
                'due_date' => $c['due_date']];
        }

        // ponytail: all rent before any electricity, so PenaltyEngine::stopDates() can read a
        // receipt against rent alone. Interleave the two by due date if that ever matters.
        $stmt = $pdo->prepare('
            SELECT ec.id, ec.amount, ec.due_date, eb.label
            FROM electricity_charges ec
            JOIN electricity_bills eb ON eb.id = ec.bill_id
            WHERE ec.boarder_id = ?
            ORDER BY ec.due_date, ec.id
        ');
        $stmt->execute([$boarderId]);
        foreach ($stmt->fetchAll() as $e) {
            $obligations[] = ['type' => 'electricity', 'ref' => (string) $e['id'], 'due' => (float) $e['amount'], 'paid' => 0.0, 'by' => null,
                'due_date' => $e['due_date'], 'label' => $e['label']];
        }

        // Penalties waived/settled by an admin override (paid with no payment) are not owed.
        $stmt = $pdo->prepare('
            SELECT p.*, pr.name AS rule_name, pr.condition_type AS rule_type
            FROM penalties p
            JOIN penalty_rules pr ON pr.id = p.rule_id
            WHERE p.boarder_id = ? AND NOT (p.status = "paid" AND p.paid_payment_id IS NULL)
            ORDER BY COALESCE(p.due_date, DATE(p.applied_at)), p.id
        ');
        $stmt->execute([$boarderId]);
        foreach ($stmt->fetchAll() as $p) {
            $obligations[] = ['type' => 'penalty', 'ref' => (string) $p['id'], 'due' => (float) $p['amount'], 'paid' => 0.0, 'by' => null, 'row' => $p];
        }

        $pdo->prepare('DELETE FROM payment_allocations WHERE boarder_id = ?')->execute([$boarderId]);
        $insert = $pdo->prepare('
            INSERT INTO payment_allocations (payment_id, boarder_id, allocation_type, reference_id, amount)
            VALUES (?, ?, ?, ?, ?)
        ');

        $placeholders = implode(',', array_fill(0, count(self::APPROVED), '?'));
        // The admin-confirmed amount; payments approved before that existed keep their claimed amount.
        $stmt = $pdo->prepare("SELECT id, COALESCE(approved_amount, claimed_amount) AS credited FROM payments
                               WHERE boarder_id = ? AND verification_status IN ({$placeholders}) ORDER BY id");
        $stmt->execute(array_merge([$boarderId], self::APPROVED));

        $credit = 0.0;
        $i = 0;
        foreach ($stmt->fetchAll() as $payment) {
            $left = (float) $payment['credited'];
            while ($left > 0.004 && $i < count($obligations)) {
                $o = &$obligations[$i];
                $take = round(min($left, $o['due'] - $o['paid']), 2);
                if ($take > 0) {
                    $insert->execute([$payment['id'], $boarderId, $o['type'], $o['ref'], $take]);
                    $o['paid'] = round($o['paid'] + $take, 2);
                    $left = round($left - $take, 2);
                }
                if ($o['paid'] >= $o['due'] - 0.004) {
                    $o['by'] = (int) $payment['id'];
                    $i++;
                }
                unset($o);
            }
            $credit = round($credit + max(0, $left), 2);
        }

        $rentDue = 0.0;
        $electricityDue = 0.0;
        $penaltiesDue = 0.0;
        $unpaidRent = [];
        $rentDueDates = [];
        $unpaidElectricity = [];
        $unpaidPenalties = [];
        $markPaid = $pdo->prepare('UPDATE penalties SET status = "paid", paid_payment_id = ?, paid_at = COALESCE(paid_at, NOW()) WHERE id = ?');
        $markUnpaid = $pdo->prepare('UPDATE penalties SET status = "unpaid", paid_payment_id = NULL, paid_at = NULL WHERE id = ?');

        foreach ($obligations as $o) {
            $remaining = round($o['due'] - $o['paid'], 2);
            if ($o['type'] === 'rent') {
                if ($remaining > 0) {
                    $rentDue += $remaining;
                    $unpaidRent[$o['ref']] = $remaining;
                    $rentDueDates[$o['ref']] = $o['due_date'];
                }
                continue;
            }
            if ($o['type'] === 'electricity') {
                if ($remaining > 0) {
                    $electricityDue += $remaining;
                    $unpaidElectricity[] = ['id' => (int) $o['ref'], 'label' => $o['label'], 'amount' => $remaining, 'due_date' => $o['due_date']];
                }
                continue;
            }
            if ($o['by'] !== null) {
                $markPaid->execute([$o['by'], $o['ref']]);
            } else {
                $markUnpaid->execute([$o['ref']]);
                $unpaidPenalties[] = array_merge($o['row'], ['remaining_amount' => $remaining, 'allocated_amount' => $o['paid'], 'status' => 'unpaid']);
                $penaltiesDue += $remaining;
            }
        }

        $dueDates = array_merge(array_values($rentDueDates), array_column($unpaidElectricity, 'due_date'));

        return [
            'boarder_id'         => $boarderId,
            'billing_period'     => date('Y-m'),
            'rent_due'           => round($rentDue, 2),
            'electricity_due'    => round($electricityDue, 2),
            'penalties_due'      => round($penaltiesDue, 2),
            'total_outstanding'  => round($rentDue + $electricityDue + $penaltiesDue, 2),
            'credit'             => $credit,
            'unpaid_rent'        => $unpaidRent,
            'rent_due_dates'     => $rentDueDates, // cycle start => Y-m-d, for each unpaid cycle
            'unpaid_electricity' => $unpaidElectricity,
            'next_due_date'      => $dueDates ? min($dueDates) : null,
            'unpaid_penalties'   => $unpaidPenalties,
        ];
    }
}
