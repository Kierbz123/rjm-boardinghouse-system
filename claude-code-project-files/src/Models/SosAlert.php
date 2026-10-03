<?php

namespace App\Models;

use App\Database;

class SosAlert
{
    public static function create(int $boarderId, ?int $roomId): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO sos_alerts (boarder_id, room_id, status) VALUES (?, ?, "active")'
        );
        $stmt->execute([$boarderId, $roomId]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function activeAlerts(): array
    {
        $sql = "SELECT sos_alerts.*, users.name AS boarder_name, rooms.room_number
                FROM sos_alerts
                JOIN users ON users.id = sos_alerts.boarder_id
                LEFT JOIN rooms ON rooms.id = sos_alerts.room_id
                WHERE sos_alerts.status != 'resolved'
                ORDER BY sos_alerts.created_at DESC
                LIMIT 50";
        return Database::getConnection()->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM sos_alerts WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** An unresolved alert this boarder raised in the last 2 minutes — repeat presses reuse it. */
    public static function recentOpenFor(int $boarderId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM sos_alerts WHERE boarder_id = ? AND status != "resolved"
               AND created_at > NOW() - INTERVAL 2 MINUTE ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$boarderId]);
        return $stmt->fetch() ?: null;
    }

    /** @return bool false if the alert wasn't active (already handled or missing). */
    public static function acknowledge(int $id, int $staffId): bool
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE sos_alerts SET status = "acknowledged", acknowledged_by = ? WHERE id = ? AND status = "active"'
        );
        $stmt->execute([$staffId, $id]);
        return $stmt->rowCount() === 1;
    }

    /** @return bool false if the alert was already resolved or missing. */
    public static function resolve(int $id): bool
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE sos_alerts SET status = "resolved", resolved_at = NOW() WHERE id = ? AND status != "resolved"'
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() === 1;
    }

    public static function activeCount(): int
    {
        return (int) Database::getConnection()
            ->query("SELECT COUNT(*) FROM sos_alerts WHERE status != 'resolved'")
            ->fetchColumn();
    }
}
