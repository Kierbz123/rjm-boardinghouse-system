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

    public static function all(): array
    {
        $sql = 'SELECT incidents.*, users.name AS reporter_name
                FROM incidents JOIN users ON users.id = incidents.reported_by
                ORDER BY incidents.created_at DESC';
        return Database::getConnection()->query($sql)->fetchAll();
    }

    public static function resolve(int $id, string $notes, int $userId = 0): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE incidents SET resolved = 1, resolution_notes = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$notes, $userId ?: null, $id]);
    }

    public static function allWithDetails(): array
    {
        $sql = "SELECT incidents.*,
                users.name AS reporter_name,
                users.email AS reporter_email,
                resolver.name AS resolver_name
                FROM incidents
                JOIN users ON users.id = incidents.reported_by
                LEFT JOIN users resolver ON resolver.id = incidents.resolved_by
                ORDER BY incidents.created_at DESC";
        return Database::getConnection()->query($sql)->fetchAll();
    }
}
