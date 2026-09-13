<?php

namespace App\Services;

use App\Database;

/** Feature 5 (Financial Ledger Export) — generated from existing tables, not a separate dataset. */
class LedgerBuilder
{
    public static function buildCsv(string $from, string $to): string
    {
        $sql = "SELECT 'payment' AS type, id, boarder_id AS related_user, claimed_amount AS amount, created_at
                FROM payments
                WHERE verification_status IN ('auto-matched','admin-approved') AND created_at BETWEEN ? AND ?
                UNION ALL
                SELECT 'expense' AS type, id, staff_id AS related_user, amount, created_at
                FROM expenses WHERE created_at BETWEEN ? AND ?
                UNION ALL
                SELECT 'penalty' AS type, id, boarder_id AS related_user, amount, applied_at AS created_at
                FROM penalties WHERE applied_at BETWEEN ? AND ?
                ORDER BY created_at";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([$from, $to, $from, $to, $from, $to]);
        $rows = $stmt->fetchAll();

        $csv = "type,id,related_user,amount,created_at\n";
        $total = 0.0;
        foreach ($rows as $r) {
            $csv .= implode(',', [$r['type'], $r['id'], $r['related_user'], $r['amount'], $r['created_at']]) . "\n";
            $total += (float) $r['amount'];
        }
        $csv .= 'TOTAL,,,' . number_format($total, 2, '.', '') . ",\n";
        return $csv;
    }
}
