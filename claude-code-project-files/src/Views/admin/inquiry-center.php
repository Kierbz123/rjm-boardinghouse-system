<?php
$pageTitle = 'Inquiry Management Center';
ob_start();
?>

<div class="inq-center-wrapper">
    <!-- Executive Page Banner with Quick Launch Command Bar -->
    <header class="dash-hero-banner">
        <div class="dash-hero-top">
            <div>
                <h1 class="dash-hero-title">
                    <span>Inquiry Management Center</span>
                </h1>
                <p class="dash-hero-sub">Log walk-in prospect details, assign preferred room tiers, and monitor real-time operational notifications.</p>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="badge" style="background:rgba(255,255,255,0.08); color:#8cc2a2; border:1px solid rgba(255,255,255,0.15); padding:0.35rem 0.85rem; font-size:0.75rem; font-weight:600; border-radius:9999px; display:inline-flex; align-items:center;">
                    <span class="live-pulse-dot" style="margin-right:0.45rem;"></span>
                    Front Desk Active
                </span>
                <span class="badge" style="background:rgba(255,255,255,0.05); color:#a3a19b; border:1px solid rgba(255,255,255,0.1); padding:0.35rem 0.75rem; font-size:0.75rem; border-radius:9999px; display:inline-flex; align-items:center; gap:0.35rem;">
                    <?= \App\Support\Icon::svg('calendar', 13) ?>
                    <span><?= date('l, M j, Y') ?></span>
                </span>
            </div>
        </div>

        <!-- Quick Launch Command Bar -->
        <div class="dash-cmd-bar">
            <span style="font-size:0.6875rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:rgba(255,255,255,0.5); margin-right:0.35rem;">
                Quick Launch:
            </span>
            <a href="#staff-inquiry-form" class="dash-cmd-btn highlight" title="Jump to walk-in intake form">
                <?= \App\Support\Icon::svg('plus', 14) ?>
                <span>Log Walk-in</span>
            </a>
            <?php if (!empty($recentInquiries)): ?>
                <a href="#recent-inquiries-ledger" class="dash-cmd-btn" title="Jump to recent inquiries ledger">
                    <?= \App\Support\Icon::svg('clipboard', 14) ?>
                    <span>Recent Ledger (<?= count($recentInquiries) ?>)</span>
                </a>
            <?php endif; ?>
            <a href="#notifications-hub" class="dash-cmd-btn" title="Review activity notifications">
                <?= \App\Support\Icon::svg('bell', 14) ?>
                <span>Notifications<?php if ($unreadCount > 0): ?> (<?= (int) $unreadCount ?>)<?php endif; ?></span>
            </a>
        </div>
    </header>

    <!-- Executive KPI Statistics Grid -->
    <div class="kpi-grid">
        <!-- Metric 1: Total Inquiries -->
        <a href="#staff-inquiry-form" class="reveal-card kpi-card metric-accent-primary" title="Total inquiry leads recorded in system">
            <div class="kpi-card-header">
                <span class="kpi-label">Total Inquiries</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('clipboard') ?></span>
            </div>
            <div class="kpi-value"><?= (int) $totalInquiries ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">All-time recorded submissions</span>
            </div>
        </a>

        <!-- Metric 2: Today's Inquiries -->
        <a href="#staff-inquiry-form" class="reveal-card kpi-card metric-accent-success" title="Inquiries received today">
            <div class="kpi-card-header">
                <span class="kpi-label">Today's Inquiries</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('calendar') ?></span>
            </div>
            <div class="kpi-value"><?= (int) $todayInquiries ?></div>
            <div class="kpi-subtext">
                <?php if ($todayInquiries > 0): ?>
                    <span class="text-emerald-700 font-semibold"><?= (int) $todayInquiries ?> new inquiry today</span>
                <?php else: ?>
                    <span class="text-neutral-400">No new submissions today</span>
                <?php endif; ?>
            </div>
        </a>

        <!-- Metric 3: Unread Notifications -->
        <a href="#notifications-hub" class="reveal-card kpi-card metric-accent-warning" title="System alerts requiring review">
            <div class="kpi-card-header">
                <span class="kpi-label">Unread Notifications</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('bell') ?></span>
            </div>
            <div class="kpi-value <?= $unreadCount > 0 ? 'text-amber-600' : '' ?>"><?= (int) $unreadCount ?></div>
            <div class="kpi-subtext">
                <?php if ($unreadCount > 0): ?>
                    <span class="text-amber-800 font-semibold"><?= (int) $unreadCount ?> alert<?= $unreadCount === 1 ? '' : 's' ?> need review</span>
                <?php else: ?>
                    <span class="text-neutral-400">All caught up</span>
                <?php endif; ?>
            </div>
        </a>

        <!-- Metric 4: Active Desk Operator -->
        <a href="/profile" class="reveal-card kpi-card metric-accent-info" title="Manage your operator profile">
            <div class="kpi-card-header">
                <span class="kpi-label">Active Desk Operator</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('user') ?></span>
            </div>
            <div class="kpi-value text-xl truncate" title="<?= htmlspecialchars($userName) ?>">
                <?= htmlspecialchars($userName) ?>
            </div>
            <div class="kpi-subtext">
                <span class="text-neutral-400"><?= htmlspecialchars(ucfirst($userRole)) ?> session active</span>
            </div>
        </a>
    </div>

    <!-- Main Workspace Split: Intake Studio & Notification Dispatch -->
    <div class="inq-workspace-grid">
        <!-- Left Column: Walk-In / Desk Intake Studio (2/3 Col on Desktop) -->
        <div class="inq-intake-col">
            <div class="inq-card inq-form-card">
                <!-- Studio Header -->
                <div class="inq-card-header">
                    <div class="inq-card-header-left">
                        <div class="inq-card-icon-badge">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                        </div>
                        <div>
                            <h2 class="inq-card-title">Log Walk-In or Phone Inquiry</h2>
                            <p class="inq-card-sub">Instant intake registration for prospective residents visiting or contacting the front desk.</p>
                        </div>
                    </div>
                    <span class="inq-badge inq-badge-accent">
                        <svg class="w-3.5 h-3.5 inline mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        Staff-Assisted Intake
                    </span>
                </div>

                <!-- Flash Status Banners -->
                <?php if ($inquirySuccess): ?>
                    <div class="inq-alert inq-alert-success">
                        <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <div>
                            <div class="font-bold text-sm">Submission Successful</div>
                            <div class="text-xs"><?= htmlspecialchars($inquirySuccess) ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($inquiryError): ?>
                    <div class="inq-alert inq-alert-error">
                        <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <div>
                            <div class="font-bold text-sm">Submission Error</div>
                            <div class="text-xs"><?= htmlspecialchars($inquiryError) ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Walk-in Inquiry Form -->
                <form id="staff-inquiry-form" action="/admin/inquiry-center/submit" method="POST" class="inq-form-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Support\Csrf::token()) ?>">

                    <!-- Fieldset 1: Prospect Contact Coordinates -->
                    <div class="inq-form-section">
                        <div class="inq-section-title">
                            <span class="inq-section-step">1</span>
                            <span>Prospect Information</span>
                        </div>
                        <div class="inq-form-grid-2">
                            <div class="inq-field-group">
                                <label for="inq-name" class="inq-label">
                                    Customer Full Name <span class="inq-req" aria-hidden="true">*</span>
                                </label>
                                <div class="inq-input-wrap">
                                    <span class="inq-input-icon">
                                        <svg class="w-4 h-4 text-neutral-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    </span>
                                    <input type="text" id="inq-name" name="name" required placeholder="e.g. Maria Santos" class="inq-input has-icon">
                                </div>
                                <span class="inq-hint">Full legal or preferred name for official registration.</span>
                            </div>

                            <div class="inq-field-group">
                                <label for="inq-phone" class="inq-label">
                                    Contact Mobile Number <span class="inq-req" aria-hidden="true">*</span>
                                </label>
                                <div class="inq-input-wrap">
                                    <span class="inq-input-icon">
                                        <svg class="w-4 h-4 text-neutral-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                    </span>
                                    <input type="tel" id="inq-phone" name="phone" required pattern="09[0-9]{9}" inputmode="numeric" data-digits="11" title="11-digit Philippine mobile number starting with 09" placeholder="09xxxxxxxxx" class="inq-input has-icon font-mono">
                                </div>
                                <span class="inq-hint">11-digit Philippine mobile number starting with 09.</span>
                            </div>
                        </div>

                        <div class="inq-field-group mt-3">
                            <label for="inq-email" class="inq-label">
                                Email Address <span class="inq-opt">(Optional)</span>
                            </label>
                            <div class="inq-input-wrap">
                                <span class="inq-input-icon">
                                    <svg class="w-4 h-4 text-neutral-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                                </span>
                                <input type="email" id="inq-email" name="email" placeholder="e.g. maria.santos@gmail.com" class="inq-input has-icon">
                            </div>
                        </div>
                    </div>

                    <!-- Fieldset 2: Room Preferences & Timeline -->
                    <div class="inq-form-section">
                        <div class="inq-section-title">
                            <span class="inq-section-step">2</span>
                            <span>Room Preference &amp; Schedule</span>
                        </div>
                        <div class="inq-form-grid-2">
                            <div class="inq-field-group">
                                <label for="inq-room" class="inq-label">
                                    Preferred Room Tier
                                </label>
                                <div class="inq-input-wrap">
                                    <select id="inq-room" name="room_type" class="inq-input inq-select">
                                        <?php foreach ($roomTypes as $t): ?>
                                            <option value="<?= htmlspecialchars($t['name']) ?>" <?= $t['is_popular'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars(\App\Models\RoomType::priceLabel($t)) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="General Inquiry / Visit Booking">General Inquiry / Physical Viewing</option>
                                    </select>
                                </div>
                            </div>

                            <div class="inq-field-group">
                                <label for="inq-date" class="inq-label">
                                    Target Move-in / Viewing Date <span class="inq-opt">(Optional)</span>
                                </label>
                                <div class="inq-input-wrap">
                                    <input type="date" id="inq-date" name="move_in_date" class="inq-input">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fieldset 3: Inquirer Message & Staff Internal Notes -->
                    <div class="inq-form-section">
                        <div class="inq-section-title">
                            <span class="inq-section-step">3</span>
                            <span>Inquiry Details &amp; Operational Notes</span>
                        </div>

                        <div class="inq-field-group mb-3">
                            <label for="inq-message" class="inq-label">
                                Questions or Specific Requirements
                            </label>
                            <textarea id="inq-message" name="message" rows="3" placeholder="Tell us if they are a student (SEAIT, NDMU, etc.), worker, or have questions about amenities, aircon, curfew, or study hours..." class="inq-input inq-textarea"></textarea>
                        </div>

                        <div class="inq-field-group inq-field-notes">
                            <label for="inq-staff-notes" class="inq-label flex items-center justify-between">
                                <span class="flex items-center gap-1.5 font-bold text-amber-900">
                                    <svg class="w-3.5 h-3.5 text-amber-700" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    Staff Internal Notes
                                </span>
                                <span class="text-xs text-amber-800 font-normal">Private · Staff &amp; Admin only</span>
                            </label>
                            <textarea id="inq-staff-notes" name="staff_notes" rows="2" placeholder="Internal notes for follow-up (e.g. visited in person with parent, needs quiet room for board exam prep, promised callback tomorrow)..." class="inq-input inq-textarea inq-staff-notes-input"></textarea>
                        </div>
                    </div>

                    <!-- Live Dynamic Feedback Box -->
                    <div id="inquiry-feedback" class="inq-feedback-box" style="display: none;" role="alert"></div>

                    <!-- Submit Button Row -->
                    <div class="inq-form-footer">
                        <button type="submit" id="btn-submit-inquiry" class="inq-btn-primary">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            <span>Submit Room Inquiry</span>
                        </button>
                        <p class="inq-footnote">
                            <svg class="w-3.5 h-3.5 inline text-neutral-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            Logged by <strong><?= htmlspecialchars($userName) ?></strong> &bull; Protected under house data privacy rules.
                        </p>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Column: Operational Notification Dispatch Hub (1/3 Col on Desktop) -->
        <div class="inq-dispatch-col" id="notifications-hub">
            <div class="inq-card inq-notif-container">
                <!-- Header with Filter Segmented Control -->
                <div class="inq-notif-header">
                    <div class="flex items-center gap-2">
                        <span class="inq-card-icon-badge inq-card-icon-badge-amber">
                            <?= \App\Support\Icon::svg('bell') ?>
                        </span>
                        <div>
                            <h2 class="inq-card-title text-base">Activity Notifications</h2>
                            <p class="inq-card-sub text-xs">Operational &amp; system alerts</p>
                        </div>
                    </div>
                    <div class="inq-filter-tabs" role="tablist" aria-label="Notification filters">
                        <button type="button" id="filter-all" class="inq-filter-tab active" role="tab" aria-selected="true">All</button>
                        <button type="button" id="filter-unread" class="inq-filter-tab" role="tab" aria-selected="false">
                            Unread<?php if ($unreadCount > 0): ?><span class="inq-tab-badge"><?= (int) $unreadCount ?></span><?php endif; ?>
                        </button>
                        <button type="button" id="filter-pinned" class="inq-filter-tab" role="tab" aria-selected="false">Pinned</button>
                    </div>
                </div>

                <!-- Scrollable Notifications Panel -->
                <div class="inq-notif-scroll-area inq-notif-panel">
                    <?php if (empty($notifications)): ?>
                        <div class="inq-notif-empty">
                            <div class="inq-empty-icon-wrap">
                                <?= \App\Support\Icon::svg('bell') ?>
                            </div>
                            <h3 class="font-bold text-neutral-800 text-sm">No notifications yet</h3>
                            <p class="text-xs text-neutral-500 mt-1">When prospective residents inquire or alerts trigger, they will appear here in real time.</p>
                        </div>
                    <?php else: ?>
                        <div id="notification-list" class="divide-y divide-neutral-100">
                            <?php foreach ($notifications as $notif): ?>
                                <?php
                                $isUnread = empty($notif['is_read']);
                                $isPinned = !empty($notif['is_pinned']);
                                $notifId = (int) $notif['id'];
                                $typeConfig = \App\Support\NotificationType::config((string) ($notif['type'] ?? ''));
                                $ts = strtotime($notif['created_at']);
                                $when = date('Y', $ts) === date('Y') ? date('M j · g:i a', $ts) : date('M j, Y', $ts);
                                ?>
                                <article class="notification-item inq-notif <?= $isUnread ? 'unread' : '' ?> <?= $isPinned ? 'pinned' : '' ?>"
                                         data-id="<?= $notifId ?>"
                                         data-read="<?= $isUnread ? '0' : '1' ?>"
                                         data-pinned="<?= $isPinned ? '1' : '0' ?>">
                                    <div class="inq-notif-head">
                                        <span class="inq-notif-type" style="background: <?= $typeConfig['bg'] ?>; color: <?= $typeConfig['color'] ?>;">
                                            <span aria-hidden="true"><?= $typeConfig['icon'] ?></span>
                                            <?= htmlspecialchars($typeConfig['label']) ?>
                                        </span>
                                        <?php if ($isPinned): ?>
                                            <span class="inq-notif-flag">
                                                <svg class="w-3 h-3 inline" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"/></svg>
                                                Pinned
                                            </span>
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
                                                <span><?= htmlspecialchars($typeConfig['actionText']) ?></span>
                                                <span aria-hidden="true">&rarr;</span>
                                            </a>
                                        <?php endif; ?>

                                        <div class="inq-notif-actions">
                                            <!-- Pin Action -->
                                            <form method="post" action="/notifications/<?= $notifId ?>/toggle-pin">
                                                <?= \App\Support\Csrf::field() ?>
                                                <?php $pinLabel = $isPinned ? 'Unpin' : 'Pin to top'; ?>
                                                <button type="submit" class="inq-notif-btn <?= $isPinned ? 'is-on' : '' ?>" title="<?= $pinLabel ?>" aria-label="<?= $pinLabel ?>">
                                                    <svg viewBox="0 0 24 24" fill="<?= $isPinned ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"/></svg>
                                                </button>
                                            </form>

                                            <!-- Toggle Read Action -->
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

                                            <!-- Delete Notification -->
                                            <form method="post" action="/notifications/<?= $notifId ?>/delete"
                                                  data-confirm="Dismiss and remove this notification?"
                                                  data-confirm-title="Remove Notification?"
                                                  data-confirm-btn="Dismiss"
                                                  data-confirm-danger>
                                                <?= \App\Support\Csrf::field() ?>
                                                <button type="submit" class="inq-notif-btn is-danger" title="Delete notification" aria-label="Delete notification">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <div id="notification-filter-empty" class="inq-notif-empty" style="display: none;">
                            <div class="inq-empty-icon-wrap">
                                <?= \App\Support\Icon::svg('bell') ?>
                            </div>
                            <p class="font-semibold text-neutral-700">No notifications match this filter.</p>
                            <p class="text-xs text-neutral-400 mt-1">Try switching to the "All" tab to view complete alerts.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Footer link to full notification center -->
                <div class="inq-notif-panel-footer">
                    <a href="/notifications" class="inq-view-all-link">
                        <span>Open Complete Notification Center</span>
                        <svg class="w-3.5 h-3.5 inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Only: Elevated Recent Inquiries Ledger Table -->
    <?php if (!empty($recentInquiries)): ?>
        <section class="inq-card inq-ledger-card" id="recent-inquiries-ledger" aria-label="Recent prospect inquiries ledger">
            <div class="inq-card-header inq-ledger-header">
                <div class="inq-card-header-left">
                    <div class="inq-card-icon-badge inq-card-icon-badge-orange">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <!-- Retain exact heading 'Recent inquiries' for test_inquiry_detail.php assertion -->
                            <h2 class="inq-card-title text-lg">Recent inquiries</h2>
                            <span class="inq-count-chip"><?= count($recentInquiries) ?> Recorded</span>
                        </div>
                        <p class="inq-card-sub">Prospect inquiry leads, contact coordinates, preferred tiers, and conversion status &bull; Admins only</p>
                    </div>
                </div>

                <!-- Instant Table Search Filter -->
                <div class="inq-ledger-controls">
                    <div class="inq-search-box">
                        <svg class="w-4 h-4 text-neutral-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" id="inq-table-search" placeholder="Search prospect, phone, room..." class="inq-search-input" aria-label="Filter inquiries table">
                    </div>
                </div>
            </div>

            <!-- Ledger Responsive Table -->
            <div class="inq-table-responsive">
                <table class="inq-table" id="inquiries-ledger-table">
                    <thead>
                        <tr>
                            <th scope="col" class="w-16">ID</th>
                            <th scope="col">Prospect &amp; Contact</th>
                            <th scope="col">Preferred Tier</th>
                            <th scope="col">Target Date</th>
                            <th scope="col">Status</th>
                            <th scope="col">Received</th>
                            <th scope="col" class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <?php foreach ($recentInquiries as $inq): ?>
                            <?php
                            $inqId = (int) $inq['id'];
                            $isConverted = !empty($inq['converted_user_id']);
                            $status = strtolower($inq['status'] ?? 'new');
                            $createdTs = strtotime($inq['created_at']);
                            $nameInitial = strtoupper(mb_substr(trim($inq['name']), 0, 1));
                            ?>
                            <tr class="inq-table-row hover:bg-neutral-50/80 transition-colors" data-inq-id="<?= $inqId ?>">
                                <!-- ID -->
                                <td class="font-mono text-xs font-bold text-neutral-500">
                                    #<?= $inqId ?>
                                </td>

                                <!-- Prospect & Contact (Strict compliance with test_probe_ops admin view of Juan & phone) -->
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="inq-avatar-circle" aria-hidden="true">
                                            <?= htmlspecialchars($nameInitial) ?>
                                        </div>
                                        <div class="min-w-0">
                                            <a href="/admin/inquiries/<?= $inqId ?>" class="font-bold text-sm text-neutral-900 hover:text-amber-700 hover:underline transition-colors block truncate">
                                                <?= htmlspecialchars($inq['name']) ?>
                                            </a>
                                            <div class="flex flex-wrap items-center gap-2 mt-0.5">
                                                <?php if (!empty($inq['phone'])): ?>
                                                    <a href="tel:<?= htmlspecialchars($inq['phone']) ?>" class="inq-phone-badge" title="Call prospect">
                                                        <svg class="w-3 h-3 text-neutral-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                                        <span><?= htmlspecialchars($inq['phone']) ?></span>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!empty($inq['email'])): ?>
                                                    <span class="text-xs text-neutral-500 truncate" title="<?= htmlspecialchars($inq['email']) ?>">
                                                        <?= htmlspecialchars($inq['email']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Preferred Tier -->
                                <td>
                                    <span class="inq-tier-chip">
                                        <svg class="w-3.5 h-3.5 text-amber-700" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                                        <span class="truncate"><?= htmlspecialchars((string) ($inq['room_type'] ?: 'General Inquiry')) ?></span>
                                    </span>
                                </td>

                                <!-- Target Move-in Date -->
                                <td class="text-xs text-neutral-600 font-medium whitespace-nowrap">
                                    <?php if (!empty($inq['move_in_date'])): ?>
                                        <span class="inline-flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-neutral-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                            <?= date('M j, Y', strtotime($inq['move_in_date'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-neutral-400 italic">Immediate / Flexible</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status Badge -->
                                <td>
                                    <?php if ($isConverted): ?>
                                        <span class="inq-status-badge inq-status-converted">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            <span>Registered as boarder</span>
                                        </span>
                                    <?php elseif ($status === 'contacted'): ?>
                                        <span class="inq-status-badge inq-status-contacted">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                            <span>Contacted</span>
                                        </span>
                                    <?php elseif ($status === 'closed'): ?>
                                        <span class="inq-status-badge inq-status-closed">
                                            <span class="w-1.5 h-1.5 rounded-full bg-neutral-400"></span>
                                            <span>Closed</span>
                                        </span>
                                    <?php else: ?>
                                        <span class="inq-status-badge inq-status-new">
                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                                            <span>New Inquiry</span>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Received Timestamp -->
                                <td class="text-xs text-neutral-500 whitespace-nowrap font-mono">
                                    <?= date('M j, g:i a', $createdTs) ?>
                                </td>

                                <!-- Action Button: view dossier -->
                                <td class="text-right whitespace-nowrap">
                                    <a href="/admin/inquiries/<?= $inqId ?>" class="inq-table-btn" title="Open complete inquiry record #<?= $inqId ?>">
                                        <span>View Dossier</span>
                                        <span aria-hidden="true">&rarr;</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Table empty search state -->
            <div id="inq-table-empty" class="inq-notif-empty" style="display: none;">
                <p class="font-semibold text-neutral-700">No matching inquiries found.</p>
                <p class="text-xs text-neutral-400 mt-1">Try clearing your search query to view all records.</p>
            </div>
        </section>
    <?php endif; ?>
</div>

<style>
/* ==========================================================================
   INQUIRY MANAGEMENT CENTER — LUMORA DESIGN SYSTEM
   Organized, responsive, clean, high-density front desk terminal
   ========================================================================== */

.inq-center-wrapper {
    max-width: 82rem;
    margin-left: auto;
    margin-right: auto;
    padding: 1.5rem 1rem 3.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
@media (min-width: 640px) {
    .inq-center-wrapper {
        padding: 1.75rem 1.5rem 4rem;
    }
}
@media (min-width: 1024px) {
    .inq-center-wrapper {
        padding: 2rem 2rem 4.5rem;
    }
}

/* Headings safety override against layout.php white-space nowrap */
.inq-center-wrapper h1,
.inq-center-wrapper h2,
.inq-center-wrapper h3,
.inq-center-wrapper h4 {
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: clip !important;
}

/* --------------------------------------------------------------------------
   Executive Page Banner & Quick Action Command Bar
   -------------------------------------------------------------------------- */
.dash-hero-banner {
    background: linear-gradient(135deg, #0a0a0a 0%, #161616 55%, #222220 100%);
    border-radius: 1rem;
    padding: 1.5rem 1.75rem;
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 8px 24px -6px rgba(10, 10, 10, 0.25);
    margin-bottom: 0.5rem;
    position: relative;
    overflow: hidden;
}
.dash-hero-banner::after {
    content: '';
    position: absolute;
    top: -40px;
    right: -40px;
    width: 220px;
    height: 220px;
    background: radial-gradient(circle, rgba(177, 95, 44, 0.18) 0%, rgba(177, 95, 44, 0) 70%);
    pointer-events: none;
}
.dash-hero-top {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    position: relative;
    z-index: 1;
}
@media (min-width: 640px) {
    .dash-hero-top {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}
.dash-hero-title {
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: -0.025em;
    color: #ffffff !important;
    line-height: 1.2;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.dash-hero-sub {
    font-size: 0.8125rem;
    color: #a3a19b;
    margin-top: 0.25rem;
}

/* Quick Action Command Bar inside Banner */
.dash-cmd-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1.25rem;
    padding-top: 1.15rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    position: relative;
    z-index: 1;
}
.dash-cmd-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #f1f0ee;
    background: rgba(255, 255, 255, 0.07);
    border: 1px solid rgba(255, 255, 255, 0.12);
    padding: 0.4rem 0.85rem;
    border-radius: 9999px;
    text-decoration: none;
    transition: all 160ms cubic-bezier(0.2, 0.8, 0.2, 1);
    white-space: nowrap;
}
.dash-cmd-btn:hover {
    background: rgba(255, 255, 255, 0.16);
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.25);
    transform: translateY(-1px);
}
.dash-cmd-btn.highlight {
    background: linear-gradient(135deg, rgba(177, 95, 44, 0.85) 0%, rgba(151, 80, 31, 0.95) 100%);
    border-color: rgba(255, 255, 255, 0.25);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(177, 95, 44, 0.35);
}
.dash-cmd-btn.highlight:hover {
    background: linear-gradient(135deg, rgba(207, 128, 71, 0.95) 0%, rgba(177, 95, 44, 1) 100%);
    box-shadow: 0 4px 12px rgba(177, 95, 44, 0.45);
}

/* Pulse animation for system health */
@keyframes pulse-emerald {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.85); }
}
.live-pulse-dot {
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 50%;
    background: #5fae84;
    display: inline-block;
    animation: pulse-emerald 2.2s ease-in-out infinite;
}

/* --------------------------------------------------------------------------
   Workspace Layout (Intake Studio + Notifications)
   -------------------------------------------------------------------------- */
.inq-workspace-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    align-items: start;
}
@media (min-width: 1024px) {
    .inq-workspace-grid {
        grid-template-columns: 7fr 5fr;
    }
}

