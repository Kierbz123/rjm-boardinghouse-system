<?php

namespace App\Models;

use App\Database;

class Room
{
    public static function all(): array
    {
        return Database::getConnection()->query('SELECT * FROM rooms ORDER BY room_number')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM rooms WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $roomNumber, ?string $floor, int $capacity, float $basePrice): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO rooms (room_number, floor, capacity, base_price) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$roomNumber, $floor, $capacity, $basePrice]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function update(int $id, string $roomNumber, ?string $floor, int $capacity, float $basePrice): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE rooms SET room_number = ?, floor = ?, capacity = ?, base_price = ? WHERE id = ?'
        );
        $stmt->execute([$roomNumber, $floor, $capacity, $basePrice, $id]);
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
        $stmt = $pdo->prepare('DELETE FROM rooms WHERE id = ?');
        $stmt->execute([$id]);
    }
}
