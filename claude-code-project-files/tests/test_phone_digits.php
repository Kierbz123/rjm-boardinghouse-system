<?php
/** Phone numbers are digits only, one rule for every form. Run via tests/run.php. */

require_once __DIR__ . '/../src/autoload.php';

use App\Support\Phone;

$failures = 0;
function check(bool $ok, string $label): void
{
    global $failures;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . "\n";
    $failures += $ok ? 0 : 1;
}

// digits(): everything a browser or a hand-typed value might carry is stripped.
check(Phone::digits('+639676355474') === '639676355474', 'a leading + is dropped');
check(Phone::digits('0917 123 4567') === '09171234567', 'spaces are dropped');
check(Phone::digits('(02) 8123-4567') === '0281234567', 'brackets and dashes are dropped');
check(Phone::digits('0917abc1234') === '09171234', 'letters are dropped');
check(Phone::digits('') === '' && Phone::digits(null) === '', 'nothing typed stays empty');
check(Phone::digits(['09171234567']) === '', 'an array never becomes a number');

// problem(): judges what was submitted. Blank clears an optional number; 7-15 digits otherwise.
check(Phone::problem('') === null && Phone::problem('   ') === null, 'an optional number may be left blank');
check(Phone::problem('', true) !== null, 'a required number may not be left blank');
check(Phone::problem('09171234567') === null, '11-digit PH mobile accepted');
check(Phone::problem('8123456') === null, '7 digits is the shortest accepted');
check(Phone::problem('812345') !== null, '6 digits is too short');
check(Phone::problem('123456789012345') === null, '15 digits is the longest accepted');
check(Phone::problem('1234567890123456') !== null, '16 digits is too long');
check(Phone::problem('+63 917 123 4567') === null, 'a formatted number is accepted and normalised, not refused');

// The hole this closes: a POST that skipped the browser's stripping must not silently blank a
// number the resident still has, so a value with no digits in it is refused, never read as blank.
check(Phone::problem('call me') !== null, 'letters are refused, not read as an empty field');
check(Phone::problem('----') !== null, 'punctuation alone is refused, not read as an empty field');
check(Phone::problem(['09171234567']) !== null, 'an array is refused outright');

// A form with two numbers on it has to say which one it means.
check(str_contains((string) Phone::problem('0918'), 'the contact number'), 'the message names the contact number');
check(str_contains((string) Phone::problem('0918', false, 'emergency contact number'), 'the emergency contact number'),
    'the message names the emergency contact number when that is the field at fault');

// Every phone field in the views is wired to the shared handler and server rule.
$views = [
    'admin/boarders.php' => ['contact_number'],
    'admin/boarders_directory.php' => ['contact_number', 'emergency_contact_number'],
    'admin/boarder_profile.php' => ['contact_number', 'emergency_contact_number'],
    'shared/profile.php' => ['contact_number', 'emergency_contact_number'],
    'admin/staff.php' => ['phone'],
    'admin/inquiry-center.php' => ['phone'],
    'shared/landing.php' => ['phone'],
];
foreach ($views as $view => $fields) {
    $html = (string) file_get_contents(__DIR__ . '/../src/Views/' . $view);
    foreach ($fields as $field) {
        preg_match_all('/<input[^>]*name="' . $field . '"[^>]*>/s', $html, $m);
        // The multi-line markup in shared/profile.php puts name= after type=, so widen the match.
        if ($m[0] === []) {
            preg_match_all('/<input\b(?:(?!<input)[\s\S])*?name="' . $field . '"(?:(?!<input)[\s\S])*?>/s', $html, $m);
        }
        check($m[0] !== [], "{$view}: {$field} input found");
        foreach ($m[0] as $tag) {
            check((bool) preg_match('/data-digits="\d+"/', $tag), "{$view}: {$field} strips non-digits and carries a digit cap");
            check(str_contains($tag, 'inputmode="numeric"'), "{$view}: {$field} opens a numeric keypad");
            check(!preg_match('/type="(?!tel)/', $tag), "{$view}: {$field} is type=tel, not free text");
            // maxlength counts the raw string, so the browser would cut "+63 917 123 4567" to
            // "+63 917 123" before the digits were stripped and quietly lose the last digits.
            // The cap belongs in data-digits, which counts digits. See app.js.
            check(!preg_match('/(?:max|min)length=/', $tag), "{$view}: {$field} caps digits, not raw characters");
        }
    }
}

// No form may keep its own private phone rule: the old regexes are gone.
foreach (['Controllers/BoarderController.php', 'Controllers/ProfileController.php', 'Controllers/StaffController.php', 'Models/Inquiry.php'] as $src) {
    $code = (string) file_get_contents(__DIR__ . '/../src/' . $src);
    check(!str_contains($code, '[0-9+()'), "{$src}: no hand-rolled phone regex left");
    check(str_contains($code, 'Phone::digits'), "{$src}: normalises through Phone::digits");
}

echo $failures === 0 ? "All phone checks passed.\n" : "{$failures} phone check(s) failed.\n";
exit($failures === 0 ? 0 : 1);
