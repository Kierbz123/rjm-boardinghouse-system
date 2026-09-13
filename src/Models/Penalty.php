<?php

namespace App\Models;

use App\Database;

class Penalty
{
    public static function create(int $boarderId, int $ruleId, float $amount, string $reason): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO penalties (boarder_id, rule_id, amount, reason) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$boarderId, $ruleId, $amount, $reason]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function allForBoarder(int $boarderId): array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM penalties WHERE boarder_id = ? ORDER BY applied_at DESC');
        $stmt->execute([$boarderId]);
        return $stmt->fetchAll();
    }
}
