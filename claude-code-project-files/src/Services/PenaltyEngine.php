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
 * Late days stop counting on the day a receipt is submitted, for the months that
 * receipt would fully pay (oldest first) — the resident is never charged for the
 * time it takes an admin to review it. If that receipt is rejected, the months
 * count as late again from their due date. The admin may also waive a fee, with a
 * reason, when approving (PaymentReviewService).
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
        if (!self::lateRules()) {
            return [];
        }

        $pdo = Database::getConnection();
        $boarderIds = $pdo->query("SELECT user_id FROM boarder_profiles WHERE status IN ('active', 'on_notice')")
            ->fetchAll(\PDO::FETCH_COLUMN);

        $pending = $pdo->prepare("SELECT claimed_amount, created_at FROM payments
                                  WHERE boarder_id = ? AND verification_status IN ('pending', 'flagged')
                                  ORDER BY id LIMIT 1");
        $applied = [];
        foreach ($boarderIds as $boarderId) {
            $boarderId = (int) $boarderId;
            $balance = BillingService::calculateBalance($boarderId, $pdo);
            $pending->execute([$boarderId]);
            $receipt = $pending->fetch();
            $stops = $receipt
                ? self::stopDates($balance, (float) $receipt['claimed_amount'], new \DateTimeImmutable(substr($receipt['created_at'], 0, 10)))
                : [];
            foreach (self::feesFor($balance, $today, $stops) as $fee) {
                $applied[] = self::apply($boarderId, $fee);
            }
        }

        return $applied;
    }

    /**
     * The months a receipt for $amount, submitted on $submittedOn, would fully pay
     * (oldest unpaid month first, the order an approval applies it in), each mapped
     * to the submission date — the last day that can count as late for that month.
     *
     * @return array<string, \DateTimeImmutable> period => submission date
     */
    public static function stopDates(array $balance, float $amount, \DateTimeImmutable $submittedOn): array
    {
        $stops = [];
        $left = $amount;
        foreach ($balance['unpaid_rent'] as $period => $unpaid) {
            if ($left + 0.004 < (float) $unpaid) {
                break; // a month this receipt doesn't fully pay stays late
            }
            $stops[$period] = $submittedOn;
            $left -= (float) $unpaid;
        }
        return $stops;
    }

    /**
     * The late fee each unpaid month should carry today, per active late-fee rule.
     * Pure calculation: nothing is written.
     *
     * @param array<string, \DateTimeImmutable> $stops from stopDates()
     * @param ?array $onlyPeriods limit to these months
     * @return list<array{period:string, rule_id:int, amount:float, days_late:int, due:string, until:string}>
     */
    public static function feesFor(array $balance, \DateTimeImmutable $today, array $stops = [], ?array $onlyPeriods = null): array
    {
        $rules = self::lateRules();
        $rulesStart = BillingService::rulesStartDate();
        $fees = [];
        foreach ($balance['unpaid_rent'] as $period => $unpaid) {
            $dueDate = $balance['rent_due_dates'][$period] ?? null;
            if ($unpaid <= 0 || $dueDate === null || ($onlyPeriods !== null && !in_array($period, $onlyPeriods, true))) {
                continue;
            }
            if ($rulesStart !== null && $dueDate < $rulesStart) {
                continue; // due before these rules went live: never backdate a fee onto it
            }
            $due = new \DateTimeImmutable($dueDate);
            $until = isset($stops[$period]) ? min($today, $stops[$period]) : $today;
            if ($until <= $due) {
                continue; // not late, or the receipt came in on time
            }
            $daysLate = (int) $due->diff($until)->days;
            foreach ($rules as $rule) {
                $fees[] = [
                    'period' => $period,
                    'rule_id' => (int) $rule['id'],
                    'amount' => round((float) $rule['amount'] * $daysLate, 2),
                    'days_late' => $daysLate,
                    'due' => $dueDate,
                    'until' => $until->format('Y-m-d'),
                ];
            }
        }
        return $fees;
    }

    /** Writes one fee from feesFor() (insert or update, never a second row). */
    public static function apply(int $boarderId, array $fee): array
    {
        $label = BillingService::periodLabel($fee['period']);
        $due = new \DateTimeImmutable($fee['due']);
        $created = Penalty::upsertLateFee(
            $boarderId,
            $fee['rule_id'],
            $fee['period'],
            $fee['amount'],
            "Late {$fee['days_late']} day(s) for {$label} rent (due " . $due->format('M j, Y') . ')',
            $fee['due']
        );
        if ($created) {
            NotificationDispatcher::penaltyApplied($boarderId, $fee['amount'], $label);
        }
        return ['boarder_id' => $boarderId, 'period' => $fee['period'], 'amount' => $fee['amount'],
            'days_late' => $fee['days_late'], 'new' => $created];
    }

    private static function lateRules(): array
    {
        return array_values(array_filter(PenaltyRule::allActive(), fn ($r) => $r['condition_type'] === 'late_per_day'));
    }
}
