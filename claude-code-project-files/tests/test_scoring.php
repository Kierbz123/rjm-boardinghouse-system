<?php
/** Maintenance priority scoring: whole-word keywords, tiers, one shared list. Run via tests/run.php. */

require_once __DIR__ . '/../src/autoload.php';

use App\Services\ScoringClient;

$failures = 0;
function check(bool $ok, string $label): void
{
    global $failures;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . "\n";
    $failures += $ok ? 0 : 1;
}

$s = fn (string $text, string $cat = 'other', bool $media = false) => ScoringClient::score($text, $cat, $media);

check($s('The carpet near my bed is a bit dirty')['matches'] === [], '"carpet" no longer matches "car"');
check($s('My laptop firewall is fine, door hinge squeaky')['matched_keywords'] === ['squeaky'], '"firewall" no longer matches "fire"');
check(in_array('leak', $s('Water is leaking from the pipe', 'plumbing')['matched_keywords'], true), '"leaking" matches "leak"');
check(in_array('gas leak', $s("There's a  gas   leak in the kitchen")['matched_keywords'], true), 'multi-word keywords tolerate extra spaces');
check($s('Fire and smoke from the outlet', 'electrical')['tier'] === 'critical', 'fire + smoke on electrical = critical');
check($s('Outlet sparking', 'electrical')['tier'] === 'high', 'sparking outlet = high');
check($s('Sink is clogged', 'plumbing')['tier'] === 'medium', 'clogged sink = medium');
check($s('Small paint scratch on the wall')['tier'] === 'low', 'cosmetic = low');
check($s('x', 'other', true)['score'] === 15.0, 'photo/video adds 10 to the base');
check($s(str_repeat('fire smoke explosion ', 50), 'structural')['score'] === 100.0, 'score caps at 100');

// Time-decay: +2 points per full day waiting, capped at +30, never above 100.
$d = fn (float $score, int $hours) => ScoringClient::withDecay($score, $hours);
check($d(13.0, 0) === ['score' => 13.0, 'tier' => 'low'], 'no wait, no change');
check($d(13.0, 47)['score'] === 15.0, 'only full days count (47 h = 1 day = +2)');
check($d(13.0, 8 * 24)['tier'] === 'medium', 'a low request waiting 8 days climbs to medium');
check($d(30.0, 13 * 24)['tier'] === 'high', 'a medium request waiting 13 days climbs to high');
check($d(60.0, 100 * 24)['score'] === 90.0 && $d(60.0, 100 * 24)['tier'] === 'critical', 'decay is capped at +30');
check($d(95.0, 100 * 24)['score'] === 100.0, 'decay never passes 100');

echo $failures === 0 ? "All scoring checks passed.\n" : "{$failures} scoring check(s) failed.\n";
exit($failures === 0 ? 0 : 1);
