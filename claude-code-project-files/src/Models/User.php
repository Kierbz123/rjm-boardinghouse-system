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
        \App\Support\PasswordPolicy::assert($password); // bcrypt would silently cut anything past 72 bytes
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO users (role, name, email, password_hash) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$role, $name, mb_strtolower(trim($email)), $hash]);
        return (int) Database::getConnection()->lastInsertId();
    }

    /** How long a password typed by an administrator works before they have to issue a new one. */
    public const TEMP_PASSWORD_HOURS = 72;

    /** An administrator chose this account's current password: it must be replaced at first sign-in and expires. */
    public static function markPasswordTemporary(int $id): void
    {
        Database::getConnection()->prepare('UPDATE users SET must_change_password = 1, temp_password_expires_at = ? WHERE id = ?')
            ->execute([date('Y-m-d H:i:s', time() + self::TEMP_PASSWORD_HOURS * 3600), $id]);
    }

    /** True when the account still holds an administrator's temporary password and its time ran out. */
    public static function temporaryPasswordExpired(array $user): bool
    {
        $expires = $user['temp_password_expires_at'] ?? null;
        return !empty($user['must_change_password']) && $expires !== null && strtotime($expires) < time();
    }

    /** "" or null clears it. The caller validates the format (StaffController::phoneProblem). */
    public static function setPhone(int $id, ?string $phone): void
    {
        Database::getConnection()->prepare('UPDATE users SET phone = ? WHERE id = ?')->execute([$phone ?: null, $id]);
    }

    /** Who a resident can ring when an SOS goes unanswered: active staff that have a phone number. */
    public static function activeStaffPhones(): array
    {
        return Database::getConnection()->query(
            "SELECT name, phone FROM users WHERE role = 'staff' AND status = 'active' AND phone IS NOT NULL ORDER BY name"
        )->fetchAll();
    }

    public static function recordLogin(int $id): void
    {
        Database::getConnection()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$id]);
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

    /** 'active' or 'inactive' — inactive accounts can't log in and their open sessions end (AuthMiddleware). */
    public static function setStatus(int $id, string $status): void
    {
        $stmt = Database::getConnection()->prepare('UPDATE users SET status = ? WHERE id = ?');
        $stmt->execute([$status === 'active' ? 'active' : 'inactive', $id]);
    }

    /** The person themselves chose a password: it is no longer a temporary or default one. */
    public static function markPasswordChosen(int $id): void
    {
        Database::getConnection()->prepare('UPDATE users SET must_change_password = 0, temp_password_expires_at = NULL, password_changed_at = NOW() WHERE id = ?')->execute([$id]);
    }

    public static function updatePassword(int $id, string $password): void
    {
        \App\Support\PasswordPolicy::assert($password);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = Database::getConnection()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $id]);
    }
}

