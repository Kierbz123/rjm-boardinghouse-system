<?php

namespace App\Controllers;

use App\Services\LedgerBuilder;

class LedgerController
{
    public static function export(): void
    {
        // Dates are whole days (Y-m-d); the "to" day is included in full.
        $isDate = fn ($d) => is_string($d) && ($dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $d)) && $dt->format('Y-m-d') === $d;
        $from = $isDate($_GET['from'] ?? null) ? $_GET['from'] : date('Y-m-01');
        $to = $isDate($_GET['to'] ?? null) ? $_GET['to'] : date('Y-m-d');
        $csv = LedgerBuilder::buildCsv("{$from} 00:00:00", "{$to} 23:59:59");

        // Enhanced CSV export with proper filename and charset
        $filename = 'rjm_ledger_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($csv));
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Add BOM for proper UTF-8 encoding in Excel
        echo "\xEF\xBB\xBF";
        echo $csv;
    }
}
