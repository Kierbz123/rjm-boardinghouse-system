<?php

namespace App\Controllers;

use App\Models\Notification;
use App\Support\Csrf;

class NotificationController
{
    public static function index(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $notifications = Notification::allFor($userId);
        require __DIR__ . '/../Views/shared/notifications.php';
    }

    public static function unreadJson(): void
    {
        header('Content-Type: application/json');
        echo json_encode(Notification::unreadFor((int) $_SESSION['user_id']));
    }

    public static function markRead(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (self::isAjax()) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Invalid session.']);
                return;
            }
            $_SESSION['flash_error'] = 'Invalid session token. Please try again.';
            header('Location: /notifications');
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $notifId = (int) $id;

        if (!Notification::belongsTo($notifId, $userId)) {
            if (self::isAjax()) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'message' => 'Not your notification.']);
                return;
            }
            $_SESSION['flash_error'] = 'Access denied to notification.';
            header('Location: /notifications');
            exit;
        }

        Notification::markRead($notifId);

        if (self::isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            return;
        }

        $_SESSION['flash_success'] = 'Notification marked as read.';
        header('Location: /notifications');
        exit;
    }

    public static function toggleRead(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $_SESSION['flash_error'] = 'Invalid session token.';
            header('Location: /notifications');
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $notifId = (int) $id;

        if (!Notification::belongsTo($notifId, $userId)) {
            $_SESSION['flash_error'] = 'Access denied to notification.';
            header('Location: /notifications');
            exit;
        }

        $isCurrentlyRead = ($_POST['is_read'] ?? '') === '1';
        if ($isCurrentlyRead) {
            Notification::markUnread($notifId);
            $_SESSION['flash_success'] = 'Marked as unread.';
        } else {
            Notification::markRead($notifId);
            $_SESSION['flash_success'] = 'Marked as read.';
        }

        header('Location: /notifications');
        exit;
    }

    public static function markAllRead(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (self::isAjax()) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Invalid session.']);
                return;
            }
            $_SESSION['flash_error'] = 'Invalid session token.';
            header('Location: /notifications');
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        Notification::markAllRead($userId);

        if (self::isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            return;
        }

        $_SESSION['flash_success'] = 'All notifications marked as read.';
        header('Location: /notifications');
        exit;
    }

    public static function togglePin(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $_SESSION['flash_error'] = 'Invalid session token.';
            header('Location: /notifications');
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $notifId = (int) $id;

        if (!Notification::belongsTo($notifId, $userId)) {
            $_SESSION['flash_error'] = 'Access denied to notification.';
            header('Location: /notifications');
            exit;
        }

        Notification::togglePin($notifId);

        $_SESSION['flash_success'] = 'Notification priority updated.';
        header('Location: /notifications');
        exit;
    }

    public static function delete(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $_SESSION['flash_error'] = 'Invalid session token.';
            header('Location: /notifications');
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $notifId = (int) $id;

        if (!Notification::belongsTo($notifId, $userId)) {
            $_SESSION['flash_error'] = 'Access denied to notification.';
            header('Location: /notifications');
            exit;
        }

        Notification::delete($notifId);

        $_SESSION['flash_success'] = 'Notification removed.';
        header('Location: /notifications');
        exit;
    }

    private static function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
    }

    public static function stream(): void
    {
        // Set infinite execution time for SSE stream
        set_time_limit(0);
        
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $lastNotificationId = 0;
        $connectionStartTime = time();
        $maxConnectionTime = 300; // 5 minutes max connection time

        // Send initial connection message
        echo "data: {\"type\":\"connected\",\"message\":\"Real-time notifications connected\"}\n\n";
        ob_flush();
        flush();

        // Keep connection alive and check for new notifications
        while (true) {
            // Check for session timeout
            if (session_status() === PHP_SESSION_NONE) {
                break;
            }

            // Check max connection time to prevent resource exhaustion
            if (time() - $connectionStartTime > $maxConnectionTime) {
                echo "data: {\"type\":\"timeout\",\"message\":\"Connection timeout - please refresh\"}\n\n";
                ob_flush();
                flush();
                break;
            }

            // Get latest unread notifications (optimized query)
            $notifications = Notification::unreadFor($userId);
            $latestId = 0;
            $newNotifications = [];

            foreach ($notifications as $notif) {
                $notifId = (int) $notif['id'];
                if ($notifId > $lastNotificationId) {
                    $newNotifications[] = $notif;
                    $latestId = max($latestId, $notifId);
                }
            }

            // Send new notifications
            if (!empty($newNotifications)) {
                foreach ($newNotifications as $notif) {
                    $data = [
                        'type' => 'notification',
                        'id' => $notif['id'],
                        'message' => $notif['message'],
                        'type_label' => $notif['type'],
                        'created_at' => $notif['created_at'],
                        'is_pinned' => $notif['is_pinned'] ?? 0
                    ];
                    echo "data: " . json_encode($data) . "\n\n";
                    $lastNotificationId = max($lastNotificationId, (int) $notif['id']);
                }
                ob_flush();
                flush();
            }

            // Send keep-alive every 30 seconds (reduced frequency)
            echo ": keep-alive\n\n";
            ob_flush();
            flush();

            // Wait before next check (increased to reduce CPU usage)
            sleep(5);
        }
    }
}
