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

    <!-- Page Banner -->
    <div class="page-banner">
        <div>
            <h1>Penalty Rules &amp; Automation</h1>
            <p>Late fee policies, flat fee enforcement &amp; manual notification triggers</p>
        </div>
        <span class="badge badge-success" style="background:rgba(255,255,255,0.12); color:#8cc2a2; border:1px solid rgba(255,255,255,0.18); padding:0.4rem 0.85rem; font-size:0.75rem;">
            <span style="width:0.45rem;height:0.45rem;border-radius:50%;background:#5fae84;display:inline-block;margin-right:0.4rem;"></span>
            Rules Engine Active
        </span>
    </div>

    <?php if ($info): ?>
        <div class="bg-emerald-50 text-emerald-800 text-body-sm rounded-lg p-3.5 border border-emerald-300 flex items-center gap-2.5" role="status">
            <span style="font-size:1.1rem; line-height:1;">✓</span>
            <span class="font-medium"><?= htmlspecialchars($info) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-error-50 text-error-700 text-body-sm rounded-lg p-3.5 border border-error-500 flex items-center gap-2.5" role="alert">
            <span style="font-size:1.1rem; line-height:1;">⚠</span>
            <span class="font-medium"><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- KPI Cards: Rules Metrics -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="reveal-card card p-4 metric-accent-primary">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Rules</span>
                <span style="font-size:1.1rem; line-height:1;">⚖️</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= $totalRules ?></p>
            <p class="text-caption text-neutral-400 mt-1">Configured policies</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-success">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Active Rules</span>
                <span style="font-size:1.1rem; line-height:1;">🟢</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= $activeRuleCount ?></p>
            <p class="text-caption text-neutral-400 mt-1">Enforced currently</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-warning">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Inactive Rules</span>
                <span style="font-size:1.1rem; line-height:1;">⏸️</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= $inactiveRules ?></p>
            <p class="text-caption text-neutral-400 mt-1">Disabled policies</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-info">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Late Rent Rate</span>
                <span style="font-size:1.1rem; line-height:1;">⏱️</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;">
                <?= $lateRule ? '₱' . number_format((float)$lateRule['amount'], 2) : 'None' ?>
            </p>
            <p class="text-caption text-neutral-400 mt-1">
                <?= $lateRule ? 'Per day overdue' : 'No active late rate' ?>
            </p>
        </div>
    </div>

    <!-- KPI Cards: Penalty Metrics -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="reveal-card card p-4 metric-accent-error">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Penalties</span>
                <span style="font-size:1.1rem; line-height:1;">⚠️</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= $totalPenalties ?></p>
            <p class="text-caption text-neutral-400 mt-1">Penalties applied</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-warning">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Amount</span>
                <span style="font-size:1.1rem; line-height:1;">₱</span>
            </div>
            <p class="text-3xl font-bold text-amber-600 mt-1" style="font-variant-numeric: tabular-nums;">₱<?= number_format($totalPenaltyAmount, 2) ?></p>
            <p class="text-caption text-amber-600 mt-1 font-semibold">Penalty revenue</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-success">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Active Rules</span>
                <span style="font-size:1.1rem; line-height:1;">📜</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= $activeRuleCount ?></p>
            <p class="text-caption text-neutral-400 mt-1">Late rent &amp; fee rules</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-info">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Avg per Penalty</span>
                <span style="font-size:1.1rem; line-height:1;">📊</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;">₱<?= $totalPenalties > 0 ? number_format($totalPenaltyAmount / $totalPenalties, 2) : '0.00' ?></p>
            <p class="text-caption text-neutral-400 mt-1">Average fee</p>
        </div>
    </div>

    <!-- Configured Rules Section -->
    <div>
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
                                    <form method="post" action="/admin/penalty-rules/<?= (int) $r['id'] ?>/toggle" class="inline">
                                        <?= \App\Support\Csrf::field() ?>
                                        <input type="hidden" name="active" value="<?= $isActive ? '0' : '1' ?>">
                                        <?php if ($isActive): ?>
                                            <button type="submit"
                                                    class="btn-danger !py-1 !px-2.5 !text-xs cursor-pointer"
                                                    onclick="return confirm('Deactivate rule &quot;<?= htmlspecialchars(addslashes($r['name'])) ?>&quot;?');">
                                                Deactivate
                                            </button>
                                        <?php else: ?>
                                            <button type="submit"
                                                    class="btn btn-secondary !py-1 !px-2.5 !text-xs cursor-pointer"
                                                    onclick="return confirm('Reactivate rule &quot;<?= htmlspecialchars(addslashes($r['name'])) ?>&quot;?');">
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
                                    <span style="font-size:2rem;">⚖️</span>
                                    <span class="text-xs font-medium text-neutral-500 mt-1 block">No penalty rules configured yet. Create one using the form below.</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Penalties History Section -->
    <div>
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
                <span>📊</span>
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
                                        <span class="badge badge-success text-xs">✓ Paid</span>
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
                                        <form method="post" action="/admin/penalties/<?= (int) $p['id'] ?>/mark-paid" class="inline" onsubmit="return confirm('Manually mark penalty #<?= (int)$p['id'] ?> (₱<?= number_format((float)$p['amount'], 2) ?>) as paid via administrative override?');">
                                            <?= \App\Support\Csrf::field() ?>
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
                                    <span style="font-size:2rem;">⚠️</span>
                                    <span class="text-xs font-medium text-neutral-500 mt-1 block">No penalty records found.</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Management & Automation Actions Grid -->
    <div>
        <div class="section-header">
            <h2 class="text-heading-sm font-semibold text-neutral-900">Automation &amp; Policy Management</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

            <!-- Card 0: Issue Manual Penalty to Boarder -->
            <form method="post" action="/admin/penalties/issue" class="form-card accent-warning">
                <div class="flex items-center gap-2 pb-1 border-b border-neutral-100">
                    <span style="font-size:1.1rem;">⚖️</span>
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
                    <span style="font-size:1.1rem;">➕</span>
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
                        <span style="font-size:1.1rem;">⚡</span>
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
                        <span>⚡ Run Check Now</span>
                    </button>
                </form>
            </div>

            <!-- Card 3: Send Rent-Due Reminders Trigger -->
            <div class="form-card accent-success flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2 pb-1 border-b border-neutral-100">
                        <span style="font-size:1.1rem;">🔔</span>
                        <h3 class="text-body font-semibold text-neutral-900">Rent-Due Reminders</h3>
                    </div>
                    <p class="text-body-sm text-neutral-600 mt-2">
                        Dispatches in-app notifications and dashboard alerts to all active boarders without a verified payment for the current month (<span class="font-semibold text-neutral-800"><?= date('F Y') ?></span>).
                    </p>
                    <div class="bg-emerald-50 rounded-lg p-2.5 mt-3 border border-emerald-200 text-caption text-emerald-800">
                        ✓ Alerts boarders safely without creating duplicate notices.
                    </div>
                </div>

                <form method="post" action="/admin/notifications/rent-due" id="rent-reminders-form" class="pt-3">
                    <?= \App\Support\Csrf::field() ?>
                    <button type="button" id="rent-reminders-btn" class="btn btn-primary w-full shadow-xs cursor-pointer" style="background-color: #2f6f4e; border-color: #2f6f4e;">
                        <span>🔔 Send Reminders Now</span>
                    </button>
                </form>
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

