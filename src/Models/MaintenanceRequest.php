<?php

namespace App\Models;

use App\Database;

class MaintenanceRequest
{
    public static function create(array $data): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO maintenance_requests
                (boarder_id, room_id, category, description, media_path, severity_score, priority_tier, scoring_pending, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, "open")'
        );
        $stmt->execute([
            $data['boarder_id'], $data['room_id'], $data['category'], $data['description'],
            $data['media_path'], $data['severity_score'], $data['priority_tier'], $data['scoring_pending'] ? 1 : 0,
        ]);
        return (int) Database::getConnection()->lastInsertId();
    }

    /** Sorted by priority tier (not submission time) per FEATURES.md §1. */
    public static function queueSorted(): array
    {
        $sql = "SELECT maintenance_requests.*, users.name AS boarder_name, rooms.room_number
                FROM maintenance_requests
                JOIN users ON users.id = maintenance_requests.boarder_id
                LEFT JOIN rooms ON rooms.id = maintenance_requests.room_id
                WHERE maintenance_requests.status != 'resolved'
                ORDER BY FIELD(maintenance_requests.priority_tier, 'critical','high','medium','low'), maintenance_requests.created_at ASC";
        return Database::getConnection()->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM maintenance_requests WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function updateStatus(int $id, string $status, int $userId = 0): void
    {
        if ($status === 'resolved') {
            $stmt = Database::getConnection()->prepare(
                'UPDATE maintenance_requests SET status = ?, resolved_at = NOW(), resolved_by = ? WHERE id = ?'
            );
            $resolvedBy = $userId > 0 ? $userId : null;
            $stmt->execute([$status, $resolvedBy, $id]);
        } else {
            $stmt = Database::getConnection()->prepare(
                'UPDATE maintenance_requests SET status = ?, resolved_at = NULL, resolved_by = NULL WHERE id = ?'
            );
            $stmt->execute([$status, $id]);
        }
    }

    public static function countsByTier(): array
    {
        $sql = "SELECT priority_tier, COUNT(*) AS c FROM maintenance_requests WHERE status != 'resolved' GROUP BY priority_tier";
        return Database::getConnection()->query($sql)->fetchAll();
    }

    public static function allWithDetails(): array
    {
        $sql = "SELECT maintenance_requests.*, 
                users.name AS boarder_name, 
                users.email AS boarder_email,
                rooms.room_number,
                resolver.name AS resolver_name
                FROM maintenance_requests
                JOIN users ON users.id = maintenance_requests.boarder_id
                LEFT JOIN rooms ON rooms.id = maintenance_requests.room_id
                LEFT JOIN users resolver ON resolver.id = maintenance_requests.resolved_by
                ORDER BY maintenance_requests.created_at DESC";
        return Database::getConnection()->query($sql)->fetchAll();
    }
}
