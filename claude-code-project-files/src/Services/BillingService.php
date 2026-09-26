<?php

namespace App\Services;

use App\Database;

/**
 * Authoritative Canonical Billing & Balance Service.
 *
 * Implements the Critical Balance Rule:
 * The balance must always be derived from canonical rent/payment and penalty records,
 * and synchronized to boarder_profiles.outstanding_balance as a cached value.
 * Direct ad-hoc arithmetic updates (e.g. $balance += $penalty) are strictly prohibited.
 */
class BillingService
{
    /**
     * Calculates the exact canonical balance breakdown for a boarder.
     *
     * @param int $boarderId
     * @param \PDO|null $pdo
     * @return array{
     *     boarder_id: int,
     *     base_price: float,
     *     billing_period: string,
     *     rent_due: float,
     *     penalties_due: float,
     *     total_outstanding: float,
     *     unpaid_penalties: array
     * }
     */
    public static function calculateBalance(int $boarderId, ?\PDO $pdo = null): array
    {
        $pdo = $pdo ?? Database::getConnection();
        $currentPeriod = date('Y-m');

        // 1. Fetch boarder profile and room base price
        $stmt = $pdo->prepare('
            SELECT bp.*, r.base_price, r.room_number
            FROM boarder_profiles bp
            LEFT JOIN rooms r ON r.id = bp.room_id
            WHERE bp.user_id = ?
        ');
        $stmt->execute([$boarderId]);
        $profile = $stmt->fetch();

        $basePrice = (float) ($profile['base_price'] ?? 0.00);

        // 2. Determine rent due for current billing period
        // Check allocated rent from payment_allocations
        $stmt = $pdo->prepare('
            SELECT COALESCE(SUM(amount), 0)
            FROM payment_allocations
            WHERE boarder_id = ? AND allocation_type = "rent" AND reference_id = ?
        ');
        $stmt->execute([$boarderId, $currentPeriod]);
        $allocatedRent = (float) $stmt->fetchColumn();

        // Fallback for legacy verified payments recorded prior to allocations table
        $stmt = $pdo->prepare('
            SELECT COALESCE(SUM(claimed_amount), 0)
            FROM payments
            WHERE boarder_id = ? AND billing_period = ?
              AND verification_status IN ("auto-matched", "admin-approved")
        ');
        $stmt->execute([$boarderId, $currentPeriod]);
        $legacyVerifiedRent = (float) $stmt->fetchColumn();

        $totalRentCovered = max($allocatedRent, $legacyVerifiedRent);
        $rentDue = max(0.00, round($basePrice - $totalRentCovered, 2));

        // 3. Determine active unpaid penalties
        $stmt = $pdo->prepare('
            SELECT p.*, pr.name AS rule_name, pr.condition_type AS rule_type,
                   COALESCE(SUM(pa.amount), 0) AS allocated_amount
            FROM penalties p
            JOIN penalty_rules pr ON pr.id = p.rule_id
            LEFT JOIN payment_allocations pa ON pa.allocation_type = "penalty" AND pa.reference_id = CAST(p.id AS CHAR)
            WHERE p.boarder_id = ? AND p.status = "unpaid"
            GROUP BY p.id
            ORDER BY p.due_date ASC, p.id ASC
        ');
        $stmt->execute([$boarderId]);
        $rawPenalties = $stmt->fetchAll();

        $penaltiesDue = 0.00;
        $unpaidPenalties = [];

        foreach ($rawPenalties as $pen) {
            $penAmount = (float) $pen['amount'];
            $alreadyPaid = (float) $pen['allocated_amount'];
            $remaining = max(0.00, round($penAmount - $alreadyPaid, 2));

            if ($remaining > 0) {
                $penaltiesDue += $remaining;
                $pen['remaining_amount'] = $remaining;
                $unpaidPenalties[] = $pen;
            }
        }

        $totalOutstanding = round($rentDue + $penaltiesDue, 2);

        return [
            'boarder_id'        => $boarderId,
            'base_price'        => $basePrice,
            'billing_period'    => $currentPeriod,
            'rent_due'          => $rentDue,
            'penalties_due'     => $penaltiesDue,
            'total_outstanding' => $totalOutstanding,
            'unpaid_penalties'  => $unpaidPenalties,
        ];
    }

    /**
     * Atomically derives canonical balance and synchronizes boarder_profiles.outstanding_balance.
     *
     * @param int $boarderId
     * @param \PDO|null $pdo
     * @return float The newly synchronized total balance.
     */
    public static function syncBalance(int $boarderId, ?\PDO $pdo = null): float
    {
        $pdo = $pdo ?? Database::getConnection();
        $balance = self::calculateBalance($boarderId, $pdo);
        $total = (float) $balance['total_outstanding'];

        $stmt = $pdo->prepare('
            UPDATE boarder_profiles
            SET outstanding_balance = ?
            WHERE user_id = ?
        ');
        $stmt->execute([$total, $boarderId]);

        return $total;
    }

    /**
     * Batch synchronization of all boarders (useful on migration/initialization).
     */
    public static function syncAllBalances(?\PDO $pdo = null): void
    {
        $pdo = $pdo ?? Database::getConnection();
        $boarders = $pdo->query('SELECT user_id FROM boarder_profiles')->fetchAll();
        foreach ($boarders as $b) {
            self::syncBalance((int) $b['user_id'], $pdo);
        }
    }
}
