<?php
/**
 * READ-ONLY report of residents whose dates or rent look wrong. Changes nothing: it only SELECTs.
 * Run it before cleaning anything up, and read what it says first.
 *
 *   php bin/find-absurd-rent.php                    uses the property_opening_date setting
 *   php bin/find-absurd-rent.php --opening=2026-01-01   for a database that predates the settings table
 *
 * Lists a resident when:  the move-in date is before the opening date or more than 12 months ahead;
 * the move-out date is before the move-in date or more than 5 years ahead; they have more than 120
 * rent cycles; or they owe more than P1,000,000. Exit code 1 when anyone is listed, 0 when nobody is.
 */

require_once __DIR__ . '/../src/autoload.php';

use App\Database;

$opening = '2026-01-01';
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--opening=(\d{4}-\d{2}-\d{2})$/', $arg, $m)) {
        $opening = $m[1];
    } else {
        fwrite(STDERR, "Unknown option {$arg}\n");
        exit(2);
    }
}
$pdo = Database::getConnection();
try {
    $fromSetting = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'property_opening_date'")->fetchColumn();
    if ($fromSetting) {
        $opening = (string) $fromSetting;
    }
} catch (PDOException $e) {
    // no settings table yet: use --opening or the default
}
$latestIn = (string) $pdo->query('SELECT DATE_ADD(CURDATE(), INTERVAL 12 MONTH)')->fetchColumn();
$latestOut = (string) $pdo->query('SELECT DATE_ADD(CURDATE(), INTERVAL 5 YEAR)')->fetchColumn();

$sql = "SELECT bp.user_id, u.email, u.name, bp.move_in_date, bp.move_out_date, bp.outstanding_balance,
               (SELECT COUNT(*) FROM rent_charges rc WHERE rc.boarder_id = bp.user_id) AS cycles
        FROM boarder_profiles bp JOIN users u ON u.id = bp.user_id
        ORDER BY bp.user_id";
$found = [];
foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $why = [];
    if ($r['move_in_date'] !== null && ($r['move_in_date'] < $opening || $r['move_in_date'] > $latestIn)) {
        $why[] = "move-in {$r['move_in_date']} is outside {$opening}..{$latestIn}";
    }
    if ($r['move_out_date'] !== null && (($r['move_in_date'] !== null && $r['move_out_date'] < $r['move_in_date']) || $r['move_out_date'] > $latestOut)) {
        $why[] = "move-out {$r['move_out_date']} is before the move-in or after {$latestOut}";
    }
    if ((int) $r['cycles'] > 120) {
        $why[] = "{$r['cycles']} rent cycles on record (more than 120)";
    }
    if ((float) $r['outstanding_balance'] > 1000000) {
        $why[] = 'owes P' . number_format((float) $r['outstanding_balance'], 2);
    }
    if ($why) {
        $found[] = sprintf("#%d %s <%s>: %s", $r['user_id'], $r['name'], $r['email'], implode('; ', $why));
    }
}

echo $found ? implode("\n", $found) . "\n" : "No resident has absurd dates or rent.\n";
echo '(checked ' . $pdo->query('SELECT COUNT(*) FROM boarder_profiles')->fetchColumn() . " resident profiles; nothing was changed)\n";
exit($found ? 1 : 0);
