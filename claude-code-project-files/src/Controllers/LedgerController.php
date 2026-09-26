<?php

namespace App\Controllers;

use App\Services\LedgerBuilder;

class LedgerController
{
    public static function export(): void
    {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d 23:59:59');
        $csv = LedgerBuilder::buildCsv($from, $to);

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
