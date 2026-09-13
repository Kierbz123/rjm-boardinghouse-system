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

    public static function all(): array
    {
        $sql = 'SELECT boarder_profiles.*, users.name, users.email, rooms.room_number, beds.label AS bed_label
                FROM boarder_profiles
                JOIN users ON users.id = boarder_profiles.user_id
                LEFT JOIN rooms ON rooms.id = boarder_profiles.room_id
                LEFT JOIN beds ON beds.id = boarder_profiles.bed_id
                ORDER BY users.name';
        return Database::getConnection()->query($sql)->fetchAll();
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
    public static function updateStatus(int $boarderId, string $newStatus, ?int $changedBy, string $reason): void
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        $current = $pdo->prepare('SELECT status, bed_id FROM boarder_profiles WHERE user_id = ? FOR UPDATE');
        $current->execute([$boarderId]);
        $row = $current->fetch();

        // Bug found during Phase 8 QA pass: updating a non-existent boarder ID
        // silently no-op'd the UPDATE, then threw an uncaught FK-constraint
        // exception on the boarder_status_log INSERT (boarder_id has no matching
        // row in users). Fail explicitly and gracefully instead.
        if ($row === false) {
            $pdo->rollBack();
            throw new \RuntimeException("Boarder #{$boarderId} not found.");
        }

        $oldStatus = $row['status'];

        $update = $pdo->prepare(
            'UPDATE boarder_profiles SET status = ?, status_updated_at = NOW() WHERE user_id = ?'
        );
        $update->execute([$newStatus, $boarderId]);

        $log = $pdo->prepare(
            'INSERT INTO boarder_status_log (boarder_id, old_status, new_status, changed_by, reason) VALUES (?, ?, ?, ?, ?)'
        );
        $log->execute([$boarderId, $oldStatus, $newStatus, $changedBy, $reason]);

        if ($newStatus === 'moved_out' && !empty($row['bed_id'])) {
            Bed::vacate((int) $row['bed_id']);
            $clearBed = $pdo->prepare('UPDATE boarder_profiles SET bed_id = NULL WHERE user_id = ?');
            $clearBed->execute([$boarderId]);
        }

        $pdo->commit();
    }

    public static function statusLog(int $boarderId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM boarder_status_log WHERE boarder_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$boarderId]);
        return $stmt->fetchAll();
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
     * Fully deletes a boarder: vacates their bed, deletes relational records,
     * profile, and the user account inside a single transaction.
     */
    public static function delete(int $userId): void
    {
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
                'penalties' => 'boarder_id',
                'payments' => 'boarder_id',
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
