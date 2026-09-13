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

    <!-- Page Banner -->
    <div class="page-banner">
        <div>
            <h1 class="text-heading-lg font-bold">Notifications &amp; Activity Log</h1>
            <p class="text-body-sm">Complete alert history, emergency broadcasts &amp; important notices</p>
        </div>
        <span class="badge badge-success" style="background:rgba(255,255,255,0.12); color:#86efac; border:1px solid rgba(255,255,255,0.15);">
            <span style="width:0.4rem;height:0.4rem;border-radius:50%;background:#4ade80;display:inline-block;margin-right:0.4rem;"></span>
            Live Activity
        </span>
    </div>

    <?php if ($error): ?>
        <div class="bg-error-50 text-error-700 text-body-sm rounded-lg p-3 border border-error-500 flex items-center gap-2" role="alert">
            <span style="font-size:1rem;">⚠</span>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-emerald-50 text-emerald-800 text-body-sm rounded-lg p-3 border border-emerald-300 flex items-center gap-2" role="alert">
            <span style="font-size:1rem;">✓</span>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="reveal-card card p-4 metric-accent-primary">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Alerts</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $totalCount ?></p>
            <p class="text-caption text-neutral-400 mt-1">Recorded notifications</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-warning">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Unread Alerts</p>
            <p class="text-3xl font-bold <?= $unreadCount > 0 ? 'text-amber-600' : 'text-neutral-900' ?> mt-1.5"><?= $unreadCount ?></p>
            <?php if ($unreadCount > 0): ?>
                <p class="text-caption text-amber-600 mt-1 font-semibold">Requires attention</p>
            <?php else: ?>
                <p class="text-caption text-neutral-400 mt-1">All caught up</p>
            <?php endif; ?>
        </div>
        <div class="reveal-card card p-4 metric-accent-error">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Pinned Important</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $pinnedCount ?></p>
            <p class="text-caption text-neutral-400 mt-1">Starred for reference</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-success">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Resolved / Read</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $readCount ?></p>
            <p class="text-caption text-neutral-400 mt-1">Acknowledged notices</p>
        </div>
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
                        <span>✓</span>
                        <span>Mark All as Read</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Filter & Search Bar -->
        <div class="card p-3 mb-3 flex flex-wrap items-center justify-between gap-3 bg-white">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider mr-1">Filter:</span>
                <button type="button" class="btn btn-secondary !py-1 !px-2.5 !text-xs notif-filter-btn active-filter" data-filter="all">
                    All (<?= $totalCount ?>)
                </button>
                <button type="button" class="btn btn-secondary !py-1 !px-2.5 !text-xs notif-filter-btn" data-filter="pinned">
                    📌 Pinned (<?= $pinnedCount ?>)
                </button>
                <button type="button" class="btn btn-secondary !py-1 !px-2.5 !text-xs notif-filter-btn" data-filter="unread">
                    Unread (<?= $unreadCount ?>)
                </button>
                <button type="button" class="btn btn-secondary !py-1 !px-2.5 !text-xs notif-filter-btn" data-filter="read">
                    Read (<?= $readCount ?>)
                </button>
            </div>
            <div class="relative flex-1 sm:max-w-xs min-w-[200px]">
                <input type="text"
                       id="notif-search"
                       placeholder="Search notifications..."
                       class="input input-sm w-full"
                       style="padding-left: 2rem;">
                <span style="position:absolute;left:0.625rem;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.875rem;">🔍</span>
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

                    // Classification
                    $typeConfig = match(true) {
                        str_contains($type, 'sos') => [
                            'icon' => '🚨',
                            'badge' => 'badge-error',
                            'label' => 'Emergency SOS',
                            'bg' => '#fef2f2',
                            'color' => '#dc2626'
                        ],
                        str_contains($type, 'maintenance') || str_contains($type, 'repair') => [
                            'icon' => '🔧',
                            'badge' => 'badge-info',
                            'label' => 'Maintenance',
                            'bg' => '#f0fdf4',
                            'color' => '#16a34a'
                        ],
                        str_contains($type, 'rent') || str_contains($type, 'payment') || str_contains($type, 'penalty') => [
                            'icon' => '💳',
                            'badge' => 'badge-warning',
                            'label' => 'Billing Notice',
                            'bg' => '#fffbeb',
                            'color' => '#d97706'
                        ],
                        default => [
                            'icon' => '🔔',
                            'badge' => 'badge-neutral',
                            'label' => 'System Alert',
                            'bg' => '#f1f5f9',
                            'color' => '#475569'
                        ],
                    };

                    $filterTags = [
                        'all',
                        $isPinned ? 'pinned' : '',
                        $isRead ? 'read' : 'unread',
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
                                                📌 Pinned
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
                                        <?= $isPinned ? '📌 Pinned' : '📍 Pin' ?>
                                    </button>
                                </form>

                                <!-- Mark Read / Unread Form -->
                                <form method="post" action="/notifications/<?= (int) $n['id'] ?>/toggle-read" class="inline">
                                    <?= \App\Support\Csrf::field() ?>
                                    <input type="hidden" name="is_read" value="<?= $isRead ? '1' : '0' ?>">
                                    <button type="submit"
                                            class="btn btn-secondary !py-1 !px-2.5 !text-xs cursor-pointer shadow-xs"
                                            title="<?= $isRead ? 'Mark as unread' : 'Mark as read' ?>">
                                        <?= $isRead ? '↺ Unread' : '✓ Read' ?>
                                    </button>
                                </form>

                                <!-- Delete Form -->
                                <form method="post" action="/notifications/<?= (int) $n['id'] ?>/delete" class="inline"
                                      onsubmit="return confirm('Dismiss and remove this notification?');">
                                    <?= \App\Support\Csrf::field() ?>
                                    <button type="submit"
                                            class="btn-danger !py-1 !px-2 !text-xs cursor-pointer"
                                            title="Delete notification">
                                        ✕
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($notifications)): ?>
                    <div class="empty-state" style="padding:4rem 0;">
                        <span style="font-size:2.5rem;">🔔</span>
                        <span class="text-sm font-semibold text-neutral-800 mt-2 block">No notifications yet</span>
                        <span class="text-xs text-neutral-500 mt-0.5 block">When activities, repairs, or emergency alerts occur, they will be logged here.</span>
                    </div>
                <?php endif; ?>

                <!-- Client-side filter no match row -->
                <div id="no-filter-match" class="empty-state hidden" style="padding:3.5rem 0;">
                    <span style="font-size:2rem;">🔍</span>
                    <span class="text-sm font-medium text-neutral-600 mt-2 block">No notifications match your current filter or search query.</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.notif-filter-btn.active-filter {
    background-color: #0f172a !important;
    color: #ffffff !important;
    border-color: #0f172a !important;
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
            filterButtons.forEach(b => b.classList.remove('active-filter'));
            btn.classList.add('active-filter');
            currentFilter = btn.getAttribute('data-filter');
            applyFilters();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value.toLowerCase().trim();
            applyFilters();
        });
    }

    // GSAP reveal animation matching dashboard and boarders
    if (window.gsap && window.ScrollTrigger) {
        let mm = gsap.matchMedia();
        mm.add({
            animate: "(prefers-reduced-motion: no-preference)",
            reduce: "(prefers-reduced-motion: reduce)",
        }, (context) => {
            if (context.conditions.animate) {
                gsap.from(".reveal-card", {
                    y: 16,
                    opacity: 0,
                    duration: 0.45,
                    stagger: 0.08,
                    ease: "power2.out",
                    scrollTrigger: { trigger: ".reveal-card", start: "top 90%", once: true },
                });
            } else {
                gsap.set(".reveal-card", { opacity: 1, y: 0 });
            }
        });
    }
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
