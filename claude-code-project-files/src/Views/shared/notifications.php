<?php
$pageTitle = 'Notifications';
ob_start();
$error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

// Compute summary metrics
$totalCount = count($notifications ?? []);
$unreadCount = count(array_filter($notifications ?? [], fn($n) => empty($n['is_read'])));
$pinnedCount = count(array_filter($notifications ?? [], fn($n) => !empty($n['is_pinned'])));
$readCount = $totalCount - $unreadCount;
?>
<div class="max-w-5xl mx-auto px-5 py-5 space-y-5">

    <!-- Executive Page Banner with Quick Launch Command Bar -->
    <header class="dash-hero-banner">
        <div class="dash-hero-top">
            <div>
                <h1 class="dash-hero-title">
                    <span>Notifications &amp; Activity Log</span>
                </h1>
                <p class="dash-hero-sub">Complete alert history, emergency broadcasts, operational notifications, and important notices.</p>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="badge" style="background:rgba(255,255,255,0.08); color:#8cc2a2; border:1px solid rgba(255,255,255,0.15); padding:0.35rem 0.85rem; font-size:0.75rem; font-weight:600; border-radius:9999px; display:inline-flex; align-items:center;">
                    <span class="live-pulse-dot" style="margin-right:0.45rem;"></span>
                    Live Activity
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
            <button type="button" onclick="const b=document.querySelector('[data-filter=unread]');if(b)b.click();" class="dash-cmd-btn highlight" title="Show unread alerts only">
                <?= \App\Support\Icon::svg('alert-circle', 14) ?>
                <span>Unread Alerts (<?= $unreadCount ?>)</span>
            </button>
            <button type="button" onclick="const b=document.querySelector('[data-filter=pinned]');if(b)b.click();" class="dash-cmd-btn" title="Show pinned important notices">
                <?= \App\Support\Icon::svg('pin', 14) ?>
                <span>Pinned Important (<?= $pinnedCount ?>)</span>
            </button>
            <button type="button" onclick="const b=document.querySelector('[data-filter=all]');if(b)b.click();" class="dash-cmd-btn" title="Show all notifications">
                <?= \App\Support\Icon::svg('bell', 14) ?>
                <span>All Alerts (<?= $totalCount ?>)</span>
            </button>
            <?php if ($unreadCount > 0): ?>
                <form method="post" action="/notifications/read-all" style="display:inline-flex; margin:0;">
                    <?= \App\Support\Csrf::field() ?>
                    <button type="submit" class="dash-cmd-btn" title="Mark all unread notifications as read">
                        <?= \App\Support\Icon::svg('check', 14) ?>
                        <span>Mark All Read</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($error): ?>
        <div class="bg-error-50 text-error-700 text-body-sm rounded-lg p-3 border border-error-500 flex items-center gap-2" role="alert">
            <?= \App\Support\Icon::svg('alert-triangle', 16) ?>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-emerald-50 text-emerald-800 text-body-sm rounded-lg p-3 border border-emerald-300 flex items-center gap-2" role="alert">
            <?= \App\Support\Icon::svg('check', 16) ?>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="kpi-grid">
        <button type="button" class="reveal-card kpi-card metric-accent-primary text-left w-full cursor-pointer notif-kpi-card" data-filter="all">
            <div class="kpi-card-header">
                <span class="kpi-label">Total Alerts</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('bell') ?></span>
            </div>
            <div class="kpi-value"><?= $totalCount ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Recorded notifications</span>
            </div>
        </button>

        <button type="button" class="reveal-card kpi-card metric-accent-warning text-left w-full cursor-pointer notif-kpi-card" data-filter="unread">
            <div class="kpi-card-header">
                <span class="kpi-label">Unread Alerts</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('alert-circle') ?></span>
            </div>
            <div class="kpi-value <?= $unreadCount > 0 ? 'text-amber-600' : 'text-neutral-900' ?>"><?= $unreadCount ?></div>
            <div class="kpi-subtext">
                <?php if ($unreadCount > 0): ?>
                    <span class="text-amber-600 font-semibold">Requires attention</span>
                <?php else: ?>
                    <span class="text-neutral-400">All caught up</span>
                <?php endif; ?>
            </div>
        </button>

        <button type="button" class="reveal-card kpi-card metric-accent-error text-left w-full cursor-pointer notif-kpi-card" data-filter="pinned">
            <div class="kpi-card-header">
                <span class="kpi-label">Pinned Important</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('pin') ?></span>
            </div>
            <div class="kpi-value text-neutral-900"><?= $pinnedCount ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Starred for reference</span>
            </div>
        </button>

        <button type="button" class="reveal-card kpi-card metric-accent-success text-left w-full cursor-pointer notif-kpi-card" data-filter="read">
            <div class="kpi-card-header">
                <span class="kpi-label">Resolved / Read</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('check-circle') ?></span>
            </div>
            <div class="kpi-value text-neutral-900"><?= $readCount ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Acknowledged notices</span>
            </div>
        </button>
    </div>

    <!-- Main Notifications Log Section -->
    <div>
        <div class="section-header">
            <div class="flex items-center gap-2">
                <h2 class="text-heading-sm font-semibold text-neutral-900">Notification History</h2>
                <span class="text-caption text-neutral-500 font-medium bg-neutral-100 px-2.5 py-1 rounded-full">
                    <?= $totalCount ?> total
                </span>
            </div>
            <?php if ($unreadCount > 0): ?>
                <form method="post" action="/notifications/read-all" class="inline">
                    <?= \App\Support\Csrf::field() ?>
                    <button type="submit"
                            class="btn btn-secondary !py-1.5 !px-3 !text-xs inline-flex items-center gap-1.5 shadow-xs cursor-pointer">
                        <span><?= \App\Support\Icon::svg('check') ?></span>
                        <span>Mark All as Read</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Sticky Command / Navigation Bar -->
        <div class="rooms-sticky-nav" id="notifications-command-bar">
            <!-- Segmented Tab Navigation -->
            <nav class="rooms-nav-list" id="notifications-subnav" aria-label="Notification filter navigation">
                <button type="button" class="rooms-nav-tab notif-filter-btn active active-filter" data-filter="all" title="View all notifications">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"></circle></svg>
                    <span>All Alerts</span>
                    <span class="rooms-nav-badge"><?= $totalCount ?></span>
                </button>
                <button type="button" class="rooms-nav-tab notif-filter-btn" data-filter="unread" title="Filter unread notifications">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <span>Unread</span>
                    <span class="rooms-nav-badge" style="<?= $unreadCount > 0 ? 'background:#e09b52; color:#fff;' : '' ?>"><?= $unreadCount ?></span>
                </button>
                <button type="button" class="rooms-nav-tab notif-filter-btn" data-filter="pinned" title="Filter pinned important alerts">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="12" y1="17" x2="12" y2="22"></line><path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"></path></svg>
                    <span>Pinned</span>
                    <span class="rooms-nav-badge"><?= $pinnedCount ?></span>
                </button>
                <button type="button" class="rooms-nav-tab notif-filter-btn" data-filter="read" title="Filter acknowledged notifications">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <span>Read</span>
                    <span class="rooms-nav-badge"><?= $readCount ?></span>
                </button>
                <button type="button" class="rooms-nav-tab notif-filter-btn" data-filter="sos" title="Filter emergency SOS alerts">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 18h12"></path><path d="M12 2v4"></path><path d="m4.93 4.93 2.83 2.83"></path><path d="m19.07 4.93-2.83 2.83"></path><path d="M4 12h16"></path><path d="M12 12v6"></path></svg>
                    <span>SOS</span>
                </button>
                <button type="button" class="rooms-nav-tab notif-filter-btn" data-filter="maintenance" title="Filter maintenance and repair updates">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                    <span>Maintenance</span>
                </button>
                <button type="button" class="rooms-nav-tab notif-filter-btn" data-filter="billing" title="Filter billing and payment alerts">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                    <span>Billing</span>
                </button>
                <button type="button" class="rooms-nav-tab notif-filter-btn" data-filter="inquiry" title="Filter inquiry alerts">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect></svg>
                    <span>Inquiry</span>
                </button>
            </nav>

            <!-- Search, View Mode & Action -->
            <div class="nav-toolbar-controls">
                <div class="nav-search-wrap">
                    <input type="text" id="notif-search" placeholder="Search notifications..." class="nav-search-input" autocomplete="off">
                    <span class="nav-search-icon">
                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    </span>
                    <button type="button" id="notif-search-clear" class="nav-search-clear" aria-label="Clear search">×</button>
                </div>
                <div class="view-mode-toggle" id="notif-view-mode-toggle" title="Quick filter view">
                    <button type="button" class="view-mode-btn active" data-quick="all">
                        All
                    </button>
                    <button type="button" class="view-mode-btn" data-quick="unread">
                        Unread
                    </button>
                </div>
            </div>
        </div>

        <!-- Notification List Card (id="notif-dropdown" for full backward compatibility) -->
        <div class="card overflow-hidden" id="notif-dropdown">
            <div class="divide-y divide-neutral-100">
                <?php foreach ($notifications as $n): ?>
                    <?php
                    $isRead = !empty($n['is_read']);
                    $isPinned = !empty($n['is_pinned']);
                    $type = strtolower($n['type'] ?? 'general');

                    $typeConfig = \App\Support\NotificationType::config($type);

                    $isBilling = str_contains($type, 'rent') || str_contains($type, 'payment') || str_contains($type, 'penalty');
                    $isSos = str_contains($type, 'sos');
                    $isMaint = str_contains($type, 'maintenance') || str_contains($type, 'repair');
                    $isInquiry = str_contains($type, 'inquiry');

                    $filterTags = [
                        'all',
                        $isPinned ? 'pinned' : '',
                        $isRead ? 'read' : 'unread',
                        $isBilling ? 'billing' : '',
                        $isSos ? 'sos' : '',
                        $isMaint ? 'maintenance' : '',
                        $isInquiry ? 'inquiry' : '',
                        $type
                    ];
                    ?>
                    <div class="p-4 sm:p-5 transition-colors notif-item-row <?= $isPinned ? 'bg-amber-50/25 border-l-4 border-l-amber-500' : ($isRead ? 'hover:bg-neutral-50/80' : 'bg-primary-50/20 border-l-4 border-l-primary-600') ?>"
                         data-tags="<?= htmlspecialchars(implode(' ', array_filter($filterTags))) ?>"
                         data-search="<?= htmlspecialchars(strtolower(($n['message'] ?? '') . ' ' . $typeConfig['label'])) ?>">
                        <div class="flex items-start justify-between gap-4">
                            <!-- Left: Icon & Content -->
                            <div class="flex items-start gap-3.5 flex-1 min-w-0">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 text-base shadow-xs mt-0.5"
                                     style="background: <?= $typeConfig['bg'] ?>; color: <?= $typeConfig['color'] ?>; border: 1px solid <?= $typeConfig['color'] ?>25;">
                                    <?= $typeConfig['icon'] ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap mb-1">
                                        <span class="badge <?= $typeConfig['badge'] ?> text-[10px] font-bold uppercase tracking-wider">
                                            <?= htmlspecialchars($typeConfig['label']) ?>
                                        </span>
                                        <span class="id-tag">#<?= (int) $n['id'] ?></span>
                                        <?php if ($isPinned): ?>
                                            <span class="badge badge-warning font-semibold text-[10px]">
                                                <?= \App\Support\Icon::svg('pin') ?> Pinned
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!$isRead): ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-primary-700 bg-primary-100 px-2 py-0.5 rounded-full">
                                                <span class="w-1.5 h-1.5 rounded-full bg-primary-600"></span>
                                                Unread
                                            </span>
                                        <?php endif; ?>
                                        <span class="text-caption text-neutral-400 font-normal">
                                            <?= !empty($n['created_at']) ? date('M j, Y • g:i a', strtotime($n['created_at'])) : '—' ?>
                                        </span>
                                    </div>
                                    <div class="text-sm text-neutral-800 font-medium leading-relaxed mt-1 break-words">
                                        <?= htmlspecialchars($n['message'] ?? '') ?>
                                    </div>
                                    <?php if (!empty($n['action_url'])): ?>
                                        <div class="mt-2.5">
                                            <a href="<?= htmlspecialchars($n['action_url']) ?>"
                                               class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-semibold bg-neutral-900 text-white hover:bg-neutral-800 transition-colors shadow-xs">
                                                <span><?= htmlspecialchars($typeConfig['actionText'] ?? 'View & Take Action') ?></span>
                                                <span aria-hidden="true">&rarr;</span>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Right: Action Buttons -->
                            <div class="action-cluster flex-shrink-0 flex items-center gap-1.5">
                                <!-- Pin / Unpin Form -->
                                <form method="post" action="/notifications/<?= (int) $n['id'] ?>/toggle-pin" class="inline">
                                    <?= \App\Support\Csrf::field() ?>
                                    <button type="submit"
                                            class="btn <?= $isPinned ? 'btn-primary' : 'btn-secondary' ?> !py-1 !px-2.5 !text-xs cursor-pointer shadow-xs"
                                            title="<?= $isPinned ? 'Unpin this notification' : 'Pin to top as important' ?>">
                                        <?= \App\Support\Icon::svg('pin') ?> <?= $isPinned ? 'Pinned' : 'Pin' ?>
                                    </button>
                                </form>

                                <!-- Mark Read / Unread Form -->
                                <form method="post" action="/notifications/<?= (int) $n['id'] ?>/toggle-read" class="inline">
                                    <?= \App\Support\Csrf::field() ?>
                                    <input type="hidden" name="is_read" value="<?= $isRead ? '1' : '0' ?>">
                                    <button type="submit"
                                            class="btn btn-secondary !py-1 !px-2.5 !text-xs cursor-pointer shadow-xs"
                                            title="<?= $isRead ? 'Mark as unread' : 'Mark as read' ?>">
                                        <?= \App\Support\Icon::svg($isRead ? 'rotate-ccw' : 'check') ?> <?= $isRead ? 'Unread' : 'Read' ?>
                                    </button>
                                </form>

                                <!-- Delete Form -->
                                <form method="post" action="/notifications/<?= (int) $n['id'] ?>/delete" class="inline"
                                      data-confirm="Dismiss and remove this notification?"
                                      data-confirm-title="Remove Notification?"
                                      data-confirm-btn="Dismiss"
                                      data-confirm-danger>
                                    <?= \App\Support\Csrf::field() ?>
                                    <button type="submit"
                                            class="btn-danger !py-1 !px-2 !text-xs cursor-pointer"
                                            title="Delete notification" aria-label="Delete notification">
                                        <?= \App\Support\Icon::svg('x') ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($notifications)): ?>
                    <div class="empty-state" style="padding:4rem 0;">
                        <span class="empty-icon"><?= \App\Support\Icon::svg('bell') ?></span>
                        <span class="text-sm font-semibold text-neutral-800 mt-2 block">No notifications yet</span>
                        <span class="text-xs text-neutral-500 mt-0.5 block">When activities, repairs, or emergency alerts occur, they will be logged here.</span>
                    </div>
                <?php endif; ?>

                <!-- Client-side filter no match row -->
                <div id="no-filter-match" class="empty-state hidden" style="padding:3.5rem 0;">
                    <span class="empty-icon"><?= \App\Support\Icon::svg('search') ?></span>
                    <span class="text-sm font-medium text-neutral-600 mt-2 block">No notifications match your current filter or search query.</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.notif-filter-btn.active-filter {
    background-color: #0a0a0a !important;
    color: #ffffff !important;
    border-color: #0a0a0a !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const filterButtons = document.querySelectorAll('.notif-filter-btn');
    const searchInput = document.getElementById('notif-search');
    const rows = document.querySelectorAll('.notif-item-row');
    const noMatch = document.getElementById('no-filter-match');

    let currentFilter = 'all';
    let searchQuery = '';

    function applyFilters() {
        let visibleCount = 0;
        rows.forEach(row => {
            const tags = (row.getAttribute('data-tags') || '').split(' ');
            const searchData = row.getAttribute('data-search') || '';

            const matchesFilter = currentFilter === 'all' || tags.includes(currentFilter);
            const matchesSearch = !searchQuery || searchData.includes(searchQuery);

            if (matchesFilter && matchesSearch) {
                row.classList.remove('hidden');
                visibleCount++;
            } else {
                row.classList.add('hidden');
            }
        });

        if (noMatch) {
            if (visibleCount === 0 && rows.length > 0) {
                noMatch.classList.remove('hidden');
            } else {
                noMatch.classList.add('hidden');
            }
        }
    }

    filterButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            filterButtons.forEach(b => {
                b.classList.remove('active-filter');
                b.classList.remove('active');
            });
            btn.classList.add('active-filter');
            btn.classList.add('active');
            currentFilter = btn.getAttribute('data-filter');
            applyFilters();

            // Sync quick view mode toggle if applicable
            document.querySelectorAll('#notif-view-mode-toggle .view-mode-btn').forEach(qb => {
                qb.classList.toggle('active', qb.getAttribute('data-quick') === currentFilter);
            });
        });
    });

    document.querySelectorAll('.notif-kpi-card').forEach(card => {
        card.addEventListener('click', () => {
            const filter = card.getAttribute('data-filter');
            const targetBtn = document.querySelector(`.notif-filter-btn[data-filter="${filter}"]`);
            if (targetBtn) {
                targetBtn.click();
            }
            document.getElementById('notif-dropdown')?.scrollIntoView({ behavior: 'smooth' });
        });
    });

    // Quick filter view toggle (All vs Unread)
    document.querySelectorAll('#notif-view-mode-toggle .view-mode-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const quick = btn.getAttribute('data-quick');
            const targetBtn = document.querySelector(`.notif-filter-btn[data-filter="${quick}"]`);
            if (targetBtn) {
                targetBtn.click();
            }
        });
    });

    const notifSearchClear = document.getElementById('notif-search-clear');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value.toLowerCase().trim();
            if (notifSearchClear) notifSearchClear.classList.toggle('visible', searchQuery.length > 0);
            applyFilters();
        });
        if (notifSearchClear) {
            notifSearchClear.addEventListener('click', () => {
                searchInput.value = '';
                notifSearchClear.classList.remove('visible');
                searchQuery = '';
                applyFilters();
                searchInput.focus();
            });
        }
    }

});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
