<?php
$pageTitle = 'Incident History';
ob_start();

$totalHistory = $totalCount ?? count($history ?? []);
$resolvedCount = count(array_filter($history ?? [], fn($h) => !empty($h['resolved'])));
$openCount = count(array_filter($history ?? [], fn($h) => empty($h['resolved'])));
$currentPage = $page ?? 1;
$totalPages = $totalPages ?? 1;
?>
<div class="max-w-5xl mx-auto px-5 py-5 space-y-5">

    <!-- Page Banner -->
    <div class="page-banner">
        <div>
            <h1 class="text-heading-lg font-bold">Incident History</h1>
            <p class="text-body-sm">Complete record of all incidents with full details and resolution tracking</p>
        </div>
        <span class="badge badge-info" style="background:rgba(255,255,255,0.12); color:#93c5fd; border:1px solid rgba(255,255,255,0.15);">
            <span style="width:0.4rem;height:0.4rem;border-radius:50%;background:#3b82f6;display:inline-block;margin-right:0.4rem;"></span>
            Full Archive
        </span>
    </div>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="reveal-card card p-4 metric-accent-primary">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Records</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $totalHistory ?></p>
            <p class="text-caption text-neutral-400 mt-1">All-time incidents</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-success">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Resolved</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $resolvedCount ?></p>
            <p class="text-caption text-neutral-400 mt-1">Closed cases</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-warning">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Open Cases</p>
            <p class="text-3xl font-bold <?= $openCount > 0 ? 'text-amber-600' : 'text-neutral-900' ?> mt-1.5"><?= $openCount ?></p>
            <p class="text-caption text-neutral-400 mt-1">Awaiting resolution</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-info">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Page <?= $currentPage ?> / <?= $totalPages ?></p>
            <p class="text-caption text-neutral-400 mt-1">Showing 50 per page</p>
        </div>
    </div>

    <!-- Main History Table Card -->
    <div class="space-y-2.5">
        <div class="section-header">
            <div class="flex items-center gap-2">
                <h2 class="text-heading-sm font-semibold text-neutral-900">Complete Incident Log</h2>
                <span class="badge badge-neutral"><?= $totalHistory ?> records</span>
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
                                            <span>📝</span> Note
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
                                <tr id="note-row-<?= (int) $h['id'] ?>" class="hidden note-drawer-row border-b border-neutral-200" style="background-color: #f8fafc;">
                                    <td colspan="8" class="p-4" style="padding: 0.875rem 1.25rem;">
                                        <div class="note-drawer-box bg-white rounded-xl border border-neutral-200 p-4 shadow-xs">
                                            <div class="flex items-center justify-between pb-2 mb-2.5 border-b border-neutral-100">
                                                <div class="flex items-center gap-2">
                                                    <span style="font-size:1rem;">📝</span>
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
                                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">📋</div>
                                        <p class="font-semibold text-neutral-700 text-body-sm">No incident history.</p>
                                        <p class="text-caption text-neutral-400 mt-0.5">No incidents have been recorded yet.</p>
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
                    <a href="?page=<?= $currentPage - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-neutral-700 bg-white border border-neutral-300 rounded hover:bg-neutral-50 transition-colors">
                        Previous
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                    <?php if ($i === $currentPage): ?>
                        <span class="px-3 py-1.5 text-sm font-medium text-white bg-primary-600 rounded">
                            <?= $i ?>
                        </span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium text-neutral-700 bg-white border border-neutral-300 rounded hover:bg-neutral-50 transition-colors">
                            <?= $i ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?= $currentPage + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-neutral-700 bg-white border border-neutral-300 rounded hover:bg-neutral-50 transition-colors">
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
    if (mainRow) mainRow.style.backgroundColor = '#f1f5f9';
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

if (window.gsap) {
    let mm = gsap.matchMedia();
    mm.add({
        animate: "(prefers-reduced-motion: no-preference)",
        reduce: "(prefers-reduced-motion: reduce)",
    }, (context) => {
        if (context.conditions.animate) {
            gsap.from(".reveal-card", {
                opacity: 0,
                y: 12,
                duration: 0.35,
                stagger: 0.05,
                ease: "power2.out"
            });
        }
    });
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
