<?php

namespace App\Models;

use App\Database;

class Payment
{
    /** How a resident may pay, as stored => as shown. */
    public const METHODS = ['gcash' => 'GCash', 'maya' => 'Maya', 'bank_transfer' => 'Bank transfer'];

    /**
     * Every new submission waits for an admin as 'pending'. An amount that differs
     * from what is owed is shown to the admin beside it, never as a different status.
     */
    public static function create(
        int $boarderId,
        string $billingPeriod,
        float $expected,
        float $claimed,
        ?string $proofPath,
        ?string $method = null,
        ?string $referenceNumber = null
    ): int {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO payments (boarder_id, billing_period, expected_amount, claimed_amount, payment_method, reference_number, proof_path, verification_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, "pending")'
        );
        $stmt->execute([$boarderId, $billingPeriod, $expected, $claimed,
            isset(self::METHODS[$method]) ? $method : null, $referenceNumber, $proofPath]);
        return (int) Database::getConnection()->lastInsertId();
    }

    /** "gcash" → "GCash"; older payments recorded no method. */
    public static function methodLabel(?string $method): string
    {
        return self::METHODS[$method] ?? 'Not recorded';
    }

    /** What the resident sees for each status. */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending', 'flagged' => 'Pending review',
            'admin-approved', 'auto-matched' => 'Approved',
            'rejected' => 'Rejected',
            default => ucfirst($status),
        };
    }

    /** Which statuses a payment may move to, from which. Rejecting an approved payment reverses it. */
    private const ALLOWED_FROM = [
        'flagged'        => ['pending'],
        'admin-approved' => ['pending', 'flagged', 'rejected'],
        'rejected'       => ['pending', 'flagged', 'admin-approved', 'auto-matched'],
    ];

    /**
     * Moves a payment to a new verification status and rebuilds the boarder's
     * balance in the same transaction. Returns false (and changes nothing) when
     * the move isn't allowed, e.g. approving twice.
     */
    public static function setVerification(int $id, string $status, ?int $verifiedBy = null, ?string $note = null): bool
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT boarder_id, verification_status FROM payments WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $payment = $stmt->fetch();
            if (!$payment || !in_array($payment['verification_status'], self::ALLOWED_FROM[$status] ?? [], true)) {
                $pdo->rollBack();
                return false;
            }

            $pdo->prepare('UPDATE payments SET verification_status = ?, verified_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ?')
                ->execute([$status, $verifiedBy, $note, $id]);
            \App\Services\BillingService::calculateBalance((int) $payment['boarder_id'], $pdo);

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * A boarder may have one receipt awaiting review at a time: approved payments are
     * applied oldest-month-first anyway, and this stops a double-click or a resubmitted
     * form from creating two payments for the same receipt.
     */
    public static function hasOpenSubmission(int $boarderId): bool
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(*) FROM payments WHERE boarder_id = ? AND verification_status IN ('pending', 'flagged')"
        );
        $stmt->execute([$boarderId]);
        return (int) $stmt->fetchColumn() > 0;
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

