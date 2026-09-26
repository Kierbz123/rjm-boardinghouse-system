<?php

namespace App\Services;

use App\Database;

/**
 * Dedicated Payment Allocation Service.
 *
 * Implements the deterministic payment distribution order:
 * 1. Rent obligation first for the payment's billing cycle.
 * 2. Unpaid penalties ordered by oldest due_date ASC, then id ASC.
 * 3. Fully covered penalties automatically transition to status = 'paid'.
 * 4. Recalculates canonical balance and updates boarder_profiles.outstanding_balance.
 */
class PaymentAllocationService
{
    /**
     * Allocates an approved payment toward a boarder's outstanding obligations.
     *
     * @param int $paymentId
     * @param \PDO|null $pdo
     * @return array
     * @throws \Throwable
     */
    public static function allocateApprovedPayment(int $paymentId, ?\PDO $pdo = null): array
    {
        $pdo = $pdo ?? Database::getConnection();
        $inExternalTx = $pdo->inTransaction();

        if (!$inExternalTx) {
            $pdo->beginTransaction();
        }

        try {
            // 1. Fetch payment row FOR UPDATE
            $stmt = $pdo->prepare('SELECT * FROM payments WHERE id = ? FOR UPDATE');
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch();

            if (!$payment || !in_array($payment['verification_status'], ['auto-matched', 'admin-approved'], true)) {
                if (!$inExternalTx) $pdo->commit();
                return [
                    'allocated' => false,
                    'reason'    => 'Payment not found or not in an approved status.'
                ];
            }

            // 2. Idempotency check: Ensure payment is not allocated more than once
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM payment_allocations WHERE payment_id = ?');
            $stmt->execute([$paymentId]);
            if ((int) $stmt->fetchColumn() > 0) {
                if (!$inExternalTx) $pdo->commit();
                return [
                    'allocated' => false,
                    'reason'    => 'Payment has already been allocated.'
                ];
            }

            $boarderId = (int) $payment['boarder_id'];
            $billingPeriod = (string) $payment['billing_period'];
            $availableAmount = (float) $payment['claimed_amount'];
            $initialPaymentAmount = $availableAmount;

            $rentAllocated = 0.00;
            $settledPenalties = [];
            $allocations = [];

            // 3. Step A: Allocate toward unpaid monthly rent for the billing cycle
            $stmt = $pdo->prepare('
                SELECT bp.*, r.base_price
                FROM boarder_profiles bp
                LEFT JOIN rooms r ON r.id = bp.room_id
                WHERE bp.user_id = ?
            ');
            $stmt->execute([$boarderId]);
            $profile = $stmt->fetch();
            $basePrice = (float) ($profile['base_price'] ?? 0.00);

            // Determine any prior allocated rent for this billing period
            $stmt = $pdo->prepare('
                SELECT COALESCE(SUM(amount), 0)
                FROM payment_allocations
                WHERE boarder_id = ? AND allocation_type = "rent" AND reference_id = ?
            ');
            $stmt->execute([$boarderId, $billingPeriod]);
            $alreadyAllocatedRent = (float) $stmt->fetchColumn();

            $unpaidRent = max(0.00, round($basePrice - $alreadyAllocatedRent, 2));

            if ($unpaidRent > 0 && $availableAmount > 0) {
                $rentToPay = min($availableAmount, $unpaidRent);
                $stmt = $pdo->prepare('
                    INSERT INTO payment_allocations (payment_id, boarder_id, allocation_type, reference_id, amount)
                    VALUES (?, ?, "rent", ?, ?)
                ');
                $stmt->execute([$paymentId, $boarderId, $billingPeriod, $rentToPay]);

                $availableAmount = round($availableAmount - $rentToPay, 2);
                $rentAllocated = $rentToPay;
                $allocations[] = [
                    'type'   => 'rent',
                    'period' => $billingPeriod,
                    'amount' => $rentToPay,
                ];
            }

            // 4. Step B: Allocate remaining funds toward unpaid penalties (due_date ASC, id ASC)
            if ($availableAmount > 0) {
                $stmt = $pdo->prepare('
                    SELECT p.*, pr.name AS rule_name,
                           COALESCE(SUM(pa.amount), 0) AS already_paid
                    FROM penalties p
                    JOIN penalty_rules pr ON pr.id = p.rule_id
                    LEFT JOIN payment_allocations pa ON pa.allocation_type = "penalty" AND pa.reference_id = CAST(p.id AS CHAR)
                    WHERE p.boarder_id = ? AND p.status = "unpaid"
                    GROUP BY p.id
                    ORDER BY p.due_date ASC, p.id ASC
                    FOR UPDATE
                ');
                $stmt->execute([$boarderId]);
                $unpaidPenalties = $stmt->fetchAll();

                foreach ($unpaidPenalties as $pen) {
                    if ($availableAmount <= 0) {
                        break;
                    }

                    $penaltyId = (int) $pen['id'];
                    $penTotal = (float) $pen['amount'];
                    $penPaid = (float) $pen['already_paid'];
                    $penRemaining = max(0.00, round($penTotal - $penPaid, 2));

                    if ($penRemaining <= 0) {
                        continue;
                    }

                    $allocAmount = min($availableAmount, $penRemaining);

                    $stmt = $pdo->prepare('
                        INSERT INTO payment_allocations (payment_id, boarder_id, allocation_type, reference_id, amount)
                        VALUES (?, ?, "penalty", ?, ?)
                    ');
                    $stmt->execute([$paymentId, $boarderId, (string) $penaltyId, $allocAmount]);

                    $availableAmount = round($availableAmount - $allocAmount, 2);
                    $allocations[] = [
                        'type'       => 'penalty',
                        'penalty_id' => $penaltyId,
                        'rule_name'  => $pen['rule_name'],
                        'amount'     => $allocAmount,
                    ];

                    // Check if fully covered
                    if (round($penRemaining - $allocAmount, 2) <= 0.001) {
                        $stmt = $pdo->prepare('
                            UPDATE penalties
                            SET status = "paid", paid_at = NOW(), paid_payment_id = ?
                            WHERE id = ?
                        ');
                        $stmt->execute([$paymentId, $penaltyId]);

                        $settledPenalties[] = [
                            'id'        => $penaltyId,
                            'rule_name' => $pen['rule_name'],
                            'amount'    => $penTotal,
                        ];
                    }
                }
            }

            // 5. Step C: Recalculate canonical balance and synchronize to boarder_profiles
            $newBalance = BillingService::syncBalance($boarderId, $pdo);

            if (!$inExternalTx) {
                $pdo->commit();
            }

            return [
                'allocated'         => true,
                'payment_id'        => $paymentId,
                'boarder_id'        => $boarderId,
                'total_paid'        => $initialPaymentAmount,
                'rent_allocated'    => $rentAllocated,
                'settled_penalties' => $settledPenalties,
                'allocations'       => $allocations,
                'remaining_balance' => $newBalance,
            ];
        } catch (\Throwable $e) {
            if (!$inExternalTx) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Retrieves all allocation records for a specific payment.
     */
    public static function getAllocationsForPayment(int $paymentId, ?\PDO $pdo = null): array
    {
        $pdo = $pdo ?? Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT pa.*,
                   CASE
                       WHEN pa.allocation_type = "penalty" THEN pr.name
                       ELSE NULL
                   END AS penalty_rule_name
            FROM payment_allocations pa
            LEFT JOIN penalties p ON pa.allocation_type = "penalty" AND p.id = CAST(pa.reference_id AS UNSIGNED)
            LEFT JOIN penalty_rules pr ON pr.id = p.rule_id
            WHERE pa.payment_id = ?
            ORDER BY pa.id ASC
        ');
        $stmt->execute([$paymentId]);
        return $stmt->fetchAll();
    }
}
