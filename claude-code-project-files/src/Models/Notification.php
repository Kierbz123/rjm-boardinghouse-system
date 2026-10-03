<?php

namespace App\Models;

use App\Database;

class Notification
{
    public static function create(int $userId, string $type, string $message, ?string $actionUrl = null, ?string $entityType = null, ?int $entityId = null): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO notifications (user_id, type, message, action_url, entity_type, entity_id) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $type, $message, $actionUrl, $entityType, $entityId]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function unreadFor(int $userId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY is_pinned DESC, created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function allFor(int $userId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY is_pinned DESC, created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function markRead(int $id): void
    {
        $stmt = Database::getConnection()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function markUnread(int $id): void
    {
        $stmt = Database::getConnection()->prepare('UPDATE notifications SET is_read = 0 WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function markAllRead(int $userId): void
    {
        $stmt = Database::getConnection()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    public static function togglePin(int $id): bool
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE notifications SET is_pinned = IF(is_pinned = 1, 0, 1) WHERE id = ?'
        );
        $stmt->execute([$id]);
        return true;
    }

    public static function delete(int $id): void
    {
        $stmt = Database::getConnection()->prepare('DELETE FROM notifications WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function updateEntityActionUrl(string $entityType, int $entityId, string $newActionUrl): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE notifications SET action_url = ? WHERE entity_type = ? AND entity_id = ?'
        );
        $stmt->execute([$newActionUrl, $entityType, $entityId]);
    }

    public static function updateEntityMessage(string $entityType, int $entityId, string $newMessage): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE notifications SET message = ? WHERE entity_type = ? AND entity_id = ?'
        );
        $stmt->execute([$newMessage, $entityType, $entityId]);
    }

    public static function belongsTo(int $id, int $userId): bool
    {
        $stmt = Database::getConnection()->prepare('SELECT COUNT(*) FROM notifications WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function broadcastToStaff(string $type, string $message, string $details = '', ?string $actionUrl = null, ?string $entityType = null, ?int $entityId = null): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE role IN ("staff", "admin")');
        $stmt->execute();
        $staffUsers = $stmt->fetchAll();

        foreach ($staffUsers as $user) {
            $fullMessage = $details ? "{$message} ({$details})" : $message;
            self::create((int) $user['id'], $type, $fullMessage, $actionUrl, $entityType, $entityId);
        }
    }

    public static function broadcastToAdmins(string $type, string $message, string $details = '', ?string $actionUrl = null): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE role = "admin"');
        $stmt->execute();
        $adminUsers = $stmt->fetchAll();

        foreach ($adminUsers as $user) {
            $fullMessage = $details ? "{$message} ({$details})" : $message;
            self::create((int) $user['id'], $type, $fullMessage, $actionUrl);
        }
    }

    public static function broadcastToBoarders(string $type, string $message, string $details = '', ?string $actionUrl = null): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE role = "boarder"');
        $stmt->execute();
        $boarders = $stmt->fetchAll();

        foreach ($boarders as $user) {
            $fullMessage = $details ? "{$message} ({$details})" : $message;
            self::create((int) $user['id'], $type, $fullMessage, $actionUrl);
        }
    }
}
