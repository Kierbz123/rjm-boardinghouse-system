<?php
$pageTitle = 'Penalty Rules';
ob_start();
$info = $_SESSION['flash_info'] ?? null; unset($_SESSION['flash_info']);
$error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);

// Compute summary metrics
$totalRules = count($rules ?? []);
$activeRuleCount = count(array_filter($rules ?? [], fn($r) => !empty($r['active'])));
$inactiveRules = $totalRules - $activeRuleCount;

// Find late fee policy
$lateRule = null;
foreach ($rules ?? [] as $r) {
    if (($r['condition_type'] ?? '') === 'late_per_day' && !empty($r['active'])) {
        $lateRule = $r;
        break;
    }
}

// Compute penalty metrics
$totalPenalties = count($penalties ?? []);
$totalPenaltyAmount = array_sum(array_map(fn($p) => (float) ($p['amount'] ?? 0), $penalties ?? []));
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <!-- Executive Page Banner with Quick Launch Command Bar -->
    <header class="dash-hero-banner">
        <div class="dash-hero-top">
            <div>
                <h1 class="dash-hero-title">
                    <span>Penalty Rules &amp; Automation</span>
                </h1>
                <p class="dash-hero-sub">Late fee policies, flat fee enforcement &amp; manual notification triggers.</p>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="badge" style="background:rgba(255,255,255,0.08); color:#8cc2a2; border:1px solid rgba(255,255,255,0.15); padding:0.35rem 0.85rem; font-size:0.75rem; font-weight:600; border-radius:9999px; display:inline-flex; align-items:center;">
                    <span class="live-pulse-dot" style="margin-right:0.45rem;"></span>
                    Rules Engine Active
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
            <button type="button" onclick="document.getElementById('penalty-check-btn')?.click();" class="dash-cmd-btn highlight" title="Run overdue late-fee check immediately">
                <?= \App\Support\Icon::svg('zap', 14) ?>
                <span>Run Late-Fee Check</span>
            </button>
            <a href="#penalty-rules-section" class="dash-cmd-btn" title="View active policy rules">
                <?= \App\Support\Icon::svg('scale', 14) ?>
                <span>Policy Rules (<?= $totalRules ?>)</span>
            </a>
            <a href="#penalty-history-section" class="dash-cmd-btn" title="View issued penalties history">
                <?= \App\Support\Icon::svg('alert-triangle', 14) ?>
                <span>Issued Penalties (<?= $totalPenalties ?>)</span>
            </a>
            <a href="/admin/payments" class="dash-cmd-btn" title="View payments and collections">
                <?= \App\Support\Icon::svg('banknote', 14) ?>
                <span>Payments</span>
            </a>
        </div>
    </header>

    <?php if ($info): ?>
        <div class="bg-emerald-50 text-emerald-800 text-body-sm rounded-lg p-3.5 border border-emerald-300 flex items-center gap-2.5" role="status">
            <?= \App\Support\Icon::svg('check', 18) ?>
            <span class="font-medium"><?= htmlspecialchars($info) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-error-50 text-error-700 text-body-sm rounded-lg p-3.5 border border-error-500 flex items-center gap-2.5" role="alert">
            <?= \App\Support\Icon::svg('alert-triangle', 18) ?>
            <span class="font-medium"><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- KPI Cards: Rules Metrics -->
    <div class="kpi-grid">
        <a href="#penalty-rules-section" class="reveal-card kpi-card metric-accent-primary" title="View all configured rules">
            <div class="kpi-card-header">
                <span class="kpi-label">Total Rules</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('scale') ?></span>
            </div>
            <div class="kpi-value"><?= $totalRules ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Configured policies</span>
            </div>
        </a>
        <a href="#penalty-rules-section" class="reveal-card kpi-card metric-accent-success" title="View active rules">
            <div class="kpi-card-header">
                <span class="kpi-label">Active Rules</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('circle-dot') ?></span>
            </div>
            <div class="kpi-value"><?= $activeRuleCount ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Enforced currently</span>
            </div>
        </a>
        <a href="#penalty-rules-section" class="reveal-card kpi-card metric-accent-warning" title="View inactive rules">
            <div class="kpi-card-header">
                <span class="kpi-label">Inactive Rules</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('alert-circle') ?></span>
            </div>
            <div class="kpi-value"><?= $inactiveRules ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Disabled policies</span>
            </div>
        </a>
        <a href="#penalty-rules-section" class="reveal-card kpi-card metric-accent-info" title="View late rent rate">
            <div class="kpi-card-header">
                <span class="kpi-label">Late Rent Rate</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('calendar') ?></span>
            </div>
            <div class="kpi-value">
                <?= $lateRule ? '₱' . number_format((float)$lateRule['amount'], 2) : 'None' ?>
            </div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">
                    <?= $lateRule ? 'Per day overdue' : 'No active late rate' ?>
                </span>
            </div>
        </a>
    </div>

    <!-- KPI Cards: Penalty Metrics -->
    <div class="kpi-grid">
        <a href="#penalty-history-section" class="reveal-card kpi-card metric-accent-error" title="View penalty history">
            <div class="kpi-card-header">
                <span class="kpi-label">Total Penalties</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('alert-triangle') ?></span>
            </div>
            <div class="kpi-value"><?= $totalPenalties ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Penalties applied</span>
            </div>
        </a>
        <a href="#penalty-history-section" class="reveal-card kpi-card metric-accent-warning" title="View penalty amount breakdown">
            <div class="kpi-card-header">
                <span class="kpi-label">Total Amount</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('banknote') ?></span>
            </div>
            <div class="kpi-value text-amber-600">₱<?= number_format($totalPenaltyAmount, 2) ?></div>
            <div class="kpi-subtext">
                <span class="text-amber-600 font-semibold">Penalty revenue</span>
            </div>
        </a>
        <a href="#penalty-rules-section" class="reveal-card kpi-card metric-accent-success" title="View enforced rules">
            <div class="kpi-card-header">
                <span class="kpi-label">Active Rules</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('file-text') ?></span>
            </div>
            <div class="kpi-value"><?= $activeRuleCount ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Late rent &amp; fee rules</span>
            </div>
        </a>
        <a href="#penalty-history-section" class="reveal-card kpi-card metric-accent-info" title="View average penalty fee">
            <div class="kpi-card-header">
                <span class="kpi-label">Avg per Penalty</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('bar-chart') ?></span>
            </div>
            <div class="kpi-value">₱<?= $totalPenalties > 0 ? number_format($totalPenaltyAmount / $totalPenalties, 2) : '0.00' ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Average fee</span>
            </div>
        </a>
    </div>

    <!-- Management & Automation Actions Grid -->
    <div>
        <div class="section-header">
            <h2 class="text-heading-sm font-semibold text-neutral-900">Automation &amp; Policy Management</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">

            <!-- Card 0: Issue Manual Penalty to Boarder -->
            <form method="post" action="/admin/penalties/issue" class="form-card accent-warning">
                <div class="flex items-center gap-2 pb-1 border-b border-neutral-100">
                    <?= \App\Support\Icon::svg('scale', 18) ?>
                    <h3 class="text-body font-semibold text-neutral-900">Issue Penalty to Boarder</h3>
                </div>

                <?= \App\Support\Csrf::field() ?>

                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">
                        Target Boarder <span class="text-error-600">*</span>
                    </label>
                    <select name="boarder_id" id="issue_boarder_id" required class="input text-xs">
                        <option value="">-- Choose resident --</option>
                        <?php foreach ($activeBoarders ?? [] as $b): ?>
                            <option value="<?= (int) $b['user_id'] ?>">
                                <?= htmlspecialchars($b['name']) ?> (Rm <?= htmlspecialchars($b['room_number'] ?? 'N/A') ?> · Bal: ₱<?= number_format((float) ($b['outstanding_balance'] ?? 0), 2) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">
                        Penalty Rule Policy <span class="text-error-600">*</span>
                    </label>
                    <select name="rule_id" id="issue_rule_id" required class="input text-xs" onchange="const opt = this.options[this.selectedIndex]; if (opt.dataset.amount) document.getElementById('issue_amount').value = opt.dataset.amount;">
                        <option value="">-- Choose rule policy --</option>
                        <?php foreach ($activeRules ?? [] as $ar): ?>
                            <option value="<?= (int) $ar['id'] ?>" data-amount="<?= (float) $ar['amount'] ?>">
                                <?= htmlspecialchars($ar['name']) ?> (₱<?= number_format((float) $ar['amount'], 2) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-caption font-semibold text-neutral-600 mb-0.5">
                            Amount (₱) <span class="text-error-600">*</span>
                        </label>
                        <input name="amount" id="issue_amount" type="number" step="0.01" min="0.01" required class="input font-mono text-xs" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-caption font-semibold text-neutral-600 mb-0.5">
                            Due Date <span class="text-error-600">*</span>
                        </label>
                        <input name="due_date" type="date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required class="input text-xs">
                    </div>
                </div>

                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">
                        Reason / Incident <span class="text-error-600">*</span>
                    </label>
                    <input name="reason" placeholder="e.g. Trash left in hallway" required class="input text-xs" maxlength="255">
                </div>

                <div class="pt-1">
                    <button type="submit" class="btn btn-primary w-full shadow-xs cursor-pointer !text-xs !py-2">
                        <span>Charge Balance</span>
                    </button>
                </div>
            </form>

            <!-- Card 1: Add New Rule -->
            <form method="post" action="/admin/penalty-rules" class="form-card">
                <div class="flex items-center gap-2 pb-1 border-b border-neutral-100">
                    <?= \App\Support\Icon::svg('plus', 18) ?>
                    <h3 class="text-body font-semibold text-neutral-900">Add Penalty Rule</h3>
                </div>

                <?= \App\Support\Csrf::field() ?>

                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">
                        Rule Name <span class="text-error-600">*</span>
                    </label>
                    <input name="name" placeholder="e.g. Late Rent Charge" required class="input" maxlength="150">
                </div>

                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">
                        Condition Type <span class="text-error-600">*</span>
                    </label>
                    <select name="condition_type" class="input">
                        <option value="late_per_day">late_per_day (Daily Overdue Fee)</option>
                        <option value="flat_damage">flat_damage (Property Damage / Incident)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">
                        Default Amount (₱) <span class="text-error-600">*</span>
                    </label>
                    <div class="relative flex items-center">
                        <span style="position:absolute;left:0.75rem;color:#6b6b6b;font-size:0.875rem;font-weight:700;">₱</span>
                        <input name="amount"
                               type="number"
                               step="0.01"
                               min="0.01"
                               placeholder="0.00"
                               required
                               class="input font-mono font-medium"
                               style="padding-left: 2rem;">
                    </div>
                </div>

                <div class="pt-1">
                    <button type="submit" class="btn btn-primary w-full shadow-xs cursor-pointer">
                        <span>Create Rule</span>
                    </button>
                </div>
            </form>

            <!-- Card 2: Run Penalty Check Trigger -->
            <div class="form-card accent-neutral flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2 pb-1 border-b border-neutral-100">
                        <?= \App\Support\Icon::svg('zap', 18) ?>
                        <h3 class="text-body font-semibold text-neutral-900">Run Penalty Check</h3>
                    </div>
                    <p class="text-body-sm text-neutral-600 mt-2">
                        Scans active tenancies, calculates unpaid rent for the current billing period, and generates penalty charges based on active rules.
                    </p>
                    <div class="bg-neutral-50 rounded-lg p-2.5 mt-3 border border-neutral-200 text-caption text-neutral-500">
                        ℹ On localhost, this is triggered manually on demand or automatically during admin sign-in.
                    </div>
                </div>

                <form method="post" action="/admin/penalties/run-check" id="penalty-check-form" class="pt-3">
                    <?= \App\Support\Csrf::field() ?>
                    <button type="button" id="penalty-check-btn" class="btn btn-primary w-full shadow-xs cursor-pointer">
                        <span><?= \App\Support\Icon::svg('zap') ?> Run Check Now</span>
                    </button>
                </form>
            </div>

            <!-- Card 3: Send Rent-Due Reminders Trigger -->
            <div class="form-card accent-success flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2 pb-1 border-b border-neutral-100">
                        <?= \App\Support\Icon::svg('bell', 18) ?>
                        <h3 class="text-body font-semibold text-neutral-900">Rent-Due Reminders</h3>
                    </div>
                    <p class="text-body-sm text-neutral-600 mt-2">
                        Dispatches in-app notifications and dashboard alerts to all active boarders without a verified payment for the current month (<span class="font-semibold text-neutral-800"><?= date('F Y') ?></span>).
                    </p>
                    <div class="bg-emerald-50 rounded-lg p-2.5 mt-3 border border-emerald-200 text-caption text-emerald-800">
                        <?= \App\Support\Icon::svg('check') ?> Alerts boarders safely without creating duplicate notices.
                    </div>
                </div>

                <form method="post" action="/admin/notifications/rent-due" id="rent-reminders-form" class="pt-3">
                    <?= \App\Support\Csrf::field() ?>
                    <button type="button" id="rent-reminders-btn" class="btn btn-primary w-full shadow-xs cursor-pointer" style="background-color: #2f6f4e; border-color: #2f6f4e;">
                        <span><?= \App\Support\Icon::svg('bell') ?> Send Reminders Now</span>
                    </button>
                </form>
            </div>

        </div>
    </div>

    <!-- Configured Rules Section -->
    <div id="penalty-rules-section">
        <div class="section-header">
            <div class="flex items-center gap-2">
                <h2>Configured Penalty Rules</h2>
                <span class="text-caption text-neutral-500 font-medium bg-neutral-100 px-3 py-1 rounded-full">
                    <?= $totalRules ?> rule<?= $totalRules !== 1 ? 's' : '' ?>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm" style="min-width: 720px;">
                    <thead>
                        <tr>
                            <th style="width: 70px;">ID</th>
                            <th style="width: 25%;">Rule Name</th>
                            <th style="width: 20%;">Condition Type</th>
                            <th style="width: 22%;">Fee Amount</th>
                            <th style="width: 13%;">Status</th>
                            <th style="width: 150px; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rules as $r): ?>
                            <?php $isActive = (bool) $r['active']; ?>
                            <tr class="border-b border-neutral-100 hover:bg-neutral-50/80 transition-colors">
                                <td class="p-3">
                                    <span class="id-tag">#<?= (int) $r['id'] ?></span>
                                </td>
                                <td class="p-3">
                                    <div class="font-bold text-neutral-800 text-sm"><?= htmlspecialchars($r['name']) ?></div>
                                </td>
                                <td class="p-3">
                                    <span class="badge badge-neutral font-mono font-medium">
                                        <?= htmlspecialchars($r['condition_type']) ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <!-- Inline Amount Update Form -->
                                    <form method="post" action="/admin/penalty-rules/<?= (int) $r['id'] ?>/amount" class="flex items-center gap-1.5">
                                        <?= \App\Support\Csrf::field() ?>
                                        <div class="relative flex items-center">
                                            <span style="position:absolute;left:0.5rem;color:#6b6b6b;font-size:0.75rem;font-weight:700;">₱</span>
                                            <input name="amount"
                                                   type="number"
                                                   step="0.01"
                                                   min="0.01"
                                                   value="<?= htmlspecialchars($r['amount']) ?>"
                                                   class="input input-sm font-mono font-medium"
                                                   style="width: 6.5rem; padding-left: 1.5rem;"
                                                   title="Update amount">
                                        </div>
                                        <button type="submit" class="btn btn-primary !py-1 !px-2.5 !text-xs cursor-pointer shadow-xs">
                                            Save
                                        </button>
                                    </form>
                                </td>
                                <td class="p-3">
                                    <span class="badge <?= $isActive ? 'badge-success' : 'badge-neutral' ?>">
                                        <?= $isActive ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <!-- Toggle Active/Inactive Form -->
                                    <form method="post" action="/admin/penalty-rules/<?= (int) $r['id'] ?>/toggle" class="inline"
                                          data-confirm="<?= $isActive ? 'Rule will no longer be applied during automated checks.' : 'Rule will be applied during automated checks.' ?>"
                                          data-confirm-title="<?= $isActive ? 'Deactivate rule &quot;' . htmlspecialchars(addslashes($r['name'])) . '&quot;?' : 'Reactivate rule &quot;' . htmlspecialchars(addslashes($r['name'])) . '&quot;?' ?>"
                                          data-confirm-btn="<?= $isActive ? 'Deactivate' : 'Reactivate' ?>"
                                          <?= $isActive ? 'data-confirm-danger' : 'data-confirm-type="info"' ?>>
                                        <?= \App\Support\Csrf::field() ?>
                                        <input type="hidden" name="active" value="<?= $isActive ? '0' : '1' ?>">
                                        <?php if ($isActive): ?>
                                            <button type="submit"
                                                    class="btn-danger !py-1 !px-2.5 !text-xs cursor-pointer">
                                                Deactivate
                                            </button>
                                        <?php else: ?>
                                            <button type="submit"
                                                    class="btn btn-secondary !py-1 !px-2.5 !text-xs cursor-pointer">
                                                Reactivate
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($rules)): ?>
                            <tr>
                                <td colspan="6" class="empty-state" style="padding:3.5rem 0;">
                                    <span class="empty-icon"><?= \App\Support\Icon::svg('scale') ?></span>
                                    <span class="text-xs font-medium text-neutral-500 mt-1 block">No penalty rules configured yet. Create one using the form above.</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Penalties History Section -->
    <div id="penalty-history-section">
        <div class="section-header">
            <div class="flex items-center gap-2">
                <h2 class="text-heading-sm font-semibold text-neutral-900">Penalty History</h2>
                <span class="text-caption text-neutral-500 font-medium bg-neutral-100 px-2.5 py-1 rounded-full">
                    <?= $totalPenalties ?> record<?= $totalPenalties !== 1 ? 's' : '' ?>
                </span>
            </div>
            <a href="/admin/ledger/export"
               class="btn btn-secondary !py-1.5 !px-3 !text-xs inline-flex items-center gap-1.5 shadow-xs"
               title="Export detailed financial ledger with user names, descriptions, and proper formatting">
                <span><?= \App\Support\Icon::svg('bar-chart') ?></span>
                <span>Export Ledger</span>
            </a>
        </div>

        <!-- Table Card -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm">
                    <thead>
                        <tr style="background:#f8f7f5; border-bottom:2px solid #e6e5e2;">
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide" style="width:4rem;">ID</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Boarder</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Rule</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Amount</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Reason</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Due Date</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Status</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Settlement / Issuer</th>
                            <th class="p-3 text-right font-semibold text-neutral-500 text-caption uppercase tracking-wide">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($penalties as $p): ?>
                            <?php $isPaid = ($p['status'] ?? 'unpaid') === 'paid'; ?>
                            <tr class="border-b border-neutral-100 hover:bg-neutral-50/80 transition-colors">
                                <td class="p-3">
                                    <span class="id-tag">#<?= (int) $p['id'] ?></span>
                                </td>
                                <td class="p-3">
                                    <div class="font-bold text-neutral-800 text-sm"><?= htmlspecialchars($p['boarder_name'] ?? 'Unknown') ?></div>
                                    <div class="text-caption text-neutral-400 font-normal">
                                        <?= htmlspecialchars($p['boarder_email'] ?? '') ?>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <div class="font-semibold text-neutral-800 text-sm"><?= htmlspecialchars($p['rule_name'] ?? 'Unknown') ?></div>
                                    <div class="text-caption text-neutral-400 font-normal">
                                        <?= htmlspecialchars($p['rule_type'] ?? '') ?>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <span class="font-bold <?= $isPaid ? 'text-neutral-500 line-through' : 'text-red-600' ?>">₱<?= number_format((float) ($p['amount'] ?? 0), 2) ?></span>
                                </td>
                                <td class="p-3">
                                    <div class="text-sm text-neutral-700"><?= htmlspecialchars($p['reason'] ?? '') ?></div>
                                </td>
                                <td class="p-3">
                                    <div class="text-xs font-mono <?= !$isPaid && !empty($p['due_date']) && strtotime($p['due_date']) < time() ? 'text-red-600 font-bold' : 'text-neutral-600' ?>">
                                        <?= !empty($p['due_date']) ? date('M j, Y', strtotime($p['due_date'])) : '—' ?>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <?php if ($isPaid): ?>
                                        <span class="badge badge-success text-xs"><?= \App\Support\Icon::svg('check') ?> Paid</span>
                                    <?php else: ?>
                                        <span class="badge badge-error text-xs">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3">
                                    <?php if ($isPaid): ?>
                                        <?php if (!empty($p['paid_payment_id'])): ?>
                                            <div class="text-xs font-semibold text-emerald-700">Payment #<?= (int) $p['paid_payment_id'] ?></div>
                                        <?php else: ?>
                                            <div class="text-xs font-medium text-neutral-600">Admin Override</div>
                                        <?php endif; ?>
                                        <div class="text-caption text-neutral-400"><?= !empty($p['paid_at']) ? date('M j, Y', strtotime($p['paid_at'])) : '' ?></div>
                                    <?php else: ?>
                                        <div class="text-xs text-neutral-500">Issued by: <?= htmlspecialchars($p['issuer_name'] ?? 'System') ?></div>
                                        <div class="text-caption text-amber-600 font-medium">Pending Settlement</div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-right">
                                    <?php if (!$isPaid): ?>
                                        <form method="post" action="/admin/penalties/<?= (int) $p['id'] ?>/mark-paid" class="inline-flex items-center justify-end gap-1.5"
                                              data-confirm="Manually mark penalty #<?= (int)$p['id'] ?> (₱<?= number_format((float)$p['amount'], 2) ?>) as paid via administrative override?"
                                              data-confirm-title="Mark Penalty Paid?"
                                              data-confirm-btn="Mark Paid"
                                              data-confirm-type="success">
                                            <?= \App\Support\Csrf::field() ?>
                                            <input type="text" name="reason" required maxlength="160" placeholder="Reason (required)"
                                                   aria-label="Reason for settling penalty #<?= (int) $p['id'] ?> without a payment" class="input input-sm !py-1 text-xs bg-white w-36">
                                            <button type="submit" class="btn btn-secondary !py-1 !px-2.5 !text-xs cursor-pointer text-emerald-700 hover:bg-emerald-50">
                                                Mark Paid
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-caption text-neutral-400">Settled</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($penalties)): ?>
                            <tr>
                                <td colspan="9" class="empty-state" style="padding:3.5rem 0;">
                                    <span class="empty-icon"><?= \App\Support\Icon::svg('alert-triangle') ?></span>
                                    <span class="text-xs font-medium text-neutral-500 mt-1 block">No penalty records found.</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Penalty Check Confirmation Modal -->
<div id="penalty-check-modal" class="modal" style="display:none" role="dialog" aria-modal="true" aria-labelledby="penalty-modal-heading">
    <div id="penalty-confirm-card" class="modal-card">
        <div id="penalty-modal-icon-wrap" class="modal-icon modal-icon-danger"><?= \App\Support\Icon::svg('zap') ?></div>
        <h3 id="penalty-modal-heading" class="modal-title">Run Penalty Check?</h3>
        <p id="penalty-modal-subtext" class="modal-text">This will scan all active tenancies and generate penalty charges based on unpaid rent. Continue?</p>
        <div class="modal-actions">
            <button type="button" id="penalty-modal-cancel" class="btn btn-secondary">Cancel</button>
            <button type="button" id="penalty-modal-confirm" class="btn btn-danger-solid">Run check</button>
        </div>
    </div>
</div>

<!-- Rent Reminders Confirmation Modal -->
<div id="rent-reminders-modal" class="modal" style="display:none" role="dialog" aria-modal="true" aria-labelledby="rent-modal-heading">
    <div id="rent-confirm-card" class="modal-card">
        <div id="rent-modal-icon-wrap" class="modal-icon"><?= \App\Support\Icon::svg('bell') ?></div>
        <h3 id="rent-modal-heading" class="modal-title">Send Rent Reminders?</h3>
        <p id="rent-modal-subtext" class="modal-text">This will send rent-due notifications to all active boarders without verified payments for <?= date('F Y') ?>. Continue?</p>
        <div class="modal-actions">
            <button type="button" id="rent-modal-cancel" class="btn btn-secondary">Cancel</button>
            <button type="button" id="rent-modal-confirm" class="btn btn-primary">Send reminders</button>
        </div>
    </div>
</div>

<script>
/* ── Custom Confirmation Modals ── */
document.addEventListener('DOMContentLoaded', () => {
    // Penalty Check Modal
    const penaltyCheckBtn = document.getElementById('penalty-check-btn');
    const penaltyCheckForm = document.getElementById('penalty-check-form');
    const penaltyModal = document.getElementById('penalty-check-modal');
    const penaltyCard = document.getElementById('penalty-confirm-card');
    const penaltyCancelBtn = document.getElementById('penalty-modal-cancel');
    const penaltyConfirmBtn = document.getElementById('penalty-modal-confirm');

    function showPenaltyModal() {
        if (!penaltyModal) return;
        penaltyModal.style.display = 'flex';
        penaltyModal.classList.add('animate-custom-backdrop');
        if (window.gsap && penaltyCard) {
            gsap.fromTo(penaltyCard,
                { opacity: 0, scale: 0.96, y: 8 },
                { opacity: 1, scale: 1, y: 0, duration: 0.3, ease: 'power3.out' }
            );
        } else if (penaltyCard) {
            penaltyCard.classList.add('animate-custom-card');
        }
        if (penaltyCancelBtn) penaltyCancelBtn.focus();
    }

    function hidePenaltyModal() {
        if (!penaltyModal) return;
        penaltyModal.style.display = 'none';
        penaltyModal.classList.remove('animate-custom-backdrop');
        if (penaltyCard) penaltyCard.classList.remove('animate-custom-card');
    }

    if (penaltyCheckBtn) {
        penaltyCheckBtn.addEventListener('click', (e) => {
            e.preventDefault();
            showPenaltyModal();
        });
    }

    if (penaltyCancelBtn) {
        penaltyCancelBtn.addEventListener('click', hidePenaltyModal);
    }

    if (penaltyConfirmBtn) {
        penaltyConfirmBtn.addEventListener('click', () => {
            hidePenaltyModal();
            if (penaltyCheckForm) penaltyCheckForm.submit();
        });
    }

    // Rent Reminders Modal
    const rentRemindersBtn = document.getElementById('rent-reminders-btn');
    const rentRemindersForm = document.getElementById('rent-reminders-form');
    const rentModal = document.getElementById('rent-reminders-modal');
    const rentCard = document.getElementById('rent-confirm-card');
    const rentCancelBtn = document.getElementById('rent-modal-cancel');
    const rentConfirmBtn = document.getElementById('rent-modal-confirm');

    function showRentModal() {
        if (!rentModal) return;
        rentModal.style.display = 'flex';
        rentModal.classList.add('animate-custom-backdrop');
        if (window.gsap && rentCard) {
            gsap.fromTo(rentCard,
                { opacity: 0, scale: 0.96, y: 8 },
                { opacity: 1, scale: 1, y: 0, duration: 0.3, ease: 'power3.out' }
            );
        } else if (rentCard) {
            rentCard.classList.add('animate-custom-card');
        }
        if (rentCancelBtn) rentCancelBtn.focus();
    }

    function hideRentModal() {
        if (!rentModal) return;
        rentModal.style.display = 'none';
        rentModal.classList.remove('animate-custom-backdrop');
        if (rentCard) rentCard.classList.remove('animate-custom-card');
    }

    if (rentRemindersBtn) {
        rentRemindersBtn.addEventListener('click', (e) => {
            e.preventDefault();
            showRentModal();
        });
    }

    if (rentCancelBtn) {
        rentCancelBtn.addEventListener('click', hideRentModal);
    }

    if (rentConfirmBtn) {
        rentConfirmBtn.addEventListener('click', () => {
            hideRentModal();
            if (rentRemindersForm) rentRemindersForm.submit();
        });
    }

    // Close modals on backdrop click
    [penaltyModal, rentModal].forEach(modal => {
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    if (modal === penaltyModal) hidePenaltyModal();
                    if (modal === rentModal) hideRentModal();
                }
            });
        }
    });

    // Close modals on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (penaltyModal && penaltyModal.style.display === 'flex') hidePenaltyModal();
            if (rentModal && rentModal.style.display === 'flex') hideRentModal();
        }
    });
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
