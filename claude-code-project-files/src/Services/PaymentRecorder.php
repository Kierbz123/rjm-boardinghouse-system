<?php

namespace App\Services;

use App\Database;
use App\Models\Payment;

/**
 * An admin or staff member records money taken in person (cash, a walk-in GCash, a bank slip).
 *
 * It goes through the same approval as an online receipt (PaymentReviewService::approve): the row is
 * filed under the oldest unpaid month, late fees stop on the day the money was received, and the amount
 * is applied oldest-first. So the balance, the allocations, "collected this month" and the ledger all
 * count it exactly like an approved online payment, with no special case anywhere else.
 */
final class PaymentRecorder
{
    public const MAX_AMOUNT = 1000000.0;

    /**
     * @param array<string, mixed> $in raw form values: boarder_id, amount, payment_method, reference_number, received_on
     * @return array{ok: bool, error?: string, payment_id?: int}
     */
    public static function record(array $in, int $actorId, string $actorRole, ?\DateTimeImmutable $today = null): array
    {
        $today = $today ?? new \DateTimeImmutable('today');
        $pdo = Database::getConnection();

        $amountText = trim((string) ($in['amount'] ?? ''));
        $method = (string) ($in['payment_method'] ?? '');
        $reference = trim((string) ($in['reference_number'] ?? ''));
        $receivedText = (string) ($in['received_on'] ?? '');
        $received = \DateTimeImmutable::createFromFormat('!Y-m-d', $receivedText);

        if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $amountText) || (float) $amountText <= 0 || (float) $amountText > self::MAX_AMOUNT) {
            return self::fail('Enter the amount received: more than ₱0 and at most ₱1,000,000, with no more than 2 decimals.');
        }
        $amount = round((float) $amountText, 2);
        if (!isset(Payment::MANUAL_METHODS[$method])) {
            return self::fail('Choose how the payment was made.');
        }
        if (mb_strlen($reference) > 50 || !preg_match('/^[A-Za-z0-9 \-]*$/', $reference)) {
            return self::fail('The reference can only have letters, numbers, spaces and dashes (up to 50).');
        }
        if (!$received || $received->format('Y-m-d') !== $receivedText) {
            return self::fail('Enter the date the payment was received.');
        }
        if ($received > $today) {
            return self::fail('The date received cannot be in the future.');
        }

        $stmt = $pdo->prepare("SELECT u.id, u.name, u.role, u.archived_at, bp.status, bp.move_in_date
            FROM users u JOIN boarder_profiles bp ON bp.user_id = u.id WHERE u.id = ?");
        $stmt->execute([(int) ($in['boarder_id'] ?? 0)]);
        $resident = $stmt->fetch();
        if (!$resident || $resident['role'] !== 'boarder' || $resident['archived_at'] !== null) {
            return self::fail('Choose a resident from the list. Archived residents cannot receive payments.');
        }
        if ($resident['status'] === 'pending') {
            return self::fail("{$resident['name']} has not moved in yet, so there is nothing to pay.");
        }
        if ($resident['move_in_date'] !== null && $received->format('Y-m-d') < $resident['move_in_date']) {
            return self::fail("The date received is before {$resident['name']} moved in ({$resident['move_in_date']}).");
        }
        $boarderId = (int) $resident['id'];

        $pdo->beginTransaction();
        try {
            // The resident's row first, in the same order BillingService takes it, so two payments recorded at
            // the same moment (or one while an approval runs) take turns instead of racing.
            $pdo->prepare('SELECT user_id FROM boarder_profiles WHERE user_id = ? FOR UPDATE')->execute([$boarderId]);

            if ($reference !== '') {
                $dup = $pdo->prepare("SELECT id FROM payments WHERE payment_method = ? AND verification_status <> 'rejected'
                    AND UPPER(REPLACE(REPLACE(reference_number, ' ', ''), '-', '')) = ? LIMIT 1");
                $dup->execute([$method, strtoupper(preg_replace('/[\s\-]+/', '', $reference))]);
                if (($dupId = $dup->fetchColumn()) !== false) {
                    $pdo->rollBack();
                    return self::fail("That reference was already used on payment #{$dupId}. Check the slip before recording it again.");
                }
            }
            // The same person entering the same amount for the same resident within seconds is a double click.
            $twice = $pdo->prepare("SELECT id FROM payments WHERE boarder_id = ? AND received_by = ? AND payment_method = ?
                AND claimed_amount = ? AND verification_status <> 'rejected' AND recorded_by_role IS NOT NULL
                AND reviewed_at >= (NOW() - INTERVAL 30 SECOND) LIMIT 1");
            $twice->execute([$boarderId, $actorId, $method, $amount]);
            if ($twice->fetchColumn() !== false) {
                $pdo->rollBack();
                return self::fail('This exact payment was just recorded. It was not entered a second time.');
            }

            // The amount owed is the server's figure; the payment is filed under the oldest unpaid month.
            $balance = BillingService::calculateBalance($boarderId, $pdo);
            $period = array_key_first($balance['unpaid_rent']) ?? $received->format('Y-m');
            // created_at is the day the money was received, which is the day late fees stop (PaymentReviewService).
            $pdo->prepare("INSERT INTO payments (boarder_id, billing_period, expected_amount, claimed_amount, payment_method,
                    reference_number, received_on, received_by, recorded_by_role, verification_status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)")
                ->execute([$boarderId, $period, $balance['total_outstanding'], $amount, $method, $reference !== '' ? $reference : null,
                    $received->format('Y-m-d'), $actorId, $actorRole, $received->format('Y-m-d') . ' ' . date('H:i:s')]);
            $paymentId = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE payments SET receipt_number = ? WHERE id = ?')->execute([sprintf('RJM-%06d', $paymentId), $paymentId]);

            $result = PaymentReviewService::approve($paymentId, $actorId, $amount, null, $today);
            if (!$result['ok']) {
                $pdo->rollBack();
                return self::fail($result['error'] ?? 'The payment could not be recorded.');
            }
            AuditLog::record($actorId, 'payment_recorded', 'payment', $paymentId, [
                'boarder_id' => $boarderId, 'amount' => $amount, 'method' => $method, 'received_on' => $received->format('Y-m-d'), 'role' => $actorRole,
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        // Outside the transaction: a failed notification must not undo money that really changed hands.
        $payment = Payment::find($paymentId);
        NotificationDispatcher::paymentApproved(
            $boarderId, $payment, PaymentAllocationService::getAllocationsForPayment($paymentId),
            BillingService::calculateBalance($boarderId, null, true)
        );
        return ['ok' => true, 'payment_id' => $paymentId];
    }

    private static function fail(string $error): array
    {
        return ['ok' => false, 'error' => $error];
    }
}
