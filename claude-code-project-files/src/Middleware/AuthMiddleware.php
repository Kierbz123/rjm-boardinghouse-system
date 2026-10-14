<?php

namespace App\Middleware;

use App\Models\User;

class AuthMiddleware
{
    private const IDLE_TIMEOUT_SECONDS = 30 * 60;

    /**
     * Blocks unauthenticated access. Redirects to /login rather than exposing any page content.
     * Also ends the session when it has been idle too long, or when the account behind it was
     * deleted, deactivated, changed role, or changed its password since this session logged in.
     */
    public static function require(): void
    {
        if (empty($_SESSION['user_id'])) {
            self::toLogin();
        }

        $idle = time() - (int) ($_SESSION['last_activity'] ?? 0);
        $user = User::findById((int) $_SESSION['user_id']);
        if (
            $idle > self::IDLE_TIMEOUT_SECONDS
            || $user === null
            || ($user['status'] ?? 'active') !== 'active'
            || $user['role'] !== ($_SESSION['role'] ?? null)
            || !hash_equals(self::passwordFingerprint($user), (string) ($_SESSION['pw_fp'] ?? ''))
        ) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['flash_error'] = 'Your session has ended. Please log in again.';
            self::toLogin();
        }

        // A seeded or default-password account may do nothing but choose its own password (or sign out).
        if (!empty($user['must_change_password']) && !self::allowedWhilePasswordMustChange()) {
            $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            if (str_starts_with($path, '/api/')) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'You must choose a new password before using the system.']);
                exit;
            }
            $_SESSION['flash_error'] = 'Choose a new password to continue.';
            header('Location: /profile');
            exit;
        }

        $_SESSION['last_activity'] = time();
    }

    private static function allowedWhilePasswordMustChange(): bool
    {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        return ($method === 'GET' && $path === '/profile')
            || ($method === 'POST' && in_array($path, ['/profile/password', '/logout'], true));
    }

    /** Changes whenever the password does, so a password change logs out other sessions. */
    public static function passwordFingerprint(array $user): string
    {
        return hash('sha256', (string) $user['password_hash']);
    }

    private static function toLogin(): never
    {
        header('Location: /login');
        exit;
    }
}
