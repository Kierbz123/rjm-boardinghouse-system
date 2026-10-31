<?php
$pageTitle = 'Admin Dashboard';
ob_start();

$totalOpenRequests = array_sum(array_column($openByTier, 'c'));

// Calculate occupancy breakdown
$totalBeds = (int) ($occupancy['total_beds'] ?? 0);
$occupiedBeds = (int) ($occupancy['occupied_beds'] ?? 0);
$vacantBeds = max(0, $totalBeds - $occupiedBeds);
$occupancyPct = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0;

// Calculate critical maintenance
$criticalCount = 0;
$highCount = 0;
foreach ($openByTier as $t) {
    $tierName = strtolower($t['priority_tier'] ?? '');
    if ($tierName === 'critical') {
        $criticalCount += (int) $t['c'];
    } elseif ($tierName === 'high') {
        $highCount += (int) $t['c'];
    }
}
?>

<style>
/* ==========================================================================
   ADMIN DASHBOARD — LUMORA EXECUTIVE COMMAND CENTER
   Responsive, high-density, organized layout with micro-interactions
   ========================================================================== */

.dashboard-wrapper {
    max-width: 82rem;
    margin-left: auto;
    margin-right: auto;
    padding: 1.5rem 1rem 3.5rem;
}
@media (min-width: 640px) {
    .dashboard-wrapper {
        padding: 1.75rem 1.5rem 4rem;
    }
}
@media (min-width: 1024px) {
    .dashboard-wrapper {
        padding: 2rem 2rem 4.5rem;
    }
}

/* Headings safety override against layout.php white-space: nowrap */
.dashboard-wrapper h1,
.dashboard-wrapper h2,
.dashboard-wrapper h3,
.dashboard-wrapper h4 {
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: clip !important;
}

/* Executive Banner */
.dash-hero-banner {
    background: linear-gradient(135deg, #0a0a0a 0%, #161616 55%, #222220 100%);
    border-radius: 1rem;
    padding: 1.5rem 1.75rem;
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 8px 24px -6px rgba(10, 10, 10, 0.25);
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
}
.dash-hero-banner::after {
    content: '';
    position: absolute;
    top: -40px;
    right: -40px;
    width: 220px;
    height: 220px;
    background: radial-gradient(circle, rgba(177, 95, 44, 0.18) 0%, rgba(177, 95, 44, 0) 70%);
    pointer-events: none;
}
.dash-hero-top {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
@media (min-width: 640px) {
    .dash-hero-top {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}
.dash-hero-title {
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: -0.025em;
    color: #ffffff !important;
    line-height: 1.2;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.dash-hero-sub {
    font-size: 0.8125rem;
    color: #a3a19b;
    margin-top: 0.25rem;
}

/* Quick Action Command Bar inside Banner */
.dash-cmd-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1.25rem;
    padding-top: 1.15rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}
.dash-cmd-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #f1f0ee;
    background: rgba(255, 255, 255, 0.07);
    border: 1px solid rgba(255, 255, 255, 0.12);
    padding: 0.4rem 0.85rem;
    border-radius: 9999px;
    text-decoration: none;
    transition: all 160ms cubic-bezier(0.2, 0.8, 0.2, 1);
    white-space: nowrap;
}
.dash-cmd-btn:hover {
    background: rgba(255, 255, 255, 0.16);
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.25);
    transform: translateY(-1px);
}
.dash-cmd-btn.highlight {
    background: linear-gradient(135deg, rgba(177, 95, 44, 0.85) 0%, rgba(151, 80, 31, 0.95) 100%);
    border-color: rgba(255, 255, 255, 0.25);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(177, 95, 44, 0.35);
}
.dash-cmd-btn.highlight:hover {
    background: linear-gradient(135deg, rgba(207, 128, 71, 0.95) 0%, rgba(177, 95, 44, 1) 100%);
    box-shadow: 0 4px 12px rgba(177, 95, 44, 0.45);
}

/* Pulse animation for system health */
@keyframes pulse-emerald {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.85); }
}
.live-pulse-dot {
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 50%;
    background: #5fae84;
    display: inline-block;
    animation: pulse-emerald 2.2s ease-in-out infinite;
}

