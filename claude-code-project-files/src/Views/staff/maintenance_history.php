<?php
$pageTitle = 'Maintenance History';
ob_start();

// Counts come from the database over everything the filters match; counting $history would
// only ever describe the 50 rows on this page (WP-21).
$counts = $counts ?? ['total' => count($history ?? []), 'resolved' => 0, 'open' => 0];
$totalHistory = $counts['total'];
$resolvedCount = $counts['resolved'];
$openCount = $counts['open'];

$filters = $filters ?? ['q' => '', 'status' => '', 'tier' => ''];
$isFiltered = implode('', $filters) !== '';
/** This page's URL with one page number, so paging never loses the search. */
$pageUrl = fn (int $n) => '/staff/maintenance/history?' . http_build_query(array_filter($filters) + ['page' => $n]);

$tierBadges = [
    'critical' => 'badge-error',
    'high'     => 'badge-warning',
    'medium'   => 'badge-info',
    'low'      => 'badge-neutral',
];

$currentPage = $page ?? 1;
$totalPages = $totalPages ?? 1;
?>
<div class="max-w-5xl mx-auto px-5 py-5 space-y-5">

    <!-- Executive Page Banner with Quick Launch Command Bar -->
    <header class="dash-hero-banner">
        <div class="dash-hero-top">
            <div>
                <h1 class="dash-hero-title">
                    <span>Maintenance History</span>
                </h1>
                <p class="dash-hero-sub">Complete historical archive of all facility repairs, work tickets, and resolution records.</p>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="badge" style="background:rgba(255,255,255,0.08); color:#8cc2a2; border:1px solid rgba(255,255,255,0.15); padding:0.35rem 0.85rem; font-size:0.75rem; font-weight:600; border-radius:9999px; display:inline-flex; align-items:center;">
                    <span class="live-pulse-dot" style="margin-right:0.45rem;"></span>
                    Full Archive Active
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
            <a href="/staff/maintenance" class="dash-cmd-btn highlight" title="Open active maintenance queue">
                <?= \App\Support\Icon::svg('wrench', 14) ?>
                <span>Active Queue (<?= $openCount ?> open)</span>
            </a>
            <a href="#maintenance-history-table" class="dash-cmd-btn" title="Jump to maintenance records table">
                <?= \App\Support\Icon::svg('clipboard', 14) ?>
                <span>Resolved Records (<?= $resolvedCount ?>)</span>
            </a>
            <a href="/staff/incidents/history" class="dash-cmd-btn" title="Open incident history records">
                <?= \App\Support\Icon::svg('shield-check', 14) ?>
                <span>Incident History</span>
            </a>
            <a href="/staff/dashboard" class="dash-cmd-btn" title="Back to staff dashboard">
                <?= \App\Support\Icon::svg('dashboard', 14) ?>
                <span>Staff Dashboard</span>
            </a>
        </div>
    </header>

    <!-- KPI Metric Cards -->
    <div class="kpi-grid">
        <a href="#maintenance-history-table" class="reveal-card kpi-card metric-accent-primary" title="View all maintenance records">
            <div class="kpi-card-header">
                <span class="kpi-label">Total Records</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('file-text') ?></span>
            </div>
            <div class="kpi-value"><?= $totalHistory ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">All-time requests</span>
            </div>
        </a>

        <a href="#maintenance-history-table" class="reveal-card kpi-card metric-accent-success" title="View resolved repairs">
            <div class="kpi-card-header">
                <span class="kpi-label">Resolved</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('check-circle') ?></span>
            </div>
            <div class="kpi-value"><?= $resolvedCount ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Completed repairs</span>
            </div>
        </a>

        <a href="/staff/maintenance" class="reveal-card kpi-card metric-accent-warning" title="Open active maintenance queue">
            <div class="kpi-card-header">
                <span class="kpi-label">Open / In Progress</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('wrench') ?></span>
            </div>
            <div class="kpi-value <?= $openCount > 0 ? 'text-amber-600' : 'text-neutral-900' ?>"><?= $openCount ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Active tickets in queue</span>
            </div>
        </a>

        <a href="#maintenance-history-table" class="reveal-card kpi-card metric-accent-info" title="Current pagination page">
            <div class="kpi-card-header">
                <span class="kpi-label">Page <?= $currentPage ?> / <?= $totalPages ?></span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('calendar') ?></span>
            </div>
            <div class="kpi-value"><?= $currentPage ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Showing 50 per page</span>
            </div>
        </a>
    </div>

    <!-- Main History Table Card -->
    <div class="space-y-2.5" id="maintenance-history-table">
        <?php
        $historyAction = '/staff/maintenance/history';
        $historySearchHint = 'Description, category, resident or room';
        $historyStatuses = ['' => 'Any status', 'open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved'];
        $historyTiers = ['' => 'Any priority', 'critical' => 'Critical', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];
        include __DIR__ . '/../shared/_history_filters.php';
        ?>

        <div class="section-header">
            <div class="flex items-center gap-2">
                <h2 class="text-heading-sm font-semibold text-neutral-900">Complete Maintenance Log</h2>
                <span class="badge badge-neutral"><?= $totalHistory ?> <?= $isFiltered ? 'matching' : '' ?> records</span>
            </div>
            <span class="text-caption text-neutral-400">Order: Most recent first</span>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto" style="-webkit-overflow-scrolling: touch;">
                <table class="w-full text-body-sm" style="min-width: 1000px;">
                    <thead>
                        <tr class="bg-neutral-50/80 border-b border-neutral-200 text-left text-neutral-500 text-caption uppercase tracking-wider">
                            <th class="p-3 font-semibold">Date & Time</th>
                            <th class="p-3 font-semibold">Priority</th>
                            <th class="p-3 font-semibold">Category</th>
                            <th class="p-3 font-semibold">Description</th>
                            <th class="p-3 font-semibold">Reported By</th>
                            <th class="p-3 font-semibold">Status</th>
                            <th class="p-3 font-semibold">Resolved By</th>
                            <th class="p-3 font-semibold">Resolved At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <?php foreach ($history as $h): ?>
                            <tr class="hover:bg-neutral-50 transition-colors">
                                <td class="p-3">
                                    <div class="font-mono text-xs text-neutral-600">
                                        <?= $h['created_at'] ? date('M j, Y H:i', strtotime($h['created_at'])) : 'N/A' ?>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <span class="badge <?= $tierBadges[$h['priority_tier'] ?? ''] ?? 'badge-neutral' ?> uppercase !text-[10px] font-bold tracking-wider">
                                        <?= htmlspecialchars($h['priority_tier'] ?? 'pending') ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <span class="badge badge-neutral text-xs capitalize font-medium">
                                        <?= htmlspecialchars($h['category'] ?? 'other') ?>
                                    </span>
                                </td>
                                <td class="p-3 max-w-xs">
                                    <div class="text-neutral-900 font-normal leading-relaxed">
                                        <?= htmlspecialchars($h['description']) ?>
                                    </div>
                                    <?php if (!empty($h['media_path'])): ?>
                                        <div class="mt-1">
                                            <a href="/<?= htmlspecialchars(ltrim($h['media_path'], '/')) ?>"
                                               target="_blank"
                                               class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:text-primary-800 underline">
                                                <span><?= \App\Support\Icon::svg('paperclip') ?></span> Media
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3">
                                    <div class="text-neutral-800 font-medium text-xs">
                                        <?= htmlspecialchars($h['boarder_name']) ?>
                                    </div>
                                    <div class="text-[10px] text-neutral-400 font-mono">
                                        <?= htmlspecialchars($h['boarder_email']) ?>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <span class="badge <?= ($h['status'] ?? '') === 'resolved' ? 'badge-success' : (($h['status'] ?? '') === 'in_progress' ? 'badge-info' : 'badge-warning') ?>">
                                        <?= htmlspecialchars($h['status'] ?? 'open') ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <?php if (!empty($h['resolver_name'])): ?>
                                        <div class="text-neutral-800 font-medium text-xs">
                                            <?= htmlspecialchars($h['resolver_name']) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-neutral-400 text-xs italic">Not resolved</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3">
                                    <?php if (!empty($h['resolved_at'])): ?>
                                        <div class="font-mono text-xs text-neutral-600">
                                            <?= date('M j, Y H:i', strtotime($h['resolved_at'])) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-neutral-400 text-xs italic">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="py-12 text-center text-neutral-400">
                                        <span class="empty-icon"><?= \App\Support\Icon::svg('clipboard') ?></span>
                                        <p class="font-semibold text-neutral-700 text-body-sm"><?= $isFiltered ? 'No records match your search.' : 'No maintenance history.' ?></p>
                                        <p class="text-caption text-neutral-400 mt-0.5">
                                            <?= $isFiltered ? 'Try a different term, or reset the filters above.' : 'No maintenance requests have been recorded yet.' ?>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-between mt-4 pt-4 border-t border-neutral-200">
            <div class="text-sm text-neutral-600">
                Showing <?= ($currentPage - 1) * 50 + 1 ?>-<?= min($currentPage * 50, $totalHistory) ?> of <?= $totalHistory ?> records
            </div>
            <div class="flex items-center gap-2">
                <?php if ($currentPage > 1): ?>
                    <a href="<?= $pageUrl($currentPage - 1) ?>" class="px-3 py-1.5 text-sm font-medium text-neutral-700 bg-white border border-neutral-300 rounded hover:bg-neutral-50 transition-colors">
                        Previous
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                    <?php if ($i === $currentPage): ?>
                        <span class="px-3 py-1.5 text-sm font-medium text-white bg-primary-600 rounded">
                            <?= $i ?>
                        </span>
                    <?php else: ?>
                        <a href="<?= $pageUrl($i) ?>" class="px-3 py-1.5 text-sm font-medium text-neutral-700 bg-white border border-neutral-300 rounded hover:bg-neutral-50 transition-colors">
                            <?= $i ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?= $pageUrl($currentPage + 1) ?>" class="px-3 py-1.5 text-sm font-medium text-neutral-700 bg-white border border-neutral-300 rounded hover:bg-neutral-50 transition-colors">
                        Next
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
