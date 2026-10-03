<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Room;
use App\Models\Bed;
use App\Models\BoarderProfile;
use App\Models\Payment;
use App\Models\Penalty;
use App\Models\PenaltyRule;
use App\Models\MaintenanceRequest;
use App\Models\Incident;
use App\Services\BillingService;
use App\Services\NotificationDispatcher;
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
            // Fix stale-bed bug: if boarder already occupies a different bed, vacate it first
            $currentProfile = BoarderProfile::find($boarderId);
            if (!empty($currentProfile['bed_id']) && (int) $currentProfile['bed_id'] !== $bedId) {
                Bed::vacate((int) $currentProfile['bed_id']);
            }

            Bed::assign($bedId, $boarderId);
            BoarderProfile::assignRoomAndBed($boarderId, (int) $bed['room_id'], $bedId);
            BillingService::calculateBalance($boarderId); // the new room's price applies from this month

            $room = Room::find((int) $bed['room_id']);
            $roomNumber = $room['room_number'] ?? (string) $bed['room_id'];
            $bedLabel = $bed['label'] ?? 'Bed';
            NotificationDispatcher::bedAssigned($boarderId, $roomNumber, $bedLabel);
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        // Only ever bounce back to a page of this app, never to wherever Referer points.
        $refererPath = (string) parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH);
        $redirect = preg_match('#^/admin/[a-z0-9/_-]*$#', $refererPath) ? $refererPath : '/admin/rooms';
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
        $returnTo = trim((string) ($_POST['return_to'] ?? ''));
        $redirectUrl = preg_match('#^/admin/boarders/\d+$#', $returnTo) ? $returnTo : '/admin/boarders';

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $contactNumber = trim((string) ($_POST['contact_number'] ?? ''));
        $emergencyContactNumber = trim((string) ($_POST['emergency_contact_number'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? $_POST['reason'] ?? ''));

        if ($name === '' || $email === '') {
            $_SESSION['flash_error'] = 'Name and email are both required.';
            header('Location: ' . $redirectUrl);
            exit;
        }
        if (mb_strlen($name) > 150 || mb_strlen($email) > 150) {
            $_SESSION['flash_error'] = 'Name and email must be 150 characters or fewer.';
            header('Location: ' . $redirectUrl);
            exit;
        }
        if (mb_strlen($contactNumber) > 50 || mb_strlen($emergencyContactNumber) > 50) {
            $_SESSION['flash_error'] = 'Contact numbers must be 50 characters or fewer.';
            header('Location: ' . $redirectUrl);
            exit;
        }
        if (User::emailTakenByAnotherUser($email, $id)) {
            $_SESSION['flash_error'] = 'That email is already in use by another account.';
            header('Location: ' . $redirectUrl);
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
                    header('Location: ' . $redirectUrl);
                    exit;
                }
            }
        }

        $_SESSION['flash_success'] = "Updated resident details for {$name}.";
        header('Location: ' . $redirectUrl);
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

    private static function resolveBoarderOrRedirect(string $userId, string $returnTo = '/admin/boarders', bool $checkCsrf = false): ?array
    {
        if ($checkCsrf && !Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            exit;
        }

        if (!ctype_digit($userId)) {
            http_response_code(404);
            echo 'Boarder not found.';
            exit;
        }

        $id = (int) $userId;
        $boarder = BoarderProfile::findWithFullDetails($id);
        if (!$boarder) {
            $_SESSION['flash_error'] = 'Boarder not found.';
            header('Location: ' . $returnTo);
            exit;
        }

        return $boarder;
    }

    private static function isValidCalendarDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        [$y, $m, $d] = explode('-', $date);
        return checkdate((int) $m, (int) $d, (int) $y);
    }

    public static function show(string $userId): void
    {
        $boarder = self::resolveBoarderOrRedirect($userId, '/admin/boarders', false);
        $id = (int) $boarder['user_id'];

        $paymentLimit = ($_GET['payments'] ?? '') === 'all' ? 500 : 12;
        $isShowingAllPayments = ($paymentLimit > 12);

        $balanceDetails = BillingService::calculateBalance($id);
        $penalties      = Penalty::allForBoarder($id);
        $payments       = Payment::allForBoarder($id, $paymentLimit);
        $paymentSummary = Payment::summaryForBoarder($id);
        $maintenance    = MaintenanceRequest::allForBoarder($id);
        $incidents      = Incident::allForBoarder($id);
        $statusLog      = BoarderProfile::statusLog($id);
        $penaltyRules   = PenaltyRule::allActive();

        require __DIR__ . '/../Views/admin/boarder_profile.php';
    }

    public static function updateDates(string $userId): void
    {
        $boarder = self::resolveBoarderOrRedirect($userId, '/admin/boarders', true);
        $id = (int) $boarder['user_id'];

        $moveIn  = trim((string) ($_POST['move_in_date'] ?? ''));
        $moveOut = trim((string) ($_POST['move_out_date'] ?? ''));

        if ($moveIn !== '' && !self::isValidCalendarDate($moveIn)) {
            $_SESSION['flash_error'] = 'Invalid move-in date. Please enter a valid calendar date in YYYY-MM-DD format.';
            header("Location: /admin/boarders/{$id}");
            exit;
        }

        if ($moveOut !== '' && !self::isValidCalendarDate($moveOut)) {
            $_SESSION['flash_error'] = 'Invalid move-out date. Please enter a valid calendar date in YYYY-MM-DD format.';
            header("Location: /admin/boarders/{$id}");
            exit;
        }

        if ($moveIn !== '' && $moveOut !== '' && $moveOut < $moveIn) {
            $_SESSION['flash_error'] = 'Move-out date cannot be before move-in date.';
            header("Location: /admin/boarders/{$id}");
            exit;
        }

        try {
            $adminId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
            BoarderProfile::updateDatesAndStatus(
                $id,
                $moveIn !== '' ? $moveIn : null,
                $moveOut !== '' ? $moveOut : null,
                $adminId
            );
            $_SESSION['flash_success'] = "Move-in/move-out dates updated for {$boarder['name']}.";
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Failed to update dates: ' . $e->getMessage();
        }

        header("Location: /admin/boarders/{$id}");
        exit;
    }

    public static function updateNotes(string $userId): void
    {
        $boarder = self::resolveBoarderOrRedirect($userId, '/admin/boarders', true);
        $id = (int) $boarder['user_id'];

        $notes = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 10000);
        BoarderProfile::updateNotes($id, $notes !== '' ? $notes : null);

        $_SESSION['flash_success'] = "Admin notes saved for {$boarder['name']}.";
        header("Location: /admin/boarders/{$id}");
        exit;
    }
}
