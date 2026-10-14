<?php

namespace App\Controllers;

use App\Models\User;
use App\Support\Csrf;

/** Admin-only staff account management: list, add, deactivate/reactivate (owner decision 7b). */
class StaffController
{
    public static function index(): void
    {
        $staff = User::allByRole('staff');
        require __DIR__ . '/../Views/admin/staff.php';
    }

    public static function create(): void
    {
        self::requireCsrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $error = match (true) {
            $name === '' || mb_strlen($name) > 150 => 'Enter a name (150 characters max).',
            !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150 => 'Enter a valid email address.',
            \App\Support\PasswordPolicy::problem($password) !== null => \App\Support\PasswordPolicy::problem($password),
            User::findByEmail($email) !== null => 'That email is already in use.',
            default => null,
        };
        if ($error !== null) {
            self::back('flash_error', $error);
        }

        User::create('staff', $name, $email, $password);
        self::back('flash_success', "Staff account for {$name} created. Share the temporary password privately; they can change it under Profile.");
    }

    public static function setStatus(string $id): void
    {
        self::requireCsrf();
        $user = User::findById((int) $id);
        if ($user === null || $user['role'] !== 'staff') {
            self::back('flash_error', 'Staff account not found.');
        }
        $active = ($_POST['status'] ?? '') === 'active';
        User::setStatus((int) $id, $active ? 'active' : 'inactive');
        self::back('flash_success', $active
            ? "{$user['name']} can log in again."
            : "{$user['name']} is deactivated and has been signed out. Their records are kept.");
    }

    /** A staff member forgot their password: the admin sets a temporary one and tells them in person. */
    public static function resetPassword(string $id): void
    {
        self::requireCsrf();
        $user = User::findById((int) $id);
        if ($user === null || $user['role'] !== 'staff') {
            self::back('flash_error', 'Staff account not found.');
        }
        $error = \App\Services\PasswordResetService::byAdmin($user, (string) ($_POST['new_password'] ?? ''), (int) $_SESSION['user_id']);
        if ($error !== null) {
            self::back('flash_error', $error);
        }
        self::back('flash_success', "New password set for {$user['name']}. They have been signed out; share it privately and ask them to change it under Profile.");
    }

    private static function requireCsrf(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            exit;
        }
    }

    private static function back(string $flashKey, string $message): never
    {
        $_SESSION[$flashKey] = $message;
        header('Location: /admin/staff');
        exit;
    }
}
