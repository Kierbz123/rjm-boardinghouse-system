<?php

namespace App\Models;

use App\Database;

/** Admin-editable key/value settings (table `settings`). */
class Setting
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $stmt = Database::getConnection()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? $default : (string) $value;
    }

    public static function set(string $key, ?string $value, ?int $userId = null): void
    {
        Database::getConnection()->prepare(
            'INSERT INTO settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
        )->execute([$key, $value, $userId]);
    }
}