<style>
/* Custom Modal Animations */
@keyframes customModalFadeIn {
    0% { opacity: 0; }
    100% { opacity: 1; }
}
@keyframes customCardPop {
    0% { opacity: 0; transform: scale(0.9) translateY(12px); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
}
.animate-custom-backdrop {
    animation: customModalFadeIn 180ms ease-out forwards;
}
.animate-custom-card {
    animation: customCardPop 220ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
.custom-modal-cancel:hover {
    background-color: #f1f0ee !important;
    color: #0a0a0a !important;
}
.custom-modal-confirm:hover {
    filter: brightness(1.08);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
}
.custom-modal-confirm:active {
    transform: scale(0.97);
}
.rent-modal-confirm {
    background: linear-gradient(135deg, #2f6f4e, #245a3f) !important;
}
.rent-modal-confirm:hover {
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25);
}
</style>

<!-- Penalty Check Confirmation Modal -->
<div id="penalty-check-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:99999; background:rgba(10, 10, 10,0.55); backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:1rem;" role="dialog" aria-modal="true" aria-labelledby="penalty-modal-heading">
    <div id="penalty-confirm-card" style="background: rgb(255, 255, 255); border-radius: 1rem; padding: 1.5rem 1.75rem; max-width: 22rem; width: 100%; margin: 0px auto; text-align: center; box-shadow: rgba(0, 0, 0, 0.25) 0px 25px 50px -12px;">
        <div id="penalty-modal-icon-wrap" style="width: 3.5rem; height: 3.5rem; border-radius: 50%; border: 2px solid rgb(239, 68, 68); color: rgb(220, 38, 38); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; margin: 0px auto 0.75rem; user-select: none;">
            ⚡
        </div>
        <h3 id="penalty-modal-heading" style="font-size: 1.1rem; font-weight: 700; color: #0a0a0a; letter-spacing: -0.01em; margin: 0 0 0.375rem;">Run Penalty Check?</h3>
        <p id="penalty-modal-subtext" style="font-size: 0.8rem; color: #6b6b6b; line-height: 1.6; margin: 0 0 1.5rem; padding: 0 0.5rem;">This will scan all active tenancies and generate penalty charges based on unpaid rent. Continue?</p>
        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem; padding-top: 0.25rem;">
            <button type="button" id="penalty-modal-cancel" class="custom-modal-cancel" style="padding: 0.5rem 1rem; border-radius: 0.75rem; font-size: 0.75rem; font-weight: 600; color: #6b6b6b; background: transparent; border: none; cursor: pointer; transition: all 150ms;">
                Cancel
            </button>
            <button type="button" id="penalty-modal-confirm" class="custom-modal-confirm" style="color: rgb(255, 255, 255); font-weight: 600; border-radius: 0.75rem; padding: 0.5rem 1.25rem; font-size: 0.75rem; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.375rem; transition: 150ms; background: linear-gradient(135deg, rgb(239, 68, 68), rgb(220, 38, 38));">
                <span>Yes</span>
                <span style="font-size: 0.95rem;">&rarr;</span>
            </button>
        </div>
    </div>
</div>

<!-- Rent Reminders Confirmation Modal -->
<div id="rent-reminders-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:99999; background:rgba(10, 10, 10,0.55); backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:1rem;" role="dialog" aria-modal="true" aria-labelledby="rent-modal-heading">
    <div id="rent-confirm-card" style="background: rgb(255, 255, 255); border-radius: 1rem; padding: 1.5rem 1.75rem; max-width: 22rem; width: 100%; margin: 0px auto; text-align: center; box-shadow: rgba(0, 0, 0, 0.25) 0px 25px 50px -12px;">
        <div id="rent-modal-icon-wrap" style="width: 3.5rem; height: 3.5rem; border-radius: 50%; border: 2px solid #2f6f4e; color: #245a3f; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; margin: 0px auto 0.75rem; user-select: none;">
            🔔
        </div>
        <h3 id="rent-modal-heading" style="font-size: 1.1rem; font-weight: 700; color: #0a0a0a; letter-spacing: -0.01em; margin: 0 0 0.375rem;">Send Rent Reminders?</h3>
        <p id="rent-modal-subtext" style="font-size: 0.8rem; color: #6b6b6b; line-height: 1.6; margin: 0 0 1.5rem; padding: 0 0.5rem;">This will send rent-due notifications to all active boarders without verified payments for <?= date('F Y') ?>. Continue?</p>
        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem; padding-top: 0.25rem;">
            <button type="button" id="rent-modal-cancel" class="custom-modal-cancel" style="padding: 0.5rem 1rem; border-radius: 0.75rem; font-size: 0.75rem; font-weight: 600; color: #6b6b6b; background: transparent; border: none; cursor: pointer; transition: all 150ms;">
                Cancel
            </button>
            <button type="button" id="rent-modal-confirm" class="custom-modal-confirm rent-modal-confirm" style="color: rgb(255, 255, 255); font-weight: 600; border-radius: 0.75rem; padding: 0.5rem 1.25rem; font-size: 0.75rem; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.375rem; transition: 150ms;">
                <span>Yes</span>
                <span style="font-size: 0.95rem;">&rarr;</span>
            </button>
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
                { opacity: 0, scale: 0.9, y: 12 },
                { opacity: 1, scale: 1, y: 0, duration: 0.25, ease: 'back.out(1.7)' }
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
                { opacity: 0, scale: 0.9, y: 12 },
                { opacity: 1, scale: 1, y: 0, duration: 0.25, ease: 'back.out(1.7)' }
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
