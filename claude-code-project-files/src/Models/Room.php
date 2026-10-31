<?php

namespace App\Models;

use App\Database;

class Room
{
    public static function all(string $orderBy = 'desc'): array
    {
        $direction = strtolower($orderBy) === 'asc' ? 'ASC' : 'DESC';
        $rows = Database::getConnection()->query(
            "SELECT r.*, t.name AS room_type_name 
             FROM rooms r 
             LEFT JOIN room_types t ON t.id = r.room_type_id 
             ORDER BY (r.room_number REGEXP '^[0-9]+$') DESC, 
                      CAST(r.room_number AS UNSIGNED) {$direction}, 
                      r.room_number {$direction}"
        )->fetchAll();

        usort($rows, function ($a, $b) use ($direction) {
            $cmp = strnatcasecmp((string) $a['room_number'], (string) $b['room_number']);
            return $direction === 'ASC' ? $cmp : -$cmp;
        });

        return $rows;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM rooms WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByNumber(string $roomNumber): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM rooms WHERE room_number = ? LIMIT 1');
        $stmt->execute([$roomNumber]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $roomNumber, ?string $floor, int $capacity, float $basePrice, ?int $roomTypeId = null): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO rooms (room_number, floor, room_type_id, capacity, base_price) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$roomNumber, $floor, $roomTypeId, $capacity, $basePrice]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function update(int $id, string $roomNumber, ?string $floor, int $capacity, float $basePrice, ?int $roomTypeId = null): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE rooms SET room_number = ?, floor = ?, room_type_id = ?, capacity = ?, base_price = ? WHERE id = ?'
        );
        $stmt->execute([$roomNumber, $floor, $roomTypeId, $capacity, $basePrice, $id]);
    }

    /**
     * Bug found in a third debugging pass: room_number is UNIQUE, and neither
     * create() nor update() checked for a collision first — a duplicate
     * number threw an uncaught PDOException (the exact same class of bug as
     * the duplicate-email crash in QA-VALIDATION-REPORT.md Bug #1, recurring
     * in a controller built afterward — proof that lesson needed re-applying,
     * not just documenting once).
     */
    public static function existsByNumber(string $roomNumber, ?int $excludingId = null): bool
    {
        if ($excludingId !== null) {
            $stmt = Database::getConnection()->prepare('SELECT COUNT(*) FROM rooms WHERE room_number = ? AND id != ?');
            $stmt->execute([$roomNumber, $excludingId]);
        } else {
            $stmt = Database::getConnection()->prepare('SELECT COUNT(*) FROM rooms WHERE room_number = ?');
            $stmt->execute([$roomNumber]);
        }
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Guarded delete — checked here, not left to the database, per the lesson
     * from QA-VALIDATION-REPORT.md Bug #2 (never let a FK constraint be the
     * thing that catches an invalid delete; check first and fail gracefully).
     */
    public static function delete(int $id): void
    {
        $pdo = Database::getConnection();
        $bedCount = $pdo->prepare('SELECT COUNT(*) FROM beds WHERE room_id = ?');
        $bedCount->execute([$id]);
        if ((int) $bedCount->fetchColumn() > 0) {
            throw new \RuntimeException('Cannot delete a room that still has beds — remove its beds first.');
        }
        $billCount = $pdo->prepare('SELECT COUNT(*) FROM electricity_bills WHERE room_id = ?');
        $billCount->execute([$id]);
        if ((int) $billCount->fetchColumn() > 0) {
            throw new \RuntimeException('Cannot delete a room that has electricity bills on record.');
        }
        $stmt = $pdo->prepare('DELETE FROM rooms WHERE id = ?');
        $stmt->execute([$id]);
    }
}
