<?php

namespace App\Controllers;

use App\Database;
use App\Models\Notification;

class InquiryController
{
    /**
     * Admin Inquiry Management Center
     * Combines room inquiry form with notification hub for staff/admin users
     */
    public static function adminCenter(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $userName = $_SESSION['name'] ?? 'Staff';
        $userRole = $_SESSION['role'] ?? '';

        // Get notifications for the current user
        $notifications = Notification::allFor($userId);
        $unreadCount = count(Notification::unreadFor($userId));

        // Get inquiry statistics
        $pdo = Database::getConnection();
        
        $inquiryStats = \App\Models\Inquiry::stats();
        $totalInquiries = (int) $inquiryStats['total'];
        $todayInquiries = (int) $inquiryStats['today'];
        // Contact details of prospects are for admins only.
        $recentInquiries = $userRole === 'admin' ? \App\Models\Inquiry::recent(10) : [];
        $roomTypes = \App\Models\RoomType::all();

        $inquirySuccess = $_SESSION['flash_inquiry_success'] ?? null;
        unset($_SESSION['flash_inquiry_success']);
        $inquiryError = $_SESSION['flash_inquiry_error'] ?? null;
        unset($_SESSION['flash_inquiry_error']);

        require __DIR__ . '/../Views/admin/inquiry-center.php';
    }

    /** Full record of one inquiry (admin only: it holds the prospect's contact details). */
    public static function show(string $id): void
    {
        $inquiry = \App\Models\Inquiry::find((int) $id);
        if ($inquiry === null) {
            \App\Support\ErrorPage::render(404);
        }
        Notification::markEntityRead((int) $_SESSION['user_id'], 'inquiry', (int) $inquiry['id']);

        // Walk-in notes are appended to the message as "Staff notes: ..."; show them separately.
        [$visitorMessage, $staffNotes] = array_pad(explode("\n\nStaff notes: ", (string) $inquiry['message'], 2), 2, '');
        $roomType = $inquiry['room_type'] ? \App\Models\RoomType::findByName($inquiry['room_type']) : null;
        $flashOk = $_SESSION['flash_inquiry_success'] ?? null;
        unset($_SESSION['flash_inquiry_success']);

        require __DIR__ . '/../Views/admin/inquiry-detail.php';
    }

    public static function updateStatus(string $id): void
    {
        $inquiryId = (int) $id;
        if (!\App\Support\Csrf::verify($_POST['csrf_token'] ?? null)) {
            $_SESSION['flash_inquiry_error'] = 'Invalid session token. Please try again.';
        } elseif (\App\Models\Inquiry::find($inquiryId) === null) {
            \App\Support\ErrorPage::render(404);
        } elseif (\App\Models\Inquiry::setStatus($inquiryId, (string) ($_POST['status'] ?? ''))) {
            $_SESSION['flash_inquiry_success'] = 'Status updated.';
        }
        header("Location: /admin/inquiries/{$inquiryId}");
        exit;
    }

    /**
     * The inquirer already has a boarder account (same email): record the inquiry as that boarder
     * instead of creating a duplicate. The account is found from the inquiry's own email, never
     * from anything the browser sends.
     */
    public static function linkBoarder(string $id): void
    {
        $inquiryId = (int) $id;
        if (!\App\Support\Csrf::verify($_POST['csrf_token'] ?? null)) {
            $_SESSION['flash_error'] = 'Invalid session token. Please try again.';
            header("Location: /admin/boarders?inquiry={$inquiryId}");
            exit;
        }
        $inquiry = \App\Models\Inquiry::find($inquiryId) ?? \App\Support\ErrorPage::render(404);
        $user = $inquiry['email'] ? \App\Models\User::findByEmail((string) $inquiry['email']) : null;
        if ($user === null || $user['role'] !== 'boarder') {
            $_SESSION['flash_error'] = 'No boarder account uses this inquiry\'s email.';
            header("Location: /admin/boarders?inquiry={$inquiryId}");
            exit;
        }
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            \App\Models\Inquiry::markConverted($inquiryId, (int) $user['id']);
            $pdo->commit();
            $_SESSION['flash_inquiry_success'] = "Inquiry linked to {$user['name']}'s existing account and closed.";
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = \App\Support\Flash::error($e, 'The inquiry could not be linked. Please try again.');
            header("Location: /admin/boarders?inquiry={$inquiryId}");
            exit;
        }
        header("Location: /admin/inquiries/{$inquiryId}");
        exit;
    }

    /** Walk-in / phone inquiry logged by staff or an admin. */
    public static function handleStaffInquiry(): void
    {
        $isJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
        if (!\App\Support\Csrf::verify($_POST['csrf_token'] ?? null)) {
            LandingController::respondToInquiry($isJson, false, 'Invalid session token. Please try again.', '/admin/inquiry-center');
        }

        $staffName = $_SESSION['name'] ?? 'Staff';
        try {
            $result = \App\Models\Inquiry::submit($_POST, 'staff', (int) $_SESSION['user_id'], null);
            if ($result['ok']) {
                \App\Services\NotificationDispatcher::inquiryReceived($result, $staffName);
            }
        } catch (\Throwable $e) {
            \App\Support\Logger::error('Staff inquiry could not be saved: ' . $e->getMessage());
            $result = ['ok' => false, 'error' => 'The inquiry could not be saved. Please try again.'];
        }

        LandingController::respondToInquiry(
            $isJson,
            $result['ok'],
            $result['ok'] ? "Inquiry #{$result['id']} for {$result['name']} saved. Admins have been notified." : $result['error'],
            '/admin/inquiry-center'
        );
    }
}
