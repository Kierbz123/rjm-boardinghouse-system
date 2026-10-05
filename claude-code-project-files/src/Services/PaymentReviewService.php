<?php

namespace App\Services;

use App\Database;
use App\Models\Payment;
use App\Models\Penalty;

/**
 * An admin's approval of a receipt, in the order that keeps late fees fair:
 *
 *  1. The months the receipt pays (oldest first) get their late fee settled up to the
 *     day the receipt was SUBMITTED, so review time is never charged to the resident.
 *     A receipt sent on or before the due date means no fee at all.
 *  2. If the admin ticks "waive", those fees are waived, with the admin's reason kept.
 *  3. The payment is approved and applied (rent months oldest first, then penalties).
 */
class PaymentReviewService
{
    /**
     * What approving this receipt would charge in late fees, for the admin's dialog.
     * Uses the amount the resident entered; nothing is written.
     *
     * @return array{total: float, text: string}
     */
    public static function lateFeePreview(array $payment, ?\DateTimeImmutable $today = null): array
    {
        $today = $today ?? new \DateTimeImmutable('today');
        $boarderId = (int) $payment['boarder_id'];
        $balance = BillingService::calculateBalance($boarderId);
        $stops = PenaltyEngine::stopDates($balance, (float) $payment['claimed_amount'], self::submittedOn($payment));
        $fees = PenaltyEngine::feesFor($balance, $today, $stops, array_keys($stops));

        $settled = self::settledFeeKeys($boarderId);
        $months = []; // one line per month, all late-fee rules added together
        foreach ($fees as $fee) {
            if (isset($settled[$fee['rule_id'] . '|' . $fee['period']])) {
                continue; // already paid or waived: approving won't change it
            }
            $months[$fee['period']] ??= $fee + ['sum' => 0.0];
            $months[$fee['period']]['sum'] += $fee['amount'];
        }
        $lines = [];
        foreach ($months as $period => $m) {
            $from = (new \DateTimeImmutable($m['due']))->modify('+1 day');
            $lines[] = BillingService::periodLabel($period) . ': ' . $m['days_late'] . ' day(s) late ('
                . $from->format('M j') . '–' . (new \DateTimeImmutable($m['until']))->format('M j') . ') ₱' . number_format($m['sum'], 2);
        }
        return ['total' => round(array_sum(array_column($months, 'sum')), 2), 'text' => implode("\n", $lines)];
    }

    /**
     * @return array{ok: bool, error?: string, waived?: list<array{period:string, amount:float}>}
     */
    public static function approve(int $paymentId, int $adminId, float $approvedAmount, ?string $waiveReason = null, ?\DateTimeImmutable $today = null): array
    {
        $today = $today ?? new \DateTimeImmutable('today');
        $payment = Payment::find($paymentId);
        if (!$payment || !in_array($payment['verification_status'], ['pending', 'flagged'], true)) {
            return ['ok' => false, 'error' => "Payment #{$paymentId} has already been reviewed."];
        }
        $boarderId = (int) $payment['boarder_id'];

        // 1. Late fees on the months this receipt pays, counted only up to its submission date.
        $balance = BillingService::calculateBalance($boarderId);
        $stops = PenaltyEngine::stopDates($balance, $approvedAmount, self::submittedOn($payment));
        foreach (PenaltyEngine::feesFor($balance, $today, $stops, array_keys($stops)) as $fee) {
            PenaltyEngine::apply($boarderId, $fee);
        }

        // 2. Optional waiver of those months' late fees, with the reason on record.
        $waived = [];
        if ($waiveReason !== null && $stops) {
            $periods = array_keys($stops);
            $in = implode(',', array_fill(0, count($periods), '?'));
            $stmt = Database::getConnection()->prepare("SELECT id, billing_period, amount FROM penalties
                WHERE boarder_id = ? AND status = 'unpaid' AND billing_period IN ({$in})");
            $stmt->execute(array_merge([$boarderId], $periods));
            foreach ($stmt->fetchAll() as $fee) {
                if (Penalty::markPaid((int) $fee['id'], $adminId, $waiveReason)['ok']) {
                    $waived[] = ['period' => $fee['billing_period'], 'amount' => (float) $fee['amount']];
                }
            }
        }

        // 3. Approve and apply the payment.
        if (!Payment::setVerification($paymentId, 'admin-approved', $adminId, null, $approvedAmount)) {
            return ['ok' => false, 'error' => "Payment #{$paymentId} has already been reviewed."];
        }
        return ['ok' => true, 'waived' => $waived];
    }

    private static function submittedOn(array $payment): \DateTimeImmutable
    {
        return new \DateTimeImmutable(substr((string) $payment['created_at'], 0, 10));
    }

    /** "ruleId|period" for late fees that are already paid or waived. */
    private static function settledFeeKeys(int $boarderId): array
    {
        $stmt = Database::getConnection()->prepare("SELECT rule_id, billing_period FROM penalties
            WHERE boarder_id = ? AND status = 'paid' AND billing_period IS NOT NULL");
        $stmt->execute([$boarderId]);
        $keys = [];
        foreach ($stmt->fetchAll() as $row) {
            $keys[$row['rule_id'] . '|' . $row['billing_period']] = true;
        }
        return $keys;
    }
}
