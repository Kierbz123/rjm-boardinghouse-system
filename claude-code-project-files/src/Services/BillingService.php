<?php

namespace App\Services;

use App\Database;

/**
 * The single source of truth for what a boarder owes.
 *
 *   owed = rent charges + penalties - approved payments
 *
 * Rent is charged per month from move-in (to move-out), prorated by days for
 * partial months. Approved payments are applied oldest-first: rent months in
 * order, then penalties by due date. Anything left over is credit.
 *
 * Allocations are rebuilt from scratch on every calculation, so approving,
 * rejecting or reversing a payment, adding a penalty or changing move dates can
 * never leave stale allocations behind.
 * ponytail: full rebuild per call; fine at tens of rows per boarder. Cache it if it ever profiles hot.
 */
class BillingService
{
    public const APPROVED = ['auto-matched', 'admin-approved'];

    /**
     * @return array{boarder_id:int, base_price:float, billing_period:string, rent_due:float,
     *   penalties_due:float, total_outstanding:float, credit:float,
     *   unpaid_rent:array<string,float>, unpaid_penalties:array}
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

                $pdo->prepare('
                    INSERT INTO rent_charges (boarder_id, period, monthly_rate, amount) VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE monthly_rate = VALUES(monthly_rate), amount = VALUES(amount)
                ')->execute([$boarderId, $period, $rate, $amount]);
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

        $stmt = $pdo->prepare('SELECT period, amount FROM rent_charges WHERE boarder_id = ? ORDER BY period');
        $stmt->execute([$boarderId]);
        foreach ($stmt->fetchAll() as $c) {
            $obligations[] = ['type' => 'rent', 'ref' => $c['period'], 'due' => (float) $c['amount'], 'paid' => 0.0, 'by' => null];
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
        $stmt = $pdo->prepare("SELECT id, claimed_amount FROM payments
                               WHERE boarder_id = ? AND verification_status IN ({$placeholders}) ORDER BY id");
        $stmt->execute(array_merge([$boarderId], self::APPROVED));

        $credit = 0.0;
        $i = 0;
        foreach ($stmt->fetchAll() as $payment) {
            $left = (float) $payment['claimed_amount'];
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
        $unpaidPenalties = [];
        $markPaid = $pdo->prepare('UPDATE penalties SET status = "paid", paid_payment_id = ?, paid_at = COALESCE(paid_at, NOW()) WHERE id = ?');
        $markUnpaid = $pdo->prepare('UPDATE penalties SET status = "unpaid", paid_payment_id = NULL, paid_at = NULL WHERE id = ?');

        foreach ($obligations as $o) {
            $remaining = round($o['due'] - $o['paid'], 2);
            if ($o['type'] === 'rent') {
                if ($remaining > 0) {
                    $rentDue += $remaining;
                    $unpaidRent[$o['ref']] = $remaining;
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
            'unpaid_penalties'  => $unpaidPenalties,
        ];
    }
}
