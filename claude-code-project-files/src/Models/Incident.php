<?php

namespace App\Models;

use App\Database;

class Incident
{
    public static function create(int $reportedBy, string $type, string $description): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO incidents (reported_by, type, description) VALUES (?, ?, ?)'
        );
        $stmt->execute([$reportedBy, $type, $description]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM incidents WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** $reportedBy limits the list to one reporter (boarders only ever see their own). */
    public static function all(?int $reportedBy = null): array
    {
        $sql = 'SELECT incidents.*, users.name AS reporter_name
                FROM incidents JOIN users ON users.id = incidents.reported_by'
            . ($reportedBy !== null ? ' WHERE incidents.reported_by = ?' : '')
            . ' ORDER BY incidents.created_at DESC';
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($reportedBy !== null ? [$reportedBy] : []);
        return $stmt->fetchAll();
    }

    public static function resolve(int $id, string $notes, int $userId = 0): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE incidents SET resolved = 1, resolution_notes = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$notes, $userId ?: null, $id]);
    }

    public static function allWithDetails(int $limit = 50, int $offset = 0, ?int $reportedBy = null): array
    {
        $sql = "SELECT incidents.*,
                users.name AS reporter_name,
                users.email AS reporter_email,
                resolver.name AS resolver_name
                FROM incidents
                JOIN users ON users.id = incidents.reported_by
                LEFT JOIN users resolver ON resolver.id = incidents.resolved_by"
            . ($reportedBy !== null ? ' WHERE incidents.reported_by = ?' : '')
            . " ORDER BY incidents.created_at DESC
                LIMIT ? OFFSET ?";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($reportedBy !== null ? [$reportedBy, $limit, $offset] : [$limit, $offset]);
        return $stmt->fetchAll();
    }

    public static function countAll(?int $reportedBy = null): int
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT COUNT(*) FROM incidents' . ($reportedBy !== null ? ' WHERE reported_by = ?' : '')
        );
        $stmt->execute($reportedBy !== null ? [$reportedBy] : []);
        return (int) $stmt->fetchColumn();
    }

    public static function allForBoarder(int $boarderId, int $limit = 10): array
    {
        $limit = max(1, (int) $limit);
        $sql = "SELECT incidents.*, resolver.name AS resolver_name
                FROM incidents
                LEFT JOIN users resolver ON resolver.id = incidents.resolved_by
                WHERE incidents.reported_by = ?
                ORDER BY incidents.created_at DESC
                LIMIT {$limit}";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([$boarderId]);
        return $stmt->fetchAll();
    }
}
