<?php

namespace App\Services;

/**
 * The Export Ledger download as a styled .xlsx. Sheet 1 "Statement" follows the owner's balance-sheet template:
 * green bands, three month columns, SUM-formula subtotals. Sheet 2 "General Ledger" is the same detail as the CSV.
 * Built by hand (XML parts in a stored zip) because this PHP has no zip extension and the app uses no Composer.
 */
class LedgerWorkbook
{
    private const METHODS = ['gcash' => 'GCash', 'maya' => 'Maya', 'bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'other' => 'Other', '' => 'Unspecified'];

    /** Style name => [font, fill, border, number format, alignment]; the position is the cellXfs index. */
    private const STYLES = [
        'default' => [0, 0, 0, 0, 'vertical="center"'],
        'title' => [1, 2, 0, 0, 'vertical="center"'],
        'titleSub' => [2, 2, 0, 0, 'vertical="bottom"'],
        'band' => [3, 3, 0, 0, 'vertical="center" wrapText="1"'],
        'bandNum' => [3, 3, 0, 0, 'horizontal="right" vertical="center" wrapText="1"'],
        'sub' => [4, 4, 0, 0, 'vertical="center"'],
        'line' => [0, 0, 1, 0, 'vertical="center"'],
        'money' => [0, 0, 1, 164, 'vertical="center"'],
        'total' => [4, 5, 1, 0, 'vertical="center"'],
        'totalMoney' => [4, 5, 1, 164, 'vertical="center"'],
        'grand' => [5, 6, 1, 0, 'vertical="center"'],
        'grandMoney' => [5, 6, 1, 164, 'vertical="center"'],
        'note' => [6, 0, 0, 0, 'vertical="center"'],
    ];

    /** @param string $from 'Y-m-d'  @param string $to 'Y-m-d' (both inclusive) */
    public static function build(string $from, string $to, string $preparedBy): string
    {
        $ns = 'http://schemas.openxmlformats.org/';
        $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $sheetType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml';
        return self::zip([
            '[Content_Types].xml' => $head . "<Types xmlns=\"{$ns}package/2006/content-types\">"
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . "<Override PartName=\"/xl/worksheets/sheet1.xml\" ContentType=\"{$sheetType}\"/><Override PartName=\"/xl/worksheets/sheet2.xml\" ContentType=\"{$sheetType}\"/>"
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => $head . "<Relationships xmlns=\"{$ns}package/2006/relationships\">"
                . "<Relationship Id=\"rId1\" Type=\"{$ns}officeDocument/2006/relationships/officeDocument\" Target=\"xl/workbook.xml\"/></Relationships>",
            'xl/workbook.xml' => $head . "<workbook xmlns=\"{$ns}spreadsheetml/2006/main\" xmlns:r=\"{$ns}officeDocument/2006/relationships\">"
                . '<sheets><sheet name="Statement" sheetId="1" r:id="rId1"/><sheet name="General Ledger" sheetId="2" r:id="rId2"/></sheets>'
                . '<calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>',
            'xl/_rels/workbook.xml.rels' => $head . "<Relationships xmlns=\"{$ns}package/2006/relationships\">"
                . "<Relationship Id=\"rId1\" Type=\"{$ns}officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet1.xml\"/>"
                . "<Relationship Id=\"rId2\" Type=\"{$ns}officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet2.xml\"/>"
                . "<Relationship Id=\"rId3\" Type=\"{$ns}officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/></Relationships>",
            'xl/styles.xml' => $head . self::styles(),
            'xl/worksheets/sheet1.xml' => $head . self::statement($to, $preparedBy),
            'xl/worksheets/sheet2.xml' => $head . self::ledger($from, $to),
        ]);
    }

    /** The template's look: title band, dark section bands, light sub-section bands, mid-green subtotals. */
    private static function styles(): string
    {
        $font = fn (string $attrs, int $size, string $color = '') => "<font>{$attrs}<sz val=\"{$size}\"/>" . ($color ? "<color rgb=\"FF{$color}\"/>" : '') . '<name val="Calibri"/></font>';
        $fonts = [$font('', 11), $font('<b/>', 18, 'FFFFFF'), $font('<b/>', 11, 'FFFFFF'), $font('<b/>', 12, 'FFFFFF'), $font('<b/>', 11), $font('<b/>', 12), $font('<i/>', 9, '666666')];
        $fills = '<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>';
        foreach (['4A8F4E', '38773E', 'C9DFBB', '9BC184', '79A86A'] as $c) {
            $fills .= "<fill><patternFill patternType=\"solid\"><fgColor rgb=\"FF{$c}\"/><bgColor indexed=\"64\"/></patternFill></fill>";
        }
        $xfs = '';
        foreach (self::STYLES as [$fo, $fi, $bo, $nf, $align]) {
            $xfs .= "<xf numFmtId=\"{$nf}\" fontId=\"{$fo}\" fillId=\"{$fi}\" borderId=\"{$bo}\" xfId=\"0\" applyNumberFormat=\"1\" applyFont=\"1\" applyFill=\"1\" applyBorder=\"1\" applyAlignment=\"1\"><alignment {$align}/></xf>";
        }
        return '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="&quot;₱&quot;#,##0.00;[Red]\-&quot;₱&quot;#,##0.00"/></numFmts>'
            . '<fonts count="' . count($fonts) . '">' . implode('', $fonts) . '</fonts>'
            . "<fills count=\"7\">{$fills}</fills>"
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left/><right/><top/><bottom style="thin"><color rgb="FFD0D7CC"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . count(self::STYLES) . "\">{$xfs}</cellXfs>"
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    /** Sheet 1: the month of $to and the two before it, side by side like the template's Year 1/2/3. */
    private static function statement(string $to, string $preparedBy): string
    {
        $end = new \DateTimeImmutable($to);
        $first = $end->modify('first day of this month');
        $months = [$first->modify('-2 months'), $first->modify('-1 month'), $first];
        $heads = array_map(fn ($m) => $m->format('M Y'), $months);
        if ($end->format('t') !== $end->format('j')) {
            $heads[2] = $end->format('M 1–j, Y'); // month to date
        }
        $index = array_flip(array_map(fn ($m) => $m->format('Y-m'), $months));

        $received = $spent = [];
        $charged = $unpaid = [0.0, 0.0, 0.0];
        foreach (LedgerBuilder::rows($months[0]->format('Y-m-d 00:00:00'), "{$to} 23:59:59") as $r) {
            $i = $index[substr((string) $r['at'], 0, 7)] ?? null;
            if ($i === null) {
                continue;
            }
            $amt = (float) $r['amount'];
            if ($r['type'] === 'Payment received') {
                $k = self::METHODS[$r['category']] ?? (string) $r['category'];
                $received[$k] ??= [0.0, 0.0, 0.0];
                $received[$k][$i] += $amt;
            } elseif ($r['type'] === 'Expense') {
                $k = (string) $r['category'];
                $spent[$k] ??= [0.0, 0.0, 0.0];
                $spent[$k][$i] += $amt;
            } else {
                $charged[$i] += $amt;
                if ($r['status'] === 'unpaid') {
                    $unpaid[$i] += $amt;
                }
            }
        }
        ksort($received, SORT_STRING);
        ksort($spent, SORT_STRING);

        $g = self::grid();
        $g->put([self::c('RJM Boardinghouse', 'title'), self::c('Financial Statement', 'title', null, 2)], 34);
        $g->put([self::c('Prepared by: ' . $preparedBy, 'titleSub'), self::c('Basis: cash received and spent', 'titleSub', null, 2)], 24);
        $g->put([self::c('Date Created:'), self::c('Period Covered:', 'default', null, 2)]);
        $g->put([self::c(date('Y-m-d H:i')), self::c($months[0]->format('M j, Y') . ' to ' . $end->format('M j, Y'), 'default', null, 2)]);
        $g->put([]);
        $g->put([self::c('Financial Statement', 'band', null, 3)]);

        // One section: dark header with the month labels, a light sub-band, the lines, a SUM subtotal. Returns the subtotal's row.
        $section = function (string $title, string $sub, array $lines, string $totalLabel) use ($g, $heads): int {
            $g->put(array_merge([self::c($title, 'band')], array_map(fn ($h) => self::c($h, 'bandNum'), $heads)));
            $g->put([self::c($sub, 'sub'), self::c(null, 'sub'), self::c(null, 'sub'), self::c(null, 'sub')]);
            $top = $g->last() + 1;
            foreach ($lines ?: ['None recorded' => [0.0, 0.0, 0.0]] as $label => $vals) {
                $g->put(array_merge([self::c((string) $label, 'line')], array_map(fn ($v) => self::c($v, 'money'), $vals)));
            }
            $bottom = $g->last();
            $sums = [];
            foreach ([0, 1, 2] as $i) {
                $col = self::col($i + 1);
                $sums[] = self::c(array_sum(array_column($lines, $i)), 'totalMoney', "SUM({$col}{$top}:{$col}{$bottom})");
            }
            $row = $g->put(array_merge([self::c($totalLabel, 'total')], $sums));
            $g->put([]);
            return $row;
        };

        $in = $section('Cash Received', 'Payments approved, by method', $received, 'Total Cash Received');
        $out = $section('Operating Expenses', 'Expenses recorded, by category', $spent, 'Total Expenses');
        $settled = array_map(fn ($c, $u) => -($c - $u), $charged, $unpaid);
        $section('Receivables', 'Penalties (money owed, not cash)', ['Penalties charged' => $charged, 'Less: paid or waived' => $settled], 'Total Receivable (unpaid penalties)');

        $net = [];
        foreach ([0, 1, 2] as $i) {
            $col = self::col($i + 1);
            $net[] = self::c(array_sum(array_column($received, $i)) - array_sum(array_column($spent, $i)), 'grandMoney', "{$col}{$in}-{$col}{$out}");
        }
        $g->put(array_merge([self::c('Net Cash Flow (received − expenses)', 'grand')], $net), 22);
        $g->put([]);
        $g->put([self::c('Receivables are owed, not collected, so they do not change the net. Payments are dated by approval, like the dashboard.', 'note', null, 3)]);

        return self::sheet($g, [45, 19, 19, 19], 0, false);
    }

    /** Sheet 2: every transaction in from..to, the same columns as the CSV, with a live running balance. */
    private static function ledger(string $from, string $to): string
    {
        $period = date('M j, Y', strtotime($from)) . ' to ' . date('M j, Y', strtotime($to));
        $g = self::grid();
        $g->put([self::c('RJM Boardinghouse - General Ledger', 'title', null, 10)], 30);
        $g->put([self::c('Period: ' . $period . '   ·   Debit = money received, Credit = money spent. Penalties are receivables and do not move the balance.', 'titleSub', null, 10)], 22);
        $g->put([]);
        $headers = ['Date', 'Reference No.', 'Type', 'Category', 'Particulars', 'Name', 'Debit (Cash In)', 'Credit (Cash Out)', 'Running Balance', 'Accrued Penalty', 'Status'];
        $g->put(array_map(fn ($h, $i) => self::c($h, $i >= 6 && $i <= 9 ? 'bandNum' : 'band'), $headers, array_keys($headers)), 32);

        $top = $g->last() + 1;
        $in = $spent = $receivable = 0.0;
        foreach (LedgerBuilder::rows("{$from} 00:00:00", "{$to} 23:59:59") as $r) {
            $amt = (float) $r['amount'];
            $debit = $credit = $accrued = null;
            if ($r['type'] === 'Payment received') {
                $debit = $amt;
                $in += $amt;
            } elseif ($r['type'] === 'Expense') {
                $credit = $amt;
                $spent += $amt;
            } else {
                $accrued = $amt;
                if ($r['status'] === 'unpaid') {
                    $receivable += $amt;
                }
            }
            $n = $g->last() + 1;
            $g->put([
                self::c(date('Y-m-d H:i', strtotime($r['at'])), 'line'),
                self::c((string) $r['ref'], 'line'),
                self::c($r['type'], 'line'),
                self::c($r['type'] === 'Payment received' ? (self::METHODS[$r['category']] ?? (string) $r['category']) : (string) $r['category'], 'line'),
                self::c((string) $r['description'], 'line'),
                self::c((string) $r['user_name'], 'line'),
                self::c($debit, 'money'),
                self::c($credit, 'money'),
                self::c($in - $spent, 'money', $n === $top ? "G{$n}-H{$n}" : 'I' . ($n - 1) . "+G{$n}-H{$n}"),
                self::c($accrued, 'money'),
                self::c((string) $r['status'], 'line'),
            ]);
        }
        $last = $g->last();
        $has = $last >= $top;
        $total = fn (string $label, float $v, ?string $f, string $style = 'total') => $g->put([self::c($label, $style, null, 5), self::c($v, $style . 'Money', $f)]);
        $g->put([]);
        $paid = $total('Total Payments Received', $in, $has ? "SUM(G{$top}:G{$last})" : null);
        $total('Total Expenses', $spent, $has ? "SUM(H{$top}:H{$last})" : null);
        $total('Net (payments − expenses)', $in - $spent, 'G' . $paid . '-G' . ($paid + 1), 'grand');
        $g->put([]);
        $total('Penalties Charged (receivable, unpaid only)', $receivable, $has ? "SUMIFS(J{$top}:J{$last},K{$top}:K{$last},\"unpaid\")" : null);

        return self::sheet($g, [17, 15, 16, 15, 36, 22, 16, 17, 17, 16, 13], 4, true);
    }

    /** Collects one sheet's rows; put() lays cells out from column A and returns the row number written. */
    private static function grid(): object
    {
        return new class {
            public array $rows = [];
            public array $merges = [];

            public function put(array $cells, int $height = 0): int
            {
                $r = count($this->rows) + 1;
                $out = [];
                $col = 0;
                foreach ($cells as [$value, $style, $formula, $merge]) {
                    $out[$col] = [$value, $style, $formula];
                    for ($k = 1; $k <= $merge; $k++) {
                        $out[$col + $k] = [null, $style, null]; // so fills and borders cover the whole merged area
                    }
                    if ($merge) {
                        $this->merges[] = LedgerWorkbook::col($col) . $r . ':' . LedgerWorkbook::col($col + $merge) . $r;
                    }
                    $col += $merge + 1;
                }
                $this->rows[$r] = [$height, $out];
                return $r;
            }

            public function last(): int
            {
                return count($this->rows);
            }
        };
    }

    /** A cell for grid()->put(): numbers become number cells, text becomes text, null is an empty styled cell. */
    private static function c(string|int|float|null $value, string $style = 'default', ?string $formula = null, int $mergeAcross = 0): array
    {
        return [$value, $style, $formula, $mergeAcross];
    }

    /** 0 => A, 1 => B … (sheets here never pass column Z). */
    public static function col(int $i): string
    {
        return chr(65 + $i);
    }

    private static function sheet(object $g, array $widths, int $freezeRows, bool $landscape): string
    {
        $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><sheetViews><sheetView workbookViewId="0" showGridLines="0">'
            . ($freezeRows ? "<pane ySplit=\"{$freezeRows}\" topLeftCell=\"A" . ($freezeRows + 1) . '" activePane="bottomLeft" state="frozen"/>' : '')
            . '</sheetView></sheetViews><cols>';
        foreach ($widths as $i => $w) {
            $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . "\" width=\"{$w}\" customWidth=\"1\"/>";
        }
        $xml .= '</cols><sheetData>';
        $styleIds = array_flip(array_keys(self::STYLES));
        foreach ($g->rows as $r => [$height, $cells]) {
            $xml .= "<row r=\"{$r}\"" . ($height ? " ht=\"{$height}\" customHeight=\"1\"" : '') . '>';
            foreach ($cells as $i => [$value, $style, $formula]) {
                $attrs = 'r="' . self::col($i) . $r . '" s="' . $styleIds[$style] . '"';
                $f = $formula !== null ? '<f>' . self::x($formula) . '</f>' : '';
                if (is_string($value) && $value !== '') {
                    $xml .= "<c {$attrs} t=\"inlineStr\"><is><t xml:space=\"preserve\">" . self::x($value) . '</t></is></c>';
                } elseif (is_int($value) || is_float($value)) {
                    $xml .= "<c {$attrs}>{$f}<v>" . sprintf('%.2F', $value) . '</v></c>';
                } else {
                    $xml .= "<c {$attrs}>{$f}</c>";
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';
        if ($g->merges) {
            $xml .= '<mergeCells count="' . count($g->merges) . '">' . implode('', array_map(fn ($m) => "<mergeCell ref=\"{$m}\"/>", $g->merges)) . '</mergeCells>';
        }
        return $xml . '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
            . '<pageSetup orientation="' . ($landscape ? 'landscape' : 'portrait') . '" fitToWidth="1" fitToHeight="0"/></worksheet>';
    }

    /** XML-escape, and drop control characters that would make the whole file unreadable. */
    private static function x(string $s): string
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s), ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** name => contents as a zip archive. ponytail: entries are stored, not deflated (no zip extension here); the file is a few dozen KB. */
    private static function zip(array $files): string
    {
        $t = getdate();
        $time = ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2);
        $date = (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];
        $data = $dir = '';
        foreach ($files as $name => $body) {
            $crc = crc32($body);
            $len = strlen($body);
            $dir .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $time, $date, $crc, $len, $len, strlen($name), 0, 0, 0, 0, 0, strlen($data)) . $name;
            $data .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $time, $date, $crc, $len, $len, strlen($name), 0) . $name . $body;
        }
        return $data . $dir . pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($dir), strlen($data), 0);
    }
}
