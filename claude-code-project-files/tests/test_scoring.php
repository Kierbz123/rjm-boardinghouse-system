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

echo $failures === 0 ? "All scoring checks passed.\n" : "{$failures} scoring check(s) failed.\n";
exit($failures === 0 ? 0 : 1);
