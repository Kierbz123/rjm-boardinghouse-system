<?php
$pageTitle = 'Occupancy Trend';
ob_start();

// Compute summary statistics
$totalSnapshots = count($trend ?? []);
$latestSnapshot = !empty($trend) ? end($trend) : null;
$latestOccupied = (int) ($latestSnapshot['occupied_beds'] ?? 0);
$latestTotal    = (int) ($latestSnapshot['total_beds'] ?? 0);
$latestVacant   = max(0, $latestTotal - $latestOccupied);
$latestPct      = $latestTotal > 0 ? round(($latestOccupied / $latestTotal) * 100) : 0;

$allPcts = array_map(fn($t) => $t['total_beds'] > 0 ? round(($t['occupied_beds'] / $t['total_beds']) * 100) : 0, $trend ?? []);
$peakPct = !empty($allPcts) ? max($allPcts) : 0;
$avgPct  = !empty($allPcts) ? round(array_sum($allPcts) / count($allPcts)) : 0;

// Reverse order for table display so newest date is at the top
$reversedTrend = array_reverse($trend ?? []);
?>
<div class="max-w-5xl mx-auto px-5 py-5 space-y-5">

    <!-- Page Banner -->
    <div class="page-banner">
        <div>
            <h1 class="text-heading-lg font-bold">Occupancy Trends</h1>
            <p class="text-body-sm">Historical capacity utilization &amp; 30-day bed occupancy tracking</p>
        </div>
        <span class="badge badge-success" style="background:rgba(255,255,255,0.12); color:#86efac; border:1px solid rgba(255,255,255,0.15);">
            <span style="width:0.4rem;height:0.4rem;border-radius:50%;background:#4ade80;display:inline-block;margin-right:0.4rem;"></span>
            Live Telemetry
        </span>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="reveal-card card p-4 metric-accent-primary">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Current Occupancy</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $latestPct ?>%</p>
            <p class="text-caption text-neutral-400 mt-1"><?= $latestOccupied ?> of <?= $latestTotal ?> beds filled</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-warning">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Occupied Beds</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $latestOccupied ?></p>
            <p class="text-caption text-neutral-400 mt-1">Active residents</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-success">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Available Beds</p>
            <p class="text-3xl font-bold <?= $latestVacant === 0 ? 'text-error-600' : 'text-neutral-900' ?> mt-1.5"><?= $latestVacant ?></p>
            <p class="text-caption text-neutral-400 mt-1">Vacant capacity</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-info">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">30-Day Average</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $avgPct ?>%</p>
            <p class="text-caption text-neutral-400 mt-1">Peak: <?= $peakPct ?>% across <?= $totalSnapshots ?> days</p>
        </div>
    </div>

    <!-- Current Capacity Status Breakdown Card -->
    <div class="card p-4 space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-body font-semibold text-neutral-900">Current Capacity Distribution</h3>
                <p class="text-caption text-neutral-500">Live allocation across all rooms and bed units</p>
            </div>
            <span class="badge <?= $latestPct >= 90 ? 'badge-error' : ($latestPct >= 70 ? 'badge-warning' : 'badge-success') ?> font-semibold">
                <?= $latestPct ?>% Capacity
            </span>
        </div>

        <!-- Multi-segment visual progress bar -->
        <div class="w-full h-3.5 bg-neutral-100 rounded-full overflow-hidden flex border border-neutral-200">
            <?php if ($latestTotal > 0): ?>
                <div style="width: <?= $latestPct ?>%;"
                     class="h-full bg-primary-600 transition-all duration-500"
                     title="Occupied: <?= $latestOccupied ?> beds (<?= $latestPct ?>%)"></div>
                <div style="width: <?= 100 - $latestPct ?>%;"
                     class="h-full bg-emerald-400 transition-all duration-500"
                     title="Available: <?= $latestVacant ?> beds (<?= 100 - $latestPct ?>%)"></div>
            <?php else: ?>
                <div style="width: 100%;" class="h-full bg-neutral-300"></div>
            <?php endif; ?>
        </div>

        <div class="flex items-center justify-between text-caption text-neutral-600 pt-1">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-primary-600 inline-block"></span>
                <span>Occupied Beds: <strong class="text-neutral-900"><?= $latestOccupied ?></strong></span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 inline-block"></span>
                <span>Available Beds: <strong class="text-neutral-900"><?= $latestVacant ?></strong></span>
            </div>
            <div class="text-neutral-400">
                Total Capacity: <strong class="text-neutral-800"><?= $latestTotal ?> beds</strong>
            </div>
        </div>
    </div>

    <!-- Daily Snapshots History Table -->
    <div>
        <div class="section-header">
            <div class="flex items-center gap-2">
                <h2 class="text-heading-sm font-semibold text-neutral-900">Historical Snapshot Log</h2>
                <span class="text-caption text-neutral-500 font-medium bg-neutral-100 px-2.5 py-1 rounded-full">
                    <?= $totalSnapshots ?> daily snapshot<?= $totalSnapshots !== 1 ? 's' : '' ?>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Snapshot Date</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Occupied</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Total Beds</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide" style="min-width:140px;">Utilization</th>
                            <th class="p-3 text-right font-semibold text-neutral-500 text-caption uppercase tracking-wide">Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reversedTrend as $t): ?>
                            <?php
                            $occ = (int) $t['occupied_beds'];
                            $tot = (int) $t['total_beds'];
                            $pct = $tot > 0 ? round(($occ / $tot) * 100) : 0;
                            $badgeClass = match(true) {
                                $pct >= 85 => 'badge-error',
                                $pct >= 60 => 'badge-warning',
                                $pct > 0   => 'badge-success',
                                default    => 'badge-neutral',
                            };
                            $dateStr = $t['snapshot_date'] ?? '';
                            $isToday = $dateStr === date('Y-m-d');
                            ?>
                            <tr class="border-b border-neutral-100 hover:bg-neutral-50/80 transition-colors <?= $isToday ? 'bg-primary-50/30' : '' ?>">
                                <td class="p-3">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-neutral-800 text-sm">
                                            <?= !empty($dateStr) ? date('M j, Y', strtotime($dateStr)) : '—' ?>
                                        </span>
                                        <?php if ($isToday): ?>
                                            <span class="badge badge-info text-[10px] !py-0.5 !px-1.5 font-bold uppercase tracking-wide">Today</span>
                                        <?php else: ?>
                                            <span class="text-caption text-neutral-400 font-normal">
                                                (<?= !empty($dateStr) ? date('D', strtotime($dateStr)) : '' ?>)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <span class="font-semibold text-neutral-900"><?= $occ ?></span>
                                    <span class="text-caption text-neutral-400">beds</span>
                                </td>
                                <td class="p-3">
                                    <span class="font-medium text-neutral-700"><?= $tot ?></span>
                                    <span class="text-caption text-neutral-400">beds</span>
                                </td>
                                <td class="p-3">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-2 bg-neutral-100 rounded-full overflow-hidden border border-neutral-200">
                                            <div style="width: <?= $pct ?>%;"
                                                 class="h-full <?= $pct >= 85 ? 'bg-error-500' : ($pct >= 60 ? 'bg-amber-500' : 'bg-success-600') ?>"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3 text-right">
                                    <span class="badge <?= $badgeClass ?> font-bold font-mono">
                                        <?= $pct ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($trend)): ?>
                            <tr>
                                <td colspan="5" class="empty-state" style="padding:3.5rem 0;">
                                    <span style="font-size:2rem;">📈</span>
                                    <span class="text-xs font-medium text-neutral-500 mt-1 block">No occupancy snapshots recorded yet.</span>
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
document.addEventListener('DOMContentLoaded', () => {
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
require __DIR__ . '/../shared/layout.php';
