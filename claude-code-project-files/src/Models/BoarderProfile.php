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
                       users.archived_at, users.archive_reason, archiver.name AS archived_by_name,
                       rooms.room_number, beds.label AS bed_label
                FROM boarder_profiles
                JOIN users ON users.id = boarder_profiles.user_id
                LEFT JOIN users archiver ON archiver.id = users.archived_by
                LEFT JOIN rooms ON rooms.id = boarder_profiles.room_id
                LEFT JOIN beds ON beds.id = boarder_profiles.bed_id
                WHERE users.status ' . ($archived ? '=' : '<>') . ' "archived"
                ORDER BY users.name';
        return Database::getConnection()->query($sql)->fetchAll();
    }

    /**
     * Retires a resident while keeping every record. Nobody is ever erased: payments, penalties, repairs,
     * incidents and SOS history all stay. They are moved out today (frees the bed, stops rent) unless
     * already moved out, can no longer log in, and drop out of the current list. Restorable.
     */
    public static function archive(int $userId, ?int $changedBy, ?string $reason = null): void
    {
        $profile = self::find($userId);
        if ($profile === null) {
            throw new \RuntimeException('Boarder not found.');
        }
        $reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;
        if ($reason !== null && mb_strlen($reason) > 255) {
            throw new \RuntimeException('The reason must be 255 characters or fewer.');
        }
        // A scheduled (future) move-out is brought forward to today: an archived resident is gone now.
        $today = date('Y-m-d');
        $moveOut = $profile['move_out_date'] !== null && $profile['move_out_date'] <= $today ? $profile['move_out_date'] : $today;
        if ($profile['status'] !== 'moved_out') {
            self::updateDatesAndStatus($userId, $profile['move_in_date'], $moveOut, $changedBy);
        }
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE users SET status = "archived", archived_at = NOW(), archived_by = ?, archive_reason = ? WHERE id = ? AND role = "boarder"');
        $stmt->execute([$changedBy, $reason, $userId]);
        \App\Services\AuditLog::record($changedBy, 'resident_archived', 'boarder', $userId, ['reason' => $reason]);
    }

    /** @return bool false when there was no archived boarder with that id */
    public static function restore(int $userId, ?int $changedBy = null): bool
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE users SET status = "active", archived_at = NULL, archived_by = NULL, archive_reason = NULL WHERE id = ? AND role = "boarder" AND status = "archived"'
        );
        $stmt->execute([$userId]);
        $restored = $stmt->rowCount() > 0;
        if ($restored) {
            \App\Services\AuditLog::record($changedBy, 'resident_restored', 'boarder', $userId);
        }
        return $restored;
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

            if ($newStatus === 'moved_out') {
                Bed::vacateHeldBy($boarderId);
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
            $current = $pdo->prepare('SELECT status, bed_id, move_out_date FROM boarder_profiles WHERE user_id = ? FOR UPDATE');
            $current->execute([$boarderId]);
            $row = $current->fetch();
            if ($row === false) {
                throw new \RuntimeException("Boarder #{$boarderId} not found.");
            }

            $stmt = $pdo->prepare('UPDATE boarder_profiles SET move_in_date = ?, move_out_date = ? WHERE user_id = ?');
            $stmt->execute([$moveIn, $moveOut, $boarderId]);

            // A move-out date today or earlier takes effect now. A LATER date is only scheduled: the resident
            // stays active in their bed until that day (HousekeepingService::applyDueMoveOuts moves them out).
            if ($moveOut !== null && $moveOut <= date('Y-m-d') && $row['status'] !== 'moved_out') {
                $oldStatus = $row['status'];
                $newStatus = 'moved_out';
                $reason = 'Move-out date set to ' . $moveOut;

                $update = $pdo->prepare('UPDATE boarder_profiles SET status = ?, status_updated_at = NOW() WHERE user_id = ?');
                $update->execute([$newStatus, $boarderId]);

                $log = $pdo->prepare(
                    'INSERT INTO boarder_status_log (boarder_id, old_status, new_status, changed_by, reason) VALUES (?, ?, ?, ?, ?)'
                );
                $log->execute([$boarderId, $oldStatus, $newStatus, $changedBy, $reason]);

                Bed::vacateHeldBy($boarderId);
                $pdo->prepare('UPDATE boarder_profiles SET bed_id = NULL WHERE user_id = ?')->execute([$boarderId]);
            } elseif ($moveOut !== null && $row['status'] !== 'moved_out' && $moveOut !== $row['move_out_date']) {
                \App\Services\AuditLog::record($changedBy, 'move_out_scheduled', 'boarder', $boarderId, ['move_out_date' => $moveOut]);
            } elseif ($moveOut === null && $row['move_out_date'] !== null && $row['status'] !== 'moved_out') {
                \App\Services\AuditLog::record($changedBy, 'move_out_cancelled', 'boarder', $boarderId, ['was' => $row['move_out_date']]);
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

    /**
     * Cancels a move-out that is still only scheduled. The resident stays exactly as they are.
     *
     * @return bool false when nothing was scheduled (no date, or they have already moved out)
     */
    public static function cancelMoveOut(int $boarderId, ?int $changedBy): bool
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT status, move_out_date FROM boarder_profiles WHERE user_id = ? FOR UPDATE');
            $stmt->execute([$boarderId]);
            $row = $stmt->fetch();
            if ($row === false || $row['move_out_date'] === null || $row['status'] === 'moved_out') {
                $pdo->rollBack();
                return false;
            }
            $pdo->prepare('UPDATE boarder_profiles SET move_out_date = NULL WHERE user_id = ?')->execute([$boarderId]);
            \App\Services\AuditLog::record($changedBy, 'move_out_cancelled', 'boarder', $boarderId, ['was' => $row['move_out_date']]);
            \App\Services\BillingService::calculateBalance($boarderId, $pdo);
            $pdo->commit();
            return true;
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
}
