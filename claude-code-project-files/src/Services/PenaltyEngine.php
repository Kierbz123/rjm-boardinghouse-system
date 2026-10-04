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
 * A month's rent is late from the day after its due date (the 5th, or 30 days
 * after move-in for a new resident — BillingService::dueDate()). Every month that
 * is still unpaid is checked, not only the current one, except months that fell
 * due before these rules went live (BillingService::rulesStartDate()).
 *
 * Safe to run any number of times: each boarder gets at most one late fee per
 * rule per month, whose amount tracks the days late until that month is paid.
 */
class PenaltyEngine
{
    /** Kept for older callers; the rule itself lives in BillingService. */
    public const DUE_DAY = BillingService::DUE_DAY;

    public static function runCheck(?\DateTimeImmutable $today = null): array
    {
        $today = $today ?? new \DateTimeImmutable('today');

        $rules = array_filter(PenaltyRule::allActive(), fn ($r) => $r['condition_type'] === 'late_per_day');
        if (empty($rules)) {
            return [];
        }

        $pdo = Database::getConnection();
        $rulesStart = BillingService::rulesStartDate();
        $boarderIds = $pdo->query("SELECT user_id FROM boarder_profiles WHERE status IN ('active', 'on_notice')")
            ->fetchAll(\PDO::FETCH_COLUMN);

        $applied = [];
        foreach ($boarderIds as $boarderId) {
            $boarderId = (int) $boarderId;
            $balance = BillingService::calculateBalance($boarderId, $pdo);

            foreach ($balance['unpaid_rent'] as $period => $unpaid) {
                $dueDate = $balance['rent_due_dates'][$period] ?? null;
                if ($unpaid <= 0 || $dueDate === null) {
                    continue;
                }
                $due = new \DateTimeImmutable($dueDate);
                if ($today <= $due) {
                    continue; // not late yet
                }
                if ($rulesStart !== null && $dueDate < $rulesStart) {
                    continue; // due before these rules went live: never backdate a fee onto it
                }
                $daysLate = (int) $due->diff($today)->days;
                $label = BillingService::periodLabel($period);

                foreach ($rules as $rule) {
                    $amount = round((float) $rule['amount'] * $daysLate, 2);
                    $created = Penalty::upsertLateFee(
                        $boarderId,
                        (int) $rule['id'],
                        $period,
                        $amount,
                        "Late {$daysLate} day(s) for {$label} rent (due " . $due->format('M j, Y') . ')',
                        $dueDate
                    );
                    if ($created) {
                        NotificationDispatcher::penaltyApplied($boarderId, $amount, $label);
                    }
                    $applied[] = ['boarder_id' => $boarderId, 'period' => $period, 'amount' => $amount, 'days_late' => $daysLate, 'new' => $created];
                }
            }
        }

        return $applied;
    }
}
