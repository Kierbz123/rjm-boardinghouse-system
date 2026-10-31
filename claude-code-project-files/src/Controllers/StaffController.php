<?php

namespace App\Controllers;

use App\Models\SosAlert;
use App\Models\User;
use App\Services\AuditLog;
use App\Support\Csrf;
use App\Support\PasswordPolicy;
use App\Support\Phone;
use App\Support\TrustedProxy;

/**
 * Admin-only User Management. The default view lists residents and staff together; the Staff tab is
 * the staff account page (add, reset, deactivate/reactivate, owner decision 7b); the Boarders tab is the
 * Resident Directory. Adding and placing a resident stays on /admin/boarders.
 */
class StaffController
{
    private const PER_PAGE = 25;

    public static function index(): void
    {
        $view = $_GET['view'] ?? 'all';
        if ($view === 'boarders') {
            \App\Services\HousekeepingService::applyDueMoveOuts(); // like every other resident list, so statuses are current
            $showArchived = ($_GET['archived'] ?? '') === '1';
            $boarders = \App\Models\BoarderProfile::all($showArchived);
            require __DIR__ . '/../Views/admin/boarders_directory.php';
            return;
        }
        if ($view === 'staff') {
            $staff = User::allByRole('staff');
            $history = AuditLog::forUsersWithRole('staff');
            require __DIR__ . '/../Views/admin/staff.php';
            return;
        }

        $pick = fn (string $key, array $allowed, string $default) => in_array($_GET[$key] ?? null, $allowed, true) ? $_GET[$key] : $default;
        $filters = [
            'q' => is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '',
            'type' => $pick('type', ['boarder', 'staff'], ''),
            'status' => $pick('status', User::MANAGEMENT_STATUSES, ''),
            'sort' => $pick('sort', User::MANAGEMENT_SORTS, 'name'),
        ];
        $page = min(10000, max(1, (int) ($_GET['page'] ?? 1)));
        ['rows' => $users, 'total' => $total] = User::managementList($filters, $page, self::PER_PAGE);
        $counts = User::managementCounts();
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        require __DIR__ . '/../Views/admin/users_all.php';
    }

    public static function create(): void
    {
        self::requireCsrf();
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) ($_POST['name'] ?? '')));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $phoneRaw = $_POST['phone'] ?? '';
        $phone = Phone::digits($phoneRaw);

        $error = match (true) {
            $name === '' || mb_strlen($name) > 150 => 'Enter a name (150 characters max).',
            !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150 => 'Enter a valid email address.',
            Phone::problem($phoneRaw) !== null => Phone::problem($phoneRaw),
            // Admin-only page, so naming the clash leaks nothing; it also covers deactivated accounts.
            User::findByEmail($email) !== null => 'That email is already in use by another account (it may be deactivated).',
            default => PasswordPolicy::problem($password, [$name, $email]),
        };
        if ($error !== null) {
            self::back('flash_error', $error);
        }

        // One transaction: an admin-known password never exists without its "temporary" mark and audit row.
        $db = \App\Database::getConnection();
        $db->beginTransaction();
        $id = User::create('staff', $name, $email, $password);
        User::markPasswordTemporary($id);
        User::setPhone($id, $phone);
        self::audit('staff_created', $id);
        $db->commit();
        self::back('flash_success', "Staff account for {$name} created. Give them the temporary password in person: it works for "
            . User::TEMP_PASSWORD_HOURS . ' hours, and they must choose their own password when they first sign in.'
            . ($phone === '' ? ' Add their phone number so residents can call them if an SOS goes unanswered.' : ''));
    }

    /** Residents see active staff phone numbers beside the SOS button (owner decision, 15 Oct 2026). */
    public static function setPhone(string $id): void
    {
        self::requireCsrf();
        $user = User::findById((int) $id);
        if ($user === null || $user['role'] !== 'staff') {
            self::back('flash_error', 'Staff account not found.');
        }
        $phoneRaw = $_POST['phone'] ?? '';
        if (($error = Phone::problem($phoneRaw)) !== null) {
            self::back('flash_error', $error);
        }
        $phone = Phone::digits($phoneRaw);
        User::setPhone((int) $id, $phone);
        self::audit('staff_phone_changed', (int) $id); // the number itself stays out of the trail
        self::back('flash_success', $phone === '' ? "Phone number removed for {$user['name']}." : "Phone number saved for {$user['name']}.");
    }

    public static function setStatus(string $id): void
    {
        self::requireCsrf();
        $user = User::findById((int) $id);
        if ($user === null || $user['role'] !== 'staff') {
            self::back('flash_error', 'Staff account not found.');
        }
        $active = ($_POST['status'] ?? '') === 'active';
        if (($user['status'] === 'active') === $active) {
            self::back('flash_info', "{$user['name']} is already " . ($active ? 'active.' : 'deactivated.'));
        }
        // Their next request fails AuthMiddleware's status check, so any open session ends there.
        User::setStatus((int) $id, $active ? 'active' : 'inactive');
        self::audit($active ? 'staff_reactivated' : 'staff_deactivated', (int) $id);
        if ($active) {
            self::back('flash_success', "{$user['name']} can log in again.");
        }
        $open = SosAlert::openAcknowledgedBy((int) $id);
        self::back('flash_success', "{$user['name']} is deactivated and has been signed out. Their records are kept."
            . ($open ? " {$open} SOS alert(s) they acknowledged are still open: anyone on staff, or you, can resolve them from the staff dashboard." : ''));
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
        self::back('flash_success', "New temporary password set for {$user['name']}. They have been signed out. Tell them in person; it works for "
            . User::TEMP_PASSWORD_HOURS . ' hours and they must choose their own password when they sign in.');
    }

    private static function audit(string $action, int $userId): void
    {
        AuditLog::record((int) $_SESSION['user_id'], $action, 'user', $userId, ['ip' => TrustedProxy::clientIp($_SERVER)]);
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
        header('Location: /admin/staff?view=staff');
        exit;
    }
}
