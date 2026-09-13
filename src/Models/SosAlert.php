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
                ORDER BY sos_alerts.created_at DESC";
        return Database::getConnection()->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM sos_alerts WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function acknowledge(int $id, int $staffId): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE sos_alerts SET status = "acknowledged", acknowledged_by = ? WHERE id = ?'
        );
        $stmt->execute([$staffId, $id]);
    }

    public static function resolve(int $id): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE sos_alerts SET status = "resolved", resolved_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$id]);
    }

    public static function activeCount(): int
    {
        return (int) Database::getConnection()
            ->query("SELECT COUNT(*) FROM sos_alerts WHERE status != 'resolved'")
            ->fetchColumn();
    }
}
