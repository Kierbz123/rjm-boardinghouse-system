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
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <!-- Executive Page Banner with Quick Launch Command Bar -->
    <header class="dash-hero-banner">
        <div class="dash-hero-top">
            <div>
                <h1 class="dash-hero-title">
                    <span>Occupancy Trends</span>
                </h1>
                <p class="dash-hero-sub">Historical capacity utilization, 30-day bed occupancy telemetry, and space forecasting.</p>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="badge" style="background:rgba(255,255,255,0.08); color:#8cc2a2; border:1px solid rgba(255,255,255,0.15); padding:0.35rem 0.85rem; font-size:0.75rem; font-weight:600; border-radius:9999px; display:inline-flex; align-items:center;">
                    <span class="live-pulse-dot" style="margin-right:0.45rem;"></span>
                    Telemetry Active
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
            <a href="#capacity-distribution" class="dash-cmd-btn highlight" title="Jump to capacity distribution breakdown">
                <?= \App\Support\Icon::svg('bar-chart', 14) ?>
                <span>Capacity Distribution</span>
            </a>
            <a href="#snapshot-log" class="dash-cmd-btn" title="Jump to daily snapshot log">
                <?= \App\Support\Icon::svg('clipboard', 14) ?>
                <span>Snapshot Log (<?= $totalSnapshots ?>)</span>
            </a>
            <a href="/admin/staff?view=boarders&status=active" class="dash-cmd-btn" title="View active boarders">
                <?= \App\Support\Icon::svg('users', 14) ?>
                <span>Active Residents (<?= $latestOccupied ?>)</span>
            </a>
            <a href="/admin/rooms" class="dash-cmd-btn" title="Manage rooms and bed slots">
                <?= \App\Support\Icon::svg('home', 14) ?>
                <span>Rooms &amp; Beds</span>
            </a>
        </div>
    </header>

    <!-- KPI Cards -->
    <div class="kpi-grid">
        <a href="#capacity-distribution" class="reveal-card kpi-card metric-accent-primary" title="View capacity distribution">
            <div class="kpi-card-header">
                <span class="kpi-label">Current Occupancy</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('trending-up') ?></span>
            </div>
            <div class="kpi-value"><?= $latestPct ?>%</div>
            <div class="kpi-subtext">
                <span class="text-neutral-400"><?= $latestOccupied ?> of <?= $latestTotal ?> beds filled</span>
            </div>
        </a>

        <a href="/admin/staff?view=boarders&status=active" class="reveal-card kpi-card metric-accent-warning" title="View active residents">
            <div class="kpi-card-header">
                <span class="kpi-label">Occupied Beds</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('users') ?></span>
            </div>
            <div class="kpi-value"><?= $latestOccupied ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Active residents</span>
            </div>
        </a>

        <a href="/admin/rooms" class="reveal-card kpi-card metric-accent-success" title="View room type offerings">
            <div class="kpi-card-header">
                <span class="kpi-label">Available Beds</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('circle-dot') ?></span>
            </div>
            <div class="kpi-value <?= $latestVacant === 0 ? 'text-error-600' : 'text-neutral-900' ?>"><?= $latestVacant ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Vacant capacity</span>
            </div>
        </a>

        <a href="#snapshot-log" class="reveal-card kpi-card metric-accent-info" title="View daily historical snapshot log">
            <div class="kpi-card-header">
                <span class="kpi-label">30-Day Average</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('bar-chart') ?></span>
            </div>
            <div class="kpi-value"><?= $avgPct ?>%</div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Peak: <?= $peakPct ?>% across <?= $totalSnapshots ?> days</span>
            </div>
        </a>
    </div>

    <!-- Current Capacity Status Breakdown Card -->
    <div class="card p-5 space-y-3.5" id="capacity-distribution">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h3 class="text-body font-bold text-neutral-900">Current Capacity Distribution</h3>
                <p class="text-caption text-neutral-500">Live allocation across all rooms and bed units</p>
            </div>
            <span class="badge <?= $latestPct >= 90 ? 'badge-error' : ($latestPct >= 70 ? 'badge-warning' : 'badge-success') ?> font-semibold" style="font-size:0.75rem; padding: 0.35rem 0.75rem;">
                <?= $latestPct ?>% Capacity Filled
            </span>
        </div>

        <!-- Multi-segment visual progress bar -->
        <div class="w-full h-4 bg-neutral-100 rounded-full overflow-hidden flex border border-neutral-200">
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

        <div class="flex flex-wrap items-center justify-between gap-3 text-caption text-neutral-600 pt-1">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-primary-600 inline-block"></span>
                <span>Occupied Beds: <strong class="text-neutral-900 font-bold"><?= $latestOccupied ?></strong></span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 inline-block"></span>
                <span>Available Beds: <strong class="text-neutral-900 font-bold"><?= $latestVacant ?></strong></span>
            </div>
            <div class="text-neutral-500 font-medium">
                Total Capacity: <strong class="text-neutral-800"><?= $latestTotal ?> beds</strong>
            </div>
        </div>
    </div>

    <!-- Daily Snapshots History Table -->
    <div id="snapshot-log">
        <div class="section-header">
            <div class="flex items-center gap-2">
                <h2>Historical Snapshot Log</h2>
                <span class="text-caption text-neutral-500 font-medium bg-neutral-100 px-3 py-1 rounded-full">
                    <?= $totalSnapshots ?> daily snapshot<?= $totalSnapshots !== 1 ? 's' : '' ?>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm" style="min-width: 650px;">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Snapshot Date</th>
                            <th style="width: 18%;">Occupied</th>
                            <th style="width: 18%;">Total Beds</th>
                            <th style="width: 20%;">Utilization</th>
                            <th style="width: 14%; text-align: right;">Rate</th>
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
                            <tr class="<?= $isToday ? 'bg-primary-50/40 font-medium' : '' ?>">
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-neutral-800 text-sm">
                                            <?= !empty($dateStr) ? date('M j, Y', strtotime($dateStr)) : '—' ?>
                                        </span>
                                        <?php if ($isToday): ?>
                                            <span class="badge badge-info text-[10px] !py-0.5 !px-2 font-bold uppercase tracking-wide">Today</span>
                                        <?php else: ?>
                                            <span class="text-caption text-neutral-400 font-normal">
                                                (<?= !empty($dateStr) ? date('D', strtotime($dateStr)) : '' ?>)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="font-bold text-neutral-900"><?= $occ ?></span>
                                    <span class="text-caption text-neutral-400">beds</span>
                                </td>
                                <td>
                                    <span class="font-medium text-neutral-700"><?= $tot ?></span>
                                    <span class="text-caption text-neutral-400">beds</span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-2 bg-neutral-100 rounded-full overflow-hidden border border-neutral-200">
                                            <div style="width: <?= $pct ?>%;"
                                                 class="h-full <?= $pct >= 85 ? 'bg-error-500' : ($pct >= 60 ? 'bg-amber-500' : 'bg-success-600') ?>"></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align: right;">
                                    <span class="badge <?= $badgeClass ?> font-bold font-mono">
                                        <?= $pct ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($trend)): ?>
                            <tr>
                                <td colspan="5" class="empty-state" style="padding:3.5rem 0;">
                                    <span class="empty-icon"><?= \App\Support\Icon::svg('trending-up') ?></span>
                                    <span class="text-sm font-semibold text-neutral-800">No occupancy snapshots recorded yet</span>
                                    <span class="text-xs text-neutral-500 mt-1">Daily metrics are logged automatically by the system.</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
