<?php
/**
 * UI refinement: icons come from App\Support\Icon only. Every icon name a view or the sidebar asks
 * for must exist (an unknown name silently draws a plain circle), and no view or script uses emoji.
 * Reads files only; writes nothing.
 */
require __DIR__ . '/lib.php';
require_once dirname(__DIR__) . '/src/autoload.php';

$project = dirname(__DIR__);
$known = (new ReflectionClass(\App\Support\Icon::class))->getConstants();
$names = array_merge(array_keys($known['PATHS']), array_keys($known['ALIASES']));

section('Icon names exist');
$unknown = [];
foreach (glob("$project/src/Views/*/*.php") as $file) {
    preg_match_all("/Icon::svg\\(\\s*'([a-z-]+)'/", file_get_contents($file), $m);
    foreach (array_diff(array_unique($m[1]), $names) as $name) {
        $unknown[] = basename($file) . ": $name";
    }
}
check($unknown === [], 'every Icon::svg() name in the views is a known icon', implode(', ', $unknown));

$sidebarIcons = [];
foreach (['admin', 'staff', 'boarder'] as $role) {
    foreach (\App\Support\NavRegistry::sidebar($role) as $items) {
        $sidebarIcons = array_merge($sidebarIcons, array_column($items, 'icon'));
    }
}
$missing = array_diff(array_unique($sidebarIcons), $names);
check($missing === [], 'every sidebar icon is a known icon', implode(', ', $missing));
check(str_contains(\App\Support\Icon::svg('bell'), 'aria-hidden="true"'), 'icons are hidden from screen readers (the text beside them names the action)');

section('No emoji used as icons');
$withEmoji = [];
foreach (array_merge(glob("$project/src/Views/*/*.php"), glob("$project/public/assets/js/*.js")) as $file) {
    if (preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', file_get_contents($file))) {
        $withEmoji[] = basename($file);
    }
}
check($withEmoji === [], 'views and scripts contain no emoji', implode(', ', $withEmoji));
