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

    <!-- Rent & Collections -->
    <div>
        <div class="section-header">
            <h2>Rent &amp; Collections</h2>
            <a href="/admin/payments" class="text-caption text-neutral-600 font-semibold bg-neutral-100 px-3 py-1 rounded-full hover:bg-neutral-200 transition-colors">
                Review payments &rarr;
            </a>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="card p-4 lg:col-span-1" style="display: flex; flex-direction: column; justify-content: space-between; gap: 1rem;">
                <div>
                    <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Outstanding</span>
                    <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;">₱<?= number_format($collections['total_outstanding'], 2) ?></p>
                    <p class="text-caption text-neutral-400 mt-1">
                        <?= $collections['owing_count'] ?> resident<?= $collections['owing_count'] === 1 ? '' : 's' ?> with a balance
                    </p>
                </div>
                <div style="border-top: 1px solid #e6e5e2; padding-top: 0.875rem;">
                    <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Overdue</span>
                    <p class="font-bold <?= $collections['overdue_count'] > 0 ? 'text-error-600' : 'text-neutral-900' ?> mt-1" style="font-size: 1.375rem; font-variant-numeric: tabular-nums;">₱<?= number_format($collections['overdue_amount'], 2) ?></p>
                    <p class="text-caption <?= $collections['overdue_count'] > 0 ? 'text-error-600 font-semibold' : 'text-neutral-400' ?> mt-1">
                        <?= $collections['overdue_count'] > 0
                            ? $collections['overdue_count'] . ' resident' . ($collections['overdue_count'] === 1 ? '' : 's') . ' past the due date'
                            : 'Nobody is past their due date' ?>
                    </p>
                </div>
                <div style="border-top: 1px solid #e6e5e2; padding-top: 0.875rem;">
                    <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Collected in <?= date('F') ?></span>
                    <p class="font-bold text-neutral-900 mt-1" style="font-size: 1.375rem; font-variant-numeric: tabular-nums;">₱<?= number_format($collections['collected_this_month'], 2) ?></p>
                    <p class="text-caption text-emerald-700 mt-1 font-medium">Approved payments this month</p>
                </div>
            </div>

            <div class="card overflow-hidden lg:col-span-2">
                <div class="overflow-x-auto">
                    <table class="w-full text-body-sm">
                        <thead>
                            <tr>
                                <th>Resident</th>
                                <th>Room</th>
                                <th>Oldest Due</th>
                                <th style="text-align: right;">Owes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($collections['debtors'] as $d): ?>
                                <tr>
                                    <td>
                                        <a href="/admin/boarders/<?= (int) $d['user_id'] ?>" class="font-medium text-neutral-900" style="text-decoration: underline; text-decoration-color: #d4d2ce; text-underline-offset: 3px;">
                                            <?= htmlspecialchars($d['name']) ?>
                                        </a>
                                    </td>
                                    <td class="text-neutral-600"><?= htmlspecialchars($d['room_number'] ?? '—') ?></td>
                                    <td>
                                        <?php if ($d['oldest_due'] === null): ?>
                                            <span class="text-caption text-neutral-400">No due date</span>
                                        <?php else: ?>
                                            <span class="text-caption text-neutral-600"><?= date('M j, Y', strtotime($d['oldest_due'])) ?></span>
                                            <?php if ($d['overdue'] > 0): ?>
                                                <span class="badge badge-error" style="margin-left: 0.375rem;">Overdue</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 700; color: #0a0a0a; font-variant-numeric: tabular-nums;">
                                        ₱<?= number_format($d['owed'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($collections['debtors'])): ?>
                                <tr>
                                    <td colspan="4" class="p-6 text-center text-neutral-400 text-xs">
                                        ✓ Nobody owes anything right now.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($collections['owing_count'] > count($collections['debtors'])): ?>
                    <p class="text-caption text-neutral-500" style="padding: 0.75rem 1rem; border-top: 1px solid #e6e5e2;">
                        Showing <?= count($collections['debtors']) ?> of <?= $collections['owing_count'] ?> residents with a balance.
                        <a href="/admin/boarders" class="font-semibold text-neutral-900" style="text-decoration: underline; text-underline-offset: 3px;">See all boarders</a>
                    </p>
                <?php endif; ?>
            </div>
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

<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
