<?php

namespace App\Models;

use App\Database;
use RuntimeException;

class Bed
{
    public static function all(string $orderBy = 'desc'): array
    {
        $direction = strtolower($orderBy) === 'asc' ? 'ASC' : 'DESC';
        $sql = "SELECT beds.*, rooms.room_number, rooms.room_type_id, u.name AS boarder_name
                FROM beds
                JOIN rooms ON rooms.id = beds.room_id
                LEFT JOIN users u ON u.id = beds.current_boarder_id
                ORDER BY (rooms.room_number REGEXP '^[0-9]+$') DESC,
                         CAST(rooms.room_number AS UNSIGNED) {$direction},
                         rooms.room_number {$direction},
                         beds.label ASC";
        $rows = Database::getConnection()->query($sql)->fetchAll();

        usort($rows, function ($a, $b) use ($direction) {
            $roomCmp = strnatcasecmp((string) $a['room_number'], (string) $b['room_number']);
            if ($roomCmp !== 0) {
                return $direction === 'ASC' ? $roomCmp : -$roomCmp;
            }
            return strnatcasecmp((string) $a['label'], (string) $b['label']);
        });

        return $rows;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM beds WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $roomId, string $label): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO beds (room_id, label, status) VALUES (?, ?, "vacant")'
        );
        $stmt->execute([$roomId, $label]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function updateLabel(int $id, string $label): void
    {
        $stmt = Database::getConnection()->prepare('UPDATE beds SET label = ? WHERE id = ?');
        $stmt->execute([$label, $id]);
    }

    /** Same class of bug as Room::existsByNumber() — beds(room_id, label) is
     *  UNIQUE and neither create() nor updateLabel() checked first. */
    public static function existsByLabelInRoom(int $roomId, string $label, ?int $excludingId = null): bool
    {
        if ($excludingId !== null) {
            $stmt = Database::getConnection()->prepare(
                'SELECT COUNT(*) FROM beds WHERE room_id = ? AND label = ? AND id != ?'
            );
            $stmt->execute([$roomId, $label, $excludingId]);
        } else {
            $stmt = Database::getConnection()->prepare('SELECT COUNT(*) FROM beds WHERE room_id = ? AND label = ?');
            $stmt->execute([$roomId, $label]);
        }
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Guarded delete — never delete a bed a boarder currently occupies. */
    public static function delete(int $id): void
    {
        $bed = self::find($id);
        if ($bed === null) {
            throw new RuntimeException('Bed not found.');
        }
        if ($bed['status'] === 'occupied') {
            throw new RuntimeException('Cannot delete an occupied bed — move the boarder out first.');
        }
        $stmt = Database::getConnection()->prepare('DELETE FROM beds WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Feature 8 (Detailed Bed Mapping): a bed already occupied cannot be
     * assigned to a second boarder. See PHASES.md Phase 2 verification.
     */
    public static function assign(int $bedId, int $boarderId): void
    {
        $pdo = Database::getConnection();
        $ownTx = !$pdo->inTransaction(); // join the caller's transaction so multi-step moves are all-or-nothing
        if ($ownTx) {
            $pdo->beginTransaction();
        }

        try {
            $stmt = $pdo->prepare('SELECT status FROM beds WHERE id = ? FOR UPDATE');
            $stmt->execute([$bedId]);
            $bed = $stmt->fetch();

            if (!$bed) {
                throw new RuntimeException('Bed not found.');
            }
            if ($bed['status'] === 'occupied') {
                throw new RuntimeException('This bed is already occupied.');
            }

            $pdo->prepare('UPDATE beds SET status = "occupied", current_boarder_id = ? WHERE id = ?')->execute([$boarderId, $bedId]);
            if ($ownTx) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTx) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function vacate(int $bedId): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE beds SET status = "vacant", current_boarder_id = NULL WHERE id = ?'
        );
        $stmt->execute([$bedId]);
    }

    /**
     * Frees whatever bed this resident holds, going by the beds table itself rather than
     * the pointer on their profile: a stale pointer can then never empty someone else's
     * bed, and a bed the profile lost track of is still freed.
     */
    public static function vacateHeldBy(int $boarderId): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE beds SET status = "vacant", current_boarder_id = NULL WHERE current_boarder_id = ?'
        );
        $stmt->execute([$boarderId]);
    }

    public static function counts(): array
    {
        $row = Database::getConnection()->query(
            "SELECT COUNT(*) AS total, SUM(status = 'occupied') AS occupied FROM beds"
        )->fetch();
        return ['total' => (int) $row['total'], 'occupied' => (int) ($row['occupied'] ?? 0)];
    }
}
