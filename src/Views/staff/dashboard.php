<?php
$pageTitle = 'Staff Dashboard';
ob_start();

$activeSosCount = count($activeSos ?? []);
$queueCount = count($queue ?? []);
$criticalCount = count(array_filter($queue ?? [], fn($q) => ($q['priority_tier'] ?? '') === 'critical'));
$highCount = count(array_filter($queue ?? [], fn($q) => ($q['priority_tier'] ?? '') === 'high'));

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
            <h1 class="text-heading-lg font-bold">Staff Dispatch &amp; Operations</h1>
            <p class="text-body-sm">Real-time emergency monitoring, priority triage &amp; resident issue response</p>
        </div>
        <span class="badge badge-success" style="background:rgba(255,255,255,0.12); color:#86efac; border:1px solid rgba(255,255,255,0.15);">
            <span style="width:0.4rem;height:0.4rem;border-radius:50%;background:#4ade80;display:inline-block;margin-right:0.4rem;"></span>
            Operations Live
        </span>
    </div>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="reveal-card card p-4 metric-accent-error <?= $activeSosCount > 0 ? 'ring-1 ring-red-300' : '' ?>">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Active SOS Alerts</p>
            <p class="text-3xl font-bold <?= $activeSosCount > 0 ? 'text-red-600' : 'text-neutral-900' ?> mt-1.5"><?= $activeSosCount ?></p>
            <p class="text-caption <?= $activeSosCount > 0 ? 'text-red-600 font-semibold' : 'text-neutral-400' ?> mt-1">
                <?= $activeSosCount > 0 ? 'Requires immediate response' : 'No active emergencies' ?>
            </p>
        </div>
        <div class="reveal-card card p-4 metric-accent-warning">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Queue Pending</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $queueCount ?></p>
            <p class="text-caption text-neutral-400 mt-1">Awaiting repair or triage</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-error">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Critical Tickets</p>
            <p class="text-3xl font-bold <?= $criticalCount > 0 ? 'text-red-600' : 'text-neutral-900' ?> mt-1.5"><?= $criticalCount ?></p>
            <p class="text-caption text-neutral-400 mt-1"><?= $criticalCount > 0 ? 'Urgent facility risks' : 'Zero critical issues' ?></p>
        </div>
        <div class="reveal-card card p-4 metric-accent-success">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Facility Telemetry</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5">24/7</p>
            <p class="text-caption text-neutral-400 mt-1">Real-time alert polling active</p>
        </div>
    </div>

    <!-- Section 1: Active Emergency SOS Alerts -->
    <div class="space-y-2.5">
        <div class="section-header">
            <div class="flex items-center gap-2">
                <h2 class="text-heading-sm font-semibold <?= $activeSosCount > 0 ? 'text-red-700' : 'text-neutral-900' ?> flex items-center gap-2">
                    <?php if ($activeSosCount > 0): ?><span class="sos-dot inline-block w-2.5 h-2.5 rounded-full bg-red-600"></span><?php endif; ?>
                    Active Emergency SOS Monitor
                </h2>
                <span class="badge <?= $activeSosCount > 0 ? 'badge-error animate-pulse' : 'badge-neutral' ?>">
                    <?= $activeSosCount ?> <?= $activeSosCount === 1 ? 'alert' : 'alerts' ?>
                </span>
            </div>
            <span class="text-caption text-neutral-400 font-mono">Poll: 8s auto-refresh</span>
        </div>

        <div class="card overflow-hidden <?= $activeSosCount > 0 ? 'border-red-300 shadow-sm' : '' ?>">
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm">
                    <thead>
                        <tr class="bg-neutral-50/80 border-b border-neutral-200 text-left text-neutral-500 text-caption uppercase tracking-wider">
                            <th class="p-3 font-semibold">Resident</th>
                            <th class="p-3 font-semibold">Assigned Room</th>
                            <th class="p-3 font-semibold">Alert Status</th>
                            <th class="p-3 font-semibold">Broadcast Time</th>
                            <th class="p-3 font-semibold text-right">Dispatch Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <?php foreach ($activeSos as $s): ?>
                            <tr class="<?= $s['status'] === 'active' ? 'bg-red-50/70 hover:bg-red-50' : 'hover:bg-neutral-50' ?> transition-colors">
                                <td class="p-3 font-semibold text-neutral-900 flex items-center gap-2">
                                    <?php if ($s['status'] === 'active'): ?>
                                        <span class="sos-dot inline-block w-2 h-2 rounded-full bg-red-600 flex-shrink-0"></span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($s['boarder_name']) ?>
                                </td>
                                <td class="p-3 font-mono font-bold text-neutral-900">
                                    <span class="id-tag">Room <?= htmlspecialchars($s['room_number'] ?? '—') ?></span>
                                </td>
                                <td class="p-3">
                                    <span class="badge <?= $s['status'] === 'active' ? 'badge-error' : ($s['status'] === 'acknowledged' ? 'badge-warning' : 'badge-success') ?>">
                                        <?= htmlspecialchars($s['status']) ?>
                                    </span>
                                </td>
                                <td class="p-3 text-neutral-500 text-caption font-mono">
                                    <?= htmlspecialchars($s['created_at']) ?>
                                </td>
                                <td class="p-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <?php if ($s['status'] === 'active'): ?>
                                            <form method="post" action="/staff/sos/<?= (int) $s['id'] ?>/ack" class="inline">
                                                <?= \App\Support\Csrf::field() ?>
                                                <button type="submit" class="btn btn-warning !py-1 !px-2.5 !text-xs font-semibold shadow-xs">
                                                    Acknowledge
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="/staff/sos/<?= (int) $s['id'] ?>/resolve" class="inline">
                                            <?= \App\Support\Csrf::field() ?>
                                            <button type="submit" class="btn btn-primary !py-1 !px-2.5 !text-xs font-semibold shadow-xs">
                                                Resolve
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($activeSos)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="py-10 text-center text-neutral-400">
                                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">🛡️</div>
                                        <p class="font-semibold text-neutral-700 text-body-sm">All clear! No active emergency alerts.</p>
                                        <p class="text-caption text-neutral-400 mt-0.5">The emergency system is actively polling for incoming broadcasts.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Section 2: Top 5 Maintenance Queue -->
    <div class="space-y-2.5">
        <div class="section-header">
            <div class="flex items-center gap-2">
                <h2 class="text-heading-sm font-semibold text-neutral-900">Priority Maintenance Queue (Top 5)</h2>
                <span class="badge badge-neutral"><?= min(5, count($queue ?? [])) ?> of <?= count($queue ?? []) ?></span>
            </div>
            <a href="/staff/maintenance" class="text-xs font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                View Full Queue &rarr;
            </a>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm">
                    <thead>
                        <tr class="bg-neutral-50/80 border-b border-neutral-200 text-left text-neutral-500 text-caption uppercase tracking-wider">
                            <th class="p-3 font-semibold">Priority</th>
                            <th class="p-3 font-semibold">Category</th>
                            <th class="p-3 font-semibold">Problem Description</th>
                            <th class="p-3 font-semibold">Resident</th>
                            <th class="p-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <?php foreach (array_slice($queue, 0, 5) as $q): ?>
                            <tr class="hover:bg-neutral-50 transition-colors">
                                <td class="p-3">
                                    <span class="badge <?= $tierBadges[$q['priority_tier'] ?? ''] ?? 'badge-neutral' ?> font-semibold uppercase !text-[10px] tracking-wider">
                                        <?= htmlspecialchars($q['priority_tier'] ?? 'pending') ?>
                                    </span>
                                </td>
                                <td class="p-3 font-medium text-neutral-700 capitalize">
                                    <?= htmlspecialchars($q['category'] ?? 'other') ?>
                                </td>
                                <td class="p-3 text-neutral-800 max-w-xs truncate" title="<?= htmlspecialchars($q['description']) ?>">
                                    <?= htmlspecialchars($q['description']) ?>
                                </td>
                                <td class="p-3 text-neutral-800 font-medium">
                                    <?= htmlspecialchars($q['boarder_name']) ?>
                                </td>
                                <td class="p-3">
                                    <span class="badge <?= ($q['status'] ?? '') === 'resolved' ? 'badge-success' : (($q['status'] ?? '') === 'in_progress' ? 'badge-info' : 'badge-warning') ?>">
                                        <?= htmlspecialchars($q['status'] ?? 'open') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($queue)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="py-8 text-center text-neutral-400">
                                        <div style="font-size: 1.75rem; margin-bottom: 0.25rem;">🔧</div>
                                        <p class="font-semibold text-neutral-700 text-body-sm">Maintenance queue is empty.</p>
                                        <p class="text-caption text-neutral-400 mt-0.5">No open repair requests currently recorded.</p>
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
/* Signature moment: pulsing indicator on active SOS alerts with reduced-motion support */
if (window.gsap) {
    let mm = gsap.matchMedia();
    mm.add({
        animate: "(prefers-reduced-motion: no-preference)",
        reduce: "(prefers-reduced-motion: reduce)",
    }, (context) => {
        if (context.conditions.animate) {
            gsap.to(".sos-dot", { opacity: 0.2, scale: 0.7, duration: 0.6, repeat: -1, yoyo: true, ease: "sine.inOut" });
            gsap.from(".reveal-card", { opacity: 0, y: 12, duration: 0.35, stagger: 0.05, ease: "power2.out" });
        }
    });
}

/* Polling for active SOS count changes (every 8 seconds) */
(function () {
    let lastCount = <?= count($activeSos) ?>;
    setInterval(async () => {
        try {
            const res = await fetch('/api/sos/active');
            if (!res.ok) return;
            const alerts = await res.json();
            if (alerts.length !== lastCount) {
                location.reload();
            }
        } catch (e) { /* retry on next interval */ }
    }, 8000);
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
