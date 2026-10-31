<?php
$pageTitle = 'Incident History';
ob_start();
// Counts come from the database over everything the filters match; counting $history would
// only ever describe the 50 rows on this page (WP-21).
$counts = $counts ?? ['total' => count($history ?? []), 'resolved' => 0, 'open' => 0];
$totalHistory = $counts['total'];
$resolvedCount = $counts['resolved'];
$openCount = $counts['open'];

$filters = $filters ?? ['q' => '', 'status' => ''];
$isFiltered = implode('', $filters) !== '';
// A resident reaches this page from their own sidebar: the staff-only shortcuts would 403 them.
$isResident = ($_SESSION['role'] ?? '') === 'boarder';
/** This page's URL with one page number, so paging never loses the search. */
$pageUrl = fn (int $n) => '/staff/incidents/history?' . http_build_query(array_filter($filters) + ['page' => $n]);


$currentPage = $page ?? 1;
$totalPages = $totalPages ?? 1;
?>
<div class="max-w-5xl mx-auto px-5 py-5 space-y-5">

    <!-- Executive Page Banner with Quick Launch Command Bar -->
    <header class="dash-hero-banner">
        <div class="dash-hero-top">
            <div>
                <h1 class="dash-hero-title">
                    <span>Incident History</span>
                </h1>
                <p class="dash-hero-sub">Complete historical record of resolved community disputes, infractions, and safety incidents.</p>
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
            <a href="/staff/incidents" class="dash-cmd-btn highlight" title="Open active incidents registry">
                <?= \App\Support\Icon::svg('alert-triangle', 14) ?>
                <span>Active Incidents (<?= $openCount ?> open)</span>
            </a>
            <a href="#incidents-history-table" class="dash-cmd-btn" title="Jump to incident records table">
                <?= \App\Support\Icon::svg('clipboard', 14) ?>
                <span>Resolved Cases (<?= $resolvedCount ?>)</span>
            </a>
            <?php if (!$isResident): ?>
            <a href="/staff/maintenance/history" class="dash-cmd-btn" title="Open maintenance history records">
                <?= \App\Support\Icon::svg('wrench', 14) ?>
                <span>Maintenance History</span>
            </a>
            <a href="/staff/dashboard" class="dash-cmd-btn" title="Back to staff dashboard">
                <?= \App\Support\Icon::svg('dashboard', 14) ?>
                <span>Staff Dashboard</span>
            </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- KPI Metric Cards -->
    <div class="kpi-grid">
        <a href="#incidents-history-table" class="reveal-card kpi-card metric-accent-primary" title="View all incident records">
            <div class="kpi-card-header">
                <span class="kpi-label">Total Records</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('file-text') ?></span>
            </div>
            <div class="kpi-value"><?= $totalHistory ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">All-time incidents</span>
            </div>
        </a>

        <a href="#incidents-history-table" class="reveal-card kpi-card metric-accent-success" title="View resolved incident records">
            <div class="kpi-card-header">
                <span class="kpi-label">Resolved</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('shield-check') ?></span>
            </div>
            <div class="kpi-value"><?= $resolvedCount ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Closed cases</span>
            </div>
        </a>

        <a href="/staff/incidents" class="reveal-card kpi-card metric-accent-warning" title="Open active incidents registry">
            <div class="kpi-card-header">
                <span class="kpi-label">Open Cases</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('alert-triangle') ?></span>
            </div>
            <div class="kpi-value <?= $openCount > 0 ? 'text-amber-600' : 'text-neutral-900' ?>"><?= $openCount ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Awaiting resolution</span>
            </div>
        </a>

        <a href="#incidents-history-table" class="reveal-card kpi-card metric-accent-info" title="Current pagination page">
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
    <div class="space-y-2.5" id="incidents-history-table">
        <?php
        $historyAction = "/staff/incidents/history";
        $historySearchHint = "Type, description, reporter or notes";
        $historyStatuses = ["" => "Any status", "open" => "Open", "resolved" => "Resolved"];
        $historyTiers = [];
        include __DIR__ . "/../shared/_history_filters.php";
        ?>

        <div class="section-header">
            <div class="flex items-center gap-2">
                <h2 class="text-heading-sm font-semibold text-neutral-900">Complete Incident Log</h2>
                <span class="badge badge-neutral"><?= $totalHistory ?> <?= $isFiltered ? "matching" : "" ?> records</span>
            </div>
            <span class="text-caption text-neutral-400">Order: Most recent first</span>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full table-compact">
                    <thead>
                        <tr class="bg-neutral-50/80 border-b border-neutral-200 text-left text-neutral-500 text-[11px] uppercase tracking-wider">
                            <th class="p-2.5 font-semibold">Date & Time</th>
                            <th class="p-2.5 font-semibold">Incident Type</th>
                            <th class="p-2.5 font-semibold">Description</th>
                            <th class="p-2.5 font-semibold">Reported By</th>
                            <th class="p-2.5 font-semibold">Status</th>
                            <th class="p-2.5 font-semibold">Resolution Notes</th>
                            <th class="p-2.5 font-semibold">Resolved By</th>
                            <th class="p-2.5 font-semibold">Resolved At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <?php foreach ($history as $h): ?>
                            <tr class="hover:bg-neutral-50 transition-colors incident-main-row" id="incident-row-<?= (int) $h['id'] ?>" data-id="<?= (int) $h['id'] ?>">
                                <td class="p-2.5">
                                    <div class="font-mono text-[11px] text-neutral-600">
                                        <?= $h['created_at'] ? date('M j, Y H:i', strtotime($h['created_at'])) : 'N/A' ?>
                                    </div>
                                </td>
                                <td class="p-2.5">
                                    <span class="badge badge-neutral font-semibold text-[11px] capitalize">
                                        <?= htmlspecialchars($h['type']) ?>
                                    </span>
                                </td>
                                <td class="p-2.5 max-w-xs">
                                    <div class="text-neutral-900 leading-relaxed font-normal text-xs">
                                        <?= htmlspecialchars($h['description']) ?>
                                    </div>
                                </td>
                                <td class="p-2.5">
                                    <div class="text-neutral-800 font-medium text-xs">
                                        <?= htmlspecialchars($h['reporter_name']) ?>
                                    </div>
                                    <div class="text-[10px] text-neutral-400 font-mono">
                                        <?= htmlspecialchars($h['reporter_email']) ?>
                                    </div>
                                </td>
                                <td class="p-2.5">
                                    <?php if (!empty($h['resolved'])): ?>
                                        <span class="badge badge-success text-[11px]">Resolved</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning text-[11px]">Open</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-2.5">
                                    <?php if (!empty($h['resolution_notes'])): ?>
                                        <button type="button"
                                                class="btn btn-secondary !py-0.5 !px-2 !text-[11px] font-semibold note-toggle-btn cursor-pointer inline-flex items-center gap-1 shadow-2xs"
                                                data-id="<?= (int) $h['id'] ?>"
                                                title="View resolution notes">
                                            <span><?= \App\Support\Icon::svg('square-pen') ?></span> Note
                                        </button>
                                    <?php else: ?>
                                        <span class="text-neutral-400 text-[11px] italic">No notes</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-2.5">
                                    <?php if (!empty($h['resolver_name'])): ?>
                                        <div class="text-neutral-800 font-medium text-xs">
                                            <?= htmlspecialchars($h['resolver_name']) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-neutral-400 text-[11px] italic">Not resolved</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-2.5">
                                    <?php if (!empty($h['resolved_at'])): ?>
                                        <div class="font-mono text-[11px] text-neutral-600">
                                            <?= date('M j, Y H:i', strtotime($h['resolved_at'])) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-neutral-400 text-[11px] italic">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($h['resolution_notes'])): ?>
                                <!-- Slide-Down Resolution Note Drawer Row -->
                                <tr id="note-row-<?= (int) $h['id'] ?>" class="hidden note-drawer-row border-b border-neutral-200" style="background-color: #f8f7f5;">
                                    <td colspan="8" class="p-4" style="padding: 0.875rem 1.25rem;">
                                        <div class="note-drawer-box bg-white rounded-xl border border-neutral-200 p-4 shadow-xs">
                                            <div class="flex items-center justify-between pb-2 mb-2.5 border-b border-neutral-100">
                                                <div class="flex items-center gap-2">
                                                    <?= \App\Support\Icon::svg('square-pen', 16) ?>
                                                    <h4 class="text-body-sm font-semibold text-neutral-900">
                                                        Resolution Notes &mdash;
                                                        <span class="text-primary-700 font-bold"><?= htmlspecialchars($h['type']) ?></span>
                                                        <span class="text-caption text-neutral-400 font-normal ml-1">#<?= (int) $h['id'] ?></span>
                                                    </h4>
                                                </div>
                                                <button type="button" class="text-caption text-neutral-400 hover:text-neutral-700 cancel-note-btn cursor-pointer bg-transparent border-none py-1 px-2 font-medium" data-id="<?= (int) $h['id'] ?>">
                                                    &times; Close
                                                </button>
                                            </div>
                                            <div class="text-xs text-neutral-700 leading-relaxed bg-neutral-50 p-3 rounded-lg border border-neutral-200/80">
                                                <?= nl2br(htmlspecialchars($h['resolution_notes'])) ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="py-12 text-center text-neutral-400">
                                        <span class="empty-icon"><?= \App\Support\Icon::svg('clipboard') ?></span>
                                        <p class="font-semibold text-neutral-700 text-body-sm"><?= $isFiltered ? "No records match your search." : "No incident history." ?></p>
                                        <p class="text-caption text-neutral-400 mt-0.5"><?= $isFiltered ? "Try a different term, or reset the filters above." : "No incidents have been recorded yet." ?></p>
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
                Showing <?= ($currentPage - 1) * 50 + 1 ?>-<?= min($currentPage * 50, $totalHistory) ?> of <?= $totalHistory ?> <?= $isFiltered ? "matching" : "" ?> records
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

