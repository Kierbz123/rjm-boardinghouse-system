<?php
$pageTitle = 'Inquiry Management Center';
ob_start();
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 py-6 space-y-6">
    <!-- Page Banner -->
    <div class="page-banner">
        <div>
            <h1>Inquiry Management Center</h1>
            <p>Submit room inquiries and manage system notifications</p>
        </div>
        <span class="badge badge-success" style="background:rgba(255,255,255,0.12); color:#8cc2a2; border:1px solid rgba(255,255,255,0.18); padding:0.4rem 0.85rem; font-size:0.75rem;">
            <span style="width:0.45rem;height:0.45rem;border-radius:50%;background:#5fae84;display:inline-block;margin-right:0.4rem;"></span>
            Staff Portal Active
        </span>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="reveal-card card p-4 metric-accent-primary">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Inquiries</span>
                <?= \App\Support\Icon::svg('clipboard', 18) ?>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= (int) $totalInquiries ?></p>
            <p class="text-caption text-neutral-400 mt-1">All-time submissions</p>
        </div>

        <div class="reveal-card card p-4 metric-accent-success">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Today's Inquiries</span>
                <?= \App\Support\Icon::svg('calendar', 18) ?>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= (int) $todayInquiries ?></p>
            <p class="text-caption text-emerald-700 mt-1 font-medium">New submissions today</p>
        </div>

        <div class="reveal-card card p-4 metric-accent-warning">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Unread Notifications</span>
                <?= \App\Support\Icon::svg('bell', 18) ?>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= (int) $unreadCount ?></p>
            <p class="text-caption text-neutral-400 mt-1">Requires attention</p>
        </div>

        <div class="reveal-card card p-4 metric-accent-info">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Staff Member</span>
                <?= \App\Support\Icon::svg('user', 18) ?>
            </div>
            <p class="text-lg font-bold text-neutral-900 mt-1"><?= htmlspecialchars($userName) ?></p>
            <p class="text-caption text-neutral-400 mt-1"><?= htmlspecialchars(ucfirst($userRole)) ?> access</p>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Inquiry Form (2/3 width) -->
        <div class="lg:col-span-2">
            <div class="section-header">
                <h2>Submit Room Inquiry</h2>
                <span class="text-caption text-neutral-400">Staff-assisted submission</span>
            </div>

            <?php if ($inquirySuccess): ?>
                <div class="contact-alert success" style="display: block; padding: 1rem; border-radius: 0.75rem; font-size: 0.875rem; margin-bottom: 1rem; background: #eef6f1; border: 1px solid #d7ebdf; color: #245a3f;">
                    <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 1rem; height: 1rem; display: inline-block; vertical-align: middle; margin-right: 0.5rem;"><polyline points="20 6 9 17 4 12"/></svg>
                    <span><?= htmlspecialchars($inquirySuccess) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($inquiryError): ?>
                <div class="contact-alert error" style="display: block; padding: 1rem; border-radius: 0.75rem; font-size: 0.875rem; margin-bottom: 1rem; background: #fdf1f0; border: 1px solid #f9dcd9; color: #7a1b14;">
                    <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 1rem; height: 1rem; display: inline-block; vertical-align: middle; margin-right: 0.5rem;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span><?= htmlspecialchars($inquiryError) ?></span>
                </div>
            <?php endif; ?>

            <div class="form-card">
                <form id="staff-inquiry-form" action="/admin/inquiry-center/submit" method="POST" class="inquiry-form-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Support\Csrf::token()) ?>">
                    
                    <div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <label for="inq-name" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #3b3a38; margin-bottom: 0.35rem;">Customer Full Name <span class="req" style="color: #b42318;">*</span></label>
                            <input type="text" id="inq-name" name="name" required placeholder="e.g. Maria Santos" class="inq-input" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d4d2ce; border-radius: 0.5rem; font-size: 0.875rem; transition: border-color 0.15s ease;">
                        </div>
                        <div class="form-group">
                            <label for="inq-phone" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #3b3a38; margin-bottom: 0.35rem;">Contact Phone / Mobile <span class="req" style="color: #b42318;">*</span></label>
                            <input type="tel" id="inq-phone" name="phone" required placeholder="e.g. 0917 123 4567" class="inq-input" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d4d2ce; border-radius: 0.5rem; font-size: 0.875rem; transition: border-color 0.15s ease;">
                        </div>
                    </div>

                    <div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <label for="inq-email" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #3b3a38; margin-bottom: 0.35rem;">Email Address (Optional)</label>
                            <input type="email" id="inq-email" name="email" placeholder="e.g. maria@gmail.com" class="inq-input" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d4d2ce; border-radius: 0.5rem; font-size: 0.875rem; transition: border-color 0.15s ease;">
                        </div>
                        <div class="form-group">
                            <label for="inq-room" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #3b3a38; margin-bottom: 0.35rem;">Preferred Room Tier</label>
                            <select id="inq-room" name="room_type" class="inq-input inq-select" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d4d2ce; border-radius: 0.5rem; font-size: 0.875rem; transition: border-color 0.15s ease; background-color: white;">
                                <?php foreach ($roomTypes as $t): ?>
                                    <option value="<?= htmlspecialchars($t['name']) ?>" <?= $t['is_popular'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars(\App\Models\RoomType::priceLabel($t)) ?>)</option>
                                <?php endforeach; ?>
                                <option value="General Inquiry / Visit Booking">General Inquiry / Physical Viewing</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label for="inq-date" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #3b3a38; margin-bottom: 0.35rem;">Target Move-in / Viewing Date</label>
                        <input type="date" id="inq-date" name="move_in_date" class="inq-input" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d4d2ce; border-radius: 0.5rem; font-size: 0.875rem; transition: border-color 0.15s ease;">
                    </div>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label for="inq-message" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #3b3a38; margin-bottom: 0.35rem;">Questions or Specific Requirements</label>
                        <textarea id="inq-message" name="message" rows="3" placeholder="Tell us if they are a student (SEAIT, NDMU, etc.), worker, or have questions about amenities or study hours..." class="inq-input inq-textarea" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d4d2ce; border-radius: 0.5rem; font-size: 0.875rem; transition: border-color 0.15s ease; resize: vertical;"></textarea>
                    </div>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label for="inq-staff-notes" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #3b3a38; margin-bottom: 0.35rem;">Staff Notes (Internal)</label>
                        <textarea id="inq-staff-notes" name="staff_notes" rows="2" placeholder="Internal notes for follow-up (only visible to staff)..." class="inq-input inq-textarea" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d4d2ce; border-radius: 0.5rem; font-size: 0.875rem; transition: border-color 0.15s ease; resize: vertical; background-color: #f8f7f5;"></textarea>
                    </div>

                    <!-- Instant feedback banner -->
                    <div id="inquiry-feedback" style="display: none; padding: 1rem; border-radius: 0.75rem; font-size: 0.875rem; margin-top: 0.5rem;"></div>

                    <button type="submit" id="btn-submit-inquiry" class="btn btn-primary" style="width: 100%; padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #b15f2c 0%, #97501f 100%); color: white; border: none; border-radius: 0.5rem; font-size: 0.9375rem; font-weight: 600; cursor: pointer; transition: all 0.15s ease; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <span>Submit Room Inquiry</span>
                        <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 1rem; height: 1rem;"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                    </button>

                    <p class="inquiry-footnote" style="margin-top: 1rem; font-size: 0.75rem; color: #6b6b6b; text-align: center;">
                        <?= \App\Support\Icon::svg('lock') ?> Submitted by <?= htmlspecialchars($userName) ?> • All data is protected under our privacy standards.
                    </p>
                </form>
            </div>
        </div>

        <?php if (!empty($recentInquiries)): ?>
        <div class="lg:col-span-3 order-last">
            <div class="section-header">
                <h2>Recent inquiries</h2>
                <span class="text-caption text-neutral-400">Admins only</span>
            </div>
            <div class="card" style="overflow:hidden;">
                <?php foreach ($recentInquiries as $inq): ?>
                    <a href="/admin/inquiries/<?= (int) $inq['id'] ?>" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 hover:bg-neutral-50" style="border-bottom:1px solid #e6e5e2;">
                        <span class="font-semibold text-neutral-900 text-sm">#<?= (int) $inq['id'] ?> · <?= htmlspecialchars($inq['name']) ?></span>
                        <span class="text-sm text-neutral-600"><?= htmlspecialchars((string) $inq['room_type']) ?></span>
                        <span class="text-caption text-neutral-400" style="margin-left:auto;"><?= !empty($inq['converted_user_id']) ? 'Registered as boarder' : htmlspecialchars(ucfirst($inq['status'])) ?> · <?= date('M j, g:i a', strtotime($inq['created_at'])) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Right Column: Notification Hub (1/3 width) -->
        <div class="lg:col-span-1">
            <div class="section-header">
                <h2>Notifications</h2>
                <div class="flex gap-2">
                    <button id="filter-all" class="text-xs font-semibold px-2 py-1 rounded bg-neutral-900 text-white">All</button>
                    <button id="filter-unread" class="text-xs font-semibold px-2 py-1 rounded bg-neutral-100 text-neutral-600 hover:bg-neutral-200">Unread</button>
                    <button id="filter-pinned" class="text-xs font-semibold px-2 py-1 rounded bg-neutral-100 text-neutral-600 hover:bg-neutral-200">Pinned</button>
                </div>
            </div>

            <div class="card inq-notif-panel">
                <?php if (empty($notifications)): ?>
                    <div class="inq-notif-empty">
                        <span class="empty-icon"><?= \App\Support\Icon::svg('bell') ?></span>
                        <p>No notifications yet</p>
                    </div>
                <?php else: ?>
                    <div id="notification-list">
                        <?php foreach ($notifications as $notif): ?>
                            <?php
                            $isUnread = empty($notif['is_read']);
                            $isPinned = !empty($notif['is_pinned']);
                            $notifId = (int) $notif['id'];
                            $typeConfig = \App\Support\NotificationType::config((string) ($notif['type'] ?? ''));
                            $ts = strtotime($notif['created_at']);
                            // The year only matters once it is no longer this year.
                            $when = date('Y', $ts) === date('Y') ? date('M j · g:i a', $ts) : date('M j, Y', $ts);
                            ?>
                            <div class="notification-item inq-notif <?= $isUnread ? 'unread' : '' ?> <?= $isPinned ? 'pinned' : '' ?>"
                                 data-id="<?= $notifId ?>"
                                 data-read="<?= $isUnread ? '0' : '1' ?>"
                                 data-pinned="<?= $isPinned ? '1' : '0' ?>">
                                <div class="inq-notif-head">
                                    <span class="inq-notif-type" style="background: <?= $typeConfig['bg'] ?>; color: <?= $typeConfig['color'] ?>;">
                                        <span aria-hidden="true"><?= $typeConfig['icon'] ?></span>
                                        <?= htmlspecialchars($typeConfig['label']) ?>
                                    </span>
                                    <?php if ($isPinned): ?>
                                        <span class="inq-notif-flag">Pinned</span>
                                    <?php endif; ?>
                                    <time class="inq-notif-time" datetime="<?= date('c', $ts) ?>" title="<?= date('M j, Y • g:i a', $ts) ?>">
                                        <?php if ($isUnread): ?>
                                            <span class="inq-notif-dot" role="img" aria-label="Unread"></span>
                                        <?php endif; ?>
                                        <?= $when ?>
                                    </time>
                                </div>

                                <p class="inq-notif-msg"><?= htmlspecialchars($notif['message'] ?? '') ?></p>

                                <div class="inq-notif-foot">
                                    <?php if (!empty($notif['action_url'])): ?>
                                        <a href="<?= htmlspecialchars($notif['action_url']) ?>" class="inq-notif-link">
                                            <?= htmlspecialchars($typeConfig['actionText']) ?> <span aria-hidden="true">→</span>
                                        </a>
                                    <?php endif; ?>

                                    <div class="inq-notif-actions">
                                        <form method="post" action="/notifications/<?= $notifId ?>/toggle-pin">
                                            <?= \App\Support\Csrf::field() ?>
                                            <?php $pinLabel = $isPinned ? 'Unpin' : 'Pin to top'; ?>
                                            <button type="submit" class="inq-notif-btn <?= $isPinned ? 'is-on' : '' ?>" title="<?= $pinLabel ?>" aria-label="<?= $pinLabel ?>">
                                                <svg viewBox="0 0 24 24" fill="<?= $isPinned ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"/></svg>
                                            </button>
                                        </form>

                                        <form method="post" action="/notifications/<?= $notifId ?>/toggle-read">
                                            <?= \App\Support\Csrf::field() ?>
                                            <input type="hidden" name="is_read" value="<?= $isUnread ? '0' : '1' ?>">
                                            <?php $readLabel = $isUnread ? 'Mark as read' : 'Mark as unread'; ?>
                                            <button type="submit" class="inq-notif-btn" title="<?= $readLabel ?>" aria-label="<?= $readLabel ?>">
                                                <?php if ($isUnread): ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                                                <?php else: ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                                                <?php endif; ?>
                                            </button>
                                        </form>

                                        <form method="post" action="/notifications/<?= $notifId ?>/delete" onsubmit="return confirm('Dismiss and remove this notification?');">
                                            <?= \App\Support\Csrf::field() ?>
                                            <button type="submit" class="inq-notif-btn is-danger" title="Delete notification" aria-label="Delete notification">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="notification-filter-empty" class="inq-notif-empty" style="display: none;">
                        <p>Nothing matches this filter.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Notification panel: one-third-width column, so each item stacks instead of sitting buttons beside the text. */
