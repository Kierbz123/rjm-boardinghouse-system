<?php

namespace App\Models;

use App\Database;

class Notification
{
    public static function create(int $userId, string $type, string $message): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO notifications (user_id, type, message) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $type, $message]);
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

    public static function belongsTo(int $id, int $userId): bool
    {
        $stmt = Database::getConnection()->prepare('SELECT COUNT(*) FROM notifications WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
