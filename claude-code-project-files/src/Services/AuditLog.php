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
}
