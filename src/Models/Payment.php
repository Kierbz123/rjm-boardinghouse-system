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
        $stmt = Database::getConnection()->prepare(
            'UPDATE payments SET verification_status = ?, verified_by = ? WHERE id = ?'
        );
        $stmt->execute([$status, $verifiedBy, $id]);
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
}
