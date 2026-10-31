<?php
/**
 * The record-payment form lists residents who have moved in. A resident still "pending" is left out on purpose (rent is
 * charged from move-in, PaymentRecorder refuses them), but the page must say who is waiting and link to their profile
 * instead of showing a silently empty dropdown.
 */
require __DIR__ . '/lib.php';

$admin = seededClient('admin');
$room = makeRoom($admin, 1, 3000, 1);
$r = makeResident($admin, $room['beds'][0], 5);
$id = (int) $r['id'];
$option = '<option value="' . $id . '"';

section('A resident who has moved in is listed');
q('UPDATE boarder_profiles SET status = "active" WHERE user_id = ?', [$id]);
$body = $admin->get('/admin/payments/record')->body;
check(str_contains($body, $option), 'an active resident is in the Resident dropdown');
check(!str_contains($body, "/admin/boarders/{$id}\""), 'and is not reported as waiting to move in');

section('A pending resident is explained, not hidden');
q('UPDATE boarder_profiles SET status = "pending" WHERE user_id = ?', [$id]);
$body = $admin->get('/admin/payments/record')->body;
check(!str_contains($body, $option), 'a pending resident is not selectable');
check(str_contains($body, 'data-testid="record-pending"') && str_contains($body, "/admin/boarders/{$id}\""), 'the page names them and links to their profile');
