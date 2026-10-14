<?php

namespace App\Services;

use App\Database;
use App\Models\BoarderProfile;
use App\Support\Logger;

/**
 * Things that must happen because a date arrived, not because someone clicked.
 * There is no cron on localhost, so admin pages call these when they open (cheap, idempotent).
 * WP-9 wraps them in a once-a-day job with the reminders.
 */
final class HousekeepingService
{
    /**
     * Residents whose scheduled move-out date has arrived become "moved out" and their bed is freed.
     *
     * @return int how many residents were moved out
     */
    public static function applyDueMoveOuts(?\DateTimeImmutable $today = null): int
    {
        $today ??= new \DateTimeImmutable('today');
        $stmt = Database::getConnection()->prepare(
            "SELECT user_id FROM boarder_profiles WHERE move_out_date IS NOT NULL AND move_out_date <= ? AND status IN ('pending', 'active', 'on_notice')"
        );
        $stmt->execute([$today->format('Y-m-d')]);
        $moved = 0;
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $userId) {
            try {
                BoarderProfile::updateStatus((int) $userId, 'moved_out', null, 'Scheduled move-out date reached');
                AuditLog::record(null, 'move_out_applied', 'boarder', (int) $userId, ['on' => $today->format('Y-m-d')]);
                $moved++;
            } catch (\Throwable $e) {
                Logger::error("Scheduled move-out of resident {$userId} failed: " . $e->getMessage());
            }
        }
        return $moved;
    }
}
