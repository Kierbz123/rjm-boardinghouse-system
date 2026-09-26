<?php

namespace App\Models;

use App\Database;

class PenaltyRule
{
    public static function allActive(): array
    {
        return Database::getConnection()->query('SELECT * FROM penalty_rules WHERE active = 1')->fetchAll();
    }

    public static function all(): array
    {
        return Database::getConnection()->query('SELECT * FROM penalty_rules ORDER BY name')->fetchAll();
    }

    public static function create(string $name, string $conditionType, float $amount): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO penalty_rules (name, condition_type, amount) VALUES (?, ?, ?)'
        );
        $stmt->execute([$name, $conditionType, $amount]);
        return (int) Database::getConnection()->lastInsertId();
    }

    /**
     * Deliberately a toggle, not a delete — see QA-VALIDATION-REPORT.md Section H.
     * penalty_rules.id is referenced by penalties.rule_id, so deleting a rule that
     * has ever been applied would either violate that FK or orphan the audit trail.
     * Deactivating preserves history and simply stops new penalties from using it.
     */
    public static function setActive(int $id, bool $active): void
    {
        $stmt = Database::getConnection()->prepare('UPDATE penalty_rules SET active = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);
    }

    public static function updateAmount(int $id, float $amount): void
    {
        $stmt = Database::getConnection()->prepare('UPDATE penalty_rules SET amount = ? WHERE id = ?');
        $stmt->execute([$amount, $id]);
    }
}
