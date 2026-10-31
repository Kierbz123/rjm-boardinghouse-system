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

    /** User Management filters: '' = everyone except archived residents. */
    public const MANAGEMENT_STATUSES = ['', 'active', 'inactive', 'pending', 'archived'];
    public const MANAGEMENT_SORTS = ['name', 'newest', 'last_login'];

    // One fixed text; optional filters are switched by their parameter, so no SQL is ever assembled at runtime.
    // boarder_profiles is keyed by user_id (1:1), so each person is one row. Admins are never listed.
    private const MANAGEMENT_FROM = "FROM users u
        LEFT JOIN boarder_profiles bp ON bp.user_id = u.id
        LEFT JOIN rooms r ON r.id = bp.room_id
        LEFT JOIN beds b ON b.id = bp.bed_id
        WHERE u.role IN ('boarder', 'staff')
          AND (? = '' OR u.role = ?)
          AND CASE ?
                WHEN 'active'   THEN u.status = 'active'
                WHEN 'inactive' THEN u.status = 'inactive'
                WHEN 'archived' THEN u.status = 'archived'
                WHEN 'pending'  THEN u.status <> 'archived' AND bp.status = 'pending'
                ELSE u.status <> 'archived'
              END
          AND (? = '' OR u.name LIKE ? OR u.email LIKE ?
               OR REGEXP_REPLACE(u.phone, '[^0-9]', '') LIKE ?
               OR REGEXP_REPLACE(bp.contact_number, '[^0-9]', '') LIKE ?
               OR r.room_number LIKE ? OR u.id = ?)";

    private const MANAGEMENT_LIST_SQL = 'SELECT u.id, u.role, u.name, u.email, u.phone, u.status, u.created_at, u.last_login_at,
            u.must_change_password, u.temp_password_expires_at, bp.status AS residency_status, bp.contact_number,
            r.room_number, b.label AS bed_label ' . self::MANAGEMENT_FROM . "
        ORDER BY CASE WHEN ? = 'newest' THEN u.id END DESC,
                 CASE WHEN ? = 'last_login' THEN u.last_login_at END DESC,
                 u.name ASC, u.id ASC
        LIMIT ? OFFSET ?";

    private const MANAGEMENT_COUNT_SQL = 'SELECT COUNT(*) ' . self::MANAGEMENT_FROM;

    /**
     * Residents and staff in one list. $f holds validated 'q', 'type', 'status', 'sort'.
     * @return array{rows: list<array>, total: int}
     */
    public static function managementList(array $f, int $page, int $perPage): array
    {
        $q = $f['q'];
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $idNum = ctype_digit(ltrim($q, '#')) ? (int) ltrim($q, '#') : 0;
        // Phones are matched on digits only, so "0917 123 4567" finds a number stored as +63 917-123-4567:
        // the national 0 and the 63 country code are dropped from the term and the rest matched anywhere.
        // No digits typed at all: NULL, which no LIKE matches, instead of '%%' matching every phone.
        $digits = (string) preg_replace(['/\D/', '/^(?:0|63)/'], '', $q);
        $phoneLike = $digits === '' ? null : '%' . $digits . '%';
        $where = [$f['type'], $f['type'], $f['status'], $q, $like, $like, $phoneLike, $phoneLike, $like, $idNum];
        $db = Database::getConnection();

        $count = $db->prepare(self::MANAGEMENT_COUNT_SQL);
        $count->execute($where);
        $list = $db->prepare(self::MANAGEMENT_LIST_SQL);
        foreach ([...$where, $f['sort'], $f['sort'], $perPage, ($page - 1) * $perPage] as $i => $value) {
            $list->bindValue($i + 1, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $list->execute();
        return ['rows' => $list->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    /** The User Management summary. Archived residents count only towards "cannot sign in". */
    public static function managementCounts(): array
    {
        $c = ['total' => 0, 'boarders' => 0, 'staff' => 0, 'active' => 0, 'inactive' => 0];
        $rows = Database::getConnection()->query(
            "SELECT role, status, COUNT(*) AS n FROM users WHERE role IN ('boarder', 'staff') GROUP BY role, status"
        )->fetchAll();
        foreach ($rows as $r) {
            $n = (int) $r['n'];
            if ($r['status'] !== 'archived') {
                $c['total'] += $n;
                $c[$r['role'] === 'boarder' ? 'boarders' : 'staff'] += $n;
            }
            $c['active'] += $r['status'] === 'active' ? $n : 0;
            $c['inactive'] += in_array($r['status'], ['inactive', 'archived'], true) ? $n : 0;
        }
        return $c;
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

