<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\LoginAttempt;
use App\Support\Csrf;
use App\Support\Logger;

class AuthController
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCKOUT_WINDOW_MINUTES = 15;
    private const MAX_FAILED_ATTEMPTS_PER_IP = 20; // slows password spraying across many emails

    public static function showLogin(): void
    {
        // If user is already authenticated, redirect to their dashboard
        if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
            header('Location: ' . self::destinationFor($_SESSION['role']));
            exit;
        }

        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);

        $defaultMobileUrl = self::loginPageUrl();

        require __DIR__ . '/../Views/shared/login.php';
    }

    public static function login(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $_SESSION['flash_error'] = 'Your session expired — please try again.';
            header('Location: /login');
            exit;
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $auth = self::verifyCredentials($email, $password);
        if (!$auth['ok']) {
            $_SESSION['flash_error'] = $auth['error'];
            $_SESSION['flash_email'] = $email;
            header('Location: /login');
            exit;
        }

        self::establishSession($auth['user']);
        header('Location: ' . self::destinationFor($auth['user']['role']));
        exit;
    }

    public static function logout(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $_SESSION = [];
        session_destroy();
        header('Location: /login');
        exit;
    }

    /** @return array{ok: true, user: array}|array{ok: false, error: string} */
    private static function verifyCredentials(string $email, string $password): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $masked = Logger::maskEmail($email);
        if (
            LoginAttempt::recentFailedCount($email, self::LOCKOUT_WINDOW_MINUTES) >= self::MAX_FAILED_ATTEMPTS
            || ($ip !== null && LoginAttempt::recentFailedCountByIp($ip, self::LOCKOUT_WINDOW_MINUTES) >= self::MAX_FAILED_ATTEMPTS_PER_IP)
        ) {
            Logger::warn("Login locked out for {$masked} from {$ip}");
            return [
                'ok' => false,
                'error' => 'Too many failed attempts. Please wait ' . self::LOCKOUT_WINDOW_MINUTES . ' minutes and try again.',
            ];
        }

        $user = User::findByEmail($email);
        // Verify against a dummy hash for unknown emails so response time doesn't reveal which accounts exist.
        $hash = $user['password_hash'] ?? '$2y$10$fWfP4shAj5Sb.lFNx10hq.zIgFMlpyqkmeog9v3bu6Lw/8/IIDK/G';
        $passwordOk = password_verify($password, $hash);
        if (!$user || !$passwordOk || ($user['status'] ?? 'active') !== 'active') {
            LoginAttempt::record($email, false, $ip);
            Logger::warn("Failed login attempt for {$masked} from {$ip}");
            return ['ok' => false, 'error' => 'Invalid email or password.'];
        }

        LoginAttempt::record($email, true, $ip);
        return ['ok' => true, 'user' => $user];
    }

    private static function establishSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['last_activity'] = time();
        $_SESSION['pw_fp'] = \App\Middleware\AuthMiddleware::passwordFingerprint($user);
        Logger::info("User {$user['id']} ({$user['role']}) logged in");
    }

    private static function destinationFor(string $role): string
    {
        $destinations = ['admin' => '/admin/dashboard', 'staff' => '/staff/dashboard', 'boarder' => '/portal/dashboard'];
        return $destinations[$role] ?? '/login';
    }

    private static function loginPageUrl(): string
    {
        return self::publicBaseUrl() . '/login';
    }

    private static function publicBaseUrl(): string
    {
        $httpHost = (string) ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000');
        $hostOnly = $httpHost;
        $port = 8000;
        if (str_contains($httpHost, ']')) {
            // Ignore IPv6 bracket form; this app is served on IPv4 localhost.
            $hostOnly = '127.0.0.1';
        } elseif (str_contains($httpHost, ':')) {
            [$hostOnly, $portPart] = explode(':', $httpHost, 2);
            $port = (int) $portPart ?: 8000;
        } elseif (!empty($_SERVER['SERVER_PORT'])) {
            $port = (int) $_SERVER['SERVER_PORT'];
        }

        $lan = self::detectLanIpv4();
        $host = $lan ?? $hostOnly;
        if ($host === '' || $host === 'localhost') {
            $host = '127.0.0.1';
        }

        $portSuffix = ($port === 80) ? '' : ':' . $port;
        return 'http://' . $host . $portSuffix;
    }

    private static function detectLanIpv4(): ?string
    {
        $candidates = [];
        $hostname = gethostname();
        if (is_string($hostname) && $hostname !== '') {
            $resolved = gethostbynamel($hostname);
            if (is_array($resolved)) {
                $candidates = array_merge($candidates, $resolved);
            }
            $single = gethostbyname($hostname);
            if (is_string($single)) {
                $candidates[] = $single;
            }
        }
        if (!empty($_SERVER['SERVER_ADDR'])) {
            $candidates[] = (string) $_SERVER['SERVER_ADDR'];
        }

        foreach ($candidates as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                continue;
            }
            if (str_starts_with($ip, '127.') || $ip === '0.0.0.0') {
                continue;
            }
            return $ip;
        }
        return null;
    }
}
