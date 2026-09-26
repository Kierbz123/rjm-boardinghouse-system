<?php

namespace App\Services;

use App\Database;

/** Feature 5 (Financial Ledger Export) — generated from existing tables, not a separate dataset. */
class LedgerBuilder
{
    public static function buildCsv(string $from, string $to): string
    {
        // Enhanced detailed ledger with user names, descriptions, and Excel-compatible date formatting
        $sql = "SELECT 'payment' AS type,
                p.id,
                u.name AS user_name,
                u.email AS user_email,
                p.billing_period AS description,
                p.claimed_amount AS amount,
                p.verification_status AS status,
                DATE_FORMAT(p.created_at, '%m/%d/%Y %H:%i') AS created_at
                FROM payments p
                JOIN users u ON u.id = p.boarder_id
                WHERE p.verification_status IN ('auto-matched','admin-approved') AND p.created_at BETWEEN ? AND ?
                UNION ALL
                SELECT 'expense' AS type,
                e.id,
                u.name AS user_name,
                u.email AS user_email,
                e.category AS description,
                e.amount,
                'approved' AS status,
                DATE_FORMAT(e.created_at, '%m/%d/%Y %H:%i') AS created_at
                FROM expenses e
                JOIN users u ON u.id = e.staff_id
                WHERE e.created_at BETWEEN ? AND ?
                UNION ALL
                SELECT 'penalty' AS type,
                pen.id,
                u.name AS user_name,
                u.email AS user_email,
                pr.name AS description,
                pen.amount,
                'applied' AS status,
                DATE_FORMAT(pen.applied_at, '%m/%d/%Y %H:%i') AS created_at
                FROM penalties pen
                JOIN users u ON u.id = pen.boarder_id
                JOIN penalty_rules pr ON pr.id = pen.rule_id
                WHERE pen.applied_at BETWEEN ? AND ?
                ORDER BY created_at";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([$from, $to, $from, $to, $from, $to]);
        $rows = $stmt->fetchAll();

        // Enhanced CSV header with more detailed columns
        $csv = "Type,ID,User Name,User Email,Description,Amount,Status,Date\n";
        $total = 0.0;
        
        foreach ($rows as $r) {
            // Proper CSV escaping for fields with commas
            // Quote the date to prevent Excel formatting issues
            $escapedFields = [
                $r['type'],
                $r['id'],
                self::escapeCsvField($r['user_name']),
                self::escapeCsvField($r['user_email']),
                self::escapeCsvField($r['description']),
                number_format((float) $r['amount'], 2, '.', ''),
                $r['status'],
                '"' . $r['created_at'] . '"'  // Quote date to force Excel text treatment
            ];
            $csv .= implode(',', $escapedFields) . "\n";
            $total += (float) $r['amount'];
        }
        
        // Total row with proper formatting
        $csv .= 'TOTAL,,,,,' . number_format($total, 2, '.', '') . ",,\n";
        return $csv;
    }
    
    private static function escapeCsvField(string $field): string
    {
        // Escape field if it contains comma, quote, or newline
        if (preg_match('/[,"\n]/', $field)) {
            return '"' . str_replace('"', '""', $field) . '"';
        }
        return $field;
    }
    
    private static function formatDateForExcel(string $dateStr): string
    {
        // Convert to Excel-friendly format (MM/DD/YYYY HH:MM)
        // Format: 09/16/2026 14:30
        return '"' . $dateStr . '"';  // Quote the date to prevent Excel formatting issues
    }
}
