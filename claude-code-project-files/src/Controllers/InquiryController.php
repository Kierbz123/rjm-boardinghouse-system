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

        $inquirySuccess = $_SESSION['flash_inquiry_success'] ?? null;
        unset($_SESSION['flash_inquiry_success']);
        $inquiryError = $_SESSION['flash_inquiry_error'] ?? null;
        unset($_SESSION['flash_inquiry_error']);

        require __DIR__ . '/../Views/admin/inquiry-center.php';
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
