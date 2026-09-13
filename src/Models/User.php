<?php

namespace App\Models;

use App\Database;

class User
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $role, string $name, string $email, string $password): int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO users (role, name, email, password_hash) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$role, $name, $email, $hash]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function allByRole(string $role): array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM users WHERE role = ? ORDER BY name');
        $stmt->execute([$role]);
        return $stmt->fetchAll();
    }

    /** Closes the CRUD gap from QA-VALIDATION-REPORT.md — name/email are current
     *  configuration, not an event record, so correcting them is appropriate
     *  (unlike payments/expenses/penalties, which stay append-only by design —
     *  see PROJECT_STRUCTURE.md's data-classification note). */
    public static function updateInfo(int $id, string $name, string $email): void
    {
        $stmt = Database::getConnection()->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
        $stmt->execute([$name, $email, $id]);
    }

    /** Same email-uniqueness check as User::create() — see Bug #1 in QA-VALIDATION-REPORT.md. */
    public static function emailTakenByAnotherUser(string $email, int $excludingId): bool
    {
        $stmt = Database::getConnection()->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $excludingId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function updatePassword(int $id, string $password): void
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = Database::getConnection()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $id]);
    }
}

