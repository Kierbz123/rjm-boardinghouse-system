<?php

namespace App\Services;

use App\Database;
use App\Models\PenaltyRule;
use App\Models\Penalty;
use App\Models\Payment;

/**
 * Feature 9 (Penalty and Fee Automation). Triggered on admin login or a
 * manual "run penalty check" button — no cron guarantee on localhost, per
 * ARCHITECTURE.md §4.5. $today is injectable so this is unit-testable
 * without manipulating the system clock.
 */
class PenaltyEngine
{
    private const DUE_DAY = 5;

    public static function runCheck(?\DateTimeImmutable $today = null): array
    {
        $today = $today ?? new \DateTimeImmutable('now');
        $applied = [];

        $dayOfMonth = (int) $today->format('j');
        if ($dayOfMonth <= self::DUE_DAY) {
            return $applied;
        }
        $daysLate = $dayOfMonth - self::DUE_DAY;
        $billingPeriod = $today->format('Y-m');

        $rules = array_filter(PenaltyRule::allActive(), fn ($r) => $r['condition_type'] === 'late_per_day');
        if (empty($rules)) {
            return $applied;
        }

        $pdo = Database::getConnection();
        $activeBoarders = $pdo->query("SELECT user_id FROM boarder_profiles WHERE status = 'active'")->fetchAll();
        $verifiedIds = Payment::verifiedBoarderIdsForPeriod($billingPeriod);

        foreach ($activeBoarders as $b) {
            $boarderId = (int) $b['user_id'];
            if (in_array($boarderId, $verifiedIds, true)) {
                continue;
            }
            foreach ($rules as $rule) {
                $amount = round((float) $rule['amount'] * $daysLate, 2);
                Penalty::create($boarderId, (int) $rule['id'], $amount, "Late {$daysLate} day(s) for {$billingPeriod}");
                NotificationDispatcher::penaltyApplied($boarderId, $amount, $billingPeriod);
                $applied[] = ['boarder_id' => $boarderId, 'amount' => $amount, 'days_late' => $daysLate];
            }
        }

        return $applied;
    }
}
