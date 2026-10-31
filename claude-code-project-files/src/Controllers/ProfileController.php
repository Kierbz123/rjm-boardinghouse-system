<?php

namespace App\Controllers;

use App\Database;
use App\Models\User;
use App\Models\BoarderProfile;
use App\Support\Csrf;
use App\Support\Phone;

class ProfileController
{
    public static function index(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            header('Location: /login');
            exit;
        }

        $user = User::findById($userId);
        if ($user === null) {
            session_destroy();
            header('Location: /login');
            exit;
        }

        $role = $user['role'] ?? 'boarder';
        $boarderProfile = null;
        $stats = [];

        $pdo = Database::getConnection();

        if ($role === 'boarder') {
            $stmt = $pdo->prepare('
                SELECT bp.*, r.room_number, r.base_price, r.floor, b.label AS bed_label
                FROM boarder_profiles bp
                LEFT JOIN rooms r ON r.id = bp.room_id
                LEFT JOIN beds b ON b.id = bp.bed_id
                WHERE bp.user_id = ?
            ');
            $stmt->execute([$userId]);
            $boarderProfile = $stmt->fetch() ?: [];

            // Activity metrics for boarder
            $payStmt = $pdo->prepare('SELECT COUNT(*) FROM payments WHERE boarder_id = ? AND verification_status IN ("auto-matched", "admin-approved")');
            $payStmt->execute([$userId]);
            $approvedPaymentsCount = (int) $payStmt->fetchColumn();

            $maintStmt = $pdo->prepare('SELECT COUNT(*) FROM maintenance_requests WHERE boarder_id = ? AND status != "resolved"');
            $maintStmt->execute([$userId]);
            $openMaintCount = (int) $maintStmt->fetchColumn();

            $stats = [
                'payments_count' => $approvedPaymentsCount,
                'open_maintenance' => $openMaintCount,
                'room_number' => $boarderProfile['room_number'] ?? null,
                'bed_label' => $boarderProfile['bed_label'] ?? null,
                'floor' => $boarderProfile['floor'] ?? null,
                'base_price' => $boarderProfile['base_price'] ?? null,
                'status' => $boarderProfile['status'] ?? ($user['status'] ?? 'active'),
            ];
        } elseif ($role === 'admin') {
            $boardersCount = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "boarder"')->fetchColumn();
            $occupiedBeds = (int) $pdo->query('SELECT COUNT(*) FROM beds WHERE status = "occupied"')->fetchColumn();
            $vacantBeds = (int) $pdo->query('SELECT COUNT(*) FROM beds WHERE status = "vacant"')->fetchColumn();
            $pendingMaintenance = (int) $pdo->query('SELECT COUNT(*) FROM maintenance_requests WHERE status != "resolved"')->fetchColumn();

            $stats = [
                'total_boarders' => $boardersCount,
                'occupied_beds' => $occupiedBeds,
                'vacant_beds' => $vacantBeds,
                'pending_maintenance' => $pendingMaintenance,
            ];
        } elseif ($role === 'staff') {
            $activeQueue = (int) $pdo->query('SELECT COUNT(*) FROM maintenance_requests WHERE status IN ("open", "in_progress")')->fetchColumn();
            $openIncidents = (int) $pdo->query('SELECT COUNT(*) FROM incidents WHERE resolved = 0')->fetchColumn();
            $activeSos = (int) $pdo->query('SELECT COUNT(*) FROM sos_alerts WHERE status != "resolved"')->fetchColumn();

            $stats = [
                'active_queue' => $activeQueue,
                'open_incidents' => $openIncidents,
                'active_sos' => $activeSos,
            ];
        }

        require __DIR__ . '/../Views/shared/profile.php';
    }

    public static function updateInfo(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $_SESSION['flash_error'] = 'Invalid session token. Please try again.';
            header('Location: /profile');
            exit;
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            header('Location: /login');
            exit;
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $contactRaw = $_POST['contact_number'] ?? '';
        $emergencyRaw = $_POST['emergency_contact_number'] ?? '';
        $contactNumber = Phone::digits($contactRaw);
        $emergencyContact = Phone::digits($emergencyRaw);

        if ($name === '' || $email === '') {
            $_SESSION['flash_error'] = 'Full name and email address are required.';
            header('Location: /profile');
            exit;
        }

        if (mb_strlen($name) > 150 || mb_strlen($email) > 150) {
            $_SESSION['flash_error'] = 'Name and email must not exceed 150 characters.';
            header('Location: /profile');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Please enter a valid email address.';
            header('Location: /profile');
            exit;
        }
        if (($phoneProblem = Phone::problem($contactRaw) ?? Phone::problem($emergencyRaw, false, 'emergency contact number')) !== null) {
            $_SESSION['flash_error'] = $phoneProblem;
            header('Location: /profile');
            exit;
        }

        if (User::emailTakenByAnotherUser($email, $userId)) {
            $_SESSION['flash_error'] = 'That email address is already registered to another account.';
            header('Location: /profile');
            exit;
        }

        User::updateInfo($userId, $name, $email);

        $currentUser = User::findById($userId);
        if (($currentUser['role'] ?? '') === 'boarder') {
            BoarderProfile::updateProfile($userId, $contactNumber !== '' ? $contactNumber : null, $emergencyContact !== '' ? $emergencyContact : null);
        }

        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;

        $_SESSION['flash_success'] = 'Profile information updated successfully.';
        header('Location: /profile');
        exit;
    }

    public static function updatePassword(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $_SESSION['flash_error'] = 'Invalid session token. Please try again.';
            header('Location: /profile');
            exit;
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            header('Location: /login');
            exit;
        }

        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $_SESSION['flash_error'] = 'All password fields are required.';
            header('Location: /profile');
            exit;
        }

        $user = User::findById($userId);
        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            $_SESSION['flash_error'] = 'The current password you entered is incorrect.';
            header('Location: /profile');
            exit;
        }

        if (($problem = \App\Support\PasswordPolicy::problem($newPassword, [$user['name'], $user['email']])) !== null) {
            $_SESSION['flash_error'] = $problem;
            header('Location: /profile');
            exit;
        }
        if (hash_equals($currentPassword, $newPassword)) {
            $_SESSION['flash_error'] = 'Choose a password different from the current one.';
            header('Location: /profile');
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            $_SESSION['flash_error'] = 'New password and confirmation do not match.';
            header('Location: /profile');
            exit;
        }

        User::updatePassword($userId, $newPassword);
        User::markPasswordChosen($userId);
        // Other sessions now fail AuthMiddleware's fingerprint check; keep this one.
        session_regenerate_id(true);
        $_SESSION['pw_fp'] = \App\Middleware\AuthMiddleware::passwordFingerprint(User::findById($userId));

        $_SESSION['flash_success'] = 'Your password has been changed successfully.';
        header('Location: /profile');
        exit;
    }
}
