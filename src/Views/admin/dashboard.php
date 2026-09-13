<?php
$pageTitle = 'Admin Dashboard';
ob_start();
$totalOpenRequests = array_sum(array_column($openByTier, 'c'));
?>
<div class="max-w-5xl mx-auto px-5 py-6 space-y-6">
    <!-- Page Banner -->
    <div class="page-banner">
        <div>
            <h1 class="text-heading-lg font-bold">Command Center</h1>
            <p class="text-body-sm">Live operational metrics &amp; facility status</p>
        </div>
        <span class="badge badge-success" style="background:rgba(255,255,255,0.12); color:#86efac; border:1px solid rgba(255,255,255,0.15);">
            <span style="width:0.4rem;height:0.4rem;border-radius:50%;background:#4ade80;display:inline-block;margin-right:0.4rem;"></span>
            System Operational
        </span>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="reveal-card card p-4 metric-accent-warning">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Pending Payments</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= (int) $pendingPayments ?></p>
            <p class="text-caption text-neutral-400 mt-1">Requires review</p>
        </div>
        <div class="reveal-card card p-4 <?= $activeSos > 0 ? 'metric-accent-error' : 'metric-accent-primary' ?>">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Active SOS Alerts</p>
            <p class="text-3xl font-bold <?= $activeSos > 0 ? 'text-error-600' : 'text-neutral-900' ?> mt-1.5"><?= (int) $activeSos ?></p>
            <p class="text-caption <?= $activeSos > 0 ? 'text-error-600 font-semibold' : 'text-neutral-400' ?> mt-1">
                <?= $activeSos > 0 ? 'Immediate action required' : 'No emergency alerts' ?>
            </p>
        </div>
        <div class="reveal-card card p-4 metric-accent-success">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Occupancy Rate</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $occupancy ? $occupancy['occupied_beds'] . '/' . $occupancy['total_beds'] : '—' ?></p>
            <p class="text-caption text-success-700 mt-1 font-medium">Beds filled</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-info">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Open Maintenance</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $totalOpenRequests ?></p>
            <p class="text-caption text-neutral-400 mt-1">Active work orders</p>
        </div>
    </div>

    <!-- Data Tables Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Open Maintenance by Priority -->
        <div>
            <div class="section-header">
                <h2 class="text-heading-sm font-semibold text-neutral-900">Maintenance Queue</h2>
                <span class="text-caption text-neutral-500 font-medium bg-neutral-100 px-2.5 py-1 rounded-full"><?= $totalOpenRequests ?> open tickets</span>
            </div>
            <table class="w-full card text-body-sm overflow-hidden">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                        <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Priority Tier</th>
                        <th class="p-3 font-semibold text-neutral-500 text-caption uppercase tracking-wide text-right">Active Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($openByTier as $row): ?>
                        <?php 
                        $tier = strtolower($row['priority_tier'] ?? 'unscored');
                        $badge = match($tier) {
                            'critical' => 'badge-error',
                            'high' => 'badge-warning',
                            'medium' => 'badge-info',
                            default => 'badge-neutral'
                        };
                        ?>
                        <tr class="border-b border-neutral-100">
                            <td class="p-3">
                                <span class="badge <?= $badge ?> capitalize"><?= htmlspecialchars($tier) ?></span>
                            </td>
                            <td class="p-3 text-right font-bold text-neutral-900"><?= (int) $row['c'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($openByTier)): ?>
                        <tr><td colspan="2" class="p-6 text-center text-neutral-500 text-xs">No open maintenance requests right now.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Expenses -->
        <div>
            <div class="section-header">
                <h2 class="text-heading-sm font-semibold text-neutral-900">Recent Expenses</h2>
                <span class="text-caption text-neutral-500 font-medium bg-neutral-100 px-2.5 py-1 rounded-full">Latest transactions</span>
            </div>
            <table class="w-full card text-body-sm overflow-hidden">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                        <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Category</th>
                        <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Amount</th>
                        <th class="p-3 text-right font-semibold text-neutral-500 text-caption uppercase tracking-wide">Logged Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentExpenses as $e): ?>
                        <tr class="border-b border-neutral-100">
                            <td class="p-3 font-medium text-neutral-800"><?= htmlspecialchars($e['category']) ?></td>
                            <td class="p-3 font-bold text-neutral-900">₱<?= number_format((float)$e['amount'], 2) ?></td>
                            <td class="p-3 text-right text-caption text-neutral-500"><?= date('M j, Y', strtotime($e['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentExpenses)): ?>
                        <tr><td colspan="3" class="p-6 text-center text-neutral-500 text-xs">No expenses logged yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
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
