<?php

namespace App\Models;

use App\Database;

class OccupancySnapshot
{
    public static function takeToday(): void
    {
        $counts = Bed::counts();
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO occupancy_snapshots (snapshot_date, total_beds, occupied_beds)
             VALUES (CURDATE(), ?, ?)
             ON DUPLICATE KEY UPDATE total_beds = VALUES(total_beds), occupied_beds = VALUES(occupied_beds)'
        );
        $stmt->execute([$counts['total'], $counts['occupied']]);
    }

    public static function trend(int $days = 30): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM occupancy_snapshots WHERE snapshot_date >= (CURDATE() - INTERVAL ? DAY) ORDER BY snapshot_date'
        );
        $stmt->bindValue(1, $days, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function latest(): ?array
    {
        $row = Database::getConnection()->query(
            'SELECT * FROM occupancy_snapshots ORDER BY snapshot_date DESC LIMIT 1'
        )->fetch();
        return $row ?: null;
    }
}
