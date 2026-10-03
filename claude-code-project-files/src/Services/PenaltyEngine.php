<?php

namespace App\Services;

use App\Database;
use App\Models\PenaltyRule;
use App\Models\Penalty;

/**
 * Feature 9 (Penalty and Fee Automation). Triggered by the admin's "run penalty
 * check" button — no cron on localhost, per ARCHITECTURE.md §4.5. $today is
 * injectable so this is testable without touching the system clock.
 *
 * Safe to run any number of times: each boarder gets at most one late fee per
 * rule per month, whose amount tracks the days late until it is paid.
 */
class PenaltyEngine
{
    public const DUE_DAY = 5;

    public static function runCheck(?\DateTimeImmutable $today = null): array
    {
        $today = $today ?? new \DateTimeImmutable('today');
        $dayOfMonth = (int) $today->format('j');
        if ($dayOfMonth <= self::DUE_DAY) {
            return [];
        }
        $daysLate = $dayOfMonth - self::DUE_DAY;
        $billingPeriod = $today->format('Y-m');

        $rules = array_filter(PenaltyRule::allActive(), fn ($r) => $r['condition_type'] === 'late_per_day');
        if (empty($rules)) {
            return [];
        }

        $pdo = Database::getConnection();
        $boarderIds = $pdo->query("SELECT user_id FROM boarder_profiles WHERE status IN ('active', 'on_notice')")
            ->fetchAll(\PDO::FETCH_COLUMN);

        $applied = [];
        foreach ($boarderIds as $boarderId) {
            $boarderId = (int) $boarderId;
            // Late only if this month's rent is still not fully covered by approved payments.
            $unpaidThisMonth = BillingService::calculateBalance($boarderId, $pdo)['unpaid_rent'][$billingPeriod] ?? 0;
            if ($unpaidThisMonth <= 0) {
                continue;
            }
            foreach ($rules as $rule) {
                $amount = round((float) $rule['amount'] * $daysLate, 2);
                $created = Penalty::upsertLateFee(
                    $boarderId,
                    (int) $rule['id'],
                    $billingPeriod,
                    $amount,
                    "Late {$daysLate} day(s) for {$billingPeriod}",
                    $today->format('Y-m-t')
                );
                if ($created) {
                    NotificationDispatcher::penaltyApplied($boarderId, $amount, $billingPeriod);
                }
                $applied[] = ['boarder_id' => $boarderId, 'amount' => $amount, 'days_late' => $daysLate, 'new' => $created];
            }
        }

        return $applied;
    }
}
