<?php

namespace App\Models;

use App\Database;

class BoarderProfile
{
    public static function find(int $userId): ?array
    {
        $sql = 'SELECT boarder_profiles.*, users.name, users.email
                FROM boarder_profiles
                JOIN users ON users.id = boarder_profiles.user_id
                WHERE user_id = ?';
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    /** Current residents by default; $archived = true lists only archived ones. */
    public static function all(bool $archived = false): array
    {
        $sql = 'SELECT boarder_profiles.*, users.name, users.email, users.status AS account_status,
                       rooms.room_number, beds.label AS bed_label
                FROM boarder_profiles
                JOIN users ON users.id = boarder_profiles.user_id
                LEFT JOIN rooms ON rooms.id = boarder_profiles.room_id
                LEFT JOIN beds ON beds.id = boarder_profiles.bed_id
                WHERE users.status ' . ($archived ? '=' : '<>') . ' "archived"
                ORDER BY users.name';
        return Database::getConnection()->query($sql)->fetchAll();
    }

    /** Payments or penalties on record — such a boarder is archived, never erased. */
    public static function hasFinancialHistory(int $userId): bool
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT (SELECT COUNT(*) FROM payments WHERE boarder_id = ?) + (SELECT COUNT(*) FROM penalties WHERE boarder_id = ?)'
        );
        $stmt->execute([$userId, $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Retires a boarder while keeping every record: moves them out today (frees the
     * bed, stops rent) unless already moved out, blocks login, hides them from lists.
     */
    public static function archive(int $userId, ?int $changedBy): void
    {
        $profile = self::find($userId);
        if ($profile === null) {
            throw new \RuntimeException('Boarder not found.');
        }
        if ($profile['status'] !== 'moved_out') {
            self::updateDatesAndStatus($userId, $profile['move_in_date'], $profile['move_out_date'] ?? date('Y-m-d'), $changedBy);
        }
        Database::getConnection()->prepare('UPDATE users SET status = "archived" WHERE id = ? AND role = "boarder"')->execute([$userId]);
    }

    public static function restore(int $userId): void
    {
        Database::getConnection()->prepare('UPDATE users SET status = "active" WHERE id = ? AND role = "boarder"')->execute([$userId]);
    }

    public static function create(int $userId, ?int $roomId, ?int $bedId): void
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO boarder_profiles (user_id, room_id, bed_id, status) VALUES (?, ?, ?, "pending")'
        );
        $stmt->execute([$userId, $roomId, $bedId]);
    }

    /**
     * Feature 10 (Status Life System): every change is logged with who/when/why.
     * Moving a boarder to "moved_out" frees their bed automatically.
     */
    /** Must match the ENUM in 0003_create_boarder_profiles_and_status_log.sql. */
    public const STATUSES = ['pending', 'active', 'on_notice', 'moved_out'];

