<?php

namespace App\Controllers;

use App\Models\User;
use App\Services\LedgerBuilder;
use App\Services\LedgerWorkbook;

class LedgerController
{
    public static function export(): void
    {
        // Dates are whole days (Y-m-d); the "to" day is included in full.
        $isDate = fn ($d) => is_string($d) && ($dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $d)) && $dt->format('Y-m-d') === $d;
        $from = $isDate($_GET['from'] ?? null) ? $_GET['from'] : date('Y-m-01');
        $to = $isDate($_GET['to'] ?? null) ? $_GET['to'] : date('Y-m-d');

        if (($_GET['format'] ?? '') === 'csv') {
            $body = "\xEF\xBB\xBF" . LedgerBuilder::buildCsv("{$from} 00:00:00", "{$to} 23:59:59"); // BOM so Excel reads UTF-8
            header('Content-Type: text/csv; charset=UTF-8');
            $filename = "rjm_ledger_{$from}_to_{$to}.csv";
        } else {
            // Styled .xlsx workbook: Statement sheet (the owner's template) + General Ledger sheet.
            $body = LedgerWorkbook::build($from, $to, (string) (User::findById((int) $_SESSION['user_id'])['name'] ?? 'Administrator'));
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $filename = "rjm_financial_statement_{$from}_to_{$to}.xlsx";
        }
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($body));
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $body;
    }
}