/* Clean Cards */
.inq-card {
    background: #ffffff;
    border: 1px solid #e6e5e2;
    border-radius: 1rem;
    box-shadow: 0 1px 3px rgba(10, 10, 10, 0.03);
    overflow: hidden;
}
.inq-card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #f0eeeb;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    background: #fdfdfc;
}
@media (min-width: 640px) {
    .inq-card-header {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}
.inq-card-header-left {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
}
.inq-card-icon-badge {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 0.625rem;
    background: #fdf5ef;
    color: #b15f2c;
    border: 1px solid rgba(177, 95, 44, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.inq-card-icon-badge-amber {
    background: #fef7ee;
    color: #b45309;
    border-color: rgba(180, 83, 9, 0.15);
}
.inq-card-icon-badge-orange {
    background: #fff4ed;
    color: #b15f2c;
    border-color: rgba(177, 95, 44, 0.18);
}
.inq-card-title {
    font-size: 1.0625rem;
    font-weight: 700;
    color: #0a0a0a;
    line-height: 1.2;
    margin: 0;
}
.inq-card-sub {
    font-size: 0.8125rem;
    color: #6b6b6b;
    margin-top: 0.2rem;
}
.inq-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.25rem 0.625rem;
    border-radius: 9999px;
    white-space: nowrap;
}
.inq-badge-accent {
    background: #fdf5ef;
    color: #b15f2c;
    border: 1px solid rgba(177, 95, 44, 0.2);
}

/* --------------------------------------------------------------------------
   Walk-in Form Body & Inputs
   -------------------------------------------------------------------------- */
.inq-form-body {
    padding: 1.5rem;
}
.inq-form-section {
    padding-bottom: 1.25rem;
    margin-bottom: 1.25rem;
    border-bottom: 1px solid #f0eeeb;
}
.inq-form-section:last-of-type {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0.5rem;
}
.inq-section-title {
    font-size: 0.8125rem;
    font-weight: 700;
    color: #262524;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.inq-section-step {
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 50%;
    background: #0a0a0a;
    color: #ffffff;
    font-size: 0.6875rem;
    display: flex;
    align-items: center;
    justify-content: center;
}
.inq-form-grid-2 {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}
@media (min-width: 640px) {
    .inq-form-grid-2 {
        grid-template-columns: repeat(2, 1fr);
    }
}
.inq-field-group {
    display: flex;
    flex-direction: column;
}
.inq-label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #3b3a38;
    margin-bottom: 0.35rem;
}
.inq-req {
    color: #b42318;
}
.inq-opt {
    color: #8c8985;
    font-weight: 400;
}
.inq-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.inq-input-icon {
    position: absolute;
    left: 0.875rem;
    pointer-events: none;
    display: flex;
    align-items: center;
}
.inq-input {
    width: 100%;
    padding: 0.625rem 0.875rem;
    border: 1px solid #d4d2ce;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    color: #0a0a0a;
    background-color: #ffffff;
    transition: border-color 140ms ease, box-shadow 140ms ease;
}
.inq-input.has-icon {
    padding-left: 2.35rem;
}
.inq-input:focus {
    outline: none;
    border-color: #b15f2c;
    box-shadow: 0 0 0 3px rgba(177, 95, 44, 0.12);
}
.inq-hint {
    font-size: 0.75rem;
    color: #8c8985;
    margin-top: 0.25rem;
}
.inq-select {
    cursor: pointer;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.65rem center;
    background-repeat: no-repeat;
    background-size: 1.25em 1.25em;
    padding-right: 2.25rem;
    -webkit-appearance: none;
    appearance: none;
}
.inq-textarea {
    resize: vertical;
    min-height: 5rem;
    line-height: 1.5;
}
.inq-staff-notes-input {
    background-color: #fdfbf7;
    border-color: #ecdccb;
    color: #493322;
}
.inq-staff-notes-input:focus {
    border-color: #b15f2c;
    background-color: #ffffff;
}

/* Feedback & Alerts */
.inq-alert {
    padding: 0.875rem 1rem;
    border-radius: 0.625rem;
    font-size: 0.875rem;
    margin: 1rem 1.5rem 0;
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
}
.inq-alert-success {
    background: #edf7f1;
    border: 1px solid #c7e6d4;
    color: #1e5a3c;
}
.inq-alert-error {
    background: #fdf2f2;
    border: 1px solid #f9d2d2;
    color: #991b1b;
}
.inq-feedback-box {
    margin-top: 1rem;
    padding: 0.875rem 1rem;
    border-radius: 0.625rem;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Submit Action Button */
.inq-form-footer {
    margin-top: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.inq-btn-primary {
    width: 100%;
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #b15f2c 0%, #97501f 100%);
    color: #ffffff;
    border: none;
    border-radius: 0.5rem;
    font-size: 0.9375rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 160ms cubic-bezier(0.2, 0.8, 0.2, 1);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    box-shadow: 0 2px 6px rgba(177, 95, 44, 0.25);
}
.inq-btn-primary:hover {
    background: linear-gradient(135deg, #be6731 0%, #a45722 100%);
    box-shadow: 0 4px 12px rgba(177, 95, 44, 0.35);
    transform: translateY(-1px);
}
.inq-btn-primary:active {
    transform: translateY(0);
}
.inq-btn-primary:disabled {
    opacity: 0.65;
    cursor: not-allowed;
    transform: none;
}
.inq-footnote {
    font-size: 0.75rem;
    color: #787572;
    text-align: center;
    margin: 0;
}

/* --------------------------------------------------------------------------
   Notifications Dispatch Hub
   -------------------------------------------------------------------------- */
.inq-notif-container {
    display: flex;
    flex-direction: column;
    height: 100%;
}
.inq-notif-header {
    padding: 1.15rem 1.25rem;
    border-bottom: 1px solid #f0eeeb;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    background: #fdfdfc;
}
@media (min-width: 480px) {
    .inq-notif-header {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}
.inq-filter-tabs {
    display: flex;
    background: #f1f0ee;
    padding: 0.2rem;
    border-radius: 0.5rem;
    gap: 0.15rem;
}
.inq-filter-tab {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.35rem 0.65rem;
    border-radius: 0.375rem;
    border: none;
    background: transparent;
    color: #6b6b6b;
    cursor: pointer;
    transition: all 120ms ease;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.inq-filter-tab.active {
    background: #0a0a0a;
    color: #ffffff;
    box-shadow: 0 1px 3px rgba(10, 10, 10, 0.15);
}
.inq-tab-badge {
    font-size: 0.6875rem;
    background: #b15f2c;
    color: #ffffff;
    padding: 0.05rem 0.35rem;
    border-radius: 9999px;
    line-height: 1.2;
}

.inq-notif-scroll-area {
    max-height: 540px;
    overflow-y: auto;
    scrollbar-width: thin;
}
.inq-notif {
    padding: 0.95rem 1.25rem;
    border-bottom: 1px solid #f0eeeb;
    transition: background-color 140ms ease;
    position: relative;
}
.inq-notif:hover {
    background-color: #faf9f7;
}
.inq-notif.unread {
    background: #fdfaf6;
    border-left: 3px solid #b15f2c;
}
.inq-notif.unread:hover {
    background: #faf4ed;
}
.inq-notif-head {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.375rem 0.5rem;
}
.inq-notif-type {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.6875rem;
    font-weight: 600;
    line-height: 1.4;
    padding: 0.15rem 0.55rem;
    border-radius: 9999px;
    white-space: nowrap;
}
.inq-notif-flag {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: #b15f2c;
}
.inq-notif-time {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    margin-left: auto;
    font-size: 0.75rem;
    color: #787572;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}
.inq-notif-dot {
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 50%;
    background: #b15f2c;
    display: inline-block;
}
.inq-notif-msg {
    margin-top: 0.45rem;
    font-size: 0.8125rem;
    line-height: 1.5;
    color: #4a4947;
    overflow-wrap: anywhere;
}
.inq-notif.unread .inq-notif-msg {
    color: #0a0a0a;
    font-weight: 600;
}
.inq-notif-foot {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
    min-height: 1.875rem;
}
.inq-notif-link {
    font-size: 0.75rem;
    font-weight: 700;
    color: #0a0a0a;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    text-decoration: underline;
    text-decoration-color: #d4d2ce;
    text-underline-offset: 3px;
}
.inq-notif-link:hover {
    color: #b15f2c;
    text-decoration-color: #b15f2c;
}
.inq-notif-actions {
    display: flex;
    gap: 0.2rem;
    margin-left: auto;
}
.inq-notif-actions form {
    display: inline-flex;
    margin: 0;
}
.inq-notif-btn {
    width: 1.875rem;
    height: 1.875rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 0.375rem;
    background: transparent;
    color: #787572;
    cursor: pointer;
    transition: background-color 140ms, color 140ms;
}
.inq-notif-btn svg {
    width: 0.9375rem;
    height: 0.9375rem;
}
.inq-notif-btn:hover {
    background: #f1f0ee;
    color: #0a0a0a;
}
.inq-notif-btn.is-on {
    color: #b15f2c;
}
.inq-notif-btn.is-danger:hover {
    background: #fdf1f0;
    color: #b42318;
}

.inq-notif-empty {
    padding: 2.5rem 1.5rem;
    text-align: center;
}
.inq-empty-icon-wrap {
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    background: #f5f4f2;
    color: #8c8985;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.85rem;
}
.inq-notif-panel-footer {
    padding: 0.75rem 1.25rem;
    background: #fdfdfc;
    border-top: 1px solid #f0eeeb;
    text-align: center;
}
.inq-view-all-link {
    font-size: 0.75rem;
    font-weight: 700;
    color: #b15f2c;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    text-decoration: none;
}
.inq-view-all-link:hover {
    text-decoration: underline;
}

/* --------------------------------------------------------------------------
   Admin Recent Inquiries Ledger Table
   -------------------------------------------------------------------------- */
.inq-ledger-card {
    margin-top: 0.5rem;
}
.inq-ledger-header {
    background: #fdfdfc;
}
.inq-count-chip {
    font-size: 0.6875rem;
    font-weight: 700;
    background: #0a0a0a;
    color: #ffffff;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    letter-spacing: 0.02em;
}
.inq-ledger-controls {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.inq-search-box {
    position: relative;
    display: flex;
    align-items: center;
}
.inq-search-box svg {
    position: absolute;
    left: 0.75rem;
    pointer-events: none;
}
.inq-search-input {
    padding: 0.45rem 0.85rem 0.45rem 2.2rem;
    font-size: 0.8125rem;
    border: 1px solid #d4d2ce;
    border-radius: 0.5rem;
    background: #ffffff;
    width: 14rem;
    transition: all 140ms ease;
}
.inq-search-input:focus {
    outline: none;
    border-color: #b15f2c;
    box-shadow: 0 0 0 3px rgba(177, 95, 44, 0.12);
    width: 17rem;
}

.inq-table-responsive {
    overflow-x: auto;
    scrollbar-width: thin;
}
.inq-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: 0.8125rem;
}
.inq-table thead th {
    background: #f9f8f6;
    padding: 0.75rem 1rem;
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6b6b6b;
    border-bottom: 1px solid #e6e5e2;
    white-space: nowrap;
}
.inq-table tbody td {
    padding: 0.85rem 1rem;
    vertical-align: middle;
}
.inq-avatar-circle {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 50%;
    background: linear-gradient(135deg, #fdf5ef 0%, #faede2 100%);
    color: #b15f2c;
    border: 1px solid rgba(177, 95, 44, 0.25);
    font-weight: 800;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.inq-phone-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-family: monospace;
    font-size: 0.75rem;
    font-weight: 600;
    color: #262524;
    background: #f4f3f0;
    padding: 0.15rem 0.45rem;
    border-radius: 0.375rem;
    text-decoration: none;
    transition: background-color 140ms;
}
.inq-phone-badge:hover {
    background: #e8e6e1;
    color: #0a0a0a;
}
.inq-tier-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #3b3a38;
    background: #fbf8f4;
    border: 1px solid #ebdccf;
    padding: 0.25rem 0.6rem;
    border-radius: 0.5rem;
    max-width: 14rem;
}
.inq-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.6875rem;
    font-weight: 700;
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    white-space: nowrap;
}
.inq-status-new {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}
.inq-status-contacted {
    background: #fefce8;
    color: #a16207;
    border: 1px solid #fef08a;
}
.inq-status-converted {
    background: #f0fdf4;
    color: #15803d;
    border: 1px solid #bbf7d0;
}
.inq-status-closed {
    background: #f5f5f4;
    color: #57534e;
    border: 1px solid #e7e5e4;
}

.inq-table-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: #0a0a0a;
    background: #f5f4f2;
    border: 1px solid #e6e5e2;
    padding: 0.35rem 0.75rem;
    border-radius: 0.5rem;
    text-decoration: none;
    transition: all 140ms ease;
}
.inq-table-btn:hover {
    background: #0a0a0a;
    color: #ffffff;
    border-color: #0a0a0a;
    transform: translateY(-1px);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // === 1. Philippine Mobile Phone Digit Formatter & Guard ===
    const inqPhone = document.getElementById('inq-phone');
    if (inqPhone) {
        inqPhone.addEventListener('keypress', function(ev) {
            if (!/[0-9]/.test(ev.key) && !ev.ctrlKey && !ev.metaKey && ev.key !== 'Enter') {
                ev.preventDefault();
            }
        });
        inqPhone.addEventListener('input', function() {
            let val = inqPhone.value.replace(/\D/g, '');
            if (val.startsWith('639') && val.length >= 12) {
                val = '0' + val.slice(2);
            }
            inqPhone.value = val.slice(0, 11);
        });
    }

    // === 2. Walk-in Inquiry Form AJAX Submission ===
    const inquiryForm = document.getElementById('staff-inquiry-form');
    const feedbackEl = document.getElementById('inquiry-feedback');
    const submitBtn = document.getElementById('btn-submit-inquiry');

    if (inquiryForm && feedbackEl) {
        inquiryForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(inquiryForm);

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<svg class="animate-spin w-4 h-4 text-white inline mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> <span>Transmitting Intake...</span>';
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

                feedbackEl.style.display = 'flex';
                if (response.ok && result.success) {
                    feedbackEl.className = 'inq-feedback-box inq-alert-success';
                    feedbackEl.innerHTML = `<svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> <div><strong class="block text-sm">Success!</strong><span class="text-xs">${String(result.message).replace(/[&<>"']/g, c => '&#' + c.charCodeAt(0) + ';')}</span></div>`;
                    inquiryForm.reset();
                    
                    // Smooth reload after 1.5s to display freshly logged inquiry and notifications
                    setTimeout(function() {
                        window.location.reload();
                    }, 1400);
                } else {
                    feedbackEl.className = 'inq-feedback-box inq-alert-error';
                    feedbackEl.innerHTML = `<svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> <div><strong class="block text-sm">Unable to submit inquiry</strong><span class="text-xs">${String(result.error || 'Please review your input fields.').replace(/[&<>"']/g, c => '&#' + c.charCodeAt(0) + ';')}</span></div>`;
                }
            } catch (err) {
                feedbackEl.style.display = 'flex';
                feedbackEl.className = 'inq-feedback-box inq-alert-error';
                feedbackEl.innerHTML = `<svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> <div><strong class="block text-sm">Network communication error</strong><span class="text-xs">Failed to reach the server. Please verify your connection and try again.</span></div>`;
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> <span>Submit Room Inquiry</span>';
                }
            }
        });
    }

    // === 3. Notification Hub Filter Segmented Controls ===
    const filterAll = document.getElementById('filter-all');
    const filterUnread = document.getElementById('filter-unread');
    const filterPinned = document.getElementById('filter-pinned');

    function setActiveFilter(activeButton) {
        [filterAll, filterUnread, filterPinned].forEach(btn => {
            if (btn) {
                btn.classList.remove('active');
                btn.setAttribute('aria-selected', 'false');
            }
        });
        if (activeButton) {
            activeButton.classList.add('active');
            activeButton.setAttribute('aria-selected', 'true');
        }
    }

    function filterNotifications(filterMode) {
        const notifItems = document.querySelectorAll('.notification-item');
        let visibleCount = 0;

        notifItems.forEach(item => {
            const isUnread = item.dataset.read === '0';
            const isPinned = item.dataset.pinned === '1';

            let show = true;
            if (filterMode === 'unread' && !isUnread) show = false;
            if (filterMode === 'pinned' && !isPinned) show = false;

            item.style.display = show ? 'block' : 'none';
            if (show) visibleCount++;
        });

        const emptyMessage = document.getElementById('notification-filter-empty');
        if (emptyMessage) {
            emptyMessage.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    if (filterAll) filterAll.addEventListener('click', () => { setActiveFilter(filterAll); filterNotifications('all'); });
    if (filterUnread) filterUnread.addEventListener('click', () => { setActiveFilter(filterUnread); filterNotifications('unread'); });
    if (filterPinned) filterPinned.addEventListener('click', () => { setActiveFilter(filterPinned); filterNotifications('pinned'); });

    // === 4. Inquiries Ledger Live Search Filter ===
    const tableSearch = document.getElementById('inq-table-search');
    const ledgerTable = document.getElementById('inquiries-ledger-table');
    const tableEmpty = document.getElementById('inq-table-empty');

    if (tableSearch && ledgerTable) {
        tableSearch.addEventListener('input', function() {
            const query = tableSearch.value.trim().toLowerCase();
            const rows = ledgerTable.querySelectorAll('tbody tr.inq-table-row');
            let matchedCount = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const matches = query === '' || text.includes(query);
                row.style.display = matches ? '' : 'none';
                if (matches) matchedCount++;
            });

            if (tableEmpty) {
                tableEmpty.style.display = matchedCount === 0 ? 'block' : 'none';
            }
        });
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
?>