/* Financial Dashboard Left Column Cards */
.fin-metric-tile {
    background: #f8f7f5;
    border: 1px solid #e6e5e2;
    border-radius: 0.75rem;
    padding: 1rem 1.15rem;
    transition: border-color 160ms ease, background-color 160ms ease;
}
.fin-metric-tile:hover {
    border-color: #d4d2ce;
    background: #ffffff;
}
.fin-metric-title {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #6b6b6b;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.fin-metric-amount {
    font-size: 1.4rem;
    font-weight: 800;
    line-height: 1.2;
    color: #0a0a0a;
    margin-top: 0.25rem;
    font-variant-numeric: tabular-nums;
}
.fin-metric-sub {
    font-size: 0.75rem;
    color: #6b6b6b;
    margin-top: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

/* Debtor avatar initials */
.resident-avatar-circle {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    background: #f1f0ee;
    border: 1px solid #e6e5e2;
    color: #3b3a38;
    font-size: 0.75rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* Maintenance Queue Tier Row */
.tier-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.65rem 0.85rem;
    border-radius: 0.5rem;
    transition: background-color 140ms ease;
    text-decoration: none;
    color: inherit;
}
.tier-row:hover {
    background-color: #f8f7f5;
}
.tier-bar-bg {
    flex: 1;
    height: 6px;
    background: #f1f0ee;
    border-radius: 9999px;
    margin: 0 1rem;
    overflow: hidden;
}
.tier-bar-fill {
    height: 100%;
    border-radius: 9999px;
}

/* Quick Navigation Telemetry Strip at Bottom */
.telemetry-grid {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 0.875rem;
}
@media (min-width: 640px) {
    .telemetry-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (min-width: 1024px) {
    .telemetry-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}
.telemetry-card {
    background: #ffffff;
    border: 1px solid #e6e5e2;
    border-radius: 0.75rem;
    padding: 1rem 1.15rem;
    text-decoration: none;
    color: inherit;
    display: flex;
    align-items: center;
    gap: 0.875rem;
    transition: all 160ms ease;
}
.telemetry-card:hover {
    border-color: #bd6b36;
    box-shadow: 0 4px 12px -2px rgba(10, 10, 10, 0.06);
    transform: translateY(-1px);
}
.telemetry-icon-wrap {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 0.5rem;
    background: #f8f7f5;
    color: #555452;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 160ms ease;
}
.telemetry-card:hover .telemetry-icon-wrap {
    background: #fbf4ef;
    color: #b15f2c;
}
</style>

<div class="dashboard-wrapper space-y-6">

    <?php if (\App\Database::describe()['insecure']): ?>
    <div class="rounded-xl border border-amber-300 bg-amber-50 text-amber-900 px-4 py-3 text-sm flex items-start gap-3 shadow-xs" role="alert" id="db-login-warning">
        <span class="text-amber-700 mt-0.5"><?= \App\Support\Icon::svg('shield-alert', 18) ?></span>
        <div>
            <strong>The database is open to anyone on this computer.</strong> The system is logged in to MariaDB as <code>root</code> with no password.
            Run <code>php bin\setup-db-user.php</code> once in the project folder, then restart the system. The online launcher will not start until this is done.
        </div>
    </div>
    <?php endif; ?>

    <!-- Executive Hero Banner -->
    <div class="dash-hero-banner">
        <div class="dash-hero-top">
            <div>
                <h1 class="dash-hero-title">
                    <span class="inline-flex text-primary-400"><?= \App\Support\Icon::svg('dashboard', 22) ?></span>
                    <span>Command Center</span>
                </h1>
                <p class="dash-hero-sub">Live operational telemetry, tenant occupancy &amp; financial operations</p>
            </div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="badge" style="background:rgba(255,255,255,0.08); color:#f1f0ee; border:1px solid rgba(255,255,255,0.15); padding:0.35rem 0.85rem; font-size:0.75rem; font-weight:600;">
                    <span class="live-pulse-dot" style="margin-right:0.45rem;"></span>
                    System Operational
                </span>
                <span class="badge" style="background:rgba(255,255,255,0.05); color:#a3a19b; border:1px solid rgba(255,255,255,0.1); padding:0.35rem 0.75rem; font-size:0.75rem;">
                    <?= \App\Support\Icon::svg('calendar', 13) ?>
                    <span class="ml-1"><?= date('l, M j, Y') ?></span>
                </span>
            </div>
        </div>

        <!-- Quick Launch Command Bar -->
        <div class="dash-cmd-bar">
            <span style="font-size:0.6875rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:rgba(255,255,255,0.5); margin-right:0.35rem;">
                Quick Launch:
            </span>
            <a href="/admin/payments/record" class="dash-cmd-btn highlight" title="Record in-person payment and issue printed receipt">
                <?= \App\Support\Icon::svg('credit-card', 14) ?>
                <span>Record Payment</span>
            </a>
            <a href="/admin/boarders#add-boarder" class="dash-cmd-btn" title="Add new resident or assign bed">
                <?= \App\Support\Icon::svg('plus', 14) ?>
                <span>Add Boarder</span>
            </a>
            <a href="/admin/rooms" class="dash-cmd-btn" title="Manage rooms, beds and electricity billing">
                <?= \App\Support\Icon::svg('bed', 14) ?>
                <span>Rooms &amp; Beds</span>
            </a>
            <a href="/staff/maintenance" class="dash-cmd-btn" title="Review open repair requests">
                <?= \App\Support\Icon::svg('wrench', 14) ?>
                <span>Maintenance (<?= $totalOpenRequests ?>)</span>
            </a>
            <a href="/admin/inquiry-center" class="dash-cmd-btn" title="Track applicant inquiries">
                <?= \App\Support\Icon::svg('message-square', 14) ?>
                <span>Inquiry Center</span>
            </a>
            <a href="/admin/expenses" class="dash-cmd-btn" title="Log and audit operating expenses">
                <?= \App\Support\Icon::svg('receipt', 14) ?>
                <span>Log Expense</span>
            </a>
        </div>
    </div>

    <!-- Elevated KPI Grid -->
    <div class="kpi-grid">
        <!-- 1. Pending Payments -->
        <a href="/admin/payments" class="reveal-card kpi-card <?= $pendingPayments > 0 ? 'metric-accent-warning' : 'metric-accent-primary' ?>" title="Review pending payments">
            <div class="kpi-card-header">
                <span class="kpi-label">Pending Payments</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('credit-card') ?></span>
            </div>
            <div class="kpi-value <?= $pendingPayments > 0 ? 'text-amber-600' : '' ?>"><?= (int) $pendingPayments ?></div>
            <div class="kpi-subtext">
                <?php if ($pendingPayments > 0): ?>
                    <span class="text-amber-700 font-semibold"><?= $pendingPayments ?> proof<?= $pendingPayments === 1 ? '' : 's' ?> awaiting verification</span>
                <?php else: ?>
                    <span class="text-neutral-400">All payment proofs verified</span>
                <?php endif; ?>
            </div>
        </a>

        <!-- 2. Active SOS Alerts -->
        <a href="/staff/incidents" class="reveal-card kpi-card <?= $activeSos > 0 ? 'metric-accent-error' : 'metric-accent-primary' ?>" title="View active SOS emergency alerts">
            <div class="kpi-card-header">
                <span class="kpi-label">Active SOS Alerts</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('siren') ?></span>
            </div>
            <div class="kpi-value <?= $activeSos > 0 ? 'text-error-600' : '' ?>"><?= (int) $activeSos ?></div>
            <div class="kpi-subtext">
                <?php if ($activeSos > 0): ?>
                    <span class="text-error-600 font-semibold">Immediate response required</span>
                <?php else: ?>
                    <span class="text-neutral-400">All residents safe &amp; secure</span>
                <?php endif; ?>
            </div>
        </a>

        <!-- 3. Occupancy Rate -->
        <a href="/admin/occupancy" class="reveal-card kpi-card metric-accent-success" title="View occupancy telemetry and bed directory">
            <div class="kpi-card-header">
                <span class="kpi-label">Current Occupancy</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('trending-up') ?></span>
            </div>
            <div class="kpi-value"><?= $occupancyPct ?>%</div>
            <div class="kpi-subtext">
                <span class="text-neutral-400"><?= $occupiedBeds ?> of <?= $totalBeds ?> beds filled</span>
            </div>
        </a>

        <!-- 4. Open Maintenance -->
        <a href="/staff/maintenance" class="reveal-card kpi-card <?= $criticalCount > 0 ? 'metric-accent-error' : ($totalOpenRequests > 0 ? 'metric-accent-info' : 'metric-accent-primary') ?>" title="View maintenance queue">
            <div class="kpi-card-header">
                <span class="kpi-label">Open Maintenance</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('wrench') ?></span>
            </div>
            <div class="kpi-value <?= $criticalCount > 0 ? 'text-red-600' : '' ?>"><?= $totalOpenRequests ?></div>
            <div class="kpi-subtext">
                <?php if ($criticalCount > 0): ?>
                    <span class="text-red-600 font-semibold"><?= $criticalCount ?> critical urgent repair<?= $criticalCount === 1 ? '' : 's' ?></span>
                <?php elseif ($totalOpenRequests > 0): ?>
                    <span class="text-neutral-400"><?= $totalOpenRequests ?> active ticket<?= $totalOpenRequests === 1 ? '' : 's' ?></span>
                <?php else: ?>
                    <span class="text-neutral-400">All repairs completed</span>
                <?php endif; ?>
            </div>
        </a>
    </div>

    <!-- Rent & Collections (Financial Command Center) -->
    <div class="space-y-3">
        <div class="section-header">
            <div>
                <h2>Rent &amp; Collections</h2>
                <p class="text-caption text-neutral-500 mt-0.5">Billing cycles, overdue balances and active receivables</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="/admin/payments/record" class="btn btn-primary !py-1.5 !px-3.5 !text-xs inline-flex items-center gap-1.5 shadow-xs font-semibold">
                    <?= \App\Support\Icon::svg('credit-card', 14) ?>
                    <span>Record Payment</span>
                </a>
                <a href="/admin/payments" class="text-caption text-neutral-600 font-semibold bg-neutral-100 px-3.5 py-1.5 rounded-full hover:bg-neutral-200 transition-colors inline-flex items-center gap-1">
                    <span>Review payments</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <!-- Financial Summary Cards (1 Col) -->
            <div class="card p-4 space-y-3.5 lg:col-span-1 shadow-card">
                <div class="flex items-center justify-between pb-2 border-b border-neutral-200">
                    <span class="text-xs font-bold uppercase tracking-wider text-neutral-500">Financial Summary</span>
                    <span class="badge badge-neutral text-xs"><?= date('F Y') ?></span>
                </div>

                <!-- Tile 1: Total Outstanding -->
                <div class="fin-metric-tile">
                    <div class="fin-metric-title">
                        <span>Total Receivables</span>
                        <span class="text-neutral-400"><?= \App\Support\Icon::svg('wallet', 14) ?></span>
                    </div>
                    <div class="fin-metric-amount">
                        ₱<?= number_format($collections['total_outstanding'], 2) ?>
                    </div>
                    <div class="fin-metric-sub">
                        <span class="badge badge-neutral text-xs font-medium">
                            <?= $collections['owing_count'] ?> resident<?= $collections['owing_count'] === 1 ? '' : 's' ?>
                        </span>
                        <span>with active balance</span>
                    </div>
                </div>

                <!-- Tile 2: Overdue Arrears -->
                <div class="fin-metric-tile <?= $collections['overdue_count'] > 0 ? '!bg-red-50/60 !border-red-200' : '' ?>">
                    <div class="fin-metric-title">
                        <span class="<?= $collections['overdue_count'] > 0 ? 'text-red-800' : '' ?>">Overdue Arrears</span>
                        <span class="<?= $collections['overdue_count'] > 0 ? 'text-red-600' : 'text-neutral-400' ?>">
                            <?= \App\Support\Icon::svg('alert-circle', 14) ?>
                        </span>
                    </div>
                    <div class="fin-metric-amount <?= $collections['overdue_count'] > 0 ? 'text-red-700' : '' ?>">
                        ₱<?= number_format($collections['overdue_amount'], 2) ?>
                    </div>
                    <div class="fin-metric-sub">
                        <?php if ($collections['overdue_count'] > 0): ?>
                            <span class="badge badge-error text-xs font-bold">
                                <?= $collections['overdue_count'] ?> resident<?= $collections['overdue_count'] === 1 ? '' : 's' ?> past due
                            </span>
                        <?php else: ?>
                            <span class="text-emerald-700 text-xs font-medium inline-flex items-center gap-1">
                                <?= \App\Support\Icon::svg('check', 12) ?> All accounts current
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tile 3: Collected this Month -->
                <div class="fin-metric-tile !bg-emerald-50/40 !border-emerald-200">
                    <div class="fin-metric-title">
                        <span class="text-emerald-900">Collected in <?= date('F') ?></span>
                        <span class="text-emerald-600"><?= \App\Support\Icon::svg('banknote', 14) ?></span>
                    </div>
                    <div class="fin-metric-amount text-emerald-900">
                        ₱<?= number_format($collections['collected_this_month'], 2) ?>
                    </div>
                    <div class="fin-metric-sub text-emerald-800 font-medium">
                        <?= \App\Support\Icon::svg('check-circle', 12) ?> Approved payments this cycle
                    </div>
                </div>

                <div class="pt-2">
                    <a href="/admin/expenses" class="w-full btn btn-secondary !py-2 !text-xs font-semibold inline-flex items-center justify-center gap-1.5">
                        <?= \App\Support\Icon::svg('bar-chart', 13) ?>
                        <span>Operating Expenses &amp; Ledger &rarr;</span>
                    </a>
                </div>
            </div>

            <!-- Active Balances & Receivables Ledger (2 Cols) -->
            <div class="card overflow-hidden lg:col-span-2 shadow-card">
                <div class="p-3.5 sm:p-4 bg-neutral-50 border-b border-neutral-200 flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <h3 class="font-bold text-sm text-neutral-900">Active Receivables Ledger</h3>
                        <span class="badge badge-neutral text-xs">
                            <?= count($collections['debtors']) ?> of <?= $collections['owing_count'] ?> account<?= $collections['owing_count'] === 1 ? '' : 's' ?>
                        </span>
                    </div>
                    <span class="text-caption text-neutral-500">Sorted by overdue &amp; balance</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-body-sm" style="min-width: 580px;">
                        <thead>
                            <tr>
                                <th>Resident</th>
                                <th>Room</th>
                                <th>Oldest Due</th>
                                <th style="text-align: right;">Balance</th>
                                <th style="text-align: right; width: 100px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($collections['debtors'] as $d): ?>
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <div class="resident-avatar-circle" title="<?= htmlspecialchars($d['name']) ?>">
                                                <?= strtoupper(substr($d['name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <a href="/admin/boarders/<?= (int) $d['user_id'] ?>" class="font-semibold text-neutral-900 hover:text-primary-700 transition-colors" style="text-decoration: underline; text-decoration-color: #d4d2ce; text-underline-offset: 3px;">
                                                    <?= htmlspecialchars($d['name']) ?>
                                                </a>
                                                <div class="text-caption text-neutral-500">ID #<?= (int) $d['user_id'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-neutral font-medium">
                                            <?= \App\Support\Icon::svg('home', 12) ?>
                                            <?= htmlspecialchars($d['room_number'] ?? 'Unassigned') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($d['oldest_due'] === null): ?>
                                            <span class="text-caption text-neutral-400">No due date</span>
                                        <?php else: ?>
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-caption text-neutral-700 font-medium"><?= date('M j, Y', strtotime($d['oldest_due'])) ?></span>
                                                <?php if ($d['overdue'] > 0): ?>
                                                    <span class="badge badge-error text-xs font-bold">Overdue</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 700; color: #0a0a0a; font-variant-numeric: tabular-nums;">
                                        ₱<?= number_format($d['owed'], 2) ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="/admin/payments/record?boarder=<?= (int) $d['user_id'] ?>" 
                                           class="btn btn-secondary !py-1 !px-2.5 !text-xs inline-flex items-center gap-1 font-semibold hover:border-primary-500"
                                           title="Record payment for <?= htmlspecialchars($d['name']) ?>">
                                            <?= \App\Support\Icon::svg('credit-card', 12) ?>
                                            <span>Pay</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($collections['debtors'])): ?>
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-neutral-500">
                                        <div class="empty-icon text-emerald-600 !bg-emerald-50">
                                            <?= \App\Support\Icon::svg('check-circle', 22) ?>
                                        </div>
                                        <p class="font-semibold text-neutral-800 text-sm mt-2">All resident accounts are settled</p>
                                        <p class="text-caption text-neutral-500 mt-1">Nobody owes any balance or rent at this time.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($collections['owing_count'] > count($collections['debtors'])): ?>
                    <div class="p-3 bg-neutral-50 border-t border-neutral-200 text-caption text-neutral-600 flex items-center justify-between flex-wrap gap-2">
                        <span>Showing top <?= count($collections['debtors']) ?> of <?= $collections['owing_count'] ?> residents with a balance.</span>
                        <a href="/admin/staff?view=boarders" class="font-semibold text-neutral-900 hover:text-primary-700" style="text-decoration: underline; text-underline-offset: 3px;">
                            See all boarders &rarr;
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Operations & Facility Health (2-Column Grid) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- 1. Maintenance Queue by Priority -->
        <div class="flex flex-col">
            <div class="section-header">
                <div>
                    <h2>Maintenance Queue</h2>
                    <p class="text-caption text-neutral-500 mt-0.5">Time-decayed repair priority breakdown</p>
                </div>
                <a href="/staff/maintenance" class="text-caption text-primary-700 font-semibold bg-primary-50 px-3.5 py-1.5 rounded-full hover:bg-primary-100 transition-colors inline-flex items-center gap-1">
                    <span><?= $totalOpenRequests ?> open tickets</span>
                    <span>&rarr;</span>
                </a>
            </div>

            <div class="card p-4 flex-1 shadow-card flex flex-col justify-between">
                <?php if (!empty($openByTier)): ?>
                    <div class="space-y-2">
                        <?php foreach ($openByTier as $row): ?>
                            <?php 
                            $tier = strtolower($row['priority_tier'] ?? 'unscored');
                            $count = (int) $row['c'];
                            $pct = $totalOpenRequests > 0 ? round(($count / $totalOpenRequests) * 100) : 0;
                            
                            $badgeClass = match($tier) {
                                'critical' => 'badge-error',
                                'high'     => 'badge-warning',
                                'medium'   => 'badge-info',
                                default    => 'badge-neutral'
                            };
                            $barColor = match($tier) {
                                'critical' => '#b42318',
                                'high'     => '#b98a12',
                                'medium'   => '#2e5aac',
                                default    => '#6b6b6b'
                            };
                            ?>
                            <a href="/staff/maintenance" class="tier-row" title="View <?= htmlspecialchars($tier) ?> tickets in maintenance queue">
                                <span class="badge <?= $badgeClass ?> capitalize font-semibold w-24 justify-center">
                                    <?= htmlspecialchars($tier) ?>
                                </span>
                                <div class="tier-bar-bg" title="<?= $pct ?>% of open tickets">
                                    <div class="tier-bar-fill" style="width: <?= max(6, $pct) ?>%; background-color: <?= $barColor ?>;"></div>
                                </div>
                                <span style="font-weight: 700; color: #0a0a0a; font-variant-numeric: tabular-nums; font-size: 0.9375rem; width: 2.5rem; text-align: right;">
                                    <?= $count ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state py-8">
                        <div class="empty-icon text-emerald-600 !bg-emerald-50">
                            <?= \App\Support\Icon::svg('check-circle', 22) ?>
                        </div>
                        <p class="font-semibold text-neutral-800 text-sm">Maintenance Queue is Empty</p>
                        <p class="text-caption text-neutral-500">No pending repairs or facility maintenance tickets.</p>
                    </div>
                <?php endif; ?>

                <div class="pt-3 mt-3 border-t border-neutral-200 flex items-center justify-between text-caption text-neutral-500">
                    <span>Priority decays automatically over time</span>
                    <a href="/staff/maintenance" class="font-semibold text-neutral-900 hover:text-primary-700">Open Queue &rarr;</a>
                </div>
            </div>
        </div>

        <!-- 2. Recent Expenses -->
        <div class="flex flex-col">
            <div class="section-header">
                <div>
                    <h2>Recent Operating Expenses</h2>
                    <p class="text-caption text-neutral-500 mt-0.5">Facility expenditures and disbursements</p>
                </div>
                <a href="/admin/expenses" class="text-caption text-neutral-600 font-semibold bg-neutral-100 px-3.5 py-1.5 rounded-full hover:bg-neutral-200 transition-colors inline-flex items-center gap-1">
                    <span>View all expenses</span>
                    <span>&rarr;</span>
                </a>
            </div>

            <div class="card overflow-hidden flex-1 shadow-card flex flex-col justify-between">
                <div class="overflow-x-auto">
                    <table class="w-full text-body-sm" style="min-width: 480px;">
                        <thead>
                            <tr>
                                <th>Category &amp; Staff</th>
                                <th>Amount</th>
                                <th style="text-align: right;">Logged Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentExpenses as $e): ?>
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <span class="badge badge-neutral font-semibold">
                                                <?= htmlspecialchars($e['category'] ?? 'General') ?>
                                            </span>
                                        </div>
                                        <div class="text-caption text-neutral-500 mt-0.5">
                                            Logged by <?= htmlspecialchars($e['staff_name'] ?? 'Staff') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="font-bold text-neutral-900" style="font-variant-numeric: tabular-nums;">
                                            ₱<?= number_format((float)$e['amount'], 2) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;" class="text-caption text-neutral-600 font-medium">
                                        <?= date('M j, Y', strtotime($e['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentExpenses)): ?>
                                <tr>
                                    <td colspan="3" class="p-8 text-center text-neutral-500">
                                        <div class="empty-icon">
                                            <?= \App\Support\Icon::svg('receipt', 20) ?>
                                        </div>
                                        <p class="font-semibold text-neutral-800 text-sm mt-1">No expenses recorded yet</p>
                                        <p class="text-caption text-neutral-500">Disbursements will be tracked here when logged.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="p-3 bg-neutral-50 border-t border-neutral-200 flex items-center justify-between text-caption text-neutral-600">
                    <span>Keep receipts documented for tax &amp; audits</span>
                    <a href="/admin/expenses#log-expense" class="font-semibold text-primary-700 hover:text-primary-800 inline-flex items-center gap-1">
                        <?= \App\Support\Icon::svg('plus', 12) ?> Log New Expense
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Facility Quick Telemetry Strip -->
    <div class="space-y-3 pt-2">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-neutral-500">Facility Operations Quick Access</span>
            <span class="text-caption text-neutral-400">RJM Boarding House Management</span>
        </div>
        <div class="telemetry-grid">
            <a href="/admin/rooms" class="telemetry-card">
                <div class="telemetry-icon-wrap">
                    <?= \App\Support\Icon::svg('bed', 18) ?>
                </div>
                <div>
                    <div class="text-sm font-bold text-neutral-900">Rooms &amp; Beds</div>
                    <div class="text-caption text-neutral-500"><?= $totalBeds ?> beds total &bull; <?= $vacantBeds ?> vacant</div>
                </div>
            </a>

            <a href="/admin/penalty-rules" class="telemetry-card">
                <div class="telemetry-icon-wrap">
                    <?= \App\Support\Icon::svg('scale', 18) ?>
                </div>
                <div>
                    <div class="text-sm font-bold text-neutral-900">Penalty Rules</div>
                    <div class="text-caption text-neutral-500">Curfew, late fees &amp; policy rules</div>
                </div>
            </a>

            <a href="/admin/occupancy" class="telemetry-card">
                <div class="telemetry-icon-wrap">
                    <?= \App\Support\Icon::svg('line-chart', 18) ?>
                </div>
                <div>
                    <div class="text-sm font-bold text-neutral-900">Occupancy History</div>
                    <div class="text-caption text-neutral-500">Daily snapshots &amp; capacity telemetry</div>
                </div>
            </a>

            <a href="/admin/staff" class="telemetry-card">
                <div class="telemetry-icon-wrap">
                    <?= \App\Support\Icon::svg('users', 18) ?>
                </div>
                <div>
                    <div class="text-sm font-bold text-neutral-900">User Management</div>
                    <div class="text-caption text-neutral-500">Residents, staff accounts &amp; access</div>
                </div>
            </a>
        </div>
    </div>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