<script>
// Resolution Note Drawer toggle
let activeNoteId = null;

function openNoteDrawer(id) {
    if (activeNoteId && activeNoteId !== id) {
        closeNoteDrawer(activeNoteId);
    }
    const row = document.getElementById('note-row-' + id);
    const mainRow = document.getElementById('incident-row-' + id);
    if (!row) return;
    row.classList.remove('hidden');
    const box = row.querySelector('.note-drawer-box');
    if (window.gsap && box) {
        gsap.fromTo(box, { opacity: 0, y: -8 }, { opacity: 1, y: 0, duration: 0.22, ease: 'power2.out' });
    }
    if (mainRow) mainRow.style.backgroundColor = '#f1f0ee';
    activeNoteId = id;
}

function closeNoteDrawer(id) {
    const row = document.getElementById('note-row-' + id);
    const mainRow = document.getElementById('incident-row-' + id);
    if (!row || row.classList.contains('hidden')) return;
    const box = row.querySelector('.note-drawer-box');
    if (window.gsap && box) {
        gsap.to(box, {
            opacity: 0,
            y: -8,
            duration: 0.18,
            ease: 'power2.in',
            onComplete: () => row.classList.add('hidden')
        });
    } else {
        row.classList.add('hidden');
    }
    if (mainRow) mainRow.style.backgroundColor = '';
    if (activeNoteId === id) activeNoteId = null;
}

document.addEventListener('click', function(e) {
    const noteToggle = e.target.closest('.note-toggle-btn');
    if (noteToggle) {
        e.stopPropagation();
        const id = noteToggle.getAttribute('data-id');
        const row = document.getElementById('note-row-' + id);
        (row && !row.classList.contains('hidden')) ? closeNoteDrawer(id) : openNoteDrawer(id);
        return;
    }

    const cancelNote = e.target.closest('.cancel-note-btn');
    if (cancelNote) {
        e.stopPropagation();
        closeNoteDrawer(cancelNote.getAttribute('data-id'));
        return;
    }
});

</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
