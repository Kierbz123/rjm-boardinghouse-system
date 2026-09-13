<?php
$pageTitle = 'Incident History';
ob_start();

$totalHistory = count($history ?? []);
$resolvedCount = count(array_filter($history ?? [], fn($h) => !empty($h['resolved'])));
$openCount = count(array_filter($history ?? [], fn($h) => empty($h['resolved'])));
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
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Archive Status</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5">Active</p>
            <p class="text-caption text-neutral-400 mt-1">Complete record</p>
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
                <table class="w-full text-body-sm">
                    <thead>
                        <tr class="bg-neutral-50/80 border-b border-neutral-200 text-left text-neutral-500 text-caption uppercase tracking-wider">
                            <th class="p-3 font-semibold">Date & Time</th>
                            <th class="p-3 font-semibold">Incident Type</th>
                            <th class="p-3 font-semibold">Description</th>
                            <th class="p-3 font-semibold">Reported By</th>
                            <th class="p-3 font-semibold">Status</th>
                            <th class="p-3 font-semibold">Resolution Notes</th>
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
                                    <span class="badge badge-neutral font-semibold text-xs capitalize">
                                        <?= htmlspecialchars($h['type']) ?>
                                    </span>
                                </td>
                                <td class="p-3 max-w-xs">
                                    <div class="text-neutral-900 leading-relaxed font-normal">
                                        <?= htmlspecialchars($h['description']) ?>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <div class="text-neutral-800 font-medium text-xs">
                                        <?= htmlspecialchars($h['reporter_name']) ?>
                                    </div>
                                    <div class="text-[10px] text-neutral-400 font-mono">
                                        <?= htmlspecialchars($h['reporter_email']) ?>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <?php if (!empty($h['resolved'])): ?>
                                        <span class="badge badge-success">Resolved</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Open</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 max-w-xs">
                                    <?php if (!empty($h['resolution_notes'])): ?>
                                        <div class="text-neutral-700 text-xs leading-relaxed bg-neutral-50 p-2 rounded border border-neutral-200">
                                            <?= htmlspecialchars($h['resolution_notes']) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-neutral-400 text-xs italic">No notes</span>
                                    <?php endif; ?>
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
    </div>

</div>

<script>
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
