<?php

namespace App\Services;

use App\Database;

/**
 * Read side of payment allocations. Allocations themselves are (re)built by
 * BillingService::calculateBalance() whenever a balance is recalculated.
 */
class PaymentAllocationService
{
    private const SELECT = '
        SELECT pa.*,
               CASE WHEN pa.allocation_type = "penalty" THEN pr.name ELSE NULL END AS penalty_rule_name
        FROM payment_allocations pa
        LEFT JOIN penalties p ON pa.allocation_type = "penalty" AND p.id = CAST(pa.reference_id AS UNSIGNED)
        LEFT JOIN penalty_rules pr ON pr.id = p.rule_id';

    /** How one payment was applied: rent months and penalties, in order. */
    public static function getAllocationsForPayment(int $paymentId, ?\PDO $pdo = null): array
    {
        $pdo = $pdo ?? Database::getConnection();
        $stmt = $pdo->prepare(self::SELECT . ' WHERE pa.payment_id = ? ORDER BY pa.id ASC');
        $stmt->execute([$paymentId]);
        return $stmt->fetchAll();
    }

    /** Every allocation, keyed by payment id — one query for the whole payments list. */
    public static function allGroupedByPayment(): array
    {
        $grouped = [];
        foreach (Database::getConnection()->query(self::SELECT . ' ORDER BY pa.id ASC') as $row) {
            $grouped[(int) $row['payment_id']][] = $row;
        }
        return $grouped;
    }
}
