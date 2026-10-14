<?php
/**
 * An inquiry notification's "View Details" must open the stored inquiry (admin only), and the
 * admin can move it through new -> contacted -> closed.
 */
require __DIR__ . '/lib.php';

$admin = seededClient('admin');
$staff = seededClient('staff');
$anon = new Client('anon');
$resident = makeResident($admin)['client'];

section('Inquiry notification links to the inquiry');
$tag = uniq();
q('DELETE FROM inquiries WHERE ip_address = ?', ['127.0.0.1']); // the per-IP rate limit would refuse ours
q("DELETE FROM notifications WHERE entity_type = 'inquiry' AND entity_id NOT IN (SELECT id FROM inquiries)");
$anon->req('POST', '/inquire', ['name' => "Kierby {$tag}", 'phone' => '09676355474', 'email' => "k{$tag}@x.test",
    'room_type' => 'Solo Executive Room', 'move_in_date' => '2026-12-01', 'message' => "Is a quiet room free? {$tag}", 'website' => '']);
$id = (int) q1('SELECT id FROM inquiries WHERE name = ?', ["Kierby {$tag}"]);
check($id > 0, 'the public inquiry is stored');
$adminId = (int) q1("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
$nid = (int) q1("SELECT id FROM notifications WHERE user_id = ? AND type = 'inquiry' ORDER BY id DESC LIMIT 1", [$adminId]);
check(q1('SELECT action_url FROM notifications WHERE id = ?', [$nid]) === "/admin/inquiries/{$id}", 'View Details points at the inquiry itself');
check(q1('SELECT entity_type FROM notifications WHERE id = ?', [$nid]) === 'inquiry' && (int) q1('SELECT entity_id FROM notifications WHERE id = ?', [$nid]) === $id, 'the notification records which inquiry it is about');

section('Detail page');
// Another recipient's copy of the same notification (the seed has one admin, so the staff user stands in).
$staffId = (int) q1("SELECT id FROM users WHERE role = 'staff' ORDER BY id LIMIT 1");
q("INSERT INTO notifications (user_id, type, message, action_url, entity_type, entity_id) VALUES (?, 'inquiry', 'copy', ?, 'inquiry', ?)", [$staffId, "/admin/inquiries/{$id}", $id]);
$otherCopy = (int) q1('SELECT MAX(id) FROM notifications');
$page = $admin->get("/admin/inquiries/{$id}");
check($page->code === 200, 'the admin opens the inquiry');
check($page->has("Is a quiet room free? {$tag}") && $page->has("k{$tag}@x.test") && $page->has('09676355474') && $page->has('Solo Executive Room') && $page->has('2026'), 'the full message, email, phone, room and move-in date are shown');
check((int) q1('SELECT is_read FROM notifications WHERE id = ?', [$nid]) === 1, 'opening it marks the notification read');
check((int) q1('SELECT is_read FROM notifications WHERE id = ?', [$otherCopy]) === 0, "other recipients' copies stay unread");
q('DELETE FROM notifications WHERE id = ?', [$otherCopy]);
check($staff->get("/admin/inquiries/{$id}")->code === 403, "staff cannot open it (prospects' contact details are admin-only)");
check($resident->get("/admin/inquiries/{$id}")->code === 403, 'a resident cannot open it');
check($anon->get("/admin/inquiries/{$id}")->code !== 200, 'a visitor who is not signed in cannot open it');
check($admin->get('/admin/inquiries/999999')->code === 404, 'an unknown inquiry is a 404');

section('Status');
$admin->post("/admin/inquiries/{$id}/status", ['status' => 'contacted']);
check(q1('SELECT status FROM inquiries WHERE id = ?', [$id]) === 'contacted', 'the admin marks it contacted');
$admin->post("/admin/inquiries/{$id}/status", ['status' => 'bogus']);
check(q1('SELECT status FROM inquiries WHERE id = ?', [$id]) === 'contacted', 'an invalid status is ignored');
$admin->post("/admin/inquiries/{$id}/status", ['status' => 'closed'], false);
check(q1('SELECT status FROM inquiries WHERE id = ?', [$id]) === 'contacted', 'a request without a CSRF token changes nothing');
check($staff->post("/admin/inquiries/{$id}/status", ['status' => 'closed'])->code === 403 && q1('SELECT status FROM inquiries WHERE id = ?', [$id]) === 'contacted', 'staff cannot change the status');
$admin->post("/admin/inquiries/{$id}/status", ['status' => 'closed']);
check(q1('SELECT status FROM inquiries WHERE id = ?', [$id]) === 'closed', 'the admin closes it');

section('Inquiry center lists recent inquiries');
check($admin->get('/admin/inquiry-center')->has("/admin/inquiries/{$id}"), 'the admin can reach the inquiry without the notification');
$staffCenter = $staff->get('/admin/inquiry-center');
check(!$staffCenter->has('Recent inquiries') && !$staffCenter->has("/admin/inquiries/{$id}"), 'staff are not shown the list');

section('Walk-in inquiries link too');
$admin->post('/admin/inquiry-center/submit', ['name' => "Walk {$tag}", 'phone' => '09171234567', 'room_type' => 'Quad Bedspace Sanctuary']);
$wid = (int) q1('SELECT id FROM inquiries WHERE name = ?', ["Walk {$tag}"]);
check($wid > 0 && (int) q1("SELECT COUNT(*) FROM notifications WHERE type = 'inquiry' AND action_url = ?", ["/admin/inquiries/{$wid}"]) >= 1, 'a staff-logged inquiry notifies admins with a working link');