    public static function updateStatus(int $boarderId, string $newStatus, ?int $changedBy, string $reason): void
    {
        if (!in_array($newStatus, self::STATUSES, true)) {
            throw new \RuntimeException('Invalid boarder status.');
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $current = $pdo->prepare('SELECT status, bed_id, move_in_date FROM boarder_profiles WHERE user_id = ? FOR UPDATE');
            $current->execute([$boarderId]);
            $row = $current->fetch();

            // Bug found during Phase 8 QA pass: updating a non-existent boarder ID
            // silently no-op'd the UPDATE, then threw an uncaught FK-constraint
            // exception on the boarder_status_log INSERT (boarder_id has no matching
            // row in users). Fail explicitly and gracefully instead.
            if ($row === false) {
                throw new \RuntimeException("Boarder #{$boarderId} not found.");
            }

            $pdo->prepare('UPDATE boarder_profiles SET status = ?, status_updated_at = NOW() WHERE user_id = ?')
                ->execute([$newStatus, $boarderId]);

            // Rent starts accruing the day a boarder becomes active, unless an admin set a move-in date.
            if (in_array($newStatus, ['active', 'on_notice'], true) && empty($row['move_in_date'])) {
                $pdo->prepare('UPDATE boarder_profiles SET move_in_date = CURDATE() WHERE user_id = ?')->execute([$boarderId]);
            }

            $pdo->prepare('INSERT INTO boarder_status_log (boarder_id, old_status, new_status, changed_by, reason) VALUES (?, ?, ?, ?, ?)')
                ->execute([$boarderId, $row['status'], $newStatus, $changedBy, mb_substr($reason, 0, 255)]);

            if ($newStatus === 'moved_out' && !empty($row['bed_id'])) {
                Bed::vacate((int) $row['bed_id']);
                $pdo->prepare('UPDATE boarder_profiles SET bed_id = NULL WHERE user_id = ?')->execute([$boarderId]);
            }

            \App\Services\BillingService::calculateBalance($boarderId, $pdo);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function statusLog(int $boarderId): array
    {
        $sql = 'SELECT bsl.*, COALESCE(u.name, "System") AS changer_name
                FROM boarder_status_log bsl
                LEFT JOIN users u ON u.id = bsl.changed_by
                WHERE bsl.boarder_id = ?
                ORDER BY bsl.created_at DESC, bsl.id DESC';
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([$boarderId]);
        return $stmt->fetchAll();
    }

    public static function findWithFullDetails(int $userId): ?array
    {
        $sql = 'SELECT bp.*, u.name, u.email, u.created_at AS user_created_at,
                       r.room_number, r.base_price, r.floor,
                       b.label AS bed_label
                FROM boarder_profiles bp
                JOIN users u ON u.id = bp.user_id
                LEFT JOIN rooms r ON r.id = bp.room_id
                LEFT JOIN beds b ON b.id = bp.bed_id
                WHERE bp.user_id = ?';
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Updates move-in and move-out dates.
     * If move_out_date is supplied and status != 'moved_out', atomically transitions status
     * to 'moved_out', vacates the bed, and logs the change to boarder_status_log in a single transaction.
     */
    public static function updateDatesAndStatus(int $boarderId, ?string $moveIn, ?string $moveOut, ?int $changedBy = null): void
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $current = $pdo->prepare('SELECT status, bed_id FROM boarder_profiles WHERE user_id = ? FOR UPDATE');
            $current->execute([$boarderId]);
            $row = $current->fetch();
            if ($row === false) {
                throw new \RuntimeException("Boarder #{$boarderId} not found.");
            }

            $stmt = $pdo->prepare('UPDATE boarder_profiles SET move_in_date = ?, move_out_date = ? WHERE user_id = ?');
            $stmt->execute([$moveIn, $moveOut, $boarderId]);

            if ($moveOut !== null && $row['status'] !== 'moved_out') {
                $oldStatus = $row['status'];
                $newStatus = 'moved_out';
                $reason = 'Move-out date set to ' . $moveOut;

                $update = $pdo->prepare('UPDATE boarder_profiles SET status = ?, status_updated_at = NOW() WHERE user_id = ?');
                $update->execute([$newStatus, $boarderId]);

                $log = $pdo->prepare(
                    'INSERT INTO boarder_status_log (boarder_id, old_status, new_status, changed_by, reason) VALUES (?, ?, ?, ?, ?)'
                );
                $log->execute([$boarderId, $oldStatus, $newStatus, $changedBy, $reason]);

                if (!empty($row['bed_id'])) {
                    $vacateBed = $pdo->prepare('UPDATE beds SET status = "vacant", current_boarder_id = NULL WHERE id = ?');
                    $vacateBed->execute([(int) $row['bed_id']]);

                    $clearBed = $pdo->prepare('UPDATE boarder_profiles SET bed_id = NULL WHERE user_id = ?');
                    $clearBed->execute([$boarderId]);
                }
            }

            // Move dates decide which months are charged.
            \App\Services\BillingService::calculateBalance($boarderId, $pdo);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function updateNotes(int $userId, ?string $notes): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE boarder_profiles SET notes = ? WHERE user_id = ?'
        );
        $stmt->execute([$notes, $userId]);
    }

    public static function assignRoomAndBed(int $boarderId, int $roomId, int $bedId): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE boarder_profiles SET room_id = ?, bed_id = ? WHERE user_id = ?'
        );
        $stmt->execute([$roomId, $bedId, $boarderId]);
    }

    public static function updateProfile(int $userId, ?string $contactNumber, ?string $emergencyContactNumber): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE boarder_profiles SET contact_number = ?, emergency_contact_number = ? WHERE user_id = ?'
        );
        $stmt->execute([$contactNumber, $emergencyContactNumber, $userId]);
    }

    /**
     * Fully deletes a boarder who was added by mistake: vacates their bed, deletes
     * relational records, profile, and the user account inside a single transaction.
     * Refused when payments or penalties exist — financial records are never erased; archive() instead.
     */
    public static function delete(int $userId): void
    {
        if (self::hasFinancialHistory($userId)) {
            throw new \RuntimeException('This boarder has payment or penalty records and can only be archived.');
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $user = User::findById($userId);
            if ($user === null || $user['role'] !== 'boarder') {
                throw new \RuntimeException('Boarder not found.');
            }

            // Free bed if assigned
            $stmt = $pdo->prepare('SELECT bed_id FROM boarder_profiles WHERE user_id = ?');
            $stmt->execute([$userId]);
            $bedId = $stmt->fetchColumn();
            if ($bedId) {
                Bed::vacate((int) $bedId);
            }
            $clearBeds = $pdo->prepare('UPDATE beds SET current_boarder_id = NULL, status = "vacant" WHERE current_boarder_id = ?');
            $clearBeds->execute([$userId]);

            // Clear relational logs & child rows
            $tables = [
                'boarder_status_log' => 'boarder_id',
                'notifications' => 'user_id',
                'sos_alerts' => 'boarder_id',
                'maintenance_requests' => 'boarder_id',
                'rent_charges' => 'boarder_id',
                'incidents' => 'reported_by',
                'boarder_profiles' => 'user_id',
            ];
            foreach ($tables as $tbl => $col) {
                $del = $pdo->prepare("DELETE FROM {$tbl} WHERE {$col} = ?");
                $del->execute([$userId]);
            }

            // Remove the user account
            $delUser = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $delUser->execute([$userId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
