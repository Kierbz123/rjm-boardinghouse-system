<?php

namespace App\Models;

use App\Database;

class Incident
{
    public static function create(int $reportedBy, string $type, string $description): int
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO incidents (reported_by, type, description) VALUES (?, ?, ?)'
        );
        $stmt->execute([$reportedBy, $type, $description]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM incidents WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** $reportedBy limits the list to one reporter (boarders only ever see their own). */
    public static function all(?int $reportedBy = null): array
    {
        $sql = 'SELECT incidents.*, users.name AS reporter_name
                FROM incidents JOIN users ON users.id = incidents.reported_by'
            . ($reportedBy !== null ? ' WHERE incidents.reported_by = ?' : '')
            . ' ORDER BY incidents.created_at DESC';
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($reportedBy !== null ? [$reportedBy] : []);
        return $stmt->fetchAll();
    }

    public static function resolve(int $id, string $notes, int $userId = 0): void
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE incidents SET resolved = 1, resolution_notes = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$notes, $userId ?: null, $id]);
    }

    /** The archive's one FROM clause, shared by the page, the count and the totals. */
    private const HISTORY_FROM = 'FROM incidents
                JOIN users ON users.id = incidents.reported_by
                LEFT JOIN users resolver ON resolver.id = incidents.resolved_by';

    /**
     * The archive's filters, as WHERE text plus its parameters. $reportedBy keeps a resident
     * inside their own reports whatever they search for.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private static function historyWhere(?int $reportedBy, array $filters): array
    {
        $where = [];
        $params = [];
        if ($reportedBy !== null) {
            $where[] = 'incidents.reported_by = ?';
            $params[] = $reportedBy;
        }
        if (($filters['q'] ?? '') !== '') {
            // % and _ typed by the user are literals, not wildcards (same rule as User::managementList).
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $where[] = '(incidents.type LIKE ? OR incidents.description LIKE ?
                         OR incidents.resolution_notes LIKE ? OR users.name LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'incidents.resolved = ?';
            $params[] = $filters['status'] === 'resolved' ? 1 : 0;
        }
        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    public static function allWithDetails(int $limit = 50, int $offset = 0, ?int $reportedBy = null, array $filters = []): array
    {
        [$where, $params] = self::historyWhere($reportedBy, $filters);
        $sql = 'SELECT incidents.*,
                users.name AS reporter_name,
                users.email AS reporter_email,
                resolver.name AS resolver_name
                ' . self::HISTORY_FROM . $where . '
                ORDER BY incidents.created_at DESC
                LIMIT ? OFFSET ?';
        $stmt = Database::getConnection()->prepare($sql);
        foreach ([...$params, $limit, $offset] as $i => $value) {
            $stmt->bindValue($i + 1, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countAll(?int $reportedBy = null, array $filters = []): int
    {
        return self::historyCounts($reportedBy, $filters)['total'];
    }

    /**
     * Totals over everything the filters match, not just the page on screen — the KPI cards
     * used to count the 50 rows they were handed, which made page 2 report nonsense.
     *
     * @return array{total: int, resolved: int, open: int}
     */
    public static function historyCounts(?int $reportedBy = null, array $filters = []): array
    {
        [$where, $params] = self::historyWhere($reportedBy, $filters);
        $stmt = Database::getConnection()->prepare(
            'SELECT COUNT(*) AS total, COALESCE(SUM(incidents.resolved = 1), 0) AS resolved ' . self::HISTORY_FROM . $where
        );
        $stmt->execute($params);
        $row = $stmt->fetch() ?: ['total' => 0, 'resolved' => 0];
        return ['total' => (int) $row['total'], 'resolved' => (int) $row['resolved'], 'open' => (int) $row['total'] - (int) $row['resolved']];
    }

    public static function allForBoarder(int $boarderId, int $limit = 10): array
    {
        $limit = max(1, (int) $limit);
        $sql = "SELECT incidents.*, resolver.name AS resolver_name
                FROM incidents
                LEFT JOIN users resolver ON resolver.id = incidents.resolved_by
                WHERE incidents.reported_by = ?
                ORDER BY incidents.created_at DESC
                LIMIT {$limit}";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([$boarderId]);
        return $stmt->fetchAll();
    }
}
