<?php

namespace App\Services;

use App\Models\LoginAttempt;
use App\Models\User;
use App\Support\Logger;

/**
 * An administrator sets a new temporary password for a resident or staff member who
 * forgot theirs (the login page tells them to ask the administrator). There is no
 * email in this system, so the admin passes the temporary password on in person.
 */
class PasswordResetService
{
    /** @return string|null an error to show the admin, or null when the password was changed */
    public static function byAdmin(array $user, string $newPassword, int $adminId): ?string
    {
        // Checked here as well as in the controllers: an admin account is never reset this way.
        if (!in_array($user['role'] ?? '', ['boarder', 'staff'], true)) {
            return 'That account cannot be reset here.';
        }
        // 72 bytes is all the password hash looks at; anything longer would be silently cut.
        if (mb_strlen($newPassword) < 8 || strlen($newPassword) > 72) {
            return 'The temporary password must be 8 to 72 characters.';
        }

        // A new hash also ends every session that account still has open (AuthMiddleware).
        User::updatePassword((int) $user['id'], $newPassword);
        // Someone who forgot their password has usually just locked themselves out.
        LoginAttempt::clearFailures((string) $user['email']);
        NotificationDispatcher::passwordResetByAdmin((int) $user['id']);
        Logger::info("Admin {$adminId} reset the password of user {$user['id']} ({$user['role']})");

        return null;
    }
}