.inq-notif-panel { max-height: 600px; overflow-y: auto; scrollbar-width: thin; }
.inq-notif { padding: 0.875rem 1rem; border-bottom: 1px solid #e6e5e2; }
.inq-notif:last-child { border-bottom: 0; }
.inq-notif.unread { background: #fdf9f5; box-shadow: inset 3px 0 0 #b15f2c; }
.inq-notif-head { display: flex; align-items: center; flex-wrap: wrap; gap: 0.375rem 0.5rem; }
.inq-notif-type { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.6875rem; font-weight: 600; line-height: 1.4; padding: 0.15rem 0.55rem; border-radius: 9999px; white-space: nowrap; }
.inq-notif-flag { font-size: 0.6875rem; font-weight: 600; color: #b15f2c; }
.inq-notif-time { display: inline-flex; align-items: center; gap: 0.375rem; margin-left: auto; font-size: 0.75rem; color: #6b6b6b; white-space: nowrap; font-variant-numeric: tabular-nums; }
.inq-notif-dot { width: 0.4rem; height: 0.4rem; border-radius: 50%; background: #b15f2c; }
.inq-notif-msg { margin-top: 0.5rem; font-size: 0.875rem; line-height: 1.5; color: #555452; overflow-wrap: anywhere; }
.inq-notif.unread .inq-notif-msg { color: #0a0a0a; font-weight: 500; }
.inq-notif-foot { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem; min-height: 1.875rem; }
.inq-notif-link { font-size: 0.8125rem; font-weight: 600; color: #0a0a0a; text-decoration: underline; text-decoration-color: #d4d2ce; text-underline-offset: 3px; }
.inq-notif-link:hover { text-decoration-color: currentColor; }
.inq-notif-actions { display: flex; gap: 0.125rem; margin-left: auto; }
.inq-notif-actions form { display: inline-flex; }
.inq-notif-btn { width: 1.875rem; height: 1.875rem; display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 0.5rem; background: transparent; color: #6b6b6b; cursor: pointer; transition: background-color 140ms, color 140ms; }
.inq-notif-btn svg { width: 0.9375rem; height: 0.9375rem; }
.inq-notif-btn:hover { background: #f1f0ee; color: #0a0a0a; }
.inq-notif-btn:focus-visible, .inq-notif-link:focus-visible { outline: 2px solid #b15f2c; outline-offset: 2px; }
.inq-notif-btn.is-on { color: #b15f2c; }
.inq-notif-btn.is-danger:hover { background: #fdf1f0; color: #b42318; }
.inq-notif-empty { padding: 2rem; text-align: center; font-size: 0.875rem; color: #6b6b6b; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // === Inquiry Form AJAX Handler ===
    const inquiryForm = document.getElementById('staff-inquiry-form');
    const feedbackEl = document.getElementById('inquiry-feedback');
    const submitBtn = document.getElementById('btn-submit-inquiry');

    if (inquiryForm && feedbackEl) {
        inquiryForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(inquiryForm);

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span>Transmitting Inquiry...</span>';
            }

            try {
                const response = await fetch('/admin/inquiry-center/submit', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const result = await response.json();

                feedbackEl.style.display = 'block';
                if (response.ok && result.success) {
                    feedbackEl.className = 'contact-alert success';
                    feedbackEl.style.background = '#eef6f1';
                    feedbackEl.style.border = '1px solid #d7ebdf';
                    feedbackEl.style.color = '#245a3f';
                    feedbackEl.innerHTML = `<svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 1rem; height: 1rem; display: inline-block; vertical-align: middle; margin-right: 0.5rem;"><polyline points="20 6 9 17 4 12"/></svg> <span>${String(result.message).replace(/[&<>"']/g, c => '&#' + c.charCodeAt(0) + ';')}</span>`;
                    inquiryForm.reset();
                    
                    // Refresh page after short delay to show updated notifications
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    feedbackEl.className = 'contact-alert error';
                    feedbackEl.style.background = '#fdf1f0';
                    feedbackEl.style.border = '1px solid #f9dcd9';
                    feedbackEl.style.color = '#7a1b14';
                    feedbackEl.innerHTML = `<svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 1rem; height: 1rem; display: inline-block; vertical-align: middle; margin-right: 0.5rem;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> <span>${String(result.error || 'Failed to submit inquiry. Please try again.').replace(/[&<>"']/g, c => '&#' + c.charCodeAt(0) + ';')}</span>`;
                }
            } catch (err) {
                feedbackEl.style.display = 'block';
                feedbackEl.className = 'contact-alert error';
                feedbackEl.style.background = '#fdf1f0';
                feedbackEl.style.border = '1px solid #f9dcd9';
                feedbackEl.style.color = '#7a1b14';
                feedbackEl.innerHTML = `<span>Network connection issue. Please try again.</span>`;
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<span>Submit Room Inquiry</span> <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 1rem; height: 1rem;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';
                }
            }
        });
    }

    // === Notification Filter Tabs ===
    const filterAll = document.getElementById('filter-all');
    const filterUnread = document.getElementById('filter-unread');
    const filterPinned = document.getElementById('filter-pinned');
    const notificationList = document.getElementById('notification-list');

    function setActiveFilter(button) {
        [filterAll, filterUnread, filterPinned].forEach(btn => {
            btn.className = 'text-xs font-semibold px-2 py-1 rounded bg-neutral-100 text-neutral-600 hover:bg-neutral-200';
        });
        button.className = 'text-xs font-semibold px-2 py-1 rounded bg-neutral-900 text-white';
    }

    function filterNotifications(filter) {
        const items = document.querySelectorAll('.notification-item');
        items.forEach(item => {
            const isUnread = item.dataset.read === '0';
            const isPinned = item.dataset.pinned === '1';
            
            let show = true;
            if (filter === 'unread' && !isUnread) show = false;
            if (filter === 'pinned' && !isPinned) show = false;
            
            item.style.display = show ? 'block' : 'none';
        });
        const empty = document.getElementById('notification-filter-empty');
        if (empty) empty.style.display = [...items].some(i => i.style.display !== 'none') ? 'none' : 'block';
    }

    if (filterAll) filterAll.addEventListener('click', () => { setActiveFilter(filterAll); filterNotifications('all'); });
    if (filterUnread) filterUnread.addEventListener('click', () => { setActiveFilter(filterUnread); filterNotifications('unread'); });
    if (filterPinned) filterPinned.addEventListener('click', () => { setActiveFilter(filterPinned); filterNotifications('pinned'); });
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
?>