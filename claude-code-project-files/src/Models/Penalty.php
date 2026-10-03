<?php

namespace App\Models;

use App\Database;
use App\Services\BillingService;

class Penalty
{
    /**
     * Automated penalty creation (e.g. from PenaltyEngine late fee runs).
     */
    public static function create(int $boarderId, int $ruleId, float $amount, string $reason): int
    {
        $pdo = Database::getConnection();
        $inTx = $pdo->inTransaction();
        if (!$inTx) {
            $pdo->beginTransaction();
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO penalties (boarder_id, rule_id, amount, reason, status) VALUES (?, ?, ?, ?, "unpaid")'
            );
            $stmt->execute([$boarderId, $ruleId, $amount, $reason]);
            $penaltyId = (int) $pdo->lastInsertId();

            BillingService::syncBalance($boarderId, $pdo);

            if (!$inTx) {
                $pdo->commit();
            }
            return $penaltyId;
        } catch (\Throwable $e) {
            if (!$inTx) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * The automatic late fee for one boarder/rule/month: created once, then its
     * amount follows the days late while still unpaid. Running the check again
     * never adds a second fee. Returns true only when the fee was newly created.
     */
    public static function upsertLateFee(int $boarderId, int $ruleId, string $billingPeriod, float $amount, string $reason, string $dueDate): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            INSERT INTO penalties (boarder_id, rule_id, amount, reason, billing_period, due_date, status)
            VALUES (?, ?, ?, ?, ?, ?, "unpaid")
            ON DUPLICATE KEY UPDATE
                amount = IF(status = "unpaid", VALUES(amount), amount),
                reason = IF(status = "unpaid", VALUES(reason), reason)
        ');
        $stmt->execute([$boarderId, $ruleId, $amount, $reason, $billingPeriod, $dueDate]);
        $created = $stmt->rowCount() === 1; // 1 = inserted, 2 = updated, 0 = unchanged
        BillingService::syncBalance($boarderId, $pdo);
        return $created;
    }

    /**
     * Manual penalty creation by Admin or Staff.
     * Enforces transactional atomicity and canonical balance synchronization.
     */
    public static function createManual(
        int $boarderId,
        int $ruleId,
        float $amount,
        string $reason,
        ?string $dueDate,
        int $issuedBy
    ): int {
        $pdo = Database::getConnection();
        $inTx = $pdo->inTransaction();
        if (!$inTx) {
            $pdo->beginTransaction();
        }

        try {
            $stmt = $pdo->prepare('
                INSERT INTO penalties (boarder_id, rule_id, amount, reason, due_date, status, issued_by)
                VALUES (?, ?, ?, ?, ?, "unpaid", ?)
            ');
            $stmt->execute([$boarderId, $ruleId, $amount, $reason, $dueDate, $issuedBy]);
            $penaltyId = (int) $pdo->lastInsertId();

            // Synchronize canonical balance atomically
            BillingService::syncBalance($boarderId, $pdo);

            if (!$inTx) {
                $pdo->commit();
            }

            return $penaltyId;
        } catch (\Throwable $e) {
            if (!$inTx) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Manual administrative override to mark a penalty as paid.
     * Idempotent: rejects duplicate submissions without re-deducting or re-stamping paid_at.
     *
     * @param int $penaltyId
     * @param int|null $markedBy
     * @return array{ok: bool, error?: string, boarder_id?: int, rule_name?: string, amount?: float}
     */
    public static function markPaid(int $penaltyId, ?int $markedBy = null): array
    {
        $pdo = Database::getConnection();
        $inTx = $pdo->inTransaction();
        if (!$inTx) {
            $pdo->beginTransaction();
        }

        try {
            // Lock row FOR UPDATE to guard against concurrent/duplicate requests
            $stmt = $pdo->prepare('
                SELECT p.*, pr.name AS rule_name
                FROM penalties p
                JOIN penalty_rules pr ON pr.id = p.rule_id
                WHERE p.id = ?
                FOR UPDATE
            ');
            $stmt->execute([$penaltyId]);
            $penalty = $stmt->fetch();

            if (!$penalty) {
                if (!$inTx) $pdo->commit();
                return ['ok' => false, 'error' => 'Penalty record not found.'];
            }

            // Idempotency check
            if ($penalty['status'] === 'paid') {
                if (!$inTx) $pdo->commit();
                return ['ok' => false, 'error' => 'This penalty has already been marked as paid.'];
            }

            $boarderId = (int) $penalty['boarder_id'];

            // Mark paid with manual override (paid_payment_id remains NULL)
            $stmt = $pdo->prepare('
                UPDATE penalties
                SET status = "paid", paid_at = NOW(), paid_payment_id = NULL
                WHERE id = ?
            ');
            $stmt->execute([$penaltyId]);

            // Synchronize balance atomically
            BillingService::syncBalance($boarderId, $pdo);

            if (!$inTx) {
                $pdo->commit();
            }

            return [
                'ok'         => true,
                'boarder_id' => $boarderId,
                'rule_name'  => $penalty['rule_name'],
                'amount'     => (float) $penalty['amount'],
            ];
        } catch (\Throwable $e) {
            if (!$inTx) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare('
            SELECT p.*, pr.name AS rule_name, pr.condition_type AS rule_type
            FROM penalties p
            JOIN penalty_rules pr ON pr.id = p.rule_id
            WHERE p.id = ?
        ');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function allForBoarder(int $boarderId): array
    {
        $stmt = Database::getConnection()->prepare('
            SELECT p.*, pr.name AS rule_name, pr.condition_type AS rule_type
            FROM penalties p
            JOIN penalty_rules pr ON pr.id = p.rule_id
            WHERE p.boarder_id = ?
            ORDER BY p.applied_at DESC
        ');
        $stmt->execute([$boarderId]);
        return $stmt->fetchAll();
    }

    public static function allWithDetails(): array
    {
        $sql = "SELECT penalties.*, 
                users.name AS boarder_name, 
                users.email AS boarder_email,
                issuer.name AS issuer_name,
                penalty_rules.name AS rule_name,
                penalty_rules.condition_type AS rule_type
                FROM penalties
                JOIN users ON users.id = penalties.boarder_id
                JOIN penalty_rules ON penalty_rules.id = penalties.rule_id
                LEFT JOIN users AS issuer ON issuer.id = penalties.issued_by
                ORDER BY penalties.applied_at DESC
                LIMIT 150";
        return Database::getConnection()->query($sql)->fetchAll();
    }
}
