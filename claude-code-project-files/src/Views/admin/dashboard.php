<?php
$pageTitle = 'Admin Dashboard';
ob_start();
$totalOpenRequests = array_sum(array_column($openByTier, 'c'));
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-6 space-y-6">
    <!-- Page Banner -->
    <div class="page-banner">
        <div>
            <h1>Command Center</h1>
            <p>Live operational metrics, facility occupancy &amp; emergency alerts</p>
        </div>
        <span class="badge badge-success" style="background:rgba(255,255,255,0.12); color:#8cc2a2; border:1px solid rgba(255,255,255,0.18); padding:0.4rem 0.85rem; font-size:0.75rem;">
            <span style="width:0.45rem;height:0.45rem;border-radius:50%;background:#5fae84;display:inline-block;margin-right:0.4rem;"></span>
            System Operational
        </span>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="reveal-card card p-4 metric-accent-warning">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Pending Payments</span>
                <span style="font-size:1.1rem; line-height:1;">💳</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= (int) $pendingPayments ?></p>
            <p class="text-caption text-neutral-400 mt-1">Requires review &amp; verification</p>
        </div>

        <div class="reveal-card card p-4 <?= $activeSos > 0 ? 'metric-accent-error' : 'metric-accent-primary' ?>">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Active SOS Alerts</span>
                <span style="font-size:1.1rem; line-height:1;">🚨</span>
            </div>
            <p class="text-3xl font-bold <?= $activeSos > 0 ? 'text-error-600' : 'text-neutral-900' ?> mt-1" style="font-variant-numeric: tabular-nums;"><?= (int) $activeSos ?></p>
            <p class="text-caption <?= $activeSos > 0 ? 'text-error-600 font-semibold' : 'text-neutral-400' ?> mt-1">
                <?= $activeSos > 0 ? 'Immediate action required' : 'No emergency alerts' ?>
            </p>
        </div>

        <div class="reveal-card card p-4 metric-accent-success">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Occupancy Rate</span>
                <span style="font-size:1.1rem; line-height:1;">🏠</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;">
                <?= $occupancy ? $occupancy['occupied_beds'] . '/' . $occupancy['total_beds'] : '—' ?>
            </p>
            <p class="text-caption text-emerald-700 mt-1 font-medium">Beds currently filled</p>
        </div>

        <div class="reveal-card card p-4 metric-accent-info">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Open Maintenance</span>
                <span style="font-size:1.1rem; line-height:1;">🛠️</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= $totalOpenRequests ?></p>
            <p class="text-caption text-neutral-400 mt-1">Active tickets in queue</p>
        </div>
    </div>

    <!-- Data Tables Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Open Maintenance by Priority -->
        <div class="flex flex-col">
            <div class="section-header">
                <h2>Maintenance Queue</h2>
                <a href="/staff/maintenance" class="text-caption text-primary-700 font-semibold bg-primary-50 px-3 py-1 rounded-full hover:bg-primary-100 transition-colors">
                    <?= $totalOpenRequests ?> open tickets &rarr;
                </a>
            </div>
            <div class="card overflow-hidden flex-1">
                <div class="overflow-x-auto">
                    <table class="w-full text-body-sm">
                        <thead>
                            <tr>
                                <th>Priority Tier</th>
                                <th style="text-align: right;">Active Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($openByTier as $row): ?>
                                <?php 
                                $tier = strtolower($row['priority_tier'] ?? 'unscored');
                                $badge = match($tier) {
                                    'critical' => 'badge-error',
                                    'high'     => 'badge-warning',
                                    'medium'   => 'badge-info',
                                    default    => 'badge-neutral'
                                };
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge <?= $badge ?> capitalize"><?= htmlspecialchars($tier) ?></span>
                                    </td>
                                    <td style="text-align: right; font-weight: 700; color: #0a0a0a; font-variant-numeric: tabular-nums;">
                                        <?= (int) $row['c'] ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($openByTier)): ?>
                                <tr>
                                    <td colspan="2" class="p-6 text-center text-neutral-400 text-xs">
                                        ✓ No open maintenance requests right now.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Expenses -->
        <div class="flex flex-col">
            <div class="section-header">
                <h2>Recent Expenses</h2>
                <a href="/admin/expenses" class="text-caption text-neutral-600 font-semibold bg-neutral-100 px-3 py-1 rounded-full hover:bg-neutral-200 transition-colors">
                    View all expenses &rarr;
                </a>
            </div>
            <div class="card overflow-hidden flex-1">
                <div class="overflow-x-auto">
                    <table class="w-full text-body-sm">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Amount</th>
                                <th style="text-align: right;">Logged Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentExpenses as $e): ?>
                                <tr>
                                    <td class="font-medium text-neutral-800"><?= htmlspecialchars($e['category']) ?></td>
                                    <td class="font-bold text-neutral-900" style="font-variant-numeric: tabular-nums;">
                                        ₱<?= number_format((float)$e['amount'], 2) ?>
                                    </td>
                                    <td style="text-align: right;" class="text-caption text-neutral-500">
                                        <?= date('M j, Y', strtotime($e['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentExpenses)): ?>
                                <tr>
                                    <td colspan="3" class="p-6 text-center text-neutral-400 text-xs">
                                        No expenses logged yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
