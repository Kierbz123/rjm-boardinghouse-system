<?php

namespace App\Services;

use App\Database;

/**
 * Feature 5 (Financial Ledger Export) — generated from existing tables, not a separate dataset.
 * Cash-basis general ledger: payments received are Debits (cash in), expenses are Credits (cash out),
 * the running balance is the cumulative net. Penalties are receivables (money owed, not cash), so they
 * sit in their own "Accrued Penalty" column and never move the balance.
 */
class LedgerBuilder
{
    private const MONEY = [2, '.', ''];

    /**
     * Every ledger line in the range, oldest first: type, id, user_name, ref, category, description, amount, status, at.
     * Payments are dated by approval (reviewed_at) so the ledger agrees with the dashboard's "collected".
     * @param string $from 'Y-m-d H:i:s' inclusive  @param string $to 'Y-m-d H:i:s' inclusive
     */
    public static function rows(string $from, string $to): array
    {
        $sql = "SELECT 'Payment received' AS type, p.id, u.name AS user_name,
                       COALESCE(p.receipt_number, p.reference_number, CONCAT('PAY-', p.id)) AS ref,
                       COALESCE(p.payment_method, '') AS category,
                       CONCAT('Rent/penalty payment for ', p.billing_period) AS description,
                       COALESCE(p.approved_amount, p.claimed_amount) AS amount, p.verification_status AS status, COALESCE(p.reviewed_at, p.created_at) AS at
                FROM payments p JOIN users u ON u.id = p.boarder_id
                WHERE p.verification_status IN ('auto-matched','admin-approved') AND COALESCE(p.reviewed_at, p.created_at) BETWEEN ? AND ?
                UNION ALL
                SELECT 'Expense', e.id, u.name, CONCAT('EXP-', e.id), e.category, COALESCE(e.description, e.category),
                       e.amount, 'recorded', e.created_at
                FROM expenses e JOIN users u ON u.id = e.staff_id
                WHERE e.created_at BETWEEN ? AND ?
                UNION ALL
                SELECT 'Penalty charged', pen.id, u.name, CONCAT('PEN-', pen.id), 'Penalty', pr.name, pen.amount, pen.status, pen.applied_at
                FROM penalties pen
                JOIN users u ON u.id = pen.boarder_id
                JOIN penalty_rules pr ON pr.id = pen.rule_id
                WHERE pen.applied_at BETWEEN ? AND ?
                ORDER BY at, type, id";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([$from, $to, $from, $to, $from, $to]);
        return $stmt->fetchAll();
    }

    /** @param string $from 'Y-m-d H:i:s' inclusive  @param string $to 'Y-m-d H:i:s' inclusive */
    public static function buildCsv(string $from, string $to): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['RJM BOARDINGHOUSE - GENERAL LEDGER']);
        fputcsv($out, ['Period', substr($from, 0, 10) . ' to ' . substr($to, 0, 10)]);
        fputcsv($out, ['Generated', date('Y-m-d H:i')]);
        fputcsv($out, ['Basis', 'Cash: Debit = money received, Credit = money spent. Penalties are receivables and do not affect the balance.']);
        fputcsv($out, []);
        fputcsv($out, ['Date', 'Reference No.', 'Type', 'Category', 'Particulars', 'Name', 'Debit (Cash In)', 'Credit (Cash Out)', 'Running Balance', 'Accrued Penalty', 'Status']);

        $in = $spent = $receivable = 0.0;
        $byCategory = [];
        foreach (self::rows($from, $to) as $r) {
            $amt = (float) $r['amount'];
            $debit = $credit = $accrued = '';
            if ($r['type'] === 'Payment received') {
                $in += $amt;
                $debit = number_format($amt, ...self::MONEY);
            } elseif ($r['type'] === 'Expense') {
                $spent += $amt;
                $byCategory[$r['category']] = ($byCategory[$r['category']] ?? 0.0) + $amt;
                $credit = number_format($amt, ...self::MONEY);
            } else {
                $accrued = number_format($amt, ...self::MONEY);
                // Paid or waived penalties stay listed but are no longer owed.
                if ($r['status'] === 'unpaid') {
                    $receivable += $amt;
                }
            }
            fputcsv($out, [
                date('Y-m-d H:i', strtotime($r['at'])),
                self::safe((string) $r['ref']),
                $r['type'],
                self::safe((string) $r['category']),
                self::safe((string) $r['description']),
                self::safe((string) $r['user_name']),
                $debit,
                $credit,
                number_format($in - $spent, ...self::MONEY),
                $accrued,
                $r['status'],
            ]);
        }

        fputcsv($out, []);
        fputcsv($out, ['TOTAL PAYMENTS RECEIVED', '', '', '', '', '', number_format($in, ...self::MONEY)]);
        fputcsv($out, ['TOTAL EXPENSES', '', '', '', '', '', number_format($spent, ...self::MONEY)]);
        fputcsv($out, ['NET (payments - expenses)', '', '', '', '', '', number_format($in - $spent, ...self::MONEY)]);
        if ($byCategory) {
            fputcsv($out, []);
            fputcsv($out, ['EXPENSES BY CATEGORY']);
            ksort($byCategory);
            foreach ($byCategory as $cat => $total) {
                fputcsv($out, ['  ' . self::safe((string) $cat), '', '', '', '', '', number_format($total, ...self::MONEY)]);
            }
        }
        // Penalties are money owed, not money in, so they are not part of the net.
        fputcsv($out, []);
        fputcsv($out, ['PENALTIES CHARGED (receivable)', '', '', '', '', '', number_format($receivable, ...self::MONEY)]);

        rewind($out);
        return stream_get_contents($out);
    }

    /** Stops spreadsheet apps from running user-entered text as a formula (=, +, -, @). */
    private static function safe(string $field): string
    {
        return preg_match('/^[=+\-@\t\r]/', $field) ? "'" . $field : $field;
    }
}
