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
        <span class="badge badge-success" style="background:rgba(255,255,255,0.12); color:#86efac; border:1px solid rgba(255,255,255,0.18); padding:0.4rem 0.85rem; font-size:0.75rem;">
            <span style="width:0.45rem;height:0.45rem;border-radius:50%;background:#4ade80;display:inline-block;margin-right:0.4rem;"></span>
            Staff Portal Active
        </span>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="reveal-card card p-4 metric-accent-primary">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Inquiries</span>
                <span style="font-size:1.1rem; line-height:1;">📋</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= (int) $totalInquiries ?></p>
            <p class="text-caption text-neutral-400 mt-1">All-time submissions</p>
        </div>

        <div class="reveal-card card p-4 metric-accent-success">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Today's Inquiries</span>
                <span style="font-size:1.1rem; line-height:1;">📅</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= (int) $todayInquiries ?></p>
            <p class="text-caption text-emerald-700 mt-1 font-medium">New submissions today</p>
        </div>

        <div class="reveal-card card p-4 metric-accent-warning">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Unread Notifications</span>
                <span style="font-size:1.1rem; line-height:1;">🔔</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= (int) $unreadCount ?></p>
            <p class="text-caption text-neutral-400 mt-1">Requires attention</p>
        </div>

        <div class="reveal-card card p-4 metric-accent-info">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Staff Member</span>
                <span style="font-size:1.1rem; line-height:1;">👤</span>
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
                <div class="contact-alert success" style="display: block; padding: 1rem; border-radius: 0.75rem; font-size: 0.875rem; margin-bottom: 1rem; background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534;">
                    <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 1rem; height: 1rem; display: inline-block; vertical-align: middle; margin-right: 0.5rem;"><polyline points="20 6 9 17 4 12"/></svg>
                    <span><?= htmlspecialchars($inquirySuccess) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($inquiryError): ?>
                <div class="contact-alert error" style="display: block; padding: 1rem; border-radius: 0.75rem; font-size: 0.875rem; margin-bottom: 1rem; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;">
                    <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 1rem; height: 1rem; display: inline-block; vertical-align: middle; margin-right: 0.5rem;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span><?= htmlspecialchars($inquiryError) ?></span>
                </div>
            <?php endif; ?>

            <div class="form-card">
                <form id="staff-inquiry-form" action="/admin/inquiry-center/submit" method="POST" class="inquiry-form-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Support\Csrf::token()) ?>">
                    
                    <div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <label for="inq-name" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #374151; margin-bottom: 0.35rem;">Customer Full Name <span class="req" style="color: #dc2626;">*</span></label>
                            <input type="text" id="inq-name" name="name" required placeholder="e.g. Maria Santos" class="inq-input" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; transition: border-color 0.15s ease;">
                        </div>
                        <div class="form-group">
                            <label for="inq-phone" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #374151; margin-bottom: 0.35rem;">Contact Phone / Mobile <span class="req" style="color: #dc2626;">*</span></label>
                            <input type="tel" id="inq-phone" name="phone" required placeholder="e.g. 0917 123 4567" class="inq-input" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; transition: border-color 0.15s ease;">
                        </div>
                    </div>

                    <div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <label for="inq-email" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #374151; margin-bottom: 0.35rem;">Email Address (Optional)</label>
                            <input type="email" id="inq-email" name="email" placeholder="e.g. maria@gmail.com" class="inq-input" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; transition: border-color 0.15s ease;">
                        </div>
                        <div class="form-group">
                            <label for="inq-room" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #374151; margin-bottom: 0.35rem;">Preferred Room Tier</label>
                            <select id="inq-room" name="room_type" class="inq-input inq-select" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; transition: border-color 0.15s ease; background-color: white;">
                                <option value="Solo Executive Room">Solo Executive Room (₱3,500/mo)</option>
                                <option value="Twin Sharing Scholar Suite" selected>Twin Sharing Scholar Suite (₱2,200/mo)</option>
                                <option value="Quad Bedspace Sanctuary">Quad Bedspace Sanctuary (₱1,600/mo)</option>
                                <option value="General Inquiry / Visit Booking">General Inquiry / Physical Viewing</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label for="inq-date" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #374151; margin-bottom: 0.35rem;">Target Move-in / Viewing Date</label>
                        <input type="date" id="inq-date" name="move_in_date" class="inq-input" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; transition: border-color 0.15s ease;">
                    </div>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label for="inq-message" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #374151; margin-bottom: 0.35rem;">Questions or Specific Requirements</label>
                        <textarea id="inq-message" name="message" rows="3" placeholder="Tell us if they are a student (SEAIT, NDMU, etc.), worker, or have questions about amenities or study hours..." class="inq-input inq-textarea" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; transition: border-color 0.15s ease; resize: vertical;"></textarea>
                    </div>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label for="inq-staff-notes" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #374151; margin-bottom: 0.35rem;">Staff Notes (Internal)</label>
                        <textarea id="inq-staff-notes" name="staff_notes" rows="2" placeholder="Internal notes for follow-up (only visible to staff)..." class="inq-input inq-textarea" style="width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.875rem; transition: border-color 0.15s ease; resize: vertical; background-color: #f8fafc;"></textarea>
                    </div>

                    <!-- Instant feedback banner -->
                    <div id="inquiry-feedback" style="display: none; padding: 1rem; border-radius: 0.75rem; font-size: 0.875rem; margin-top: 0.5rem;"></div>

                    <button type="submit" id="btn-submit-inquiry" class="btn btn-primary" style="width: 100%; padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; border: none; border-radius: 0.5rem; font-size: 0.9375rem; font-weight: 600; cursor: pointer; transition: all 0.15s ease; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <span>Submit Room Inquiry</span>
                        <svg class="icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 1rem; height: 1rem;"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                    </button>

                    <p class="inquiry-footnote" style="margin-top: 1rem; font-size: 0.75rem; color: #64748b; text-align: center;">
                        🔒 Submitted by <?= htmlspecialchars($userName) ?> • All data is protected under our privacy standards.
                    </p>
                </form>
            </div>
        </div>

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

            <div class="card" style="max-height: 600px; overflow-y: auto;">
                <?php if (empty($notifications)): ?>
                    <div style="padding: 2rem; text-align: center; color: #64748b;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔔</div>
                        <p style="font-size: 0.875rem;">No notifications yet</p>
                    </div>
                <?php else: ?>
                    <div id="notification-list" style="display: flex; flex-direction: column; gap: 0.75rem; padding: 0.75rem;">
                        <?php foreach ($notifications as $notif): ?>
                            <?php 
                            $isUnread = ($notif['is_read'] ?? 0) == 0;
                            $isPinned = ($notif['is_pinned'] ?? 0) == 1;
                            $createdDate = date('M d, Y • h:i a', strtotime($notif['created_at']));
                            $notifId = (int) $notif['id'];
                            ?>
                            
                            <div class="notification-item <?= $isUnread ? 'unread' : '' ?> <?= $isPinned ? 'pinned' : '' ?>" 
                                 data-id="<?= $notifId ?>" 
                                 data-read="<?= $isUnread ? '0' : '1' ?>" 
                                 data-pinned="<?= $isPinned ? '1' : '0' ?>"
                                 style="border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.875rem; background: <?= $isUnread ? '#f8fafc' : '#ffffff' ?>; <?= $isPinned ? 'border-left: 3px solid #2563eb;' : '' ?>">
                                
                                <div class="flex items-start justify-between gap-3">
                                    <!-- Left: Icon & Content -->
                                    <div class="flex items-start gap-3 flex-1 min-w-0">
                                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 text-base shadow-xs mt-0.5" style="background: #f8fafc; color: #475569; border: 1px solid #47556925;">
                                            📋
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                                <?php 
                                                $notifType = strtolower($notif['type'] ?? 'notification');
                                                $typeLabel = str_contains($notifType, 'inquiry') ? 'Inquiry' : ucfirst($notifType);
                                                $typeBadge = str_contains($notifType, 'inquiry') ? 'badge-primary' : 'badge-neutral';
                                                $typeBg = str_contains($notifType, 'inquiry') ? '#eff6ff' : '#f1f5f9';
                                                $typeColor = str_contains($notifType, 'inquiry') ? '#2563eb' : '#475569';
                                                ?>
                                                <span class="badge <?= $typeBadge ?> text-[10px] font-bold uppercase tracking-wider" style="background: <?= $typeBg ?>; color: <?= $typeColor ?>; padding: 0.15rem 0.5rem; border-radius: 0.375rem;">
                                                    <?= htmlspecialchars($typeLabel) ?>
                                                </span>
                                                <span class="id-tag">#<?= $notifId ?></span>
                                                
                                                <?php if ($isUnread): ?>
                                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-primary-700 bg-primary-100 px-2 py-0.5 rounded-full">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-primary-600"></span>
                                                        Unread
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <span class="text-caption text-neutral-400 font-normal">
                                                    <?= htmlspecialchars($createdDate) ?>
                                                </span>
                                            </div>
                                            <div class="text-sm text-neutral-800 font-medium leading-relaxed mt-1 break-words">
                                                <?= htmlspecialchars($notif['message'] ?? '') ?>
                                            </div>
                                            
                                            <?php if (!empty($notif['action_url'])): ?>
                                                <div class="mt-2.5">
                                                    <a href="<?= htmlspecialchars($notif['action_url']) ?>" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-semibold bg-neutral-900 text-white hover:bg-neutral-800 transition-colors shadow-xs">
                                                        <span>View Details</span>
                                                        <span aria-hidden="true">→</span>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Right: Action Buttons -->
                                    <div class="action-cluster flex-shrink-0 flex items-center gap-1.5">
                                        <!-- Pin / Unpin Form -->
                                        <form method="post" action="/notifications/<?= $notifId ?>/toggle-pin" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Support\Csrf::token()) ?>">
                                            <button type="submit" class="btn btn-secondary !py-1 !px-2.5 !text-xs cursor-pointer shadow-xs" title="<?= $isPinned ? 'Unpin from top' : 'Pin to top as important' ?>">
                                                <?= $isPinned ? '📍 Unpin' : '📍 Pin' ?>
                                            </button>
                                        </form>

                                        <!-- Mark Read / Unread Form -->
                                        <form method="post" action="/notifications/<?= $notifId ?>/toggle-read" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Support\Csrf::token()) ?>">
                                            <input type="hidden" name="is_read" value="<?= $isUnread ? '0' : '1' ?>">
                                            <button type="submit" class="btn btn-secondary !py-1 !px-2.5 !text-xs cursor-pointer shadow-xs" title="<?= $isUnread ? 'Mark as read' : 'Mark as unread' ?>">
                                                <?= $isUnread ? '✓ Read' : '○ Unread' ?>
                                            </button>
                                        </form>

                                        <!-- Delete Form -->
                                        <form method="post" action="/notifications/<?= $notifId ?>/delete" class="inline" onsubmit="return confirm('Dismiss and remove this notification?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Support\Csrf::token()) ?>">
                                            <button type="submit" class="btn-danger !py-1 !px-2 !text-xs cursor-pointer" title="Delete notification">
                                                ✕
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

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
                    feedbackEl.style.background = '#f0fdf4';
                    feedbackEl.style.border = '1px solid #bbf7d0';
                    feedbackEl.style.color = '#166534';
                    feedbackEl.innerHTML = `<svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 1rem; height: 1rem; display: inline-block; vertical-align: middle; margin-right: 0.5rem;"><polyline points="20 6 9 17 4 12"/></svg> <span>${String(result.message).replace(/[&<>"']/g, c => '&#' + c.charCodeAt(0) + ';')}</span>`;
                    inquiryForm.reset();
                    
                    // Refresh page after short delay to show updated notifications
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    feedbackEl.className = 'contact-alert error';
                    feedbackEl.style.background = '#fef2f2';
                    feedbackEl.style.border = '1px solid #fecaca';
                    feedbackEl.style.color = '#991b1b';
                    feedbackEl.innerHTML = `<svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 1rem; height: 1rem; display: inline-block; vertical-align: middle; margin-right: 0.5rem;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> <span>${String(result.error || 'Failed to submit inquiry. Please try again.').replace(/[&<>"']/g, c => '&#' + c.charCodeAt(0) + ';')}</span>`;
                }
            } catch (err) {
                feedbackEl.style.display = 'block';
                feedbackEl.className = 'contact-alert error';
                feedbackEl.style.background = '#fef2f2';
                feedbackEl.style.border = '1px solid #fecaca';
                feedbackEl.style.color = '#991b1b';
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