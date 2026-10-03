<?php

namespace App\Models;

use App\Database;

class MaintenanceRequest
{
    /** Must match the ENUMs in 0007_create_maintenance_requests.sql. */
    public const CATEGORIES = ['electrical', 'plumbing', 'structural', 'appliance', 'other'];
    public const STATUSES = ['open', 'in_progress', 'resolved'];

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

    /** Sorted by priority tier (not submission time) per FEATURES.md §1. Optimized with LIMIT. */
    public static function queueSorted(): array
    {
        $sql = "SELECT maintenance_requests.*, users.name AS boarder_name, rooms.room_number
                FROM maintenance_requests
                JOIN users ON users.id = maintenance_requests.boarder_id
                LEFT JOIN rooms ON rooms.id = maintenance_requests.room_id
                WHERE maintenance_requests.status != 'resolved'
                ORDER BY FIELD(maintenance_requests.priority_tier, 'critical','high','medium','low'), maintenance_requests.created_at ASC
                LIMIT 100";
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

    public static function allWithDetails(int $limit = 50, int $offset = 0): array
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
                ORDER BY maintenance_requests.created_at DESC
                LIMIT ? OFFSET ?";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    public static function countAll(): int
    {
        $sql = "SELECT COUNT(*) FROM maintenance_requests";
        return (int) Database::getConnection()->query($sql)->fetchColumn();
    }

    public static function allForBoarder(int $boarderId, int $limit = 10): array
    {
        $limit = max(1, (int) $limit);
        $sql = "SELECT mr.*, r.room_number
                FROM maintenance_requests mr
                LEFT JOIN rooms r ON r.id = mr.room_id
                WHERE mr.boarder_id = ?
                ORDER BY mr.created_at DESC
                LIMIT {$limit}";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([$boarderId]);
        return $stmt->fetchAll();
    }
}
