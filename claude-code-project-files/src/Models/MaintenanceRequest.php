<?php

namespace App\Models;

use App\Database;

class MaintenanceRequest
{
    /** Must match the ENUMs in 0007_create_maintenance_requests.sql. */
    public const CATEGORIES = ['electrical', 'plumbing', 'structural', 'appliance', 'other'];
    public const STATUSES = ['open', 'in_progress', 'resolved'];

    public static function create(array $data): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO maintenance_requests
                (boarder_id, room_id, category, description, media_path, severity_score, priority_tier, scoring_pending, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, "open")'
        );
        $stmt->execute([
            $data['boarder_id'], $data['room_id'], $data['category'], $data['description'],
            $data['media_path'], $data['severity_score'], $data['priority_tier'], $data['scoring_pending'] ? 1 : 0,
        ]);
        return (int) Database::getConnection()->lastInsertId();
    }

    /**
     * Every unresolved request with the time-decayed score and tier (FEATURES.md §1), most urgent first,
     * then oldest first. `priority_tier` / `severity_score` hold the decayed values; `base_tier` is what
     * the request was scored at when it was submitted.
     */
    public static function openDecayed(): array
    {
        $rows = Database::getConnection()->query(
            "SELECT maintenance_requests.*, users.name AS boarder_name, rooms.room_number,
                    TIMESTAMPDIFF(HOUR, maintenance_requests.created_at, NOW()) AS hours_waiting
             FROM maintenance_requests
             JOIN users ON users.id = maintenance_requests.boarder_id
             LEFT JOIN rooms ON rooms.id = maintenance_requests.room_id
             WHERE maintenance_requests.status != 'resolved'"
        )->fetchAll();
        $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        foreach ($rows as &$r) {
            $r['base_tier'] = $r['priority_tier'];
            if (!$r['scoring_pending'] && $r['severity_score'] !== null) {
                $d = \App\Services\ScoringClient::withDecay((float) $r['severity_score'], (int) $r['hours_waiting']);
                $r['severity_score'] = $d['score'];
                $r['priority_tier'] = $d['tier'];
            }
        }
        unset($r);
        usort($rows, fn ($a, $b) => [$rank[$a['priority_tier']] ?? 4, $a['created_at']] <=> [$rank[$b['priority_tier']] ?? 4, $b['created_at']]);
        return $rows;
    }

    public static function queueSorted(): array
    {
        return array_slice(self::openDecayed(), 0, 100);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM maintenance_requests WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function updateStatus(int $id, string $status, int $userId = 0): void
    {
        if ($status === 'resolved') {
            $stmt = Database::getConnection()->prepare(
                'UPDATE maintenance_requests SET status = ?, resolved_at = NOW(), resolved_by = ? WHERE id = ?'
            );
            $resolvedBy = $userId > 0 ? $userId : null;
            $stmt->execute([$status, $resolvedBy, $id]);
        } else {
            $stmt = Database::getConnection()->prepare(
                'UPDATE maintenance_requests SET status = ?, resolved_at = NULL, resolved_by = NULL WHERE id = ?'
            );
            $stmt->execute([$status, $id]);
        }
    }


    /** The archive's one FROM clause, shared by the page, the count and the totals. */
    private const HISTORY_FROM = 'FROM maintenance_requests
                JOIN users ON users.id = maintenance_requests.boarder_id
                LEFT JOIN rooms ON rooms.id = maintenance_requests.room_id
                LEFT JOIN users resolver ON resolver.id = maintenance_requests.resolved_by';

    /**
     * The archive's filters, as WHERE text plus its parameters.
     * $filters: q (free text), status, tier — all already validated by the controller.
     *
     * @return array{0: string, 1: list<string>}
     */
    private static function historyWhere(array $filters): array
    {
        $where = [];
        $params = [];
        if (($filters['q'] ?? '') !== '') {
            // % and _ typed by the user are literals, not wildcards (same rule as User::managementList).
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $where[] = '(maintenance_requests.description LIKE ? OR maintenance_requests.category LIKE ?
                         OR users.name LIKE ? OR rooms.room_number LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'maintenance_requests.status = ?';
            $params[] = $filters['status'];
        }
        if (($filters['tier'] ?? '') !== '') {
            $where[] = 'maintenance_requests.priority_tier = ?';
            $params[] = $filters['tier'];
        }
        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    public static function allWithDetails(int $limit = 50, int $offset = 0, array $filters = []): array
    {
        [$where, $params] = self::historyWhere($filters);
        $sql = 'SELECT maintenance_requests.*,
                users.name AS boarder_name,
                users.email AS boarder_email,
                rooms.room_number,
                resolver.name AS resolver_name
                ' . self::HISTORY_FROM . $where . '
                ORDER BY maintenance_requests.created_at DESC
                LIMIT ? OFFSET ?';
        $stmt = Database::getConnection()->prepare($sql);
        foreach ([...$params, $limit, $offset] as $i => $value) {
            $stmt->bindValue($i + 1, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countAll(array $filters = []): int
    {
        return self::historyCounts($filters)['total'];
    }

    /**
     * Totals over everything the filters match, not just the page on screen — the KPI cards
     * used to count the 50 rows they were handed, which made page 2 report nonsense.
     *
     * @return array{total: int, resolved: int, open: int}
     */
    public static function historyCounts(array $filters = []): array
    {
        [$where, $params] = self::historyWhere($filters);
        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(*) AS total, COALESCE(SUM(maintenance_requests.status = 'resolved'), 0) AS resolved "
            . self::HISTORY_FROM . $where
        );
        $stmt->execute($params);
        $row = $stmt->fetch() ?: ['total' => 0, 'resolved' => 0];
        return ['total' => (int) $row['total'], 'resolved' => (int) $row['resolved'], 'open' => (int) $row['total'] - (int) $row['resolved']];
    }

    public static function allForBoarder(int $boarderId, int $limit = 10): array
    {
        $limit = max(1, (int) $limit);
        $sql = "SELECT mr.*, r.room_number
                FROM maintenance_requests mr
                LEFT JOIN rooms r ON r.id = mr.room_id
                WHERE mr.boarder_id = ?
                ORDER BY mr.created_at DESC
                LIMIT {$limit}";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([$boarderId]);
        return $stmt->fetchAll();
    }
}
