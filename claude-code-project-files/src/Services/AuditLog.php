<?php

namespace App\Services;

use App\Database;

/**
 * The trail of sensitive actions: who did what to which record, and when. Writes join the caller's
 * transaction (same connection), so an action that rolls back leaves no trail and one that commits
 * always does.
 */
final class AuditLog
{
    public static function record(?int $actorId, string $action, string $entityType, ?int $entityId, array $details = []): void
    {
        Database::getConnection()->prepare(
            'INSERT INTO audit_log (actor_id, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)'
        )->execute([$actorId ?: null, $action, $entityType, $entityId, $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null]);
    }

    /** @return array<int, list<array>> per user id, the account's trail newest first, with the actor's name */
    public static function forUsersWithRole(string $role, int $perUser = 20): array
    {
        // ponytail: reads every row for the role; add paging when one account's history runs to thousands
        $stmt = Database::getConnection()->prepare(
            "SELECT a.*, u.name AS actor_name FROM audit_log a LEFT JOIN users u ON u.id = a.actor_id
             WHERE a.entity_type = 'user' AND a.entity_id IN (SELECT id FROM users WHERE role = ?)
             ORDER BY a.id DESC"
        );
        $stmt->execute([$role]);
        $byUser = [];
        foreach ($stmt->fetchAll() as $row) {
            if (count($byUser[(int) $row['entity_id']] ?? []) < $perUser) {
                $byUser[(int) $row['entity_id']][] = $row;
            }
        }
        return $byUser;
    }
}
