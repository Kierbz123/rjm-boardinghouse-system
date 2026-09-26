<?php

namespace App\Models;

use App\Database;

/** Backs the login rate-limiting closed in Phase 8 — see PHASES.md. */
class LoginAttempt
{
    public static function record(string $email, bool $successful, ?string $ipAddress): void
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO login_attempts (email, ip_address, successful) VALUES (?, ?, ?)'
        );
        $stmt->execute([$email, $ipAddress, $successful ? 1 : 0]);
    }

    /** Failed attempts for this email within the trailing $windowMinutes — a plain
     *  time window, not "since last success", so a lockout always expires on its own
     *  rather than potentially compounding forever after someone's very first success. */
    public static function recentFailedCount(string $email, int $windowMinutes): int
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = ? AND successful = 0 AND created_at > (NOW() - INTERVAL ? MINUTE)'
        );
        $stmt->execute([$email, $windowMinutes]);
        return (int) $stmt->fetchColumn();
    }
}
