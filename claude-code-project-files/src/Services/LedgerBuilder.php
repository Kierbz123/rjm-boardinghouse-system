<?php

namespace App\Services;

use App\Database;

/** Feature 5 (Financial Ledger Export) — generated from existing tables, not a separate dataset. */
class LedgerBuilder
{
    /** @param string $from 'Y-m-d H:i:s' inclusive  @param string $to 'Y-m-d H:i:s' inclusive */
    public static function buildCsv(string $from, string $to): string
    {
        $sql = "SELECT 'Payment received' AS type, p.id, u.name AS user_name, u.email AS user_email,
                       CONCAT('Rent/penalty payment for ', p.billing_period) AS description,
                       COALESCE(p.approved_amount, p.claimed_amount) AS amount, p.verification_status AS status, p.created_at AS at
                FROM payments p JOIN users u ON u.id = p.boarder_id
                WHERE p.verification_status IN ('auto-matched','admin-approved') AND p.created_at BETWEEN ? AND ?
                UNION ALL
                SELECT 'Expense', e.id, u.name, u.email, CONCAT(e.category, IF(e.description IS NULL, '', CONCAT(': ', e.description))),
                       e.amount, 'recorded', e.created_at
                FROM expenses e JOIN users u ON u.id = e.staff_id
                WHERE e.created_at BETWEEN ? AND ?
                UNION ALL
                SELECT 'Penalty charged', pen.id, u.name, u.email, pr.name, pen.amount, pen.status, pen.applied_at
                FROM penalties pen
                JOIN users u ON u.id = pen.boarder_id
                JOIN penalty_rules pr ON pr.id = pen.rule_id
                WHERE pen.applied_at BETWEEN ? AND ?
                ORDER BY at, type, id";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([$from, $to, $from, $to, $from, $to]);

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Type', 'ID', 'User Name', 'User Email', 'Description', 'Amount', 'Status', 'Date']);
        $totals = ['Payment received' => 0.0, 'Expense' => 0.0, 'Penalty charged' => 0.0];
        foreach ($stmt->fetchAll() as $r) {
            fputcsv($out, [
                $r['type'],
                $r['id'],
                self::safe($r['user_name']),
                self::safe($r['user_email']),
                self::safe((string) $r['description']),
                number_format((float) $r['amount'], 2, '.', ''),
                $r['status'],
                date('Y-m-d H:i', strtotime($r['at'])),
            ]);
            $totals[$r['type']] += (float) $r['amount'];
        }

        // Penalties are money owed, not money in, so they are not part of the net.
        fputcsv($out, []);
        fputcsv($out, ['TOTAL PAYMENTS RECEIVED', '', '', '', '', number_format($totals['Payment received'], 2, '.', '')]);
        fputcsv($out, ['TOTAL EXPENSES', '', '', '', '', number_format($totals['Expense'], 2, '.', '')]);
        fputcsv($out, ['NET (payments - expenses)', '', '', '', '', number_format($totals['Payment received'] - $totals['Expense'], 2, '.', '')]);
        fputcsv($out, ['PENALTIES CHARGED (receivable)', '', '', '', '', number_format($totals['Penalty charged'], 2, '.', '')]);

        rewind($out);
        return stream_get_contents($out);
    }

    /** Stops spreadsheet apps from running user-entered text as a formula (=, +, -, @). */
    private static function safe(string $field): string
    {
        return preg_match('/^[=+\-@\t\r]/', $field) ? "'" . $field : $field;
    }
}
