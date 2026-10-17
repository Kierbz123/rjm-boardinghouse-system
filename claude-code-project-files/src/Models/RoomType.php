<?php

namespace App\Models;

use App\Database;

/**
 * The advertised offerings ("Choose how you live"): what a room is and what it costs.
 * Physical rooms point at one through rooms.room_type_id; beds and occupancy stay on rooms/beds.
 */
class RoomType
{
    /** With each type's linked physical rooms, beds and vacant beds counted from real records. */
    public static function all(): array
    {
        $rows = Database::getConnection()->query(
            'SELECT t.*,
                    (SELECT COUNT(*) FROM rooms r WHERE r.room_type_id = t.id) AS room_count,
                    (SELECT COUNT(*) FROM beds b JOIN rooms r ON r.id = b.room_id WHERE r.room_type_id = t.id) AS bed_count,
                    (SELECT COUNT(*) FROM beds b JOIN rooms r ON r.id = b.room_id
                      WHERE r.room_type_id = t.id AND b.status = \'vacant\') AS vacant_bed_count
             FROM room_types t ORDER BY t.sort_order, t.id'
        )->fetchAll();
        return array_map([self::class, 'withFeatures'], $rows);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM room_types WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? self::withFeatures($row) : null;
    }

    /** Inquiries store the offering's name as the visitor saw it; match it case-insensitively. */
    public static function findByName(string $name): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM room_types WHERE LOWER(name) = LOWER(?)');
        $stmt->execute([trim($name)]);
        $row = $stmt->fetch();
        return $row ? self::withFeatures($row) : null;
    }

    /**
     * Saves the offering and brings every room of this type in line with it (capacity and rent)
     * in one transaction. Returns how many rooms were updated.
     */
    public static function update(int $id, string $name, string $capacityLabel, int $capacity, float $price, string $priceUnit, ?string $badge, bool $popular, array $features): int
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE room_types SET name = ?, capacity_label = ?, capacity = ?, price = ?, price_unit = ?, badge = ?, is_popular = ?, features = ? WHERE id = ?'
            )->execute([$name, $capacityLabel, $capacity, $price, $priceUnit, $badge, (int) $popular, implode("\n", $features), $id]);
            $rooms = $pdo->prepare('UPDATE rooms SET capacity = ?, base_price = ? WHERE room_type_id = ?');
            $rooms->execute([$capacity, self::ratePerBed(['capacity' => $capacity, 'price' => $price, 'price_unit' => $priceUnit]), $id]);
            $pdo->commit();
            return $rooms->rowCount();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * What each resident of a room of this type is billed per month (rooms.base_price):
     * the price itself when charged per bed, an equal share of it when charged per room.
     */
    public static function ratePerBed(array $type): float
    {
        $price = (float) $type['price'];
        return $type['price_unit'] === 'bed' ? $price : round($price / max(1, (int) $type['capacity']), 2);
    }

    /** The most beds any room of this type already has; the type's capacity cannot go below it. */
    public static function largestBedCount(int $id): int
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT COALESCE(MAX(n), 0) FROM (SELECT COUNT(b.id) AS n FROM rooms r LEFT JOIN beds b ON b.room_id = r.id
              WHERE r.room_type_id = ? GROUP BY r.id) t'
        );
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    public static function nameTaken(string $name, int $excludingId): bool
    {
        $stmt = Database::getConnection()->prepare('SELECT COUNT(*) FROM room_types WHERE LOWER(name) = LOWER(?) AND id != ?');
        $stmt->execute([$name, $excludingId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** "₱3,500 / month" or "₱2,200 / bed / month". */
    public static function priceLabel(array $type): string
    {
        return '₱' . number_format((float) $type['price']) . ($type['price_unit'] === 'bed' ? ' / bed / month' : ' / month');
    }

    private static function withFeatures(array $row): array
    {
        $row['feature_list'] = array_values(array_filter(array_map('trim', explode("\n", (string) $row['features'])), 'strlen'));
        return $row;
    }
}
