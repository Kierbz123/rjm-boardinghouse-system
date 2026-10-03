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
        
        // Count total inquiries from notifications (type = 'inquiry')
        $totalInquiries = (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'inquiry'")->fetchColumn();
        
        // Count inquiries from today
        $todayInquiries = (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'inquiry' AND DATE(created_at) = CURDATE()")->fetchColumn();

        // Get recent inquiries (last 10)
        $recentInquiries = $pdo->query("
            SELECT * FROM notifications 
            WHERE type = 'inquiry' 
            ORDER BY created_at DESC 
            LIMIT 10
        ")->fetchAll();

        $inquirySuccess = $_SESSION['flash_inquiry_success'] ?? null;
        unset($_SESSION['flash_inquiry_success']);
        $inquiryError = $_SESSION['flash_inquiry_error'] ?? null;
        unset($_SESSION['flash_inquiry_error']);

        require __DIR__ . '/../Views/admin/inquiry-center.php';
    }

    /**
     * Handle staff-submitted inquiry (delegates to existing logic)
     * This is essentially the same as LandingController::handleInquiry
     * but includes staff context
     */
    public static function handleStaffInquiry(): void
    {
        // CSRF verification
        if (!\App\Support\Csrf::verify($_POST['csrf_token'] ?? null)) {
            $isJson = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');
            if ($isJson) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid session token.']);
                exit;
            }
            $_SESSION['flash_inquiry_error'] = 'Invalid session token. Please try again.';
            header('Location: /admin/inquiry-center');
            exit;
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $roomType = trim((string) ($_POST['room_type'] ?? 'General Inquiry'));
        $moveInDate = trim((string) ($_POST['move_in_date'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        $staffNotes = trim((string) ($_POST['staff_notes'] ?? ''));

        $staffName = $_SESSION['name'] ?? 'Staff';

        $isJson = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');

        if ($name === '' || $phone === '') {
            $errorMsg = 'Please provide both your name and a valid contact phone number.';
            if ($isJson) {
                header('Content-Type: application/json');
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => $errorMsg]);
                exit;
            }
            $_SESSION['flash_inquiry_error'] = $errorMsg;
            header('Location: /admin/inquiry-center');
            exit;
        }

        try {
            $pdo = Database::getConnection();
            $adminIds = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll(\PDO::FETCH_COLUMN);

            $notifMsg = "Staff Inquiry from {$staffName}: {$name} ({$phone})";
            $notifDetails = "Requested: {$roomType}" . 
                          ($moveInDate ? " | Move-in: {$moveInDate}" : "") . 
                          ($email ? " | Email: {$email}" : "") . 
                          ($message ? " | Notes: {$message}" : "") .
                          ($staffNotes ? " | Staff Notes: {$staffNotes}" : "");

            foreach ($adminIds as $adminId) {
                \App\Models\Notification::create(
                    (int) $adminId,
                    'inquiry',
                    $notifMsg . ' — ' . $notifDetails,
                    '/admin/inquiry-center'
                );
            }
        } catch (\Throwable $e) {
            // Graceful resilience: log or ignore notification error
        }

        $successMsg = "Thank you, {$name}! Your room inquiry has been submitted by {$staffName}. Our team will follow up promptly.";

        if ($isJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $successMsg]);
            exit;
        }

        $_SESSION['flash_inquiry_success'] = $successMsg;
        header('Location: /admin/inquiry-center');
        exit;
    }
}