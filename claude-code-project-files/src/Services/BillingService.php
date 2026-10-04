<?php

namespace App\Services;

use App\Database;

/**
 * The single source of truth for what a boarder owes.
 *
 *   owed = rent charges + penalties - approved payments
 *
 * Rent is charged per month from move-in (to move-out), prorated by days for
 * partial months. Each month is due on the 5th, except that nothing falls due
 * during a resident's first 30 days (see dueDate()). Approved payments are
 * applied oldest-first: rent months in order, then penalties by due date.
 * Anything left over is credit.
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

    /** Rent is due on this day of each month. */
    public const DUE_DAY = 5;

    /** Nothing is due until this many days after a resident moves in. */
    public const FIRST_PAYMENT_GRACE_DAYS = 30;

    /**
     * When a month's rent is due: the 5th of that month, or 30 days after move-in if
     * that is later. Moving in on 20 Oct makes both October (prorated) and November
     * due on 19 Nov; December is due on 5 Dec as usual.
     */
    public static function dueDate(string $period, \DateTimeImmutable $moveIn): \DateTimeImmutable
    {
        $fifth = new \DateTimeImmutable($period . '-' . sprintf('%02d', self::DUE_DAY));
        $graceEnds = $moveIn->modify('+' . self::FIRST_PAYMENT_GRACE_DAYS . ' days');
        return max($fifth, $graceEnds);
    }

    /**
     * @return array{boarder_id:int, base_price:float, billing_period:string, rent_due:float,
     *   penalties_due:float, total_outstanding:float, credit:float,
     *   unpaid_rent:array<string,float>, rent_due_dates:array<string,string>,
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
     * The day these due-date rules went live (when migration 0025 was applied). Late
     * fees are only charged on months due from then on, so the first penalty run after
     * an upgrade never backdates fees onto months that were already overdue under the
     * old rules (security review W4). Null when the date isn't recorded.
     */
    public static function rulesStartDate(): ?string
    {
        $stmt = Database::getConnection()->prepare('SELECT DATE(applied_at) FROM schema_migrations WHERE filename = ?');
        $stmt->execute(['0025_add_rent_due_dates_and_payment_method.sql']);
        return $stmt->fetchColumn() ?: null;
    }

    /** "2026-10" → "October 2026". */
    public static function periodLabel(string $period): string
    {
        return (new \DateTimeImmutable($period . '-01'))->format('F Y');
    }

    /** Recalculates and caches the balance; returns the amount owed. */
    public static function syncBalance(int $boarderId, ?\PDO $pdo = null): float
    {
        return self::calculateBalance($boarderId, $pdo)['total_outstanding'];
    }

    /**
     * One charge per month occupied. The current month follows today's room price
     * and dates; past months keep the rate they were billed at, but their prorated
     * amount still follows the move-in/move-out dates if an admin corrects them.
     */
    private static function syncRentCharges(int $boarderId, ?array $profile, \PDO $pdo): void
    {
        $existing = $pdo->prepare('SELECT period, monthly_rate FROM rent_charges WHERE boarder_id = ?');
        $existing->execute([$boarderId]);
        $billedRates = $existing->fetchAll(\PDO::FETCH_KEY_PAIR);

        $periods = [];
        if ($profile && $profile['move_in_date']) {
            $moveIn = new \DateTimeImmutable($profile['move_in_date']);
            $moveOut = $profile['move_out_date'] ? new \DateTimeImmutable($profile['move_out_date']) : null;
            $currentPeriod = date('Y-m');
            $lastMonth = new \DateTimeImmutable(min($currentPeriod, $moveOut ? $moveOut->format('Y-m') : $currentPeriod) . '-01');

            for ($month = new \DateTimeImmutable($moveIn->format('Y-m-01')); $month <= $lastMonth; $month = $month->modify('+1 month')) {
                $monthEnd = $month->modify('last day of this month');
                $from = max($moveIn, $month);
                $to = $moveOut ? min($moveOut, $monthEnd) : $monthEnd;
                $days = (int) $from->diff($to)->format('%r%a') + 1;
                if ($days <= 0) {
                    continue;
                }

                $period = $month->format('Y-m');
                $rate = ($period < $currentPeriod && isset($billedRates[$period]))
                    ? (float) $billedRates[$period]
                    : (float) ($profile['base_price'] ?? 0);
                $amount = round($rate * $days / (int) $month->format('t'), 2);
                $dueDate = self::dueDate($period, $moveIn)->format('Y-m-d');

                $pdo->prepare('
                    INSERT INTO rent_charges (boarder_id, period, monthly_rate, amount, due_date) VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE monthly_rate = VALUES(monthly_rate), amount = VALUES(amount), due_date = VALUES(due_date)
                ')->execute([$boarderId, $period, $rate, $amount, $dueDate]);
                $periods[] = $period;
            }
        }

        // Months outside the occupancy window (dates corrected, or never moved in) owe nothing.
        $placeholders = $periods ? implode(',', array_fill(0, count($periods), '?')) : "''";
        $pdo->prepare("DELETE FROM rent_charges WHERE boarder_id = ? AND period NOT IN ({$placeholders})")
            ->execute(array_merge([$boarderId], $periods));
    }

    /** Applies approved payments oldest-first to rent months, then penalties. */
    private static function allocate(int $boarderId, \PDO $pdo): array
    {
        $obligations = [];

        $stmt = $pdo->prepare('SELECT period, amount, due_date FROM rent_charges WHERE boarder_id = ? ORDER BY period');
        $stmt->execute([$boarderId]);
        foreach ($stmt->fetchAll() as $c) {
            $obligations[] = ['type' => 'rent', 'ref' => $c['period'], 'due' => (float) $c['amount'], 'paid' => 0.0, 'by' => null,
                'due_date' => $c['due_date']];
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
        $penaltiesDue = 0.0;
        $unpaidRent = [];
        $rentDueDates = [];
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
            if ($o['by'] !== null) {
                $markPaid->execute([$o['by'], $o['ref']]);
            } else {
                $markUnpaid->execute([$o['ref']]);
                $unpaidPenalties[] = array_merge($o['row'], ['remaining_amount' => $remaining, 'allocated_amount' => $o['paid'], 'status' => 'unpaid']);
                $penaltiesDue += $remaining;
            }
        }

        return [
            'boarder_id'        => $boarderId,
            'billing_period'    => date('Y-m'),
            'rent_due'          => round($rentDue, 2),
            'penalties_due'     => round($penaltiesDue, 2),
            'total_outstanding' => round($rentDue + $penaltiesDue, 2),
            'credit'            => $credit,
            'unpaid_rent'       => $unpaidRent,
            'rent_due_dates'    => $rentDueDates, // period => Y-m-d, for each unpaid month
            'next_due_date'     => $rentDueDates ? min($rentDueDates) : null,
            'unpaid_penalties'  => $unpaidPenalties,
        ];
    }
}
