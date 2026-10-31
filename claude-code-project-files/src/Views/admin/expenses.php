<?php
$pageTitle = 'Expenses';
ob_start();
$error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

// Compute summary metrics
$totalCount = count($expenses ?? []);
$totalAmount = array_sum(array_column($expenses ?? [], 'amount'));

// Compute top category by spending
$categorySpending = [];
foreach ($expenses ?? [] as $e) {
    $cat = trim($e['category'] ?? 'General');
    $categorySpending[$cat] = ($categorySpending[$cat] ?? 0) + (float) ($e['amount'] ?? 0);
}
arsort($categorySpending);
$topCategory = !empty($categorySpending) ? array_key_first($categorySpending) : 'None';
$latestExpense = !empty($expenses) ? $expenses[0] : null;
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <!-- Executive Page Banner with Quick Launch Command Bar -->
    <header class="dash-hero-banner">
        <div class="dash-hero-top">
            <div>
                <h1 class="dash-hero-title">
                    <span>Operating Expenses</span>
                </h1>
                <p class="dash-hero-sub">Operational expenditures, maintenance costs, and facility disbursements.</p>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="badge" style="background:rgba(255,255,255,0.08); color:#8cc2a2; border:1px solid rgba(255,255,255,0.15); padding:0.35rem 0.85rem; font-size:0.75rem; font-weight:600; border-radius:9999px; display:inline-flex; align-items:center;">
                    <span class="live-pulse-dot" style="margin-right:0.45rem;"></span>
                    Financials Active
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
            <a href="#log-expense-form" class="dash-cmd-btn highlight" title="Log a new operating expense">
                <?= \App\Support\Icon::svg('plus', 14) ?>
                <span>Log Expense</span>
            </a>
            <a href="#expense-records" class="dash-cmd-btn" title="Jump to expense entries table">
                <?= \App\Support\Icon::svg('clipboard', 14) ?>
                <span>Expense Ledger (<?= $totalCount ?>)</span>
            </a>
            <a href="/admin/payments" class="dash-cmd-btn" title="View collections and receipts">
                <?= \App\Support\Icon::svg('banknote', 14) ?>
                <span>Payments &amp; Collections</span>
            </a>
            <a href="/admin/ledger/export?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="dash-cmd-btn" title="Export monthly general ledger">
                <?= \App\Support\Icon::svg('download', 14) ?>
                <span>Export Ledger</span>
            </a>
        </div>
    </header>

    <?php if ($error): ?>
        <div class="bg-error-50 text-error-700 text-body-sm rounded-lg p-3.5 border border-error-500 flex items-center gap-2.5" role="alert">
            <?= \App\Support\Icon::svg('alert-triangle', 18) ?>
            <span class="font-medium"><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-emerald-50 text-emerald-800 text-body-sm rounded-lg p-3.5 border border-emerald-300 flex items-center gap-2.5" role="alert">
            <?= \App\Support\Icon::svg('check', 18) ?>
            <span class="font-medium"><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="kpi-grid">
        <a href="#expense-records" class="reveal-card kpi-card metric-accent-primary" title="View all logged expenses">
            <div class="kpi-card-header">
                <span class="kpi-label">Total Entries</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('file-text') ?></span>
            </div>
            <div class="kpi-value"><?= $totalCount ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Disbursements logged</span>
            </div>
        </a>

        <a href="#expense-records" class="reveal-card kpi-card metric-accent-error" title="View total disbursement records">
            <div class="kpi-card-header">
                <span class="kpi-label">Total Disbursed</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('banknote') ?></span>
            </div>
            <div class="kpi-value">₱<?= number_format($totalAmount, 2) ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Cumulative expenditure</span>
            </div>
        </a>

        <a href="#log-expense-form" class="reveal-card kpi-card metric-accent-warning" title="Log expense or review categories">
            <div class="kpi-card-header">
                <span class="kpi-label">Top Category</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('tag') ?></span>
            </div>
            <div class="kpi-value" title="<?= htmlspecialchars($topCategory) ?>">
                <?= htmlspecialchars($topCategory) ?>
            </div>
            <div class="kpi-subtext">
                <span class="text-amber-600 font-semibold" style="font-variant-numeric: tabular-nums;">
                    <?= !empty($categorySpending) ? '₱' . number_format(reset($categorySpending), 2) : '—' ?>
                </span>
            </div>
        </a>

        <a href="#expense-records" class="reveal-card kpi-card metric-accent-info" title="View most recent transactions">
            <div class="kpi-card-header">
                <span class="kpi-label">Latest Entry</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('calendar') ?></span>
            </div>
            <div class="kpi-value">
                <?= $latestExpense ? '₱' . number_format((float)$latestExpense['amount'], 2) : '—' ?>
            </div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">
                    <?= $latestExpense && !empty($latestExpense['created_at']) ? date('M j, Y', strtotime($latestExpense['created_at'])) : 'No records' ?>
                </span>
            </div>
        </a>
    </div>

    <!-- Main Content Layout (Table + Log Form) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        <!-- Left 2 Cols: Expenses Table -->
        <div class="lg:col-span-2 space-y-4" id="expense-records">
            <div class="section-header">
                <div class="flex items-center gap-2">
                    <h2>Expense Records</h2>
                    <span class="text-caption text-neutral-500 font-medium bg-neutral-100 px-3 py-1 rounded-full">
                        <?= $totalCount ?> entry<?= $totalCount !== 1 ? 's' : '' ?>
                    </span>
                </div>
                <a href="/admin/ledger/export?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>"
                   class="btn btn-secondary !py-1.5 !px-3.5 !text-xs inline-flex items-center gap-1.5 shadow-xs font-semibold"
                   title="Download this month's financial statement and general ledger (payments, expenses, penalties) as an Excel workbook">
                    <span><?= \App\Support\Icon::svg('bar-chart') ?></span>
                    <span>Export Ledger</span>
                </a>
            </div>

            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-body-sm" style="min-width: 650px;">
                        <thead>
                            <tr>
                                <th style="width: 70px;">ID</th>
                                <th style="width: 40%;">Category &amp; Details</th>
                                <th style="width: 20%;">Amount</th>
                                <th style="width: 20%;">Logged By</th>
                                <th style="width: 20%; text-align: right;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $e): ?>
                                <tr>
                                    <td>
                                        <span class="id-tag">#<?= (int) ($e['id'] ?? 0) ?></span>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <span class="badge badge-neutral font-semibold">
                                                <?= htmlspecialchars($e['category'] ?? 'General') ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($e['description'])): ?>
                                            <div class="text-caption text-neutral-600 mt-1 max-w-sm truncate" title="<?= htmlspecialchars($e['description']) ?>">
                                                <?= htmlspecialchars($e['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="font-bold text-neutral-900 text-sm" style="font-variant-numeric: tabular-nums;">
                                            ₱<?= number_format((float)$e['amount'], 2) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="font-medium text-neutral-800 text-xs">
                                            <?= htmlspecialchars($e['staff_name'] ?? 'Staff') ?>
                                        </div>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="text-caption text-neutral-600 font-medium">
                                            <?= !empty($e['created_at']) ? date('M j, Y', strtotime($e['created_at'])) : '—' ?>
                                        </div>
                                        <div class="text-[11px] text-neutral-400">
                                            <?= !empty($e['created_at']) ? date('g:i a', strtotime($e['created_at'])) : '' ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($expenses)): ?>
                                <tr>
                                    <td colspan="5" class="empty-state" style="padding:3.5rem 0;">
                                        <span class="empty-icon"><?= \App\Support\Icon::svg('clipboard') ?></span>
                                        <span class="text-sm font-semibold text-neutral-800">No expenses logged yet</span>
                                        <span class="text-xs text-neutral-500 mt-1">Use the form on the right to log your first expenditure.</span>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right 1 Col: Log Expense Form Card -->
        <div class="space-y-4">
            <div class="section-header">
                <h2>New Expenditure</h2>
            </div>

            <form method="post" action="/admin/expenses" class="form-card" id="log-expense-form">
                <div class="flex items-center gap-2 pb-2 border-b border-neutral-100">
                    <?= \App\Support\Icon::svg('banknote', 18) ?>
                    <h3 class="text-body font-semibold text-neutral-900">Log Operating Expense</h3>
                </div>

                <?= \App\Support\Csrf::field() ?>

                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-1">
                        Category <span class="text-error-600">*</span>
                    </label>
                    <input name="category"
                           placeholder="e.g. Electricity, Water, Repairs, Cleaning"
                           required
                           class="input"
                           maxlength="100">
                </div>

                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-1">
                        Amount (₱) <span class="text-error-600">*</span>
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

                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-1">
                        Description <span class="font-normal text-neutral-400">(optional)</span>
                    </label>
                    <textarea name="description"
                              rows="3"
                              maxlength="255"
                              placeholder="Add notes, invoice/receipt details, vendor name..."
                              class="input text-body-sm"
                              style="resize:vertical;"></textarea>
                </div>

                <div class="pt-2 mt-auto">
                    <button type="submit" class="btn btn-primary w-full !py-2 !text-xs font-semibold shadow-xs cursor-pointer">
                        Save Expenditure
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
