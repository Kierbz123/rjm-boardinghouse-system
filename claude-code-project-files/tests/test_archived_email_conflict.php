<?php
/**
 * Add Boarder with an ARCHIVED resident's e-mail (24 Oct 2026). users.email stays unique and the
 * default resident list hides archived rows, so a bare "That email is already in use." is a dead end.
 * The refusal must name the archived resident, and the page must offer Restore (the existing route).
 */
require __DIR__ . '/lib.php';

$admin = seededClient('admin');

section('Setup: a resident who is then archived');
$tag = uniq();
$name = "Archived Person {$tag}";
$email = "archived{$tag}@t.test";
$admin->post('/admin/boarders', ['name' => $name, 'email' => $email, 'password' => 'Tmp-resident-river-lamp']);
$id = (int) q1('SELECT id FROM users WHERE email = ?', [$email]);
check($id > 0, 'the resident was created');
$admin->post("/admin/boarders/{$id}/delete", ['reason' => 'archived-email conflict test']);
check(q1('SELECT status FROM users WHERE id = ?', [$id]) === 'archived', 'the resident is archived');

section('Re-adding the archived e-mail names the account and offers Restore');
$r = $admin->post('/admin/boarders', ['name' => 'Someone Else', 'email' => strtoupper($email), 'password' => 'Tmp-cobalt-river-fern']);
$page = $admin->get((string) $r->loc, true);
$text = html_entity_decode(preg_replace('/\s+/', ' ', strip_tags((string) preg_replace('#<(script|style)\b.*?</\1>#is', ' ', $page->body))), ENT_QUOTES);
check(stripos($text, "That email belongs to {$name}, an archived resident") !== false, 'the refusal names the archived resident',
    preg_match('/(That email[^.]*\.)/i', $text, $m) ? "flash was: {$m[1]}" : 'no email message on the page');
check(stripos($text, 'That email is already in use.') === false, 'not the bare "already in use" message');
check($page->has("action=\"/admin/boarders/{$id}/restore\"") && stripos($text, 'Restore resident') !== false, 'the page offers a Restore resident button for that account');
check($page->has("href=\"/admin/boarders/{$id}\""), 'the page links to their profile');
check((int) q1('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) === 1, 'no second account was created');

section('Restore, then the same e-mail is an ordinary active conflict');
$admin->post("/admin/boarders/{$id}/restore");
check(q1('SELECT status FROM users WHERE id = ?', [$id]) === 'active', 'Restore re-enables the archived resident');
$r = $admin->post('/admin/boarders', ['name' => 'Someone Else', 'email' => $email, 'password' => 'Tmp-cobalt-river-fern']);
check(flashHas($admin, $r, 'That email is already in use.'), 'an active holder gets the plain "already in use" message');
check((int) q1('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) === 1, 'still exactly one account with that e-mail');
