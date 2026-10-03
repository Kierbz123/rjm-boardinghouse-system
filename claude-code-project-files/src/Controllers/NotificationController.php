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
}
