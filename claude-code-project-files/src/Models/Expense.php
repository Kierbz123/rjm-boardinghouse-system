<?php

namespace App\Models;

use App\Database;

class Expense
{
    public static function create(int $staffId, string $category, float $amount, ?string $description, ?string $receiptPath): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO expenses (staff_id, category, amount, description, receipt_path) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$staffId, $category, $amount, $description, $receiptPath]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function all(): array
    {
        $sql = 'SELECT expenses.*, users.name AS staff_name
                FROM expenses JOIN users ON users.id = expenses.staff_id
                ORDER BY expenses.created_at DESC';
        return Database::getConnection()->query($sql)->fetchAll();
    }

    public static function recent(int $limit = 5): array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM expenses ORDER BY created_at DESC LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
