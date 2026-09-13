<?php
$pageTitle = 'Maintenance Queue';
ob_start();

$totalInQueue = count($queue ?? []);
$criticalCount = count(array_filter($queue ?? [], fn($q) => ($q['priority_tier'] ?? '') === 'critical'));
$highCount = count(array_filter($queue ?? [], fn($q) => ($q['priority_tier'] ?? '') === 'high'));
$medLowCount = count(array_filter($queue ?? [], fn($q) => in_array($q['priority_tier'] ?? '', ['medium', 'low'], true)));

$tierBadges = [
    'critical' => 'badge-error',
    'high'     => 'badge-warning',
    'medium'   => 'badge-info',
    'low'      => 'badge-neutral',
];
?>
<div class="max-w-5xl mx-auto px-5 py-5 space-y-5">

    <!-- Page Banner -->
    <div class="page-banner">
        <div>
            <h1 class="text-heading-lg font-bold">Maintenance Queue</h1>
            <p class="text-body-sm">Requests triaged by AI priority scoring and severity tier, not submission time</p>
        </div>
        <span class="badge badge-warning" style="background:rgba(255,255,255,0.12); color:#fde047; border:1px solid rgba(255,255,255,0.15);">
            <span style="width:0.4rem;height:0.4rem;border-radius:50%;background:#eab308;display:inline-block;margin-right:0.4rem;"></span>
            AI Priority Sorted
        </span>
    </div>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="reveal-card card p-4 metric-accent-primary">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total in Queue</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $totalInQueue ?></p>
            <p class="text-caption text-neutral-400 mt-1">Open repair tickets</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-error <?= $criticalCount > 0 ? 'ring-1 ring-red-300' : '' ?>">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Critical Urgency</p>
            <p class="text-3xl font-bold <?= $criticalCount > 0 ? 'text-red-600' : 'text-neutral-900' ?> mt-1.5"><?= $criticalCount ?></p>
            <p class="text-caption <?= $criticalCount > 0 ? 'text-red-600 font-semibold' : 'text-neutral-400' ?> mt-1">
                <?= $criticalCount > 0 ? 'Immediate action required' : 'Zero safety emergencies' ?>
            </p>
        </div>
        <div class="reveal-card card p-4 metric-accent-warning">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">High Priority</p>
            <p class="text-3xl font-bold <?= $highCount > 0 ? 'text-amber-600' : 'text-neutral-900' ?> mt-1.5"><?= $highCount ?></p>
            <p class="text-caption text-neutral-400 mt-1"><?= $highCount > 0 ? 'Requires prompt dispatch' : 'Normal queue levels' ?></p>
        </div>
        <div class="reveal-card card p-4 metric-accent-info">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Medium &amp; Low</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $medLowCount ?></p>
            <p class="text-caption text-neutral-400 mt-1">Routine maintenance tickets</p>
        </div>
    </div>

    <!-- Main Queue Table Card -->
    <div class="space-y-2.5">
        <div class="section-header">
            <div class="flex items-center gap-2">
                <h2 class="text-heading-sm font-semibold text-neutral-900">Active Maintenance Tickets</h2>
                <span class="badge badge-neutral"><?= $totalInQueue ?> total</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-caption text-neutral-400">Order: Critical &rarr; High &rarr; Medium &rarr; Low</span>
                <a href="/staff/maintenance/history" class="text-xs font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                    View Full History &rarr;
                </a>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm">
                    <thead>
                        <tr class="bg-neutral-50/80 border-b border-neutral-200 text-left text-neutral-500 text-caption uppercase tracking-wider">
                            <th class="p-3 font-semibold">Priority Tier</th>
                            <th class="p-3 font-semibold">Category</th>
                            <th class="p-3 font-semibold">Description &amp; Proof</th>
                            <th class="p-3 font-semibold">Resident</th>
                            <th class="p-3 font-semibold">Current Status</th>
                            <th class="p-3 font-semibold text-right">Update Workflow</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <?php foreach ($queue as $q): ?>
                            <tr class="hover:bg-neutral-50 transition-colors">
                                <td class="p-3">
                                    <div class="flex flex-col gap-1 items-start">
                                        <span class="priority-badge badge <?= $tierBadges[$q['priority_tier'] ?? ''] ?? 'badge-neutral' ?> uppercase !text-[10px] font-bold tracking-wider">
                                            <?= htmlspecialchars($q['priority_tier'] ?? 'pending') ?>
                                        </span>
                                        <?php if (!empty($q['scoring_pending'])): ?>
                                            <span class="text-[10px] text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded font-medium"
                                                  title="Scoring service was unreachable at submission; default fallback applied">
                                                ⚠ not yet AI-scored
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <span class="badge badge-neutral text-xs capitalize font-medium">
                                        <?= htmlspecialchars($q['category'] ?? 'other') ?>
                                    </span>
                                </td>
                                <td class="p-3 max-w-sm">
                                    <div class="text-neutral-900 font-normal leading-relaxed">
                                        <?= htmlspecialchars($q['description']) ?>
                                    </div>
                                    <?php if (!empty($q['media_path'])): ?>
                                        <div class="mt-1.5">
                                            <a href="/<?= htmlspecialchars(ltrim($q['media_path'], '/')) ?>"
                                               target="_blank"
                                               class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:text-primary-800 underline">
                                                <span>📎</span> View Attached Media
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-neutral-800 font-medium">
                                    <?= htmlspecialchars($q['boarder_name']) ?>
                                </td>
                                <td class="p-3">
                                    <span class="badge <?= ($q['status'] ?? '') === 'resolved' ? 'badge-success' : (($q['status'] ?? '') === 'in_progress' ? 'badge-info' : 'badge-warning') ?>">
                                        <?= htmlspecialchars($q['status'] ?? 'open') ?>
                                    </span>
                                </td>
                                <td class="p-3 text-right">
                                    <form method="post" action="/staff/maintenance/<?= (int) $q['id'] ?>/status" class="inline-flex items-center justify-end gap-1.5">
                                        <?= \App\Support\Csrf::field() ?>
                                        <select name="status" class="input input-sm !py-1 text-xs bg-white">
                                            <option value="open" <?= ($q['status'] ?? '') === 'open' ? 'selected' : '' ?>>open</option>
                                            <option value="in_progress" <?= ($q['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>in_progress</option>
                                            <option value="resolved" <?= ($q['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>resolved</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary !py-1 !px-2.5 !text-xs font-semibold shadow-xs">
                                            Update
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($queue)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="py-12 text-center text-neutral-400">
                                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">🎉</div>
                                        <p class="font-semibold text-neutral-700 text-body-sm">All caught up! Maintenance queue is empty.</p>
                                        <p class="text-caption text-neutral-400 mt-0.5">No open or in-progress repair tickets at this time.</p>
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
/* Signature moment: priority badges settle into place on load */
if (window.gsap) {
    let mm = gsap.matchMedia();
    mm.add({
        animate: "(prefers-reduced-motion: no-preference)",
        reduce: "(prefers-reduced-motion: reduce)",
    }, (context) => {
        if (context.conditions.animate) {
            gsap.from(".priority-badge", {
                scale: 0.6,
                opacity: 0,
                duration: 0.35,
                stagger: 0.05,
                ease: "back.out(2)"
            });
            gsap.from(".reveal-card", {
                opacity: 0,
                y: 12,
                duration: 0.35,
                stagger: 0.05,
                ease: "power2.out"
            });
        } else {
            gsap.set(".priority-badge", { opacity: 1, scale: 1 });
        }
    });
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
