<?php

namespace App\Models;

use App\Database;

class Payment
{
    public static function create(int $boarderId, string $billingPeriod, float $expected, float $claimed, ?string $proofPath): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO payments (boarder_id, billing_period, expected_amount, claimed_amount, proof_path, verification_status)
             VALUES (?, ?, ?, ?, ?, "pending")'
        );
        $stmt->execute([$boarderId, $billingPeriod, $expected, $claimed, $proofPath]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function setVerification(int $id, string $status, ?int $verifiedBy = null): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE payments SET verification_status = ?, verified_by = ? WHERE id = ?'
        );
        $stmt->execute([$status, $verifiedBy, $id]);

        if (in_array($status, ['auto-matched', 'admin-approved'], true)) {
            \App\Services\PaymentAllocationService::allocateApprovedPayment($id, $pdo);
        } else {
            $payment = self::find($id);
            if ($payment) {
                \App\Services\BillingService::syncBalance((int) $payment['boarder_id'], $pdo);
            }
        }
    }

    public static function all(): array
    {
        $sql = 'SELECT payments.*, users.name AS boarder_name
                FROM payments JOIN users ON users.id = payments.boarder_id
                ORDER BY payments.created_at DESC';
        return Database::getConnection()->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM payments WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function pendingOrFlaggedCount(): int
    {
        return (int) Database::getConnection()
            ->query("SELECT COUNT(*) FROM payments WHERE verification_status IN ('pending','flagged')")
            ->fetchColumn();
    }

    public static function hasVerifiedPaymentForPeriod(int $boarderId, string $billingPeriod): bool
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(*) FROM payments WHERE boarder_id = ? AND billing_period = ?
             AND verification_status IN ('auto-matched','admin-approved')"
        );
        $stmt->execute([$boarderId, $billingPeriod]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Bulk version of hasVerifiedPaymentForPeriod() — fixes an N+1 pattern found
     * auditing PenaltyEngine::runCheck() and sendRentDueReminders(), both of which
     * called the single-boarder version once per boarder in a loop. One query
     * for all boarders, not benign at this system's expected scale but cheap
     * and safe to fix properly rather than leave.
     */
    public static function verifiedBoarderIdsForPeriod(string $billingPeriod): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT DISTINCT boarder_id FROM payments WHERE billing_period = ?
             AND verification_status IN ('auto-matched','admin-approved')"
        );
        $stmt->execute([$billingPeriod]);
        return array_map('intval', array_column($stmt->fetchAll(), 'boarder_id'));
    }

    public static function allForBoarder(int $boarderId, int $limit = 12): array
    {
        $limit = max(1, (int) $limit);
        $sql = "SELECT payments.*, u_verifier.name AS verifier_name
                FROM payments
                LEFT JOIN users u_verifier ON u_verifier.id = payments.verified_by
                WHERE payments.boarder_id = ?
                ORDER BY payments.created_at DESC
                LIMIT {$limit}";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([$boarderId]);
        return $stmt->fetchAll();
    }

    public static function summaryForBoarder(int $boarderId): array
    {
        $sql = "SELECT
                    COALESCE(SUM(CASE WHEN verification_status IN ('admin-approved', 'auto-matched') THEN claimed_amount ELSE 0 END), 0) AS total_paid,
                    COUNT(*) AS count,
                    COALESCE(SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_count
                FROM payments
                WHERE boarder_id = ?";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([$boarderId]);
        $row = $stmt->fetch();
        return [
            'total_paid'    => (float) ($row['total_paid'] ?? 0.0),
            'count'         => (int) ($row['count'] ?? 0),
            'pending_count' => (int) ($row['pending_count'] ?? 0),
        ];
    }
}

