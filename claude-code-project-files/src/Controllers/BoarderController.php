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
use App\Services\PasswordResetService;
use App\Database;
use App\Support\Csrf;
use RuntimeException;

class BoarderController
{
    public static function index(): void
    {
        \App\Services\HousekeepingService::applyDueMoveOuts(); // move-outs whose day has come
        $showArchived = ($_GET['archived'] ?? '') === '1';
        $boarders = BoarderProfile::all($showArchived);
        $rooms = Room::all();
        $beds = Bed::all();

        // What the admin typed before a failed submit wins over the inquiry it came from.
        $old = $_SESSION['old_boarder_input'] ?? [];
        unset($_SESSION['old_boarder_input']);
        $conversion = self::conversionFor((int) ($_GET['inquiry'] ?? $old['inquiry_id'] ?? 0), $rooms, $beds);
        $addForm = [
            'name'           => $old['name'] ?? $conversion['inquiry']['name'] ?? '',
            'email'          => $old['email'] ?? $conversion['inquiry']['email'] ?? '',
            'contact_number' => $old['contact_number'] ?? $conversion['inquiry']['phone'] ?? '',
            'room_id'        => (int) ($old['room_id'] ?? $conversion['room_id'] ?? 0),
            'bed_id'         => (int) ($old['bed_id'] ?? 0),
            'inquiry_id'     => isset($conversion['inquiry']) ? (int) $conversion['inquiry']['id'] : 0,
        ];
        require __DIR__ . '/../Views/admin/boarders.php';
    }

