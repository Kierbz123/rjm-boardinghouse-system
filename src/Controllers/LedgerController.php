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

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="ledger.csv"');
        echo $csv;
    }
}
