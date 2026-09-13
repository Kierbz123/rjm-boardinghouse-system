<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Room;
use App\Models\Bed;
use App\Models\BoarderProfile;
use App\Support\Csrf;
use RuntimeException;

class BoarderController
{
    public static function index(): void
    {
        $boarders = BoarderProfile::all();
        $rooms = Room::all();
        $beds = Bed::all();
        require __DIR__ . '/../Views/admin/boarders.php';
    }

    public static function create(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $bedId = !empty($_POST['bed_id']) ? (int) $_POST['bed_id'] : null;

        if ($name === '' || $email === '' || $password === '') {
            $_SESSION['flash_error'] = 'Name, email, and password are all required.';
            header('Location: /admin/boarders');
            exit;
        }
        if (mb_strlen($name) > 150 || mb_strlen($email) > 150) {
            $_SESSION['flash_error'] = 'Name and email must be 150 characters or fewer.';
            header('Location: /admin/boarders');
            exit;
        }

        // Bug found during Phase 8 QA pass: creating a boarder with an email that
        // already exists threw an uncaught PDO integrity-constraint exception
        // (caught only by the generic 500 handler). Check first, fail gracefully.
        if (User::findByEmail($email) !== null) {
            $_SESSION['flash_error'] = 'That email is already in use.';
            header('Location: /admin/boarders');
            exit;
        }

        $userId = User::create('boarder', $name, $email, $password);
        $roomId = !empty($_POST['room_id']) ? (int) $_POST['room_id'] : null;

        if ($bedId) {
            $bed = Bed::find($bedId);
            $roomId = !empty($bed['room_id']) ? (int) $bed['room_id'] : $roomId;
            try {
                Bed::assign($bedId, $userId);
            } catch (RuntimeException $e) {
                $_SESSION['flash_error'] = $e->getMessage();
                header('Location: /admin/boarders');
                exit;
            }
        }

        BoarderProfile::create($userId, $roomId, $bedId);
        header('Location: /admin/boarders');
        exit;
    }

    public static function updateStatus(string $userId): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $newStatus = (string) ($_POST['status'] ?? '');
        $reason = (string) ($_POST['reason'] ?? '');
        try {
            BoarderProfile::updateStatus((int) $userId, $newStatus, (int) $_SESSION['user_id'], $reason);
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        header('Location: /admin/boarders');
        exit;
    }

    /** Assigns an existing boarder to a bed. Rejects if the bed is already occupied — Phase 2 verification. */
    public static function assignBed(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $boarderId = (int) $_POST['boarder_id'];
        $bedId = (int) $_POST['bed_id'];
        try {
            $bed = Bed::find($bedId);
            Bed::assign($bedId, $boarderId);
            BoarderProfile::assignRoomAndBed($boarderId, (int) $bed['room_id'], $bedId);
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        $redirect = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/admin/rooms';
        header('Location: ' . $redirect);
        exit;
    }

    /** Comprehensive update for boarder details: name, email, contact numbers, status, and note. */
    public static function updateInfo(string $userId): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }

        $id = (int) $userId;
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $contactNumber = trim((string) ($_POST['contact_number'] ?? ''));
        $emergencyContactNumber = trim((string) ($_POST['emergency_contact_number'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? $_POST['reason'] ?? ''));

        if ($name === '' || $email === '') {
            $_SESSION['flash_error'] = 'Name and email are both required.';
            header('Location: /admin/boarders');
            exit;
        }
        if (mb_strlen($name) > 150 || mb_strlen($email) > 150) {
            $_SESSION['flash_error'] = 'Name and email must be 150 characters or fewer.';
            header('Location: /admin/boarders');
            exit;
        }
        if (mb_strlen($contactNumber) > 50 || mb_strlen($emergencyContactNumber) > 50) {
            $_SESSION['flash_error'] = 'Contact numbers must be 50 characters or fewer.';
            header('Location: /admin/boarders');
            exit;
        }
        if (User::emailTakenByAnotherUser($email, $id)) {
            $_SESSION['flash_error'] = 'That email is already in use by another account.';
            header('Location: /admin/boarders');
            exit;
        }

        // Update user record (name, email)
        User::updateInfo($id, $name, $email);

        // Update boarder profile record (contact numbers)
        BoarderProfile::updateProfile(
            $id,
            $contactNumber !== '' ? $contactNumber : null,
            $emergencyContactNumber !== '' ? $emergencyContactNumber : null
        );

        // Update status if specified and either changed or note attached
        if ($status !== '') {
            $current = BoarderProfile::find($id);
            if ($current && ($current['status'] !== $status || $note !== '')) {
                try {
                    $adminId = (int) ($_SESSION['user_id'] ?? 0);
                    $reasonText = $note !== '' ? $note : 'Updated by administrator';
                    BoarderProfile::updateStatus($id, $status, $adminId ?: null, $reasonText);
                } catch (\Throwable $e) {
                    $_SESSION['flash_error'] = $e->getMessage();
                    header('Location: /admin/boarders');
                    exit;
                }
            }
        }

        $_SESSION['flash_success'] = "Updated resident details for {$name}.";
        header('Location: /admin/boarders');
        exit;
    }

    public static function delete(string $userId): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }

        try {
            BoarderProfile::delete((int) $userId);
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: /admin/boarders');
        exit;
    }
}