    /**
     * Opening /admin/boarders?inquiry=N only fills the Add Boarder form for review; nothing is
     * created until the admin submits it. The inquiry is read from the database, never the URL.
     * Picks the first room of the requested type that has a vacant bed; the bed is left to the admin.
     */
    private static function conversionFor(int $inquiryId, array $rooms, array $beds): ?array
    {
        if ($inquiryId <= 0) {
            return null;
        }
        $inquiry = \App\Models\Inquiry::find($inquiryId);
        if ($inquiry === null) {
            return ['error' => "Inquiry #{$inquiryId} was not found."];
        }
        if ($inquiry['converted_user_id'] !== null) {
            return ['error' => "Inquiry #{$inquiryId} was already registered as "
                . ($inquiry['converted_user_name'] ?? 'a boarder') . '.', 'user_id' => (int) $inquiry['converted_user_id']];
        }

        $requested = trim((string) $inquiry['room_type']);
        $type = $requested !== '' ? \App\Models\RoomType::findByName($requested) : null;
        $roomId = null;
        if ($type !== null) {
            $vacantRoomIds = array_map('intval', array_column(array_filter($beds, fn ($b) => $b['status'] === 'vacant'), 'room_id'));
            foreach ($rooms as $r) {
                if ((int) $r['room_type_id'] === (int) $type['id'] && in_array((int) $r['id'], $vacantRoomIds, true)) {
                    $roomId = (int) $r['id'];
                    break;
                }
            }
        }
        $notice = match (true) {
            $type === null => "\"{$requested}\" is not one of the room types, so no room was picked. Choose one below if you want to assign a room now.",
            $roomId === null => "No {$type['name']} has a vacant bed right now. Choose another room, or link a room to this type on Rooms & Beds.",
            default => null,
        };
        // Emails are unique: if this person already has an account, say so before the admin fills in a password.
        $existing = $inquiry['email'] ? User::findByEmail((string) $inquiry['email']) : null;
        return ['inquiry' => $inquiry, 'room_type' => $type, 'room_id' => $roomId, 'notice' => $notice, 'existing' => $existing];
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
        $contact = trim((string) ($_POST['contact_number'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $bedId = !empty($_POST['bed_id']) ? (int) $_POST['bed_id'] : null;
        $inquiryId = max(0, (int) ($_POST['inquiry_id'] ?? 0));

        // A failed submit goes back to the form with what was typed (never the password)
        // and, when it came from an inquiry, still linked to it.
        $fail = function (string $message) use ($name, $email, $contact, $bedId, $inquiryId): never {
            $_SESSION['flash_error'] = $message;
            $_SESSION['old_boarder_input'] = ['name' => $name, 'email' => $email, 'contact_number' => $contact,
                'room_id' => (int) ($_POST['room_id'] ?? 0), 'bed_id' => (int) $bedId, 'inquiry_id' => $inquiryId];
            header('Location: /admin/boarders' . ($inquiryId > 0 ? "?inquiry={$inquiryId}" : ''));
            exit;
        };

        if ($name === '' || $email === '' || $password === '') {
            $fail('Name, email, and password are all required.');
        }
        if (mb_strlen($name) > 150 || mb_strlen($email) > 150) {
            $fail('Name and email must be 150 characters or fewer.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fail('Please enter a valid email address.');
        }
        if ($contact !== '' && !preg_match('/^[0-9+()\-\s]{7,20}$/', $contact)) {
            $fail('Please enter a valid contact number.');
        }
        if (($problem = \App\Support\PasswordPolicy::problem($password)) !== null) {
            $fail($problem);
        }

        // Bug found during Phase 8 QA pass: creating a boarder with an email that
        // already exists threw an uncaught PDO integrity-constraint exception
        // (caught only by the generic 500 handler). Check first, fail gracefully.
        if (User::findByEmail($email) !== null) {
            $fail('That email is already in use.');
        }

        // Account, bed, profile and the inquiry's conversion are saved together or not at all.
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $userId = User::create('boarder', $name, $email, $password);
            $roomId = !empty($_POST['room_id']) ? (int) $_POST['room_id'] : null;
            if ($bedId) {
                $bed = Bed::find($bedId) ?? throw new RuntimeException('Bed not found.');
                $roomId = (int) $bed['room_id'];
                Bed::assign($bedId, $userId);
            }
            BoarderProfile::create($userId, $roomId, $bedId, $contact !== '' ? $contact : null);
            if ($inquiryId > 0) {
                \App\Models\Inquiry::markConverted($inquiryId, $userId);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $fail(self::messageFor($e, 'The resident could not be added. Please try again.'));
        }
        $_SESSION['flash_success'] = "Added {$name}." . ($inquiryId > 0 ? " Inquiry #{$inquiryId} is marked as registered." : '');
        header('Location: /admin/boarders');
        exit;
    }

    /** Our own "why not" messages are shown as written; anything unexpected is logged and replaced. */
    private static function messageFor(\Throwable $e, string $fallback): string
    {
        return \App\Support\Flash::error($e, $fallback);
    }

    /** Assigns an existing boarder to a bed. Rejects if the bed is already occupied — Phase 2 verification. */
    public static function assignBed(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $boarderId = (int) ($_POST['boarder_id'] ?? 0);
        $bedId = (int) ($_POST['bed_id'] ?? 0);
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $bed = Bed::find($bedId) ?? throw new RuntimeException('Bed not found.');
            $currentProfile = BoarderProfile::find($boarderId) ?? throw new RuntimeException('Boarder not found.');
            if ($currentProfile['status'] === 'moved_out') {
                throw new RuntimeException('A moved-out boarder cannot be assigned a bed. Change their status first.');
            }
            if ((int) $currentProfile['bed_id'] === $bedId) {
                throw new RuntimeException('That boarder already has this bed.');
            }

            // Moving beds: free the one they hold. If the new bed turns out to be taken,
            // the rollback below puts the boarder back in the old bed.
            Bed::vacateHeldBy($boarderId);
            Bed::assign($bedId, $boarderId);
            BoarderProfile::assignRoomAndBed($boarderId, (int) $bed['room_id'], $bedId);
            BillingService::calculateBalance($boarderId, $pdo); // the new room's price applies from this month
            $pdo->commit();

            $room = Room::find((int) $bed['room_id']);
            NotificationDispatcher::bedAssigned($boarderId, $room['room_number'] ?? (string) $bed['room_id'], $bed['label'] ?? 'Bed');
            $_SESSION['flash_success'] = "{$currentProfile['name']} assigned to Room " . ($room['room_number'] ?? '') . " / {$bed['label']}.";
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['flash_error'] = self::messageFor($e, 'The bed could not be assigned. Please try again.');
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

        // This route edits residents only; an id belonging to a staff or admin account is refused.
        if (BoarderProfile::find($id) === null) {
            $_SESSION['flash_error'] = 'Boarder not found.';
            header('Location: /admin/boarders');
            exit;
        }

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
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Please enter a valid email address.';
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
                    $_SESSION['flash_error'] = self::messageFor($e, 'The status could not be changed. Please try again.');
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

        // Owner decision: residents with payment/penalty history are archived (records kept);
        // only those added by mistake, with no financial history, are erased.
        $id = (int) $userId;
        try {
            // Nobody is ever erased: repairs, incidents, SOS, payments and penalties all stay.
            BoarderProfile::archive($id, (int) $_SESSION['user_id'], (string) ($_POST['reason'] ?? ''));
            $_SESSION['flash_success'] = 'Resident archived: moved out, login disabled, all their records kept. Use "Show archived" to restore them.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = self::messageFor($e, 'The resident could not be removed. Please try again.');
        }

        header('Location: /admin/boarders');
        exit;
    }

    public static function restore(string $userId): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        if (BoarderProfile::restore((int) $userId, (int) $_SESSION['user_id'])) {
            $_SESSION['flash_success'] = 'Resident restored. They can log in again; assign a bed and set them active to resume rent.';
        } else {
            $_SESSION['flash_error'] = 'No archived resident with that number was found.';
        }
        header('Location: /admin/boarders');
        exit;
    }

    /** A resident forgot their password: the admin sets a temporary one and tells them in person. */
    public static function resetPassword(string $userId): void
    {
        $boarder = self::resolveBoarderOrRedirect($userId, '/admin/boarders', true);
        $id = (int) $boarder['user_id'];

        $error = PasswordResetService::byAdmin(
            User::findById($id),
            (string) ($_POST['new_password'] ?? ''),
            (int) $_SESSION['user_id']
        );
        if ($error !== null) {
            $_SESSION['flash_error'] = $error;
        } else {
            $_SESSION['flash_success'] = "New password set for {$boarder['name']}. They have been signed out; share it privately and ask them to change it under Profile.";
        }
        header("Location: /admin/boarders/{$id}");
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

        try {
            $balanceDetails = BillingService::calculateBalance($id, null, true);
        } catch (\RuntimeException $e) {
            $_SESSION['flash_error'] = "Rent cannot be worked out for {$boarder['name']}: " . $e->getMessage();
            header('Location: /admin/boarders');
            exit;
        }
        $dateBounds     = ['min' => \App\Services\DateRules::openingDate(), 'max' => \App\Services\DateRules::latestMoveIn(), 'maxOut' => \App\Services\DateRules::latestMoveOut()];
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

        // The same limits the date picker shows and the database trigger enforces (src/Services/DateRules.php).
        $problem = ($moveIn !== '' ? \App\Services\DateRules::moveInProblem($moveIn) : null)
            ?? ($moveOut !== '' ? \App\Services\DateRules::moveOutProblem($moveOut, $moveIn !== '' ? $moveIn : null) : null);
        if ($problem !== null) {
            $_SESSION['flash_error'] = $problem;
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
            $_SESSION['flash_success'] = $moveOut !== '' && $moveOut > date('Y-m-d')
                ? "Move-out scheduled for {$moveOut}. {$boarder['name']} stays active in their bed until then; use Cancel move-out to undo it."
                : "Move-in/move-out dates updated for {$boarder['name']}.";
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = self::messageFor($e, 'The dates could not be updated. Please try again.');
        }

        header("Location: /admin/boarders/{$id}");
        exit;
    }

    /** Takes back a move-out that was only scheduled: the resident carries on as before. */
    public static function cancelMoveOut(string $userId): void
    {
        $boarder = self::resolveBoarderOrRedirect($userId, '/admin/boarders', true);
        $id = (int) $boarder['user_id'];

        try {
            $cancelled = BoarderProfile::cancelMoveOut($id, !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null);
            if ($cancelled) {
                $_SESSION['flash_success'] = "The scheduled move-out of {$boarder['name']} is cancelled.";
            } else {
                $_SESSION['flash_error'] = "There is no scheduled move-out to cancel for {$boarder['name']}.";
            }
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = self::messageFor($e, 'The move-out could not be cancelled. Please try again.');
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
