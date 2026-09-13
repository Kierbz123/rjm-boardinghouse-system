<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\LoginAttempt;
use App\Models\QrLoginChallenge;
use App\Support\Csrf;
use App\Support\Logger;

class AuthController
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCKOUT_WINDOW_MINUTES = 15;

    public static function showLogin(): void
    {
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);

        $qrToken = null;
        $qrLoginUrl = self::loginPageUrl();
        $defaultMobileUrl = self::loginPageUrl();
        try {
            $challenge = QrLoginChallenge::create(self::qrCreatorKey());
            $qrToken = $challenge['token'];
            $qrLoginUrl = self::publicBaseUrl() . '/qr/' . $qrToken;
        } catch (\Throwable $e) {
            Logger::warn('QR login challenge unavailable: ' . $e->getMessage());
        }

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

    public static function showQr(string $token): void
    {
        $token = self::normalizeQrToken($token);
        $challenge = $token !== null ? QrLoginChallenge::findByToken($token) : null;
        $status = $challenge ? QrLoginChallenge::publicStatus($challenge) : 'invalid';
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);
        $lastEmail = $_SESSION['flash_email'] ?? '';
        unset($_SESSION['flash_email']);
        $loggedInUser = null;
        if (!empty($_SESSION['user_id'])) {
            $loggedInUser = User::findById((int) $_SESSION['user_id']);
        }
        require __DIR__ . '/../Views/shared/qr_login.php';
    }

    public static function approveQr(string $token): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $_SESSION['flash_error'] = 'Your session expired — please try again.';
            header('Location: /qr/' . rawurlencode($token));
            exit;
        }

        $normalized = self::normalizeQrToken($token);
        $challenge = $normalized !== null ? QrLoginChallenge::findByToken($normalized) : null;
        $status = $challenge ? QrLoginChallenge::publicStatus($challenge) : 'invalid';
        if ($status !== 'pending') {
            header('Location: /qr/' . rawurlencode($token));
            exit;
        }

        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $user = User::findById((int) $_SESSION['user_id']);
        } else {
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $auth = self::verifyCredentials($email, $password);
            if (!$auth['ok']) {
                $_SESSION['flash_error'] = $auth['error'];
                $_SESSION['flash_email'] = $email;
                header('Location: /qr/' . rawurlencode($normalized));
                exit;
            }
            $user = $auth['user'];
            self::establishSession($user);
        }

        if (!$user || !QrLoginChallenge::approve($normalized, (int) $user['id'])) {
            $_SESSION['flash_error'] = 'This QR code expired. Generate a new one on the computer.';
            header('Location: /qr/' . rawurlencode($normalized));
            exit;
        }

        Logger::info("User {$user['id']} approved QR login challenge");
        header('Location: /qr/' . rawurlencode($normalized));
        exit;
    }

    public static function qrStatus(string $token): void
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        $normalized = self::normalizeQrToken($token);
        if ($normalized === null) {
            echo json_encode(['status' => 'invalid']);
            return;
        }
        $challenge = QrLoginChallenge::findByToken($normalized);
        echo json_encode([
            'status' => $challenge ? QrLoginChallenge::publicStatus($challenge) : 'invalid',
        ]);
    }

    public static function claimQr(string $token): void
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Invalid session.']);
            return;
        }

        $normalized = self::normalizeQrToken($token);
        if ($normalized === null) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Invalid QR code.']);
            return;
        }

        $consumed = QrLoginChallenge::consume($normalized, self::qrCreatorKey());
        if ($consumed === null) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'message' => 'QR login is not ready.']);
            return;
        }

        $user = User::findById((int) $consumed['user_id']);
        if (!$user) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'message' => 'Account no longer available.']);
            return;
        }

        self::establishSession($user);
        echo json_encode([
            'ok' => true,
            'redirect' => self::destinationFor($user['role']),
        ]);
    }

    /** @return array{ok: true, user: array}|array{ok: false, error: string} */
    private static function verifyCredentials(string $email, string $password): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $recentFailures = LoginAttempt::recentFailedCount($email, self::LOCKOUT_WINDOW_MINUTES);
        if ($recentFailures >= self::MAX_FAILED_ATTEMPTS) {
            Logger::warn("Login locked out for email: {$email} ({$recentFailures} recent failures)");
            return [
                'ok' => false,
                'error' => 'Too many failed attempts. Please wait ' . self::LOCKOUT_WINDOW_MINUTES . ' minutes and try again.',
            ];
        }

        $user = User::findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            LoginAttempt::record($email, false, $ip);
            Logger::warn("Failed login attempt for email: {$email}");
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
        Logger::info("User {$user['id']} ({$user['role']}) logged in");
    }

    private static function destinationFor(string $role): string
    {
        $destinations = ['admin' => '/admin/dashboard', 'staff' => '/staff/dashboard', 'boarder' => '/portal/dashboard'];
        return $destinations[$role] ?? '/login';
    }

    private static function qrCreatorKey(): string
    {
        if (empty($_SESSION['qr_creator_key']) || !is_string($_SESSION['qr_creator_key'])) {
            $_SESSION['qr_creator_key'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['qr_creator_key'];
    }

    private static function normalizeQrToken(string $token): ?string
    {
        $token = strtolower(trim($token));
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1 ? $token : null;
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